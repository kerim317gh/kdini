<?php

return [
    'connections' => [
        'default' => [
            'host'   => env('FTP_HOST', 'ftp.example.com'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            'root'   => env('FTP_ROOT', '/'), // The root directory on the FTP server
            'port'   => env('FTP_PORT', 21),
            'ssl'    => env('FTP_SSL', false),
            'timeout'  => 30,
        ],
    ],
];
