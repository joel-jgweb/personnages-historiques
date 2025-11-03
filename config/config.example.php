<?php
// config/config.example.php
// Exemple non sensible de configuration pour le projet personnages-historiques
return [
    'root_path' => dirname(__DIR__),
    'data_path' => dirname(__DIR__) . '/data',
    'public_path' => dirname(__DIR__) . '/www',
    'db' => [
        'driver' => 'sqlite',
        'sqlite_path' => dirname(__DIR__) . '/data/portraits.sqlite',
    ],
    'base_url' => 'http://localhost',
    'display_errors' => true,
    'log_path' => dirname(__DIR__) . '/logs',
];
