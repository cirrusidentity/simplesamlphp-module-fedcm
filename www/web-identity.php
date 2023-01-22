<?php

use SimpleSAML\Module;

$output = [
    'provider_urls' => [
        Module::getModuleURL('fedcm/manifest.php')
    ]
];

header("Content-Type: application/json;charset=utf-8");
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
