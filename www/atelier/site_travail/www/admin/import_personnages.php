<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$csvFile = '/var/alternc/html/i/ihs/atelier/site_travail/data/personnages.csv';
$dbFile  = '/var/alternc/html/i/ihs/atelier/site_travail/data/portraits.sqlite'; // ⚠️ adapte si besoin

if (!file_exists($csvFile)) die("Fichier CSV introuvable.\n");

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur SQLite : " . $e->getMessage() . "\n");
}

$handle = fopen($csvFile, 'r');
if (!$handle) die("Impossible d'ouvrir le CSV.\n");

// 👇 ICI : on utilise ';' comme séparateur
$headers = fgetcsv($handle, 0, ';'); // <-- C'EST LA CLÉ !
if ($headers === false) die("CSV vide.\n");

// Nettoyer les espaces éventuels (ex: " nom " → "nom")
$headers = array_map('trim', $headers);

// Vérification de sécurité : s'assurer qu'aucun en-tête n'est vide
if (in_array('', $headers, true)) {
    die("Erreur : un en-tête est vide. Vérifie ton CSV.\n");
}

// Préparer la requête
$columns = '"' . implode('", "', $headers) . '"';
$placeholders = ':' . implode(', :', $headers);
$sql = "INSERT INTO personnages ($columns) VALUES ($placeholders)";

$stmt = $pdo->prepare($sql);

$compteur = 0;
while (($row = fgetcsv($handle, 0, ';')) !== false) { // 👈 aussi ici !
    $data = [];
    foreach ($headers as $i => $col) {
        $value = isset($row[$i]) ? trim($row[$i]) : '';
        $data[$col] = ($value === '') ? null : $value;
    }
    $stmt->execute($data);
    $compteur++;
}

fclose($handle);
echo "✅ $compteur personnages importés avec succès.\n";