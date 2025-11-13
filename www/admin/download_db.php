<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'permissions.php';
checkUserPermission([1]);

// Chemin vers la base de données
$dbPath = '../../data/portraits.sqlite';

if (!file_exists($dbPath)) {
    die("Erreur : fichier portraits.sqlite introuvable.");
}

// Forcer le téléchargement
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="portraits.sqlite"');
header('Content-Length: ' . filesize($dbPath));

readfile($dbPath);
exit;

?>
