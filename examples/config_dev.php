<?php
// config/config_dev.php

return [
    'database' => [
        'host' => 'localhost',
        'username' => 'dev_user',
        'password' => 'dev_password',
        'database' => 'myapp_development',
    ],
    'cache' => [
        'driver' => 'file',
        'path' => '/tmp/cache',
    ],
    'debug' => true,
    'log_level' => 'debug',
    'api_endpoint' => 'http://localhost:8000/api/v1',
];