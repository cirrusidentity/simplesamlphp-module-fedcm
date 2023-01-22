<?php

namespace SimpleSAML\Module\fedcm\Controller;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Session;
use SimpleSAML\Module\fedcm\FedCM\SecUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Controller class for manifest endpoint functionality
 * for the fedcm module.
 *
 * @package SimpleSAML\Module\fedcm
 */
class Manifest
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
        Logger::debug('*** Accessing manifest endpoint');

        $output = [];
        $httpResponseCode = 403;

        if (SecUtils::isFedCmRequest($request)) {
            $moduleConfig = Configuration::getConfig('module_fedcm.php');

            $branding = $moduleConfig->getOptionalArray('branding', []);
            $output = [
                'accounts_endpoint' => Module::getModuleURL('fedcm/accounts-list'),
                'client_metadata_endpoint' => Module::getModuleURL('fedcm/client-metadata'),
                'id_assertion_endpoint' => Module::getModuleURL('fedcm/identity-assertion')
            ];
            if ($branding) {
                $output['branding'] = $branding;
            }
            $httpResponseCode = 200;
        }

        $response = new Response(
            json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            $httpResponseCode,
            ['Content-Type' => 'application/json;charset=utf-8']
        );

        Logger::debug('*** returning manifest response = ' . $response->getContent());
 
        return $response;
    }
}
