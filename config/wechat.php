<?php

return [

    'debug' => env('WECHAT_DEBUG', false),

    'web_api' => [
        'connect_timeout' => 30,
        'max_attempts' => 10,
    ],

    'job' => [
        'connection' => env('QUEUE_DRIVER', 'redis'),
        'queue' => env('JOB_QUEUE', 'default'),
    ],
];
