<?php

$config = [

    // This is a authentication source which handles admin authentication.
    'admin' => [
        'core:AdminPassword',
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
