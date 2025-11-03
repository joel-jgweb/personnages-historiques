<?php
// acces_docs.php - Script sécurisé pour servir les fichiers uploadés dans /data/docs/
// Utilisation : /acces_docs.php?f=IMG_250427120000.jpg

$CONFIG = require_once __DIR__ . '/config.php';

// Chemin vers le dossier sécurisé (en dehors de www/)
$docsPath = rtrim($CONFIG['data_path'], '/') . '/docs/';
define('SECURE_DOCS_DIR', $docsPath);

// Récupérer le nom du fichier demandé
$filename = $_GET['f'] ?? '';

// Validation stricte du nom de fichier
if (empty($filename)) {
    http_response_code(400);
    exit('❌ Nom de fichier manquant.');
}

// Bloquer les tentatives de traversal (ex: ../../etc/passwd)
if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || $filename[0] === '.') {
    http_response_code(403);
    exit('❌ Accès interdit.');
}

// Vérifier l'extension (sécurité + compatibilité MIME)
$allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'zip'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_exts)) {
    http_response_code(403);
    exit('❌ Format non autorisé.');
}

// Chemin complet du fichier
$filepath = SECURE_DOCS_DIR . $filename;

// Vérifier que le fichier existe
if (!file_exists($filepath)) {
    http_response_code(404);
    exit('❌ Fichier non trouvé.');
}

// Déterminer le bon Content-Type
$mimeType = mime_content_type($filepath);
if ($mimeType === false) {
    // Fallback si mime_content_type() échoue
    $mimeMap = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain',
        'zip'  => 'application/zip'
    ];
    $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';
}

// Servir le fichier
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;