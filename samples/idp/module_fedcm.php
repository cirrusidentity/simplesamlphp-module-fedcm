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
];
