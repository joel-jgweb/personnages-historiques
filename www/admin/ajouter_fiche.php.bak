<?php
// ajouter_fiche.php - Création d'une fiche avec éditeur restreint et saisie structurée pour les tableaux
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'permissions.php';
// Autorise : Rédacteur Fiches (3), Administrateur Fiches (2), Super-Administrateur (1), Administrateur Simple (6)
checkUserPermission([1, 2, 3, 6]);

require_once '../config.php';
$dbPath = __DIR__ . '/../../data/portraits.sqlite';
$message = '';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Erreur de connexion à la base de données : " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $nom = trim($_POST['Nom'] ?? '');
    $metier = $_POST['Metier'] ?? '';
    $engagements = $_POST['Engagements'] ?? '';
    $details = $_POST['Details'] ?? '';
    $sources = $_POST['Sources'] ?? '';
    $donnees_genealogiques = $_POST['Donnees_genealogiques'] ?? '';
    $photo = $_POST['Photo'] ?? '';
    $est_en_ligne = $_POST['est_en_ligne'] ?? '0';
// --- Génération du tableau pour Iconographie ---
$icono_descs = $_POST['iconographie_description'] ?? [];
$icono_links = $_POST['iconographie_lien'] ?? [];
$iconographie = '';

if (!empty($icono_descs)) {
    $iconographie_lines = ["| Description | Télécharger |", "|-------------|-------------|"];
    foreach ($icono_descs as $i => $desc) {
        if (!empty($desc) || !empty($icono_links[$i])) {
            $desc_clean = str_replace(['|', "\n"], '', $desc);
            $link_clean = str_replace(['|', "\n"], '', $icono_links[$i]);
            $link_markdown = urlToMarkdownLink($link_clean, 'Télécharger');
            $iconographie_lines[] = "| $desc_clean | $link_markdown |";
        }
    }
    if (count($iconographie_lines) > 2) {
        $iconographie = implode("\n", $iconographie_lines);
    }
}
   // --- Génération du tableau pour Documents ---
$doc_descs = $_POST['documents_description'] ?? [];
$doc_links = $_POST['documents_lien'] ?? [];
$documents = ''; // Valeur par défaut : champ vide

