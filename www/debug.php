<?php
// Debug helper — supprimer après usage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Debug PHP — index.php diagnostics</h2>";

// 1) Version PHP et extensions utiles
echo "<h3>PHP</h3><pre>";
echo "PHP Version: " . PHP_VERSION . PHP_EOL;
echo "Extensions: " . PHP_EOL;
$exts = ['pdo', 'pdo_sqlite', 'sqlite3'];
foreach ($exts as $e) {
    echo sprintf("  %s: %s\n", $e, extension_loaded($e) ? 'OK' : 'MISSING');
}
echo "</pre>";

// 2) Vérifier existence des fichiers critiques
$files = [
    '__DIR__/config.php' => __DIR__ . '/config.php',
    'config/config.php' => __DIR__ . '/../config/config.php',
    'config/config.local.php' => __DIR__ . '/../config/config.local.php',
    'data/portraits.sqlite' => __DIR__ . '/../data/portraits.sqlite',
];
echo "<h3>Fichiers</h3><pre>";
foreach ($files as $label => $path) {
    echo $label . " => " . $path . " : ";
    if (file_exists($path)) {
        echo "FOUND (size " . filesize($path) . " bytes)\n";
    } else {
        echo "MISSING\n";
    }
}
echo "</pre>";

// 3) Tester require_once de www/config.php
echo "<h3>Include test</h3><pre>";
$ok = @include_once __DIR__ . '/config.php';
if ($ok === false) {
    echo "include_once __DIR__ . '/config.php' FAILED\n";
} else {
    echo "include_once __DIR__ . '/config.php' OK\n";
}
echo "</pre>";

// 4) Tester connexion SQLite si fichier présent
echo "<h3>Test PDO SQLite</h3><pre>";
$dbPath = __DIR__ . '/../data/portraits.sqlite';
if (!file_exists($dbPath)) {
    echo "DB file not found: $dbPath\n";
} else {
    try {
        $pdo = new PDO("sqlite:$dbPath");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "PDO connection OK\n";
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' LIMIT 3");
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables (up to 3): " . implode(', ', $rows) . "\n";
    } catch (Exception $e) {
        echo "PDO ERROR: " . $e->getMessage() . "\n";
    }
}
echo "</pre>";

// End
echo "<p>Supprime ce fichier debug.php dès que possible.</p>";
?>