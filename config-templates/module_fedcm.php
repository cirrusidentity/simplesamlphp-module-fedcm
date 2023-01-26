<?php

$config = [
    # The "branding" structure below mirrors the structure detailed at https://fedidcg.github.io/FedCM/#dictdef-identityproviderbranding 
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
    # Map of FedCM account attributes (https://fedidcg.github.io/FedCM/#dictdef-identityprovideraccount) to the IdP attributes you wish to use to populate them
    'fedcmAttributeMapping' => [
        'id' => 'eduPersonPrincipalName',
        'name' => 'displayName',
        'email' => 'mail',
        'given_name' => 'givenName'
    ],
    # Map of RP entity IDs (which we map to the FedCM client ID (https://fedidcg.github.io/FedCM/#client-id)) to a map containing the privacy URL and terms-of-service URL, which will be returned from the client metadata endpoint (https://fedidcg.github.io/FedCM/#idp-api-client-id-metadata-endpoint)
    'clientMetadataMapping' => [
        'https://sp.college.edu/sp' => [
            'privacyUrl' => 'https://www.cirrusidentity.com/privacy-policy',
            'termsOfServiceUrl' => 'https://www.cirrusidentity.com/terms-of-service'
        ]
    ]
];
