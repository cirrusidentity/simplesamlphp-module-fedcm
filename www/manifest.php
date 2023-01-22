<?php

use SimpleSAML\Configuration;
use SimpleSAML\Module;

$moduleConfig = Configuration::getConfig('module_fedcm.php');

$branding = $moduleConfig->getOptionalArray('branding', []);
$output = [
    'accounts_endpoint' => Module::getModuleURL('fedcm/accounts_list'),
    'client_metadata_endpoint' => Module::getModuleURL('fedcm/client_metadata.php'),
    'id_assertion_endpoint' => Module::getModuleURL('fedcm/identity_assertion.php')
];
if ($branding) {
    $output['branding'] = $branding;
}

header("Content-Type: application/json;charset=utf-8");
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
