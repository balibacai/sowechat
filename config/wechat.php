<?php

return [

    'debug' => env('APP_DEBUG', false),

    'web_api' => [
        'connect_timeout' => 30,
        'max_attempts' => 10,
    ],

    'job' => [
        'connection' => 'default',
        'queue' => 'default',
    ],
];
