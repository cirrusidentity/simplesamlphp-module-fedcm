<?php

$config = [
    'branding' => [
        'background_color' => '0x56A1D3',
        'color' => '0xFFFFFF',
        'icons' => [
            [
                'url' => 'https://s3.amazonaws.com/cirrusidentity-public/images/cirrusidentity-iphone-114.png',
                'size' => 25
            ]
        ]
    ],
    'fedcmAttributeMapping' => [
        'id' => 'eduPersonPrincipalName',
        'name' => 'displayName',
        'email' => 'mail',
        'given_name' => 'givenName'
    ],
    'clientMetadataMapping' => [
        'https://sp.college.edu/sp' => [
            'privacyUrl' => 'https://www.cirrusidentity.com/privacy-policy',
            'termsOfServiceUrl' => 'https://www.cirrusidentity.com/terms-of-service'
        ]
    ]
];
