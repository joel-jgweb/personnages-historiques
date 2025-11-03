<?php
/**
 * config.example.php - Example configuration file
 * Copy this file to config.local.php and customize for your environment
 */

return [
    // Database configuration
    'db' => [
        'type' => 'sqlite',
        'path' => __DIR__ . '/../data/portraits.sqlite',
    ],
    
    // Path configuration
    'paths' => [
        'root' => dirname(__DIR__),
        'data' => dirname(__DIR__) . '/data',
        'www' => dirname(__DIR__) . '/www',
    ],
    
    // Application settings
    'app' => [
        'debug' => false,
        'timezone' => 'Europe/Paris',
    ],
];
