<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbPath = __DIR__ . '/../data/portraits.sqlite';
$dataDir = __DIR__ . '/../data';

echo "<h2>🔍 Diagnostic des Permissions</h2>";

// Tester l'existence du fichier
if (!file_exists($dbPath)) {
    die("<p style='color:red;'>❌ Le fichier <code>portraits.sqlite</code> n'existe pas à l'emplacement : " . htmlspecialchars($dbPath) . "</p>");
} else {
    echo "<p style='color:green;'>✅ Fichier <code>portraits.sqlite</code> trouvé.</p>";
}

// Tester les droits de lecture
if (!is_readable($dbPath)) {
    die("<p style='color:red;'>❌ Le fichier <code>portraits.sqlite</code> n'est PAS lisible par le serveur web.</p>");
} else {
    echo "<p style='color:green;'>✅ Fichier <code>portraits.sqlite</code> lisible.</p>";
}

// Tester les droits d'écriture sur le fichier
if (!is_writable($dbPath)) {
    echo "<p style='color:orange;'>⚠️ Le fichier <code>portraits.sqlite</code> n'est PAS accessible en écriture.</p>";
} else {
    echo "<p style='color:green;'>✅ Fichier <code>portraits.sqlite</code> accessible en écriture.</p>";
}

// Tester les droits d'écriture sur le dossier
if (!is_writable($dataDir)) {
    echo "<p style='color:orange;'>⚠️ Le dossier <code>data/</code> n'est PAS accessible en écriture.</p>";
} else {
    echo "<p style='color:green;'>✅ Dossier <code>data/</code> accessible en écriture.</p>";
}

// Tester la connexion PDO
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green;'>✅ Connexion à la base de données réussie.</p>";

    // Tester une requête simple
    $stmt = $pdo->query("SELECT COUNT(*) FROM personnages");
    $count = $stmt->fetchColumn();
    echo "<p style='color:green;'>✅ Requête SQL réussie. Nombre de fiches : " . $count . "</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erreur PDO : " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>