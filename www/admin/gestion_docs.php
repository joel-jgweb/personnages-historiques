<?php
// gestion_docs.php - Gestion complète des documents uploadés (avec md5_hash comme clé)
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'permissions.php';
checkUserPermission([1]); // Super-Admin uniquement

define('UPLOAD_DIR', '../../data/docs/');
define('DB_PATH', '../../data/portraits.sqlite');

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("❌ Erreur BDD : " . htmlspecialchars($e->getMessage()));
}

$message = '';
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'zip'];
$max_file_size = 5 * 1024 * 1024;

// ==========================
// 1. UPLOAD
// ==========================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "upload") {
    $file_input = $_FILES["fileToUpload"] ?? null;
    $description = trim($_POST["description"] ?? '');

    if (strlen($description) > 50) {
        $message = "❌ La description ne doit pas dépasser 50 caractères.";
    } elseif (!$file_input || $file_input['error'] !== UPLOAD_ERR_OK) {
        $message = "❌ Erreur lors de l’upload.";
    } else {
        $file_ext = strtolower(pathinfo($file_input["name"], PATHINFO_EXTENSION));
        $file_size = $file_input["size"];

        if ($file_size > $max_file_size) {
            $message = "❌ Fichier trop volumineux (max 5 Mo).";
        } elseif (!in_array($file_ext, $allowed_extensions)) {
            $message = "❌ Format non autorisé. Formats : JPG, PNG, GIF, WEBP, PDF, TXT, ZIP.";
        } else {
            try {
                $md5_hash = md5_file($file_input["tmp_name"]);
                $stmt = $pdo->prepare("SELECT nom_fichier FROM gesdoc WHERE md5_hash = ?");
                $stmt->execute([$md5_hash]);
                if ($stmt->fetchColumn()) {
                    $message = "⚠️ Ce fichier existe déjà (MD5 identique).";
                } else {
                    // Déterminer préfixe
                    if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $file_type = 'image';
                        $prefix = 'IMG';
                    } elseif (in_array($file_ext, ['pdf', 'txt'])) {
                        $file_type = 'document';
                        $prefix = 'DOC';
                    } elseif ($file_ext === 'zip') {
                        $file_type = 'zip';
                        $prefix = 'ZIP';
                    } else {
                        $file_type = 'autre';
                        $prefix = 'OTH';
                    }
                    $new_name = $prefix . '_' . date('ymdHis') . '.' . $file_ext;
                    $target = UPLOAD_DIR . $new_name;

                    if (move_uploaded_file($file_input["tmp_name"], $target)) {
                        $stmt = $pdo->prepare("INSERT INTO gesdoc (type, nom_fichier, md5_hash, description) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$file_type, $new_name, $md5_hash, $description]);
                        $message = "✅ Fichier uploadé : <strong>" . htmlspecialchars($new_name) . "</strong>";
                    } else {
                        $message = "❌ Impossible d’enregistrer le fichier. Vérifiez les permissions.";
                    }
                }
            } catch (Exception $e) {
                $message = "❌ Erreur : " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// ==========================
// 2. MODIFICATION DE DESCRIPTION
// ==========================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "update_desc") {
    $md5_hash = $_POST["md5_hash"] ?? '';
    $new_desc = trim($_POST["description"] ?? '');
    if (strlen($new_desc) > 50) {
        $message = "❌ Description trop longue (max 50 caractères).";
    } else {
        $stmt = $pdo->prepare("UPDATE gesdoc SET description = ? WHERE md5_hash = ?");
        $stmt->execute([$new_desc, $md5_hash]);
        $message = "✅ Description mise à jour.";
    }
}

// ==========================
// 3. SUPPRESSION
// ==========================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "delete") {
    $md5_hash = $_POST["md5_hash"] ?? '';
    $stmt = $pdo->prepare("SELECT nom_fichier FROM gesdoc WHERE md5_hash = ?");
    $stmt->execute([$md5_hash]);
    $file = $stmt->fetchColumn();
    if ($file) {
        $filepath = UPLOAD_DIR . $file;
        if (file_exists($filepath)) unlink($filepath);
        $stmt = $pdo->prepare("DELETE FROM gesdoc WHERE md5_hash = ?");
        $stmt->execute([$md5_hash]);
        $message = "🗑️ Fichier supprimé.";
    } else {
        $message = "❌ Fichier introuvable.";
    }
}

// ==========================
// 4. RECHERCHE
// ==========================
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM gesdoc WHERE description LIKE ? ORDER BY nom_fichier DESC");
    $stmt->execute(["%$search%"]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM gesdoc ORDER BY nom_fichier DESC");
    $stmt->execute();
}
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>📁 Gestion des Documents</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="container">
        <h1>📁 Gestion des Documents</h1>

        <?php if ($message): ?>
            <div class="message <?= strpos($message, '✅') !== false ? 'success' : (strpos($message, '🗑️') !== false ? 'warning' : 'error') ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Upload -->
        <div class="upload-section">
            <h2>📤 Intégrer un nouveau document</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="form-group">
                    <label>Fichier</label>
                    <input type="file" name="fileToUpload" required>
                </div>
                <div class="form-group">
                    <label>Description (max. 50 caractères)</label>
                    <input type="text" name="description" maxlength="50" placeholder="Ex: Photo de groupe, 1942">
                </div>
                <button type="submit">📤 Upload</button>
            </form>
        </div>

        <!-- Recherche -->
        <div class="search-bar">
            <form method="GET">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher par description...">
                <button type="submit">🔍 Rechercher</button>
                <?php if ($search): ?>
                    <a href="gestion_docs.php" style="margin-left: 10px; color: #007bff;">(Tout afficher)</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Liste des documents -->
        <h2>📄 Gérer les documents existants (<?= count($documents) ?>)</h2>
        <?php if (empty($documents)): ?>
            <p style="text-align: center; color: #6c757d;">Aucun document trouvé.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($documents as $doc): ?>
                    <?php
                    $ext = strtolower(pathinfo($doc['nom_fichier'], PATHINFO_EXTENSION));
                    $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    $file_url = '../acces_docs.php?f=' . urlencode($doc['nom_fichier']);
                    ?>
                    <div class="card">
                        <div class="thumbnail">
                            <?php if ($is_image): ?>
                                <img src="<?= $file_url ?>" alt="Miniature" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <span>
                                    <?php
                                    if ($ext === 'pdf') echo '📄';
                                    elseif ($ext === 'txt') echo '📝';
                                    elseif ($ext === 'zip') echo '📦';
                                    else echo '📎';
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="filename"><?= htmlspecialchars($doc['nom_fichier']) ?></div>
                        <div class="desc"><?= htmlspecialchars($doc['description'] ?: '—') ?></div>

                        <!-- Modifier description -->
                        <form method="POST" style="margin-top: 8px;">
                            <input type="hidden" name="action" value="update_desc">
                            <input type="hidden" name="md5_hash" value="<?= htmlspecialchars($doc['md5_hash']) ?>">
                            <input type="text" name="description" value="<?= htmlspecialchars($doc['description'] ?? '') ?>" maxlength="50" placeholder="Nouvelle description">
                            <button type="submit" style="margin-top: 5px; padding: 4px 8px; font-size: 0.8em;">✏️</button>
                        </form>

                        <!-- Supprimer -->
                        <form method="POST" onsubmit="return confirm('⚠️ Supprimer ce fichier ? Cette action est irréversible.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="md5_hash" value="<?= htmlspecialchars($doc['md5_hash']) ?>">
                            <button type="submit" class="btn-delete">🗑️ Supprimer</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
