<?php
// Affiche les erreurs PHP pour debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

require_once __DIR__ . '/../config.php';
$dbPath = __DIR__ . '/../../data/portraits.sqlite';
$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Vérifie si c'est un upload AJAX
if (empty($_POST['ajax']) || empty($_FILES['fileToUpload'])) {
    echo json_encode(['success' => false, 'message' => 'Requête incorrecte']);
    exit;
}

$description = trim($_POST['description'] ?? '');
if (!$description) {
    echo json_encode(['success' => false, 'message' => 'La description est obligatoire']);
    exit;
}

$file = $_FILES['fileToUpload'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Erreur upload']);
    exit;
}

// Détermine le dossier de stockage (../docs)
$docs_dir = __DIR__ . '/../../docs/';
if (!is_dir($docs_dir)) {
    mkdir($docs_dir, 0775, true);
}

// Sécurité : nettoie le nom de fichier original
$original_name = preg_replace('/[^A-Za-z0-9_\-.]/', '_', basename($file['name']));

// Calcule le hash du fichier
$file_content = file_get_contents($file['tmp_name']);
$md5_hash = md5($file_content);

// Vérifie si le fichier existe déjà dans la base
$stmt = $pdo->prepare("SELECT chemin_rel, description FROM gesdoc WHERE md5_hash = ?");
$stmt->execute([$md5_hash]);
$exists = $stmt->fetch(PDO::FETCH_ASSOC);

if ($exists) {
    // Fichier déjà présent, ne le réuploade pas
    echo json_encode([
        'success' => true,
        'already_exists' => true,
        'md5_hash' => $md5_hash,
        'chemin_rel' => $exists['chemin_rel'],
        'description' => $exists['description'],
        'message' => 'Fichier déjà présent dans la base. Utilisation du fichier existant.'
    ]);
    exit;
}

// Construit le chemin de stockage
$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
$stored_name = $md5_hash . ($ext ? ".$ext" : "");
$chemin_rel = 'docs/' . $stored_name;
$full_path = $docs_dir . $stored_name;

// Sauvegarde le fichier
if (!move_uploaded_file($file['tmp_name'], $full_path)) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde du fichier']);
    exit;
}

// Ajoute l'entrée dans gesdoc
$stmt = $pdo->prepare("INSERT INTO gesdoc (md5_hash, chemin_rel, nom_fichier, description) VALUES (?, ?, ?, ?)");
$stmt->execute([$md5_hash, $chemin_rel, $stored_name, $description]);

echo json_encode([
    'success' => true,
    'md5_hash' => $md5_hash,
    'chemin_rel' => $chemin_rel,
    'description' => $description,
    'new_file' => true,
    'message' => 'Fichier uploadé et enregistré avec succès.'
]);
exit;
?>