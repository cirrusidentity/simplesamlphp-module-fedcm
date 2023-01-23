<?php

namespace SimpleSAML\Module\fedcm\Controller;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Session;
use SimpleSAML\Module\fedcm\FedCM\SecUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Controller class for client metadata functionality for the
 * fedcm module.
 *
 * This class serves the privacy and TOS URLs for the provided client ID.
 *
 * @package SimpleSAML\Module\fedcm
 */
class ClientMetadata
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
        Logger::debug('*** Accessing client metadata endpoint');

        $metadata = [];
        $httpResponseCode = 403;

        if (SecUtils::isFedCmRequest($request)) {
            $clientId = $request->query->get('client_id');

            $moduleConfig = Configuration::getConfig('module_fedcm.php');
            $metadataMap = $moduleConfig->getArray('clientMetadataMapping');

            if (array_key_exists($clientId, $metadataMap)) {
                $metadata['privacy_policy_url'] = $metadataMap[$clientId]['privacyUrl'];
                $metadata['terms_of_service_url'] = $metadataMap[$clientId]['termsOfServiceUrl'];
            }
            $httpResponseCode = 200;
        }

        $response = new Response(
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            $httpResponseCode,
            ['Content-Type' => 'application/json;charset=utf-8']
        );

        Logger::debug('*** returning response = ' . $response->getContent());
 
        return $response;
    }
}
