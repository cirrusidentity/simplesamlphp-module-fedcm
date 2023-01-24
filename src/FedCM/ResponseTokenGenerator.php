<?php

namespace SimpleSAML\Module\fedcm\FedCM;

use DOMNodeList;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SAML2\Assertion;
use SAML2\Constants;
use SAML2\DOMDocumentFactory;
use SAML2\EncryptedAssertion;
use SAML2\Response;
use SAML2\Utils as SAML2Utils;
use SAML2\XML\ds\X509Certificate;
use SAML2\XML\ds\X509Data;
use SAML2\XML\ds\KeyInfo;
use SAML2\XML\saml\AttributeValue;
use SAML2\XML\saml\Issuer;
use SAML2\XML\saml\NameID;
use SAML2\XML\saml\SubjectConfirmation;
use SAML2\XML\saml\SubjectConfirmationData;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\IdP;
use SimpleSAML\Logger;
use SimpleSAML\Stats;
use SimpleSAML\Utils;
use Symfony\Component\HttpFoundation\Response as HTTPResponse;


/**
 * Generates SAML response and returns as opaque token in FedCM
 * identity assertion endpoint JSON response.
 * 
 * @package SimpleSAML\Module\fedcm\FedCM
 */
class ResponseTokenGenerator
{
    /**
     * Send an opaque token containing SAML2 response to FedCM.
     *
     * @param array $state The authentication state.
     */
    public static function sendResponseToken(array $state): void
    {
        Assert::keyExists($state, 'saml:RequestId'); // Can be NULL
        Assert::keyExists($state, 'saml:RelayState'); // Can be NULL.
        Assert::notNull($state['Attributes']);
        Assert::notNull($state['SPMetadata']);
        Assert::notNull($state['saml:ConsumerURL']);

        $spMetadata = $state["SPMetadata"];
        $spEntityId = $spMetadata['entityid'];
        $spMetadata = Configuration::loadFromArray(
            $spMetadata,
            '$metadata[' . var_export($spEntityId, true) . ']'
        );

        Logger::info('Sending SAML 2.0 Response to ' . var_export($spEntityId, true));

        $requestId = $state['saml:RequestId'];
        $relayState = $state['saml:RelayState'];
        $consumerURL = $state['saml:ConsumerURL'];

        $idp = IdP::getByState($state);

        $idpMetadata = $idp->getConfig();

        $assertion = self::buildAssertion($idpMetadata, $spMetadata, $state);

        if (isset($state['saml:AuthenticatingAuthority'])) {
            $assertion->setAuthenticatingAuthority($state['saml:AuthenticatingAuthority']);
        }

        // create the session association (for logout)
        $association = [
            'id'                => 'saml:' . $spEntityId,
            'Handler'           => '\SimpleSAML\Module\saml\IdP\SAML2',
            'Expires'           => $assertion->getSessionNotOnOrAfter(),
            'saml:entityID'     => $spEntityId,
            'saml:NameID'       => $state['saml:idp:NameID'],
            'saml:SessionIndex' => $assertion->getSessionIndex(),
        ];

        // maybe encrypt the assertion
        $assertion = self::encryptAssertion($idpMetadata, $spMetadata, $assertion);

        // create the response
        $ar = self::buildResponse($idpMetadata, $spMetadata, $consumerURL);
        $ar->setInResponseTo($requestId);
        $ar->setRelayState($relayState);
        $ar->setAssertions([$assertion]);

        // register the session association with the IdP
        $idp->addAssociation($association);

        $statsData = [
            'spEntityID'  => $spEntityId,
            'idpEntityID' => $idpMetadata->getString('entityid'),
            'protocol'    => 'saml2',
        ];
        if (isset($state['saml:AuthnRequestReceivedAt'])) {
            $statsData['logintime'] = microtime(true) - $state['saml:AuthnRequestReceivedAt'];
        }
        Stats::log('saml:idp:Response', $statsData);

        // send the response
        $msgStr = $ar->toSignedXML();
        SAML2Utils::getContainer()->debugMessage($msgStr, 'out');
        $msgStr = $msgStr->ownerDocument->saveXML($msgStr);
        $msgStr = base64_encode($msgStr);
        $response = new HTTPResponse(
            json_encode(['token' => $msgStr], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            200,
            ['Content-Type' => 'application/json;charset=utf-8']
        );
        $response->send();
        exit(0);
    }

    /**
     * Build an assertion based on information in the metadata.
     *
     * @param \SimpleSAML\Configuration $idpMetadata The metadata of the IdP.
     * @param \SimpleSAML\Configuration $spMetadata The metadata of the SP.
     * @param array &$state The state array with information about the request.
     *
     * @return \SAML2\Assertion  The assertion.
     *
     * @throws \SimpleSAML\Error\Exception In case an error occurs when creating a holder-of-key assertion.
     */
    private static function buildAssertion(
        Configuration $idpMetadata,
        Configuration $spMetadata,
        array &$state
    ): Assertion {
        Assert::notNull($state['Attributes']);
        Assert::notNull($state['saml:ConsumerURL']);

        $httpUtils = new Utils\HTTP();
        $now = time();

        $signAssertion = $spMetadata->getOptionalBoolean('saml20.sign.assertion', null);
        if ($signAssertion === null) {
            $signAssertion = $idpMetadata->getOptionalBoolean('saml20.sign.assertion', true);
        }

        $config = Configuration::getInstance();

        $a = new Assertion();
        if ($signAssertion) {
            \SimpleSAML\Module\saml\Message::addSign($idpMetadata, $spMetadata, $a);
        }

        $issuer = new Issuer();
        $issuer->setValue($idpMetadata->getString('entityid'));
        $issuer->setFormat(Constants::NAMEID_ENTITY);
        $a->setIssuer($issuer);

        $audience = array_merge([$spMetadata->getString('entityid')], $spMetadata->getOptionalArray('audience', []));
        $a->setValidAudiences($audience);

        $a->setNotBefore($now - 30);

        $assertionLifetime = $spMetadata->getOptionalInteger('assertion.lifetime', null);
        if ($assertionLifetime === null) {
            $assertionLifetime = $idpMetadata->getOptionalInteger('assertion.lifetime', 300);
        }
        $a->setNotOnOrAfter($now + $assertionLifetime);

        if (isset($state['saml:AuthnContextClassRef'])) {
            $a->setAuthnContextClassRef($state['saml:AuthnContextClassRef']);
        } elseif ($httpUtils->isHTTPS()) {
            $a->setAuthnContextClassRef(Constants::AC_PASSWORD_PROTECTED_TRANSPORT);
        } else {
            $a->setAuthnContextClassRef(Constants::AC_PASSWORD);
        }

        $sessionStart = $now;
        if (isset($state['AuthnInstant'])) {
            $a->setAuthnInstant($state['AuthnInstant']);
            $sessionStart = $state['AuthnInstant'];
        }

        $sessionLifetime = $config->getOptionalInteger('session.duration', 8 * 60 * 60);
        $a->setSessionNotOnOrAfter($sessionStart + $sessionLifetime);

        $randomUtils = new Utils\Random();
        $a->setSessionIndex($randomUtils->generateID());

        $sc = new SubjectConfirmation();
        $scd = new SubjectConfirmationData();
        $scd->setNotOnOrAfter($now + $assertionLifetime);
        $scd->setRecipient($state['saml:ConsumerURL']);
        $scd->setInResponseTo($state['saml:RequestId']);
        $sc->setSubjectConfirmationData($scd);

        // ProtcolBinding of SP's <AuthnRequest> overwrites IdP hosted metadata configuration
        $hokAssertion = null;
        if ($state['saml:Binding'] === Constants::BINDING_HOK_SSO) {
            $hokAssertion = true;
        }
        if ($hokAssertion === null) {
            $hokAssertion = $idpMetadata->getOptionalBoolean('saml20.hok.assertion', false);
        }

        if ($hokAssertion) {
            // Holder-of-Key
            $sc->setMethod(Constants::CM_HOK);

            if ($httpUtils->isHTTPS()) {
                if (isset($_SERVER['SSL_CLIENT_CERT']) && !empty($_SERVER['SSL_CLIENT_CERT'])) {
                    // extract certificate data (if this is a certificate)
                    $clientCert = $_SERVER['SSL_CLIENT_CERT'];
                    $pattern = '/^-----BEGIN CERTIFICATE-----([^-]*)^-----END CERTIFICATE-----/m';
                    if (preg_match($pattern, $clientCert, $matches)) {
                        // we have a client certificate from the browser which we add to the HoK assertion
                        $x509Certificate = new X509Certificate();
                        $x509Certificate->setCertificate(str_replace(["\r", "\n", " "], '', $matches[1]));

                        $x509Data = new X509Data();
                        $x509Data->addData($x509Certificate);

                        $keyInfo = new KeyInfo();
                        $keyInfo->addInfo($x509Data);

                        $scd->addInfo($keyInfo);
                    } else {
                        throw new Error\Exception(
                            'Error creating HoK assertion: No valid client certificate provided during '
                            . 'TLS handshake with IdP'
                        );
                    }
                } else {
                    throw new Error\Exception(
                        'Error creating HoK assertion: No client certificate provided during TLS handshake with IdP'
                    );
                }
            } else {
                throw new Error\Exception(
                    'Error creating HoK assertion: No HTTPS connection to IdP, but required for Holder-of-Key SSO'
                );
            }
        } else {
            // Bearer
            $sc->setMethod(Constants::CM_BEARER);
        }
        $sc->setSubjectConfirmationData($scd);
        $a->setSubjectConfirmation([$sc]);

        // add attributes
        if ($spMetadata->getOptionalBoolean('simplesaml.attributes', true)) {
            $attributeNameFormat = self::getAttributeNameFormat($idpMetadata, $spMetadata);
            $a->setAttributeNameFormat($attributeNameFormat);
            $attributes = self::encodeAttributes($idpMetadata, $spMetadata, $state['Attributes']);
            $a->setAttributes($attributes);
        }

        $nameId = self::generateNameId($idpMetadata, $spMetadata, $state);
        $state['saml:idp:NameID'] = $nameId;
        $a->setNameId($nameId);

        $encryptNameId = $spMetadata->getOptionalBoolean('nameid.encryption', null);
        if ($encryptNameId === null) {
            $encryptNameId = $idpMetadata->getOptionalBoolean('nameid.encryption', false);
        }
        if ($encryptNameId) {
            $a->encryptNameId(\SimpleSAML\Module\saml\Message::getEncryptionKey($spMetadata));
        }

        return $a;
    }

    /**
     * Build a authentication response based on information in the metadata.
     *
     * @param \SimpleSAML\Configuration $idpMetadata The metadata of the IdP.
     * @param \SimpleSAML\Configuration $spMetadata The metadata of the SP.
     * @param string                    $consumerURL The Destination URL of the response.
     *
     * @return \SAML2\Response The SAML2 Response corresponding to the given data.
     */
    private static function buildResponse(
        Configuration $idpMetadata,
        Configuration $spMetadata,
        string $consumerURL
    ): Response {
        $signResponse = $spMetadata->getOptionalBoolean('saml20.sign.response', null);
        if ($signResponse === null) {
            $signResponse = $idpMetadata->getOptionalBoolean('saml20.sign.response', true);
        }

        $r = new Response();
        $issuer = new Issuer();
        $issuer->setValue($idpMetadata->getString('entityid'));
        $issuer->setFormat(Constants::NAMEID_ENTITY);
        $r->setIssuer($issuer);
        $r->setDestination($consumerURL);

        if ($signResponse) {
            \SimpleSAML\Module\saml\Message::addSign($idpMetadata, $spMetadata, $r);
        }

        return $r;
    }

   /**
     * Helper function for encoding attributes.
     *
     * @param \SimpleSAML\Configuration $idpMetadata The metadata of the IdP.
     * @param \SimpleSAML\Configuration $spMetadata The metadata of the SP.
     * @param array $attributes The attributes of the user.
     *
     * @return array  The encoded attributes.
     *
     * @throws \SimpleSAML\Error\Exception In case an unsupported encoding is specified by configuration.
     */
    private static function encodeAttributes(
        Configuration $idpMetadata,
        Configuration $spMetadata,
        array $attributes
    ): array {
        $base64Attributes = $spMetadata->getOptionalBoolean('base64attributes', null);
        if ($base64Attributes === null) {
            $base64Attributes = $idpMetadata->getOptionalBoolean('base64attributes', false);
        }

        if ($base64Attributes) {
            $defaultEncoding = 'base64';
        } else {
            $defaultEncoding = 'string';
        }

        $srcEncodings = $idpMetadata->getOptionalArray('attributeencodings', []);
        $dstEncodings = $spMetadata->getOptionalArray('attributeencodings', []);

        /*
         * Merge the two encoding arrays. Encodings specified in the target metadata
         * takes precedence over the source metadata.
         */
        $encodings = array_merge($srcEncodings, $dstEncodings);

        $ret = [];
        foreach ($attributes as $name => $values) {
            $ret[$name] = [];
            if (array_key_exists($name, $encodings)) {
                $encoding = $encodings[$name];
            } else {
                $encoding = $defaultEncoding;
            }

            foreach ($values as $value) {
                // allow null values
                if ($value === null) {
                    $ret[$name][] = $value;
                    continue;
                }

                $attrval = $value;
                if ($value instanceof DOMNodeList) {
                    /** @psalm-suppress PossiblyNullPropertyFetch */
                    $attrval = new AttributeValue($value->item(0)->parentNode);
                }

                switch ($encoding) {
                    case 'string':
                        $value = (string) $attrval;
                        break;
                    case 'base64':
                        $value = base64_encode((string) $attrval);
                        break;
                    case 'raw':
                        if (is_string($value)) {
                            $doc = DOMDocumentFactory::fromString('<root>' . $value . '</root>');
                            /** @psalm-suppress PossiblyNullPropertyFetch */
                            $value = $doc->firstChild->childNodes;
                        }
                        Assert::isInstanceOfAny($value, [\DOMNodeList::class, \SAML2\XML\saml\NameID::class]);
                        break;
                    default:
                        throw new Error\Exception('Invalid encoding for attribute ' .
                            var_export($name, true) . ': ' . var_export($encoding, true));
                }
                $ret[$name][] = $value;
            }
        }

        return $ret;
    }

    /**
     * Encrypt an assertion.
     *
     * This function takes in a \SAML2\Assertion and encrypts it if encryption of
     * assertions are enabled in the metadata.
     *
     * @param \SimpleSAML\Configuration $idpMetadata The metadata of the IdP.
     * @param \SimpleSAML\Configuration $spMetadata The metadata of the SP.
     * @param \SAML2\Assertion $assertion The assertion we are encrypting.
     *
     * @return \SAML2\Assertion|\SAML2\EncryptedAssertion  The assertion.
     *
     * @throws \SimpleSAML\Error\Exception In case the encryption key type is not supported.
     */
    private static function encryptAssertion(
        Configuration $idpMetadata,
        Configuration $spMetadata,
        Assertion $assertion
    ) {
        $encryptAssertion = $spMetadata->getOptionalBoolean('assertion.encryption', null);
        if ($encryptAssertion === null) {
            $encryptAssertion = $idpMetadata->getOptionalBoolean('assertion.encryption', false);
        }
        if (!$encryptAssertion) {
            // we are _not_ encrypting this assertion, and are therefore done
            return $assertion;
        }


        $sharedKey = $spMetadata->getOptionalString('sharedkey', null);
        if ($sharedKey !== null) {
            $algo = $spMetadata->getOptionalString('sharedkey_algorithm', null);
            if ($algo === null) {
                // If no algorithm is configured, use a sane default
                $algo = $idpMetadata->getOptionalString('sharedkey_algorithm', XMLSecurityKey::AES128_GCM);
            }

            $key = new XMLSecurityKey($algo);
            $key->loadKey($sharedKey);
        } else {
            $keys = $spMetadata->getPublicKeys('encryption', true);
            if (!empty($keys)) {
                $key = $keys[0];
                switch ($key['type']) {
                    case 'X509Certificate':
                        $pemKey = "-----BEGIN CERTIFICATE-----\n" .
                            chunk_split($key['X509Certificate'], 64) .
                            "-----END CERTIFICATE-----\n";
                        break;
                    default:
                        throw new Error\Exception('Unsupported encryption key type: ' . $key['type']);
                }

                // extract the public key from the certificate for encryption
                $key = new XMLSecurityKey(XMLSecurityKey::RSA_OAEP_MGF1P, ['type' => 'public']);
                $key->loadKey($pemKey);
            } else {
                throw new Error\ConfigurationError(
                    'Missing encryption key for entity `' . $spMetadata->getString('entityid') . '`',
                    $spMetadata->getString('metadata-set') . '.php',
                    null
                );
            }
        }

        $ea = new EncryptedAssertion();
        $ea->setAssertion($assertion, $key);
        return $ea;
    }

    /**
     * Helper for buildAssertion to decide on an NameID to set
     */
    private static function generateNameId(
        Configuration $idpMetadata,
        Configuration $spMetadata,
        array $state
    ): NameID {
        Logger::debug('Determining value for NameID');
        $nameIdFormat = null;

        if (isset($state['saml:NameIDFormat'])) {
            $nameIdFormat = $state['saml:NameIDFormat'];
        }

        if ($nameIdFormat === null || !isset($state['saml:NameID'][$nameIdFormat])) {
            // either not set in request, or not set to a format we supply. Fall back to old generation method
            $nameIdFormat = current($spMetadata->getOptionalArrayizeString('NameIDFormat', []));
            if ($nameIdFormat === false) {
                $nameIdFormat = current(
                    $idpMetadata->getOptionalArrayizeString('NameIDFormat', [Constants::NAMEID_TRANSIENT])
                );
            }
        }

        if (isset($state['saml:NameID'][$nameIdFormat])) {
            Logger::debug(sprintf('NameID of desired format %s found in state', var_export($nameIdFormat, true)));
            return $state['saml:NameID'][$nameIdFormat];
        }

        // We have nothing else to work with, so default to transient
        if ($nameIdFormat !== Constants::NAMEID_TRANSIENT) {
            Logger::notice(sprintf(
                'Requested NameID of format %s, but can only provide transient',
                var_export($nameIdFormat, true)
            ));
            $nameIdFormat = Constants::NAMEID_TRANSIENT;
        }

        $randomUtils = new Utils\Random();
        $nameIdValue = $randomUtils->generateID();

        $spNameQualifier = $spMetadata->getOptionalString('SPNameQualifier', null);
        if ($spNameQualifier === null) {
            $spNameQualifier = $spMetadata->getString('entityid');
        }

        Logger::info(sprintf(
            'Setting NameID to (%s, %s, %s)',
            var_export($nameIdFormat, true),
            var_export($nameIdValue, true),
            var_export($spNameQualifier, true)
        ));
        $nameId = new NameID();
        $nameId->setFormat($nameIdFormat);
        $nameId->setValue($nameIdValue);
        $nameId->setSPNameQualifier($spNameQualifier);

        return $nameId;
    }

    /**
     * Determine which NameFormat we should use for attributes.
     *
     * @param \SimpleSAML\Configuration $idpMetadata The metadata of the IdP.
     * @param \SimpleSAML\Configuration $spMetadata The metadata of the SP.
     *
     * @return string  The NameFormat.
     */
    private static function getAttributeNameFormat(
        Configuration $idpMetadata,
        Configuration $spMetadata
    ): string {
        // try SP metadata first
        $attributeNameFormat = $spMetadata->getOptionalString('attributes.NameFormat', null);
        if ($attributeNameFormat !== null) {
            return $attributeNameFormat;
        }
        $attributeNameFormat = $spMetadata->getOptionalString('AttributeNameFormat', null);
        if ($attributeNameFormat !== null) {
            return $attributeNameFormat;
        }

        // look in IdP metadata
        $attributeNameFormat = $idpMetadata->getOptionalString('attributes.NameFormat', null);
        if ($attributeNameFormat !== null) {
            return $attributeNameFormat;
        }
        $attributeNameFormat = $idpMetadata->getOptionalString('AttributeNameFormat', null);
        if ($attributeNameFormat !== null) {
            return $attributeNameFormat;
        }

        // default
        return Constants::NAMEFORMAT_URI;
    }

}
