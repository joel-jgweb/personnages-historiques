<?php
/**
 * upload_docs.php
 * Gère l'upload sécurisé de documents/images pour les sections Iconographie et Documents.
 * Stocke les fichiers dans data/docs/ et les référence dans la table gesdoc.
 */

// 🔒 Désactiver l'affichage des erreurs en production
// Décommente uniquement temporairement en développement :
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

session_start();

// 🔐 Vérification : utilisateur connecté ?
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non authentifié.']);
    exit;
}

// 🔐 Vérification : rôle autorisé ?
$allowed_roles = [1, 2, 3, 6]; // Super-Admin, Admin Fiches, Rédacteur Fiches, Admin Simple
if (!in_array($_SESSION['user_statut'] ?? null, $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes pour uploader des fichiers.']);
    exit;
}

// 📁 Chemin vers le dossier de stockage (cohérent avec la structure du projet)
$docs_dir = __DIR__ . '/../../data/docs/';
if (!is_dir($docs_dir)) {
    if (!mkdir($docs_dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Impossible de créer le dossier de stockage.']);
        exit;
    }
}

// 🗃️ Connexion à la base SQLite
$dbPath = __DIR__ . '/../../data/portraits.sqlite';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Base de données introuvable.']);
    exit;
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données.']);
    exit;
}

// 📥 Vérification de la requête AJAX
if (empty($_POST['ajax']) || empty($_FILES['fileToUpload'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
    exit;
}

// 📝 Description obligatoire
$description = trim($_POST['description'] ?? '');
if (!$description) {
    echo json_encode(['success' => false, 'message' => 'La description est obligatoire.']);
    exit;
}

$file = $_FILES['fileToUpload'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (dépasse upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux (dépasse MAX_FILE_SIZE).',
        UPLOAD_ERR_PARTIAL    => 'Téléchargement partiel.',
        UPLOAD_ERR_NO_FILE    => 'Aucun fichier sélectionné.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
        UPLOAD_ERR_CANT_WRITE => 'Échec d’écriture sur disque.',
        UPLOAD_ERR_EXTENSION  => 'Extension bloquée.',
    ];
    $msg = $errors[$file['error']] ?? 'Erreur inconnue lors de l’upload.';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// 🔍 Validation du type MIME (sécurité essentielle)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close();

$allowedMimeTypes = [
    // Images
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    // Documents
    'application/pdf',
    'text/plain',
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
];

if (!in_array($mimeType, $allowedMimeTypes)) {
    echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Seuls les images, PDF, TXT et documents Word sont acceptés.']);
    exit;
}

// 📏 Limite de taille (10 Mo)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max. 10 Mo).']);
    exit;
}

// 🔁 Vérification de déduplication par hash
$fileContent = file_get_contents($file['tmp_name']);
$md5Hash = md5($fileContent);

$stmt = $pdo->prepare("SELECT nom_fichier FROM gesdoc WHERE md5_hash = ?");
$stmt->execute([$md5Hash]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo json_encode([
        'success' => true,
        'already_exists' => true,
        'existing_file_name' => $existing['nom_fichier'],
        'message' => 'Ce fichier existe déjà. Utilisation de la version existante.'
    ]);
    exit;
}

// 💾 Sauvegarde du fichier
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$ext = strtolower(preg_replace('/[^a-z0-9]/', '', $ext)); // nettoyer l'extension
$storedName = $md5Hash . ($ext ? '.' . $ext : '');
$fullPath = $docs_dir . $storedName;

if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
    echo json_encode(['success' => false, 'message' => 'Échec de l’enregistrement du fichier sur le serveur.']);
    exit;
}

// 📚 Enregistrement dans la base
$stmt = $pdo->prepare("INSERT INTO gesdoc (md5_hash, chemin_rel, nom_fichier, description) VALUES (?, ?, ?, ?)");
$stmt->execute([$md5Hash, 'docs/' . $storedName, $storedName, $description]);

// ✅ Succès
echo json_encode([
    'success' => true,
    'new_file_name' => $storedName,
    'message' => 'Fichier uploadé et enregistré avec succès.'
]);