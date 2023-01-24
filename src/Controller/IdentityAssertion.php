<?php

namespace SimpleSAML\Module\fedcm\Controller;

use Exception;
use SAML2\Constants;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\IdP;
use SimpleSAML\Logger;
use SimpleSAML\Session;
use SimpleSAML\Metadata\MetaDataStorageHandler;
use SimpleSAML\Module\fedcm\FedCM\SecUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Controller class for identity assertion endpoint functionality
 * for the fedcm module.
 *
 * This class serves the opaque token containing signed assertions
 * about the user.
 *
 * @package SimpleSAML\Module\fedcm
 */
class IdentityAssertion
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var \SimpleSAML\Session */
    protected \SimpleSAML\Session $session;

    /**
     * Controller constructor.
     *
     * It initializes the global configuration and auth source configuration for the controllers implemented here.
     *
     * @param \SimpleSAML\Configuration              $config The configuration to use by the controllers.
     * @param \SimpleSAML\Session                    $session The session to use by the controllers.
     *
     * @throws \Exception
     */
    public function __construct(
        Configuration $config,
        Session $session
    ) {
        $this->config = $config;
        $this->session = $session;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response|void  A Symfony Response-object.
     */
    public function main(Request $request)
    {
        Logger::debug('*** Accessing identity assertion endpoint');

        // TODO: check Referer against origin of ACS endpoint for provided client_id (entity ID)

        $httpResponseCode = 403;

        if ($request->getMethod() === 'POST' && SecUtils::isFedCmRequest($request)) {
            $moduleConfig = Configuration::getConfig('module_fedcm.php');
            $attrMap = $moduleConfig->getArray('fedcmAttributeMapping');

            $metadata = \SimpleSAML\Metadata\MetaDataStorageHandler::getMetadataHandler();
            $idpEntityId = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
            $idp = \SimpleSAML\IdP::getById('saml2:' . $idpEntityId);
            $auth = $idp->getConfig()->getString('auth');
            if (Auth\Source::getById($auth) !== null) {
                $authSource = new Auth\Simple($auth);
                if ($authSource->isAuthenticated()) {
                    // ensure that account_id matches corresponding attribute from attribute map
                    $authSourceAttrs = $authSource->getAttributes();
                    if (
                        array_key_exists($attrMap['id'], $authSourceAttrs) &&
                        $authSourceAttrs[$attrMap['id']][0] === $request->request->get('account_id')
                    ) {
                        try {
                            self::generateAuthnRequest($idp, $request->request->get('client_id'));
                        } catch (Exception $e) {
                            Logger::error($e->getMessage());
                        }
                    }
                } else {
                    Logger::warning(sprintf("User %s is not authenticated", $request->request->get('account_id')));
                }
            } else {
                Logger::error('Could not find auth source for ' . $auth);
            }
        }
        $response = new Response(
            null,
            $httpResponseCode
        );

        Logger::debug('*** returning error response');
 
        return $response;
    }

    /**
     * Generate and process an authentication request.
     *
     * @param \SimpleSAML\IdP $idp The IdP we are generating AuthnRequest for.
     */
    private static function generateAuthnRequest(IdP $idp, string $spEntityId): void
    {
        $metadata = MetaDataStorageHandler::getMetadataHandler();

        $supportedBindings = [Constants::BINDING_HTTP_POST];

        $spMetadata = $metadata->getMetaDataConfig($spEntityId, 'saml20-sp-remote');

        $acsEndpoint = $spMetadata->getDefaultEndpoint('AssertionConsumerService', $supportedBindings);

        if ($acsEndpoint === null) {
            throw new Exception('Unable to use any of the ACS endpoints found for SP \'' . $spEntityId . '\'');
        }

        $requestId = null;
        $relayState = null;
        $IDPList = [$idp->getId()];
        $ProxyCount = null;
        $RequesterID = null;
        $forceAuthn = false;
        $isPassive = false;
        $extensions = null;
        $allowCreate = true;
        $authnContext = null;
        $nameIDFormat = null;

        Logger::info(
            'fedcm: about to handle authn request for: ' . var_export($spEntityId, true)
        );

        $state = [
            'Responder' => ['\SimpleSAML\Module\fedcm\FedCM\ResponseTokenGenerator', 'sendResponseToken'],
            Auth\State::EXCEPTION_HANDLER_FUNC => [
                '\SimpleSAML\Module\saml\IdP\SAML2',
                'handleAuthError'
            ],
            'SPMetadata'                  => $spMetadata->toArray(),
            'saml:RelayState'             => $relayState,
            'saml:RequestId'              => $requestId,
            'saml:IDPList'                => $IDPList,
            'saml:ProxyCount'             => $ProxyCount,
            'saml:RequesterID'            => $RequesterID,
            'ForceAuthn'                  => $forceAuthn,
            'isPassive'                   => $isPassive,
            'saml:ConsumerURL'            => $acsEndpoint['Location'],
            'saml:Binding'                => $acsEndpoint['Binding'],
            'saml:NameIDFormat'           => $nameIDFormat,
            'saml:AllowCreate'            => $allowCreate,
            'saml:Extensions'             => $extensions,
            'saml:AuthnRequestReceivedAt' => microtime(true),
            'saml:RequestedAuthnContext'  => $authnContext,
        ];

        $idp->handleAuthenticationRequest($state);
    }

}
