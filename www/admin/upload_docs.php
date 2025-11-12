<?php
// upload_docs.php - Upload sécurisé avec dédoublonnage MD5 et description
// Nommage : préfixes IMG_ pour images, DOC_ pour documents, OTH_ pour les autres
// Après le préfixe, le nom utilise la forme AAMMJJHHmmss.extension (PHP: date 'ymdHis').
// Si un fichier avec le même MD5 existe on le réutilise.
// Si un fichier porte déjà le nom exact demandé MAIS a un MD5 différent, on ajoute un suffixe numérique
// pour éviter l'écrasement (ex: IMG_251111190224_1.jpg). Ceci n'est fait qu'en cas de collision réelle.
// Enregistre dans la table `gesdoc` (ID_Unique, nom_fichier, type, md5_hash, description)
// Appelle la callback du parent avec payload contenant filename, md5, gesdoc_id, type et description.

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'permissions.php';
// L'accès peut être restreint via checkUserPermission si souhaité
// checkUserPermission([1]);

// Dossier d'uploads (serveur)
define('UPLOAD_DIR', __DIR__ . '/../../data/docs/');
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

$allowed_extensions = ['jpg','jpeg','png','gif','webp','pdf','txt','zip','doc','docx','odt'];
$max_file_size = 8 * 1024 * 1024; // 8 Mo par défaut

// champ cible (Photo | Iconographie | Documents) passé en GET par l'appel popup
$field = $_GET['field'] ?? '';

// description fournie par l'utilisateur (max 50 comme dans le schéma demandé)
$description = trim($_POST['description'] ?? '');
if (strlen($description) > 50) {
    $description = substr($description, 0, 50);
}

$message = '';
$uploadedFilename = '';
$insertedId = null;
$md5 = null;
$type = null;

function detect_type_from_extension($ext) {
    $ext = strtolower($ext);
    $images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $documents = ['pdf', 'txt', 'doc', 'docx', 'odt'];
    $zips = ['zip'];
    if (in_array($ext, $images)) return 'image';
    if (in_array($ext, $documents)) return 'document';
    if (in_array($ext, $zips)) return 'zip';
    return 'autre';
}

function prefix_for_type($type) {
    if ($type === 'image') return 'IMG_';
    if ($type === 'document' || $type === 'zip') return 'DOC_';
    return 'OTH_';
}

// Générateur de nom respectant la charte : PREFIX_AAMMJJHHmmss.ext
function generate_named_filename($prefix, $ext) {
    // date('ymdHis') => AAMMJJHHmmss
    $ts = date('ymdHis');
    return $prefix . $ts . '.' . $ext;
}

