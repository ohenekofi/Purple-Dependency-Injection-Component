<?php

return [
    'host' => 'localhost',
    'database' => 'games',
    'username' => 'root',
    'password' => '',
    'driver' => 'mysql',
    'cache' => [
        'driver' => 'redis',
        'host' => 'redis.example.com',
        'port' => 6379,
    ],
    'debug' => false,
    'log_level' => 'error',
    // You can even use environment variables or conditionals
    'api_key' => getenv('API_KEY') ?: 'default_api_key',
    'feature_flags' => [
        'new_feature' => PHP_VERSION_ID >= 70400,
    ],
];
/*
return [
    'database' => [
        'host' => 'localhost',
        'name' => 'production_db',
        'user' => 'prod_user',
        'password' => 'prod_password',
    ],
    'cache' => [
        'driver' => 'redis',
        'host' => 'redis.example.com',
        'port' => 6379,
    ],
    'debug' => false,
    'log_level' => 'error',
    // You can even use environment variables or conditionals
    'api_key' => getenv('API_KEY') ?: 'default_api_key',
    'feature_flags' => [
        'new_feature' => PHP_VERSION_ID >= 70400,
    ],
];
*/