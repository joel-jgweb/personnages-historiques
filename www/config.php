<?php
// www/config.php
// Bootstrap public : charge la configuration centrale dans /config
// Préfère config/config.local.php (ignoré par git) puis config/config.example.php

$localConfig = dirname(__DIR__) . '/config/config.local.php';
$exampleConfig = dirname(__DIR__) . '/config/config.example.php';

if (file_exists($localConfig)) {
    $CONFIG = require $localConfig;
} elseif (file_exists($exampleConfig)) {
    $CONFIG = require $exampleConfig;
} else {
    throw new RuntimeException('Configuration file not found. Create config/config.local.php from config/config.local.php.template or provide config/config.example.php.');
}

// Defaults
$CONFIG['root_path']   = $CONFIG['root_path']   ?? dirname(__DIR__);
$CONFIG['data_path']   = $CONFIG['data_path']   ?? $CONFIG['root_path'] . '/data';
$CONFIG['public_path'] = $CONFIG['public_path'] ?? $CONFIG['root_path'] . '/www';

// SQLite path explicit or constructed from data_path
if (!empty($CONFIG['db']['sqlite_path'])) {
    $DB_SQLITE_PATH = $CONFIG['db']['sqlite_path'];
} else {
    $DB_SQLITE_PATH = rtrim($CONFIG['data_path'], '/') . '/portraits.sqlite';
}

// Helper function to get a PDO connection to the SQLite DB
if (!function_exists('get_sqlite_pdo')) {
    function get_sqlite_pdo($path = null) {
        global $DB_SQLITE_PATH;
        $sqlite = $path ?? $DB_SQLITE_PATH;
        if (!file_exists($sqlite)) {
            throw new RuntimeException('SQLite database not found at: ' . $sqlite);
        }
        $pdo = new PDO('sqlite:' . $sqlite);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}

return $CONFIG;
?>