<?php

$config = [

    // This is a authentication source which handles admin authentication.
    'admin' => [
        'core:AdminPassword',
    ],

    'default-sp' => [
        'saml:SP',
        'entityID' => 'https://sp.college.edu/sp',
        'privatekey' => 'server.pem',
        'certificate' => 'server.crt',
        'idp' => 'urn:fedcm:idp',
        'AssertionConsumerService' => [
            [
                'index' => 0,
                'isDefault' => true,
                'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                'Location' => 'https://your-forwarding-url.ngrok.io/sample-idp/module.php/saml/sp/saml2-acs.php/default-sp'
            ]
        ]
    ],

    'example-userpass' => [
        'exampleauth:UserPass',
        'student:studentpass' => [
            'uid' => ['student'],
            'eduPersonPrincipalName' => 'student@college.edu',
            'givenName' => 'Firsty',
            'sn' => 'Lasty',
            'displayName' => 'Firsty Lasty',
            'mail' => 'student@college.edu'
        ]
    ]
];
