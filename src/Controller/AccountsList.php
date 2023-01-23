<?php

namespace SimpleSAML\Module\fedcm\Controller;

use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Session;
use SimpleSAML\Module\fedcm\FedCM\SecUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Controller class for accounts list functionality for the
 * fedcm module.
 *
 * This class serves the list of signed-in accounts on the IdP
 * for the user.
 *
 * @package SimpleSAML\Module\fedcm
 */
class AccountsList
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
     * @return \Symfony\Component\HttpFoundation\Response  A Symfony Response-object.
     */
    public function main(Request $request): Response
    {
        Logger::debug('*** Accessing accounts list endpoint');

        $accts = [];
        $httpResponseCode = 403;

        if (SecUtils::isFedCmRequest($request)) {
            $moduleConfig = Configuration::getConfig('module_fedcm.php');
            $attrMap = $moduleConfig->getArray('fedcmAttributeMapping');

            $metadata = \SimpleSAML\Metadata\MetaDataStorageHandler::getMetadataHandler();
            $idpEntityId = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
            $idp = \SimpleSAML\IdP::getById('saml2:' . $idpEntityId);
            $auth = $idp->getConfig()->getString('auth');
            if (Auth\Source::getById($auth) !== null) {
                $authSource = new Auth\Simple($auth);
                if ($authSource->isAuthenticated()) {
                    $authSourceAttrs = $authSource->getAttributes();
                    Logger::debug('*** attrs = ' . print_r($authSourceAttrs, true));
                    $acctAttrs = [];
                    foreach ($attrMap as $key => $val) {
                        if (array_key_exists($val, $authSourceAttrs)) {
                            $acctAttrs[$key] = $authSourceAttrs[$val][0];
                        }
                    }
                    if ($acctAttrs) {
                        $accts[] = $acctAttrs;
                    }
                }
                $httpResponseCode = 200;
            }
        }
        $response = new Response(
            json_encode(['accounts' => $accts], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            $httpResponseCode,
            ['Content-Type' => 'application/json;charset=utf-8']
        );

        Logger::debug('*** returning response = ' . $response->getContent());
 
        return $response;
    }
}
