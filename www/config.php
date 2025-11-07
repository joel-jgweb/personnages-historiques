<?php
// www/config.php
// Bootstrap minimal pour les scripts web (inclut ../config/config.php et charge config.local.php si présent).
// Ce fichier doit exister dans la racine publique (www/) parce que de nombreux scripts font require_once __DIR__ . '/config.php'.

// Production : ne pas afficher les erreurs directement (active temporairement pour debug si nécessaire)
if (!ini_get('display_errors')) {
    ini_set('display_errors', 0);
}
error_reporting(E_ALL);

// Inclure les fonctions et utilitaires versionnés (config/config.php)
$centralConfigPath = __DIR__ . '/../config/config.php';
if (file_exists($centralConfigPath)) {
    require_once $centralConfigPath;
} else {
    // Si le fichier central manque, provoquons un message lisible plutôt qu'un fatal silencieux.
    error_log("www/config.php: fichier central manquant: $centralConfigPath");
    // Ne pas die() pour permettre un debugging plus propre ; les scripts consumeront l'absence de fonctions.
}

// Charger la config locale (non versionnée) si présente
$localConfig = [];
$localPath = __DIR__ . '/../config/config.local.php';
if (file_exists($localPath)) {
    // Le fichier doit retourner un tableau, ex: return [ 'database_path' => '/chemin/portraits.sqlite', ... ];
    $cfg = require $localPath;
    if (is_array($cfg)) {
        $localConfig = $cfg;
    } else {
        error_log("www/config.php: config.local.php ne retourne pas un tableau.");
    }
}

// Helpers utilitaires pour chemins (usage : getDatabasePath(), getDataDir(), getDocsDir())
if (!function_exists('getDataDir')) {
    function getDataDir(): string {
        global $localConfig;
        if (!empty($localConfig['data_path'])) {
            return rtrim($localConfig['data_path'], '/\\');
        }
        return __DIR__ . '/../data';
    }
}

if (!function_exists('getDatabasePath')) {
    function getDatabasePath(): string {
        global $localConfig;
        if (!empty($localConfig['database_path'])) {
            return $localConfig['database_path'];
        }
        return getDataDir() . '/portraits.sqlite';
    }
}

if (!function_exists('getDocsDir')) {
    function getDocsDir(): string {
        global $localConfig;
        if (!empty($localConfig['docs_path'])) {
            return rtrim($localConfig['docs_path'], '/\\') . '/';
        }
        return getDataDir() . '/docs/';
    }
}

// Fournir $config global si loadSiteConfig est disponible
if (!function_exists('loadSiteConfig')) {
    // loadSiteConfig devrait être défini dans ../config/config.php ; si absent on expose des valeurs par défaut légères
    function loadSiteConfig($pdo = null): array {
        return [
            'site_title' => 'Personnages Historiques',
            'site_subtitle' => '',
            'primary_color' => '#2c3e50',
            'secondary_color' => '#6c757d',
            'background_color' => '#ffffff',
            'logo_path' => null,
            'background_image' => null,
        ];
    }
}

// Optionnel : rendre $localConfig et fonctions accessibles aux scripts qui incluent ce fichier
return null;
?>