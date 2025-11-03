<?php
session_start();
$CONFIG = require_once __DIR__ . '/config.php';
// Ici tu peux vérifier les droits d'accès si besoin

if (!isset($_GET['file'])) {
    http_response_code(400);
    exit('Missing file parameter.');
}
$filename = basename($_GET['file']); // Sécurité : pas de path traversal
$docsPath = rtrim($CONFIG['data_path'], '/') . '/docs';
$filepath = $docsPath . "/" . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    exit('File not found.');
}

// Détection du type MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filepath);
header('Content-Type: ' . $mime);

// Pour afficher ou télécharger selon l'usage
if (isset($_GET['download'])) {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}

readfile($filepath);
exit;
?>