// Traitement de l'upload
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["fileToUpload"])) {
    $file = $_FILES["fileToUpload"];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "❌ Erreur lors de l'upload (code {$file['error']})";
    } else {
        $size = $file['size'];
        $origName = basename($file['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($size > $max_file_size) {
            $message = "❌ Fichier trop volumineux (max " . ($max_file_size/1024/1024) . " MB)";
        } elseif (!in_array($ext, $allowed_extensions)) {
            $message = "❌ Extension non autorisée";
        } else {
            // calcul du MD5
            $md5 = @md5_file($file['tmp_name']);
            if ($md5 === false) $md5 = null;

            $type = detect_type_from_extension($ext);
            $prefix = prefix_for_type($type);

            // Vérifier si un fichier physique identique existe déjà (par MD5)
            $existingName = null;
            if ($md5 !== null) {
                foreach (glob(UPLOAD_DIR . '*') as $p) {
                    if (is_file($p) && @md5_file($p) === $md5) {
                        $existingName = basename($p);
                        break;
                    }
                }
            }

            if ($existingName) {
                // Réutiliser le fichier existant (évite duplication)
                $uploadedFilename = $existingName;
                $message = "ℹ️ Fichier déjà présent sur le serveur, utilisation du fichier existant : " . htmlspecialchars($uploadedFilename);
            } else {
                // Générer le nom demandé (PREFIX_AAMMJJHHmmss.ext)
                $baseName = generate_named_filename($prefix, $ext);
                $dest = UPLOAD_DIR . $baseName;

                // Si le fichier existe déjà au nom exact (très peu probable sauf upload multiple par seconde),
                // et que son MD5 est différent, on ajoute un suffixe numérique (_1, _2, ...) pour éviter écrasement.
                if (file_exists($dest)) {
                    // comparer MD5 si possible
                    $existingMd5 = @md5_file($dest);
                    if ($existingMd5 !== false && $md5 !== null && $existingMd5 === $md5) {
                        // contenu identique, réutiliser
                        $uploadedFilename = basename($dest);
                        $message = "ℹ️ Fichier identique déjà présent (même nom et même contenu) : " . htmlspecialchars($uploadedFilename);
                    } else {
                        // collision de nom mais contenu différent -> ajouter suffixe numérique
                        $i = 1;
                        do {
                            $candidate = pathinfo($baseName, PATHINFO_FILENAME) . '_' . $i . '.' . $ext;
                            $dest = UPLOAD_DIR . $candidate;
                            $i++;
                        } while (file_exists($dest) && $i < 1000); // sécurité
                        // si trouve un slot libre ou dépasse la boucle
                        if (move_uploaded_file($file['tmp_name'], $dest)) {
                            $uploadedFilename = basename($dest);
                            $message = "✅ Upload réussi (nom ajusté pour éviter collision) : " . htmlspecialchars($uploadedFilename);
                        } else {
                            $message = "❌ Échec du déplacement du fichier.";
                        }
                    }
                } else {
                    // nom libre -> déplacer
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $uploadedFilename = $baseName; // NOM SEUL (sans chemin)
                        $message = "✅ Upload réussi : " . htmlspecialchars($uploadedFilename);
                    } else {
                        $message = "❌ Échec du déplacement du fichier.";
                    }
                }
            }

            // Insertion / réutilisation dans la table gesdoc (schéma demandé)
            if ($uploadedFilename) {
                $dbPath = __DIR__ . '/../../data/portraits.sqlite';
                try {
                    $pdo = new PDO("sqlite:$dbPath");
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Créer la table gesdoc si elle n'existe pas, avec le schéma EXACT demandé
                    $createSql = "CREATE TABLE IF NOT EXISTS gesdoc (
                        ID_Unique INTEGER PRIMARY KEY AUTOINCREMENT,
                        nom_fichier TEXT NOT NULL,
                        type TEXT,
                        md5_hash TEXT,
                        description VARCHAR(50)
                    )";
                    $pdo->exec($createSql);

                    // Chercher un enregistrement existant par md5 (priorité) ou par nom de fichier
                    $found = null;
                    if ($md5) {
                        $stmt = $pdo->prepare("SELECT ID_Unique, nom_fichier FROM gesdoc WHERE md5_hash = ? LIMIT 1");
                        $stmt->execute([$md5]);
                        $found = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                    if (!$found) {
                        $stmt2 = $pdo->prepare("SELECT ID_Unique, nom_fichier FROM gesdoc WHERE nom_fichier = ? LIMIT 1");
                        $stmt2->execute([$uploadedFilename]);
                        $found = $stmt2->fetch(PDO::FETCH_ASSOC);
                    }

                    if ($found) {
                        // réutiliser l'enregistrement existant
                        $insertedId = $found['ID_Unique'];
                        // mettre à jour la description si fournie et si vide
                        if (!empty($description)) {
                            $upd = $pdo->prepare("UPDATE gesdoc SET description = COALESCE(NULLIF(description, ''), ?) WHERE ID_Unique = ?");
                            $upd->execute([$description, $insertedId]);
                        }
                        // éventuellement mettre à jour md5_hash si absent
                        if ($md5) {
                            $upd2 = $pdo->prepare("UPDATE gesdoc SET md5_hash = COALESCE(NULLIF(md5_hash, ''), ?) WHERE ID_Unique = ?");
                            $upd2->execute([$md5, $insertedId]);
                        }
                        // mettre à jour type si absent
                        if ($type) {
                            $upd3 = $pdo->prepare("UPDATE gesdoc SET type = COALESCE(NULLIF(type, ''), ?) WHERE ID_Unique = ?");
                            $upd3->execute([$type, $insertedId]);
                        }
                    } else {
                        // Insérer un nouvel enregistrement
                        $ins = $pdo->prepare("INSERT INTO gesdoc (nom_fichier, type, md5_hash, description) VALUES (?, ?, ?, ?)");
                        $ins->execute([$uploadedFilename, $type, $md5, $description]);
                        $insertedId = $pdo->lastInsertId();
                    }
                } catch (PDOException $e) {
                    // ne pas bloquer l'upload physique si insertion en base échoue
                    $message .= " — ⚠️ Erreur base gesdoc : " . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
}

// Page HTML d'upload : si upload réussi, on appelle le parent via JS callback et on ferme la popup
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Upload document</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:20px}
    .alert{padding:10px;border-radius:4px;margin-bottom:10px}
    .success{background:#e6ffed;border:1px solid #8de19a}
    .error{background:#ffe6e6;border:1px solid #e18d8d}
    label{display:block;margin-top:8px}
    </style>
</head>
<body>
    <h1>Uploader un fichier</h1>
    <?php if ($message): ?>
        <div class="<?= $uploadedFilename ? 'alert success' : 'alert error' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if (!$uploadedFilename): ?>
        <form method="post" enctype="multipart/form-data">
            <div>
                <label for="fileToUpload">Fichier :</label>
                <input type="file" name="fileToUpload" id="fileToUpload" required>
            </div>
            <div>
                <label for="description">Description (facultative, max 50 caractères) :</label>
                <input type="text" name="description" id="description" maxlength="50" value="<?= htmlspecialchars($description) ?>">
            </div>
            <div style="margin-top:8px;">
                <button type="submit">Téléverser</button>
            </div>
        </form>
        <p style="font-size:90%;color:#666">Extensions autorisées: <?= implode(', ', $allowed_extensions) ?> — max <?= ($max_file_size/1024/1024) ?> MB</p>
    <?php else: ?>
        <!-- Upload réussi : appeler le parent (popup) et fermer -->
        <script>
            (function(){
                try {
                    var payload = {
                        field: <?= json_encode($field) ?>,
                        filename: <?= json_encode($uploadedFilename) ?>,
                        md5: <?= json_encode($md5) ?>,
                        gesdoc_id: <?= json_encode($insertedId) ?>,
                        type: <?= json_encode($type) ?>,
                        description: <?= json_encode($description) ?>
                    };
                    if (window.opener && typeof window.opener.uploadDocsCallback === 'function') {
                        window.opener.uploadDocsCallback(payload);
                    } else if (window.opener && window.opener.postMessage) {
                        window.opener.postMessage({ type: 'upload_docs_result', payload: payload }, '*');
                    }
                } catch(e) {
                    console.warn('Callback error', e);
                }
                setTimeout(function(){ window.close(); }, 400);
            })();
        </script>
        <p>Upload réussi — fermeture de la fenêtre...</p>
    <?php endif; ?>
</body>
</html>