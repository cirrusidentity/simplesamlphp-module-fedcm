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
 * Controller class for "signin_url" functionality for the
 * fedcm module.
 *
 * This class forces sign-in if there is no authenticated session
 * for the request.
 *
 * @package SimpleSAML\Module\fedcm
 */
class SignIn
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
        Logger::debug('*** Accessing signin_url endpoint');

        $httpResponseCode = 403;
        $headers = [];

        if (SecUtils::isFedCmRequest($request)) {
            $metadata = \SimpleSAML\Metadata\MetaDataStorageHandler::getMetadataHandler();
            $idpEntityId = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
            $idp = \SimpleSAML\IdP::getById('saml2:' . $idpEntityId);
            $auth = $idp->getConfig()->getString('auth');
            if (Auth\Source::getById($auth) !== null) {
                $authSource = new Auth\Simple($auth);
                // force authentication if not authenticated, else return immediately
                Logger::debug('*** Requiring auth...');
                $authSource->requireAuth();
                Logger::debug('*** requireAuth() returned');
                // tell FedCM API user is signed-in
                $headers['IdP-Sign-in-Status'] = 'action=login';
                $httpResponseCode = 200;
            }
        }
        $response = new Response(
            null,
            $httpResponseCode,
            $headers
        );
 
        return $response;
    }
}
