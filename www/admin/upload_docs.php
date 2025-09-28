<?php
// ====================================================================
// --- CONFIGURATION DES CHEMINS ---
// ====================================================================
// Le chemin vers le dossier d'upload depuis www/admin/
define('UPLOAD_DIR', '../../data/docs/');
// Le chemin vers la base de données SQLite
define('DB_PATH', '../../data/portraits.sqlite');

// ====================================================================
// --- FONCTIONS UTILITAIRES ---
// ====================================================================

/**
 * Détermine le type de fichier et retourne le préfixe pour le nommage en BDD.
 * @param string $extension L'extension du fichier
 * @return array Contient [type_bdd, prefixe_nom]
 */
function get_file_type_and_prefix($extension) {
    $images = ['jpg', 'jpeg', 'png', 'gif'];
    $documents = ['pdf', 'txt'];
    $zips = ['zip'];

    $extension = strtolower($extension);

    if (in_array($extension, $images)) {
        return ['image', 'IMG'];
    } elseif (in_array($extension, $documents)) {
        return ['document', 'DOC'];
    } elseif (in_array($extension, $zips)) {
        return ['zip', 'ZIP'];
    }
    return ['autre', 'OTH']; 
}

// ====================================================================
// --- GESTION DU TRAITEMENT D'UPLOAD ---
// ====================================================================

$message = '';
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'zip'];
$max_file_size = 5 * 1024 * 1024; // 5 Mo

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit"])) {
    $file_input = $_FILES["fileToUpload"];
    
    // Vérification de l'absence d'erreur d'upload
    if ($file_input['error'] !== UPLOAD_ERR_OK) {
        $message = "Erreur lors de l'upload du fichier. Code: " . $file_input['error'];
    } else {
        // 1. Validation de la taille et de l'extension
        $file_name = basename($file_input["name"]);
        $file_size = $file_input["size"];
        $file_tmp = $file_input["tmp_name"];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_size > $max_file_size) {
            $message = "❌ Le fichier est trop volumineux (max 5Mo).";
        } elseif (!in_array($file_ext, $allowed_extensions)) {
            $message = "❌ Seuls les formats Images (JPG, PNG, GIF), PDF, TXT et ZIP sont autorisés.";
        } else {
            try {
                // 2. Calcul du HASH MD5 (clé d'identification)
                $md5_hash = md5_file($file_tmp);

                // 3. Connexion à la base de données SQLite
                $pdo = new PDO('sqlite:' . DB_PATH);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // 4. Vérification d'unicité MD5 (Doublon déjà uploadé ?)
                $stmt = $pdo->prepare("SELECT nom_fichier FROM gesdoc WHERE md5_hash = :md5");
                $stmt->execute([':md5' => $md5_hash]);
                $existing_file = $stmt->fetchColumn();

                if ($existing_file) {
                    $message = "⚠️ Ce fichier existe déjà dans la base sous le nom : <strong>" . htmlspecialchars($existing_file) . "</strong> (MD5 identique).";
                } else {
                    
                    // --- NOUVELLE LOGIQUE DE NOMMAGE ---
                    
                    // a. Détermination du type BDD et du préfixe
                    list($file_type, $file_prefix) = get_file_type_and_prefix($file_ext);
                    
                    // b. Génération de l'estampille AAMMDDHHmmss
                    // date('ymdHis') donne une chaîne de 12 caractères (ex: 250925214730)
                    $timestamp = date('ymdHis'); 
                    
                    // c. Construction du nouveau nom : PREFIXE_AAMMDDHHmmss.ext
                    $new_file_name = $file_prefix . '_' . $timestamp . '.' . $file_ext;
                    $target_path = UPLOAD_DIR . $new_file_name;

                    // 6. Déplacement physique du fichier
                    if (move_uploaded_file($file_tmp, $target_path)) {
                        
                        // 7. Enregistrement en base de données
                        // Le $file_type utilisé ici est le 'image', 'document' ou 'zip' pour la colonne 'type'
                        $stmt = $pdo->prepare(
                            "INSERT INTO gesdoc (type, nom_fichier, md5_hash) VALUES (:type, :nom, :md5)"
                        );
                        $stmt->execute([
                            ':type' => $file_type,
                            ':nom' => $new_file_name,
                            ':md5' => $md5_hash
                        ]);

                        $message = "✅ Fichier **" . htmlspecialchars($file_name) . "** uploadé avec succès !<br>";
                        $message .= "Nouveau nom: <strong>" . $new_file_name . "</strong><br>";
                        $message .= "MD5: <strong>" . $md5_hash . "</strong><br>";

                    } else {
                        $message = "❌ Erreur de déplacement du fichier. Vérifiez les permissions du répertoire " . UPLOAD_DIR;
                    }
                }
            } catch (PDOException $e) {
                $message = "❌ Erreur de base de données : " . $e->getMessage();
            } catch (Exception $e) {
                $message = "❌ Une erreur inattendue est survenue.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Upload de Documents</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .message { padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    </style>
</head>
<body>

    <h2>Upload de Documents (Admin)</h2>

    <?php if ($message): ?>
        <div class="message <?php 
            if (strpos($message, '✅') !== false) echo 'success';
            else if (strpos($message, '⚠️') !== false) echo 'warning';
            else echo 'error';
        ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>
    
    <form action="upload_docs.php" method="post" enctype="multipart/form-data">
        <label for="fileToUpload">Sélectionner un fichier (Images, PDF, TXT, ZIP) :</label><br>
        <input type="file" name="fileToUpload" id="fileToUpload" required><br><br>
        <input type="submit" value="Uploader et Enregistrer" name="submit">
    </form>

</body>
</html>