// Ne générer le tableau que s'il y a au moins une description ou un lien
if (!empty($doc_descs)) {
    $documents_lines = ["| Description | Télécharger |", "|-------------|-------------|"];
    foreach ($doc_descs as $i => $desc) {
        $link = $doc_links[$i] ?? '';
        // Ne pas ajouter de ligne si les deux champs sont vides
        if (empty($desc) && empty($link)) {
            continue;
        }
        // Nettoyer les caractères interdits dans un tableau Markdown
        $desc_clean = str_replace(['|', "\n"], '', $desc);
        $link_clean = str_replace(['|', "\n"], '', $link);
        
        // Transformer une URL brute en lien Markdown [Télécharger](url)
        if (!empty($link_clean) && filter_var($link_clean, FILTER_VALIDATE_URL)) {
            $link_markdown = "[Télécharger]($link_clean)";
        } else {
            // Si ce n'est pas une URL valide, garder le texte tel quel (ou vide)
            $link_markdown = $link_clean;
        }
        
        $documents_lines[] = "| $desc_clean | $link_markdown |";
    }
    // Ne sauvegarder le tableau que s'il contient au moins une ligne de données (au-delà de l'en-tête)
    if (count($documents_lines) > 2) {
        $documents = implode("\n", $documents_lines);
    }
}

    // --- Gestion des métadonnées ---
    $auteur = $_SESSION['nom_prenom'] ?? 'Inconnu';
    $derniere_modif = date('Y-m-d H:i:s');
    $valideur = ($est_en_ligne == '1') ? ($_SESSION['nom_prenom'] ?? 'Système') : null;

    if (empty($nom)) {
        $message = "<div class='alert alert-warning'>⚠️ Le champ 'Nom' est obligatoire.</div>";
    } else {
        try {
            $sql = "INSERT INTO personnages (
                Nom, Metier, Engagements, Details, Sources, Donnees_genealogiques,
                Iconographie, Photo, Documents, est_en_ligne, auteur, valideur, derniere_modif
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nom, $metier, $engagements, $details, $sources,
                $donnees_genealogiques, $iconographie, $photo, $documents,
                $est_en_ligne, $auteur, $valideur, $derniere_modif
            ]);
            $new_id = $pdo->lastInsertId();
            $message = "<div class='alert alert-success'>🎉 Fiche #$new_id créée avec succès !</div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>➕ Ajouter une fiche</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/admin/js/simplemde/simplemde.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f9f9f9; }
        .container { max-width: 900px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); position: relative; }
        h1 { text-align: center; color: #333; }
        label { font-weight: bold; display: block; margin-top: 15px; }
        input[type="text"], textarea, select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
        textarea { height: 100px; resize: vertical; }
        button { background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        button:hover { background-color: #218838; }
        .alert { padding: 10px; margin: 20px 0; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 15px; }
        .btn-download { position: absolute; top: 20px; right: 20px; background-color: #dc3545; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-download:hover { background-color: #c82333; text-decoration: none; }
        .info-text { font-size: 0.9em; color: #6c757d; margin-top: 0.5rem; }
        .resource-pair { display: flex; gap: 10px; margin-bottom: 10px; }
        .resource-pair input { flex: 1; }
        .btn-add { background: #17a2b8; padding: 5px 10px; font-size: 0.9rem; }
        .btn-remove { background: #dc3545; padding: 5px 10px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
      
        <h1>➕ Ajouter une nouvelle fiche</h1>
        <?php if ($message): ?><?= $message ?><?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="action" value="ajouter">
            <div class="form-group">
                <label for="Nom">Nom * :</label>
                <input type="text" name="Nom" id="Nom" value="<?= htmlspecialchars($_POST['Nom'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="Metier">Métier :</label>
                <textarea name="Metier" id="Metier"><?= htmlspecialchars($_POST['Metier'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="Engagements">Engagements :</label>
                <textarea name="Engagements" id="Engagements"><?= htmlspecialchars($_POST['Engagements'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="Details">Détails :</label>
                <textarea name="Details" id="Details"><?= htmlspecialchars($_POST['Details'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="Sources">Sources :</label>
                <textarea name="Sources" id="Sources"><?= htmlspecialchars($_POST['Sources'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="Donnees_genealogiques">Données généalogiques :</label>
                <textarea name="Donnees_genealogiques" id="Donnees_genealogiques"><?= htmlspecialchars($_POST['Donnees_genealogiques'] ?? '') ?></textarea>
            </div>

            <!-- Section Iconographie -->
            <div class="form-group">
                <label>Iconographie</label>
                <div id="iconographie-container">
                    <!-- Les paires seront ajoutées ici par JS -->
                </div>
                <button type="button" class="btn-add" onclick="addResourcePair('iconographie')">➕ Ajouter une ligne</button>
            </div>

            <!-- Section Documents -->
            <div class="form-group">
                <label>Documents</label>
                <div id="documents-container">
                    <!-- Les paires seront ajoutées ici par JS -->
                </div>
                <button type="button" class="btn-add" onclick="addResourcePair('documents')">➕ Ajouter une ligne</button>
            </div>

            <div class="form-group">
                <label for="Photo">Photo (URL ou chemin relatif) :</label>
                <input type="text" name="Photo" id="Photo" value="<?= htmlspecialchars($_POST['Photo'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="auteur">Auteur :</label>
                <input type="text" name="auteur" id="auteur" value="<?= htmlspecialchars($_SESSION['nom_prenom']) ?>" readonly>
            </div>

            <div class="form-group">
                <label for="est_en_ligne">Statut de publication :</label>
                <?php
                $roles_autorises = [1, 2, 4, 6];
                $peut_modifier = in_array($_SESSION['user_statut'], $roles_autorises);
                $valeur_actuelle = $_POST['est_en_ligne'] ?? '0';
                ?>
                <select name="est_en_ligne" id="est_en_ligne" <?= $peut_modifier ? '' : 'disabled title="Seuls les valideurs et administrateurs peuvent publier."' ?>>
                    <option value="0" <?= ($valeur_actuelle === '0') ? 'selected' : '' ?>>🔴 Hors ligne (brouillon)</option>
                    <option value="1" <?= ($valeur_actuelle === '1') ? 'selected' : '' ?>>✅ En ligne (publique)</option>
                </select>
                <?php if (!$peut_modifier): ?>
                    <input type="hidden" name="est_en_ligne" value="<?= $valeur_actuelle ?>">
                    <p class="info-text"><em>ℹ️ Seuls les valideurs et administrateurs peuvent publier une fiche.</em></p>
                <?php endif; ?>
            </div>

            <button type="submit">💾 Créer la fiche</button>
        </form>
    </div>

    <script src="/admin/js/simplemde/simplemde.min.js"></script>
    <script>
        // Initialiser SimpleMDE en mode restreint
        const simplemdeDetails = new SimpleMDE({
            element: document.getElementById("Details"),
            toolbar: ["bold", "italic", "link"],
            spellChecker: false,
            status: false
        });
        const simplemdeSources = new SimpleMDE({
            element: document.getElementById("Sources"),
            toolbar: ["bold", "italic", "link"],
            spellChecker: false,
            status: false
        });

        // Gestion dynamique des ressources (Iconographie et Documents)
        function addResourcePair(type) {
            const container = document.getElementById(type + '-container');
            const div = document.createElement('div');
            div.className = 'resource-pair';
            div.innerHTML = `
                <input type="text" name="${type}_description[]" placeholder="Description" required>
                <input type="url" name="${type}_lien[]" placeholder="https://...">
                <button type="button" class="btn-remove" onclick="this.parentElement.remove()">🗑️</button>
            `;
            container.appendChild(div);
        }

        // Pré-remplir avec les données POST en cas d'erreur
        document.addEventListener('DOMContentLoaded', () => {
            const iconoDescs = <?= json_encode($_POST['iconographie_description'] ?? []) ?>;
            const iconoLinks = <?= json_encode($_POST['iconographie_lien'] ?? []) ?>;
            iconoDescs.forEach((desc, i) => {
                addResourcePair('iconographie');
                const inputs = document.querySelectorAll('#iconographie-container .resource-pair')[i];
                inputs.querySelector('input[type="text"]').value = desc;
                inputs.querySelector('input[type="url"]').value = iconoLinks[i] || '';
            });

            const docDescs = <?= json_encode($_POST['documents_description'] ?? []) ?>;
            const docLinks = <?= json_encode($_POST['documents_lien'] ?? []) ?>;
            docDescs.forEach((desc, i) => {
                addResourcePair('documents');
                const inputs = document.querySelectorAll('#documents-container .resource-pair')[i];
                inputs.querySelector('input[type="text"]').value = desc;
                inputs.querySelector('input[type="url"]').value = docLinks[i] || '';
            });
        });
    </script>
</body>
</html>