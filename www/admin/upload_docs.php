<?php
// upload_docs.php - Upload sécurisé avec dédoublonnage MD5 et description
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'permissions.php';
checkUserPermission([1]); // Seul le Super-Admin (1) peut uploader

// Chemins
define('UPLOAD_DIR', '../../data/docs/');
define('DB_PATH', '../../data/portraits.sqlite');

/**
 * Détermine le type de fichier et retourne le préfixe pour le nommage en BDD.
 */
function get_file_type_and_prefix($extension) {
    $images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $documents = ['pdf', 'txt'];
    $zips = ['zip'];
    $extension = strtolower($extension);
    if (in_array($extension, $images)) return ['image', 'IMG'];
    if (in_array($extension, $documents)) return ['document', 'DOC'];
    if (in_array($extension, $zips)) return ['zip', 'ZIP'];
    return ['autre', 'OTH'];
}

$message = '';
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'zip'];
$max_file_size = 5 * 1024 * 1024; // 5 Mo

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit"])) {
    $file_input = $_FILES["fileToUpload"] ?? null;
    $description = trim($_POST["description"] ?? '');

    // Validation description
    if (strlen($description) > 50) {
        $message = "❌ La description ne doit pas dépasser 50 caractères.";
    } elseif ($file_input['error'] !== UPLOAD_ERR_OK) {
        $message = "❌ Erreur lors de l'upload du fichier. Code: " . $file_input['error'];
    } else {
        $file_name = basename($file_input["name"]);
        $file_size = $file_input["size"];
        $file_tmp = $file_input["tmp_name"];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_size > $max_file_size) {
            $message = "❌ Le fichier est trop volumineux (max 5 Mo).";
        } elseif (!in_array($file_ext, $allowed_extensions)) {
            $message = "❌ Format non autorisé. Seuls JPG, PNG, GIF, WEBP, PDF, TXT et ZIP sont acceptés.";
        } else {
            try {
                $md5_hash = md5_file($file_tmp);
                $pdo = new PDO('sqlite:' . DB_PATH);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Vérifier si le fichier existe déjà
                $stmt = $pdo->prepare("SELECT nom_fichier FROM gesdoc WHERE md5_hash = :md5");
                $stmt->execute([':md5' => $md5_hash]);
                if ($existing = $stmt->fetchColumn()) {
                    $message = "⚠️ Ce fichier existe déjà sous le nom : <strong>" . htmlspecialchars($existing) . "</strong>.";
                } else {
                    list($file_type, $file_prefix) = get_file_type_and_prefix($file_ext);
                    $new_file_name = $file_prefix . '_' . date('ymdHis') . '.' . $file_ext;
                    $target_path = UPLOAD_DIR . $new_file_name;

                    if (move_uploaded_file($file_tmp, $target_path)) {
                        // Insérer avec description
                        $stmt = $pdo->prepare(
                            "INSERT INTO gesdoc (type, nom_fichier, md5_hash, description) VALUES (:type, :nom, :md5, :desc)"
                        );
                        $stmt->execute([
                            ':type' => $file_type,
                            ':nom' => $new_file_name,
                            ':md5' => $md5_hash,
                            ':desc' => $description
                        ]);
                        $message = "
                            ✅ Fichier uploadé avec succès !<br>
                            <strong>Nom :</strong> " . htmlspecialchars($new_file_name) . "<br>
                            <strong>MD5 :</strong> " . htmlspecialchars($md5_hash) . "<br>
                            <strong>Description :</strong> " . htmlspecialchars($description ?: '—') . "
                        ";
                    } else {
                        $message = "❌ Impossible d’enregistrer le fichier. Vérifiez les permissions du dossier <code>" . UPLOAD_DIR . "</code>.";
                    }
                }
            } catch (Exception $e) {
                $message = "❌ Erreur : " . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>📤 Upload de Documents</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="container">
        <h2>📤 Upload de Documents (Admin)</h2>

        <?php if ($message): ?>
            <div class="message <?= strpos($message, '✅') !== false ? 'success' : (strpos($message, '⚠️') !== false ? 'warning' : 'error') ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form action="upload_docs.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="fileToUpload">📁 Fichier à uploader</label>
                <input type="file" name="fileToUpload" id="fileToUpload" required>
            </div>

            <div class="form-group">
                <label for="description">📝 Description (max. 50 caractères)</label>
                <input type="text" name="description" id="description" maxlength="50" placeholder="Ex: Photo de Jean Dupont, 1945">
                <div class="char-count"><span id="charCount">0</span> / 50</div>
            </div>

            <button type="submit" name="submit">📤 Uploader le fichier</button>
        </form>

        <div class="info">
            <strong>Formats acceptés :</strong> JPG, PNG, GIF, WEBP, PDF, TXT, ZIP (max 5 Mo)<br>
            Les fichiers identiques (même MD5) ne sont pas dupliqués.
        </div>
    </div>

    <script>
        // Compteur de caractères en temps réel
        document.getElementById('description').addEventListener('input', function() {
            const count = this.value.length;
            document.getElementById('charCount').textContent = count;
        });
    </script>
</body>
</html>
