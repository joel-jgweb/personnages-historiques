<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'permissions.php';
checkUserPermission([1]);
// configurer_site.php - Interface d'administration pour modifier la configuration du site
require_once __DIR__ . '/../config.php';

session_start();

// Chemin vers la base de données
$dbPath = '../../data/portraits.sqlite';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Erreur de connexion à la base de données : " . $e->getMessage());
}

$message = '';

// Charger la configuration actuelle
$config = loadSiteConfig($pdo);

// Fonction utilitaire pour traiter le téléchargement d'une image
function handleImageUpload($fileKey, $prefix, $targetDir) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$fileKey];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxFileSize = 5 * 1024 * 1024; // 5 Mo

    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception("Type de fichier non autorisé. Formats acceptés : JPG, PNG, GIF, WebP.");
    }

    if ($file['size'] > $maxFileSize) {
        throw new Exception("Fichier trop volumineux. Taille maximale : 5 Mo.");
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = $prefix . '_' . uniqid() . '.' . strtolower($extension);
    $targetPath = $targetDir . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Échec du téléchargement du fichier. Vérifiez les permissions du dossier.");
    }

    return '/admin/data_config/' . $newFilename;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
    try {
        $site_title = trim($_POST['site_title'] ?? $config['site_title']);
        $site_subtitle = trim($_POST['site_subtitle'] ?? $config['site_subtitle']);
        $association_name = trim($_POST['association_name'] ?? $config['association_name']);
        $association_address = trim($_POST['association_address'] ?? $config['association_address']);
        $primary_color = trim($_POST['primary_color'] ?? $config['primary_color']);
        $secondary_color = trim($_POST['secondary_color'] ?? $config['secondary_color']);
        $background_color = trim($_POST['background_color'] ?? $config['background_color']);
        $background_choice = $_POST['background_choice'] ?? 'color'; // 'color' ou 'image'

        $logo_path = $config['logo_path'];
        $background_image = $config['background_image'];

        // Dossier cible pour les uploads
        $uploadDir = __DIR__ . '/data_config/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // Gérer le logo
        if (!empty($_FILES['logo_file']['name'])) {
            $logo_path = handleImageUpload('logo_file', 'logo', $uploadDir);
        } elseif (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
            if (!empty($config['logo_path']) && file_exists(__DIR__ . '/../..' . $config['logo_path'])) {
                unlink(__DIR__ . '/../..' . $config['logo_path']);
            }
            $logo_path = null;
        }

        // Gérer l'image de fond SEULEMENT si l'utilisateur a choisi "image"
        if ($background_choice === 'image') {
            if (!empty($_FILES['background_file']['name'])) {
                $background_image = handleImageUpload('background_file', 'bg', $uploadDir);
            } elseif (isset($_POST['remove_background']) && $_POST['remove_background'] === '1') {
                if (!empty($config['background_image']) && file_exists(__DIR__ . '/../..' . $config['background_image'])) {
                    unlink(__DIR__ . '/../..' . $config['background_image']);
                }
                $background_image = null;
            }
        } else {
            // Si l'utilisateur choisit "couleur", on ignore l'image de fond
            $background_image = null;
        }

        // Mettre à jour la base de données
        $sql = "UPDATE configuration SET 
                    site_title = ?,
                    site_subtitle = ?,
                    association_name = ?,
                    association_address = ?,
                    logo_path = ?,
                    primary_color = ?,
                    secondary_color = ?,
                    background_color = ?,
                    background_image = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = 1";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $site_title,
            $site_subtitle,
            $association_name,
            $association_address,
            $logo_path,
            $primary_color,
            $secondary_color,
            $background_color,
            $background_image // Peut être NULL si l'utilisateur choisit "couleur"
        ]);

        if ($result) {
            $message = "<div style='padding: 1rem; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px; margin-bottom: 1.5rem;'>✅ Configuration enregistrée avec succès !</div>";
            $config = loadSiteConfig($pdo);
        } else {
            throw new Exception("Échec de la mise à jour en base de données.");
        }
    } catch (Exception $e) {
        $message = "<div style='padding: 1rem; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 1.5rem;'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="admin.css">
    <meta charset="UTF-8">
    <title>Configurer le Site</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #555;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        input[type="color"] {
            width: 80px;
            height: 40px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .color-picker {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .upload-section {
            border: 2px dashed #ddd;
            padding: 1rem;
            border-radius: 8px;
            background: #fafafa;
        }
        button {
            background-color: #28a745;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            margin-top: 1rem;
        }
        button:hover {
            background-color: #218838;
        }
        .current-image {
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        .current-image img {
            max-height: 100px;
            margin-top: 0.5rem;
            border: 1px solid #eee;
            border-radius: 4px;
        }
        .preview-box {
            background: <?= htmlspecialchars($config['background_color']) ?>;
            <?php if (!empty($config['background_image'])): ?>
                background-image: url('<?= htmlspecialchars($config['background_image']) ?>');
                background-size: cover;
                background-position: center;
            <?php endif; ?>
            padding: 2rem;
            border-radius: 10px;
            margin: 2rem 0;
            color: white;
            text-align: center;
        }
        .preview-title {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: <?= htmlspecialchars($config['primary_color']) ?>;
        }
        .preview-subtitle {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        .preview-btn {
            background: <?= htmlspecialchars($config['secondary_color']) ?>;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 30px;
            font-weight: bold;
        }
        /* Style pour les boutons radio */
        .radio-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        /* Cacher la section image si "Couleur" est sélectionné */
        .bg-image-section {
            transition: all 0.3s ease;
        }
        .bg-image-section.hidden {
            display: none;
            opacity: 0;
            height: 0;
            padding: 0;
            margin: 0;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Configuration du Site</h1>

        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_config">

            <div class="form-group">
                <label for="site_title">Titre du site</label>
                <input type="text" name="site_title" id="site_title" value="<?= htmlspecialchars($config['site_title']) ?>" required>
            </div>

            <div class="form-group">
                <label for="site_subtitle">Sous-titre</label>
                <textarea name="site_subtitle" id="site_subtitle" rows="2"><?= htmlspecialchars($config['site_subtitle']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="association_name">Nom de l'association</label>
                <input type="text" name="association_name" id="association_name" value="<?= htmlspecialchars($config['association_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="association_address">Adresse de l'association</label>
                <textarea name="association_address" id="association_address" rows="3"><?= htmlspecialchars($config['association_address']) ?></textarea>
            </div>

            <!-- Section Upload Logo -->
            <div class="form-group">
                <label>Logo de l'association</label>
                <div class="upload-section">
                    <input type="file" name="logo_file" id="logo_file" accept="image/*">
                    <?php if (!empty($config['logo_path'])): ?>
                        <div class="current-image">
                            Logo actuel :<br>
                            <img src="<?= htmlspecialchars($config['logo_path']) ?>" alt="Logo actuel">
                        </div>
                        <label>
                            <input type="checkbox" name="remove_logo" value="1">
                            Supprimer ce logo
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Choix entre Couleur ou Image de fond -->
            <div class="form-group">
                <label>Arrière-plan de la page d'accueil</label>
                <div class="radio-group">
                    <div class="radio-option">
                        <input type="radio" name="background_choice" id="bg_color" value="color" <?= (empty($config['background_image']) || !isset($_POST['background_choice']) || $_POST['background_choice'] === 'color') ? 'checked' : '' ?>>
                        <label for="bg_color">Couleur unie</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" name="background_choice" id="bg_image" value="image" <?= (!empty($config['background_image']) && (isset($_POST['background_choice']) && $_POST['background_choice'] === 'image')) ? 'checked' : '' ?>>
                        <label for="bg_image">Image</label>
                    </div>
                </div>
            </div>

            <!-- Section Couleur de fond (toujours visible) -->
            <div class="form-group">
                <label for="background_color">Couleur de fond</label>
                <input type="color" name="background_color" id="background_color" value="<?= htmlspecialchars($config['background_color']) ?>">
            </div>

            <!-- Section Image de fond (visible seulement si "Image" est sélectionné) -->
            <div class="form-group bg-image-section <?= (empty($config['background_image']) && (!isset($_POST['background_choice']) || $_POST['background_choice'] !== 'image')) ? 'hidden' : '' ?>">
                <label>Image de fond</label>
                <div class="upload-section">
                    <input type="file" name="background_file" id="background_file" accept="image/*">
                    <?php if (!empty($config['background_image'])): ?>
                        <div class="current-image">
                            Image de fond actuelle :<br>
                            <img src="<?= htmlspecialchars($config['background_image']) ?>" alt="Image de fond actuelle">
                        </div>
                        <label>
                            <input type="checkbox" name="remove_background" value="1">
                            Supprimer cette image
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Couleurs du thème</label>
                <div class="color-picker">
                    <div>
                        <label for="primary_color">Primaire (titres)</label>
                        <input type="color" name="primary_color" id="primary_color" value="<?= htmlspecialchars($config['primary_color']) ?>">
                    </div>
                    <div>
                        <label for="secondary_color">Secondaire (boutons)</label>
                        <input type="color" name="secondary_color" id="secondary_color" value="<?= htmlspecialchars($config['secondary_color']) ?>">
                    </div>
                </div>
            </div>

            <button type="submit">💾 Enregistrer la configuration</button>
        </form>

        <h2 style="margin-top: 3rem; text-align: center;">👁️ Aperçu en temps réel</h2>
        <div class="preview-box">
            <?php if (!empty($config['logo_path'])): ?>
                <img src="<?= htmlspecialchars($config['logo_path']) ?>" alt="Logo" style="max-height: 80px; margin-bottom: 1rem;">
            <?php endif; ?>
            <h3 class="preview-title"><?= htmlspecialchars($config['site_title']) ?></h3>
            <p class="preview-subtitle"><?= htmlspecialchars($config['site_subtitle']) ?></p>
            <button class="preview-btn">Lancer la recherche</button>
        </div>
    </div>

    <script>
        // Gérer l'affichage/masquage de la section image de fond
        document.addEventListener('DOMContentLoaded', function() {
            const bgColorRadio = document.getElementById('bg_color');
            const bgImageRadio = document.getElementById('bg_image');
            const bgImageSection = document.querySelector('.bg-image-section');

            function toggleBgImageSection() {
                if (bgImageRadio.checked) {
                    bgImageSection.classList.remove('hidden');
                } else {
                    bgImageSection.classList.add('hidden');
                }
            }

            bgColorRadio.addEventListener('change', toggleBgImageSection);
            bgImageRadio.addEventListener('change', toggleBgImageSection);

            // Initialisation
            toggleBgImageSection();
        });
    </script>
</body>
</html>