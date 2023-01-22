<?php

namespace SimpleSAML\Module\fedcm\Controller;

use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Session;
use SimpleSAML\Module\fedcm\FedCM\SecUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Controller class for web-identity endpoint functionality
 * for the fedcm module.
 *
 * @package SimpleSAML\Module\fedcm
 */
class WebIdentity
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
        Logger::debug('*** Accessing web-identity endpoint');

        $output = [];
        $httpResponseCode = 403;

        if (SecUtils::isFedCmRequest($request)) {
            $output = [
                'provider_urls' => [
                    Module::getModuleURL('fedcm/manifest')
                ]
            ];
            $httpResponseCode = 200;
        }

        $response = new Response(
            json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            $httpResponseCode,
            ['Content-Type' => 'application/json;charset=utf-8']
        );

        Logger::debug('*** returning web-identity response = ' . $response->getContent());
 
        return $response;
    }
}
