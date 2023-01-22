<?php

namespace SimpleSAML\Module\fedcm\FedCM;

use Symfony\Component\HttpFoundation\Request;

/**
 * Various security utilities for use within the fedcm module.
 * @package SimpleSAML\Module\fedcm\FedCM
 */
class SecUtils
{
    /**
     * Check if request was initiated by FedCM API by
     * checking if Sec-Fetch-Dest headers is set to 'webidentity'
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return boolean
     */
    public static function isFedCmRequest(Request $request): bool
    {
        return ($request->headers->get('Sec-Fetch-Dest') === 'webidentity');
    }
}
