<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'permissions.php';
checkUserPermission([1, 2, 3, 6]);
require_once '../config.php';
$dbPath = __DIR__ . '/../../data/portraits.sqlite';
$message = '';
$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user_statut = $_SESSION['user_statut'] ?? null;
$user_nom_prenom = $_SESSION['nom_prenom'] ?? null;
$is_admin = in_array($user_statut, [1,2,6]);
$is_contrib = $user_statut == 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $nom = trim($_POST['Nom'] ?? '');
    $metier = $_POST['Metier'] ?? '';
    $engagements = $_POST['Engagements'] ?? '';
    $details = $_POST['Details'] ?? '';
    $sources = $_POST['Sources'] ?? '';
    $donnees_genealogiques = $_POST['Donnees_genealogiques'] ?? '';
    $photo = $_POST['Photo'] ?? '';
    $est_en_ligne = $_POST['est_en_ligne'] ?? '0';

    $icono_links = $_POST['iconographie_lien'] ?? [];
    $doc_links = $_POST['documents_lien'] ?? [];

    // Si contributeur, pas de gestion docs/images et fiche toujours hors ligne
    if ($is_contrib) {
        $est_en_ligne = '0';
        $icono_links = [];
        $doc_links = [];
    }

    // Update gesdoc descriptions (seulement admin/valideur/super-user)
    if ($is_admin && isset($_POST['description_gesdoc'])) {
        foreach ($_POST['description_gesdoc'] as $chemin => $desc) {
            $filename = basename($chemin);
            $stmt = $pdo->prepare("UPDATE gesdoc SET description = :desc WHERE nom_fichier = :nom");
            $stmt->execute([':desc' => $desc, ':nom' => $filename]);
        }
    }

    $iconographie = implode("\n", $icono_links);
    $documents = implode("\n", $doc_links);

    $auteur = $user_nom_prenom ?? 'Inconnu';
    $derniere_modif = date('Y-m-d H:i:s');
    $valideur = ($est_en_ligne == '1') ? $auteur : null;

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
    <script src="/admin/js/simplemde/simplemde.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f9f9f9; }
        .container { max-width: 900px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);}
        h1 { text-align: center; color: #333; }
        label { font-weight: bold; display: block; margin-top: 15px; }
        input[type="text"], textarea, select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
        textarea { height: 120px; resize: vertical; }
        button { background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; }
        .resource-pair { display: flex; gap: 10px; margin-bottom: 10px; align-items: center;}
        .resource-pair input[type="text"] { flex: 2; }
        .resource-pair input[type="hidden"] { flex: 0; }
        .resource-pair .filepath { flex: 3; font-size:0.9em; color:#333; background:#f8f8f8; border:1px solid #ddd; padding:2px 6px; border-radius:4px;}
        .resource-pair img { max-width:80px;max-height:80px;border:1px solid #aaa; background:#fff;}
        .resource-pair .doc-link { font-size:0.95em; margin-right:8px;}
        .btn-upload { background: #0052cc; color: #fff; padding: 5px 10px; margin-left: 10px; font-size: 0.9rem; }
        .btn-remove { background: #dc3545; padding: 5px 10px; font-size: 0.9rem; }
        #uploadModal { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.4); display:none; align-items:center; justify-content:center; z-index:9999;}
        #uploadModal .modal-content { background: #fff; padding:20px; border-radius:8px; min-width:300px; box-shadow:0 2px 8px rgba(0,0,0,0.15);}
        #descError { color: #dc3545; font-size: 0.95em; margin: 5px 0 0 0; display:none;}
        .select-emoji option[value="0"] { color: #c00; }
        .select-emoji option[value="1"] { color: #28a745; }
        .alert-info { background:#eef; color:#0052cc; border:1px solid #bcd; padding:10px; border-radius:4px; margin:10px 0;}
        .alert-danger { background:#fee; color:#c00; border:1px solid #c00; padding:10px; border-radius:4px; margin:10px 0;}
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
            <input type="text" name="Nom" id="Nom" value="<?= isset($_POST['Nom']) ? htmlspecialchars($_POST['Nom']) : '' ?>" required>
        </div>
        <div class="form-group">
            <label for="Metier">Métier :</label>
            <textarea name="Metier" id="Metier"><?= isset($_POST['Metier']) ? htmlspecialchars($_POST['Metier']) : '' ?></textarea>
        </div>
        <div class="form-group">
            <label for="Engagements">Engagements :</label>
            <textarea name="Engagements" id="Engagements"><?= isset($_POST['Engagements']) ? htmlspecialchars($_POST['Engagements']) : '' ?></textarea>
        </div>
        <div class="form-group">
            <label for="Details">Détails :</label>
            <textarea name="Details" id="Details"><?= isset($_POST['Details']) ? htmlspecialchars($_POST['Details']) : '' ?></textarea>
        </div>
        <div class="form-group">
            <label for="Sources">Sources :</label>
            <textarea name="Sources" id="Sources"><?= isset($_POST['Sources']) ? htmlspecialchars($_POST['Sources']) : '' ?></textarea>
        </div>
        <div class="form-group">
            <label for="Donnees_genealogiques">Données généalogiques :</label>
            <textarea name="Donnees_genealogiques" id="Donnees_genealogiques"><?= isset($_POST['Donnees_genealogiques']) ? htmlspecialchars($_POST['Donnees_genealogiques']) : '' ?></textarea>
        </div>
        <div class="form-group">
            <label for="Photo">Photo (URL ou chemin relatif) :</label>
            <input type="text" name="Photo" id="Photo" value="<?= isset($_POST['Photo']) ? htmlspecialchars($_POST['Photo']) : '' ?>">
        </div>
        <div class="form-group">
            <label for="auteur">Auteur :</label>
            <input type="text" name="auteur" id="auteur" value="<?= isset($_SESSION['nom_prenom']) ? htmlspecialchars($_SESSION['nom_prenom']) : '' ?>" readonly>
        </div>
        <div class="form-group">
            <label>Iconographie</label>
            <div id="iconographie-container">
                <?php
                if ($is_admin && !empty($_POST['iconographie_lien'])) {
                    foreach ($_POST['iconographie_lien'] as $chemin) {
                        $filename = basename($chemin);
                        $desc = '';
                        try {
                            $stmt = $pdo->prepare("SELECT description FROM gesdoc WHERE nom_fichier = ?");
                            $stmt->execute([$filename]);
                            $desc = $stmt->fetchColumn();
                        } catch (Exception $e) {}
                        $is_img = preg_match('/\.(jpg|jpeg|png|gif)$/i', $chemin);
                        $public_url = "/fetch_doc.php?file=" . urlencode($filename);
                        echo '<div class="resource-pair">';
                        echo '<input type="text" name="description_gesdoc[' . htmlspecialchars($chemin) . ']" value="' . htmlspecialchars($desc) . '">';
                        echo '<input type="hidden" name="iconographie_lien[]" value="' . htmlspecialchars($chemin) . '">';
                        if ($is_img) echo '<img src="' . $public_url . '" style="max-width:80px;max-height:80px;">';
                        echo '<span class="filepath">' . htmlspecialchars($chemin) . '</span>';
                        echo '<button type="button" class="btn-remove" onclick="this.parentElement.remove()">🗑️</button>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
            <?php if ($is_admin): ?>
            <button type="button" class="btn-upload" onclick="openUploadModal('iconographie')">📤 Ajouter une image</button>
            <?php else: ?>
            <div class="alert-info">Pour ajouter ou retirer des images, contactez l’administrateur.</div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Documents</label>
            <div id="documents-container">
                <?php
                if ($is_admin && !empty($_POST['documents_lien'])) {
                    foreach ($_POST['documents_lien'] as $chemin) {
                        $filename = basename($chemin);
                        $desc = '';
                        try {
                            $stmt = $pdo->prepare("SELECT description FROM gesdoc WHERE nom_fichier = ?");
                            $stmt->execute([$filename]);
                            $desc = $stmt->fetchColumn();
                        } catch (Exception $e) {}
                        $is_img = preg_match('/\.(jpg|jpeg|png|gif)$/i', $chemin);
                        $is_pdf = preg_match('/\.pdf$/i', $chemin);
                        $is_txt = preg_match('/\.txt$/i', $chemin);
                        $public_url = "/fetch_doc.php?file=" . urlencode($filename);
                        echo '<div class="resource-pair">';
                        echo '<input type="text" name="description_gesdoc[' . htmlspecialchars($chemin) . ']" value="' . htmlspecialchars($desc) . '">';
                        echo '<input type="hidden" name="documents_lien[]" value="' . htmlspecialchars($chemin) . '">';
                        if ($is_img) {
                            echo '<img src="' . $public_url . '" style="max-width:80px;max-height:80px;">';
                        } elseif ($is_pdf) {
                            echo '<a class="doc-link" href="' . $public_url . '" target="_blank">📄 PDF</a>';
                        } elseif ($is_txt) {
                            echo '<a class="doc-link" href="' . $public_url . '" target="_blank">📄 TXT</a>';
                        }
                        echo '<span class="filepath">' . htmlspecialchars($chemin) . '</span>';
                        echo '<button type="button" class="btn-remove" onclick="this.parentElement.remove()">🗑️</button>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
            <?php if ($is_admin): ?>
            <button type="button" class="btn-upload" onclick="openUploadModal('documents')">📤 Ajouter un document</button>
            <?php else: ?>
            <div class="alert-info">Pour ajouter ou retirer des documents, contactez l’administrateur.</div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="est_en_ligne">Statut de publication :</label>
            <select name="est_en_ligne" id="est_en_ligne" class="select-emoji" <?= $is_contrib ? 'disabled' : '' ?>>
                <option value="0" selected>🔴 Brouillon / Hors ligne</option>
                <option value="1" <?= (isset($_POST['est_en_ligne']) && $_POST['est_en_ligne'] == '1' && $is_admin) ? 'selected' : '' ?>>🟩 Publié / En ligne</option>
            </select>
            <?php if ($is_contrib): ?>
                <div class="alert-info">Vous ne pouvez pas publier votre fiche.</div>
            <?php endif; ?>
        </div>
        <button type="submit">Créer la fiche</button>
      </form>
    </div>
    <div id="uploadModal">
      <div class="modal-content">
        <input type="file" id="fileInput" />
        <label for="descInput">Description (obligatoire) :</label>
        <input type="text" id="descInput" maxlength="150" required>
        <div id="descError">La description est obligatoire.</div>
        <button type="button" onclick="uploadFile()">Envoyer</button>
        <button type="button" onclick="closeUploadModal()">Annuler</button>
      </div>
    </div>
    <script>
        const IS_ADMIN = <?= json_encode($is_admin) ?>;
        const IS_CONTRIB = <?= json_encode($is_contrib) ?>;
        const mdeOptions = {spellChecker:false,status:false,toolbar:["bold","italic","link"]};
        window.addEventListener('DOMContentLoaded', function() {
            new SimpleMDE({ element: document.getElementById('Details'), ...mdeOptions });
            new SimpleMDE({ element: document.getElementById('Sources'), ...mdeOptions });
        });
        let currentUploadType = '';
        function openUploadModal(type) {
            if(!IS_ADMIN){
                alert("Vous n'avez pas le droit de gérer les documents. Veuillez contacter l'administrateur.");
                return;
            }
            currentUploadType = type;
            document.getElementById('fileInput').value = '';
            document.getElementById('descInput').value = '';
            document.getElementById('descError').style.display = 'none';
            document.getElementById('uploadModal').style.display = 'flex';
            if(type === 'iconographie') {
                document.getElementById('fileInput').accept = 'image/jpeg,image/png,image/gif,image/jpg';
            } else {
                document.getElementById('fileInput').accept = 'application/pdf,image/jpeg,image/png,image/gif,image/jpg,text/plain';
            }
        }
        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
        }
        function addResourcePair(type, desc, path) {
            let container = document.getElementById(type + '-container');
            let div = document.createElement('div');
            div.className = 'resource-pair';
            let isImg = /\.(jpg|jpeg|png|gif)$/i.test(path);
            let isPdf = /\.pdf$/i.test(path);
            let isTxt = /\.txt$/i.test(path);
            let media = '';
            if (type === 'iconographie' && isImg) {
                media = `<img src="${path}" alt="img">`;
            } else if (type === 'documents') {
                if (isImg) {
                    media = `<img src="${path}" alt="img">`;
                } else if (isPdf) {
                    media = `<a class="doc-link" href="${path}" target="_blank">📄 PDF</a>`;
                } else if (isTxt) {
                    media = `<a class="doc-link" href="${path}" target="_blank">📄 TXT</a>`;
                }
            }
            div.innerHTML = `
                <input type="text" name="description_gesdoc[${path}]" value="${desc.replace(/\"/g,'&quot;')}" ${!IS_ADMIN ? 'readonly' : ''}>
                <input type="hidden" name="${type}_lien[]" value="${path}">
                ${media}
                <span class="filepath">${path}</span>
                ${IS_ADMIN ? '<button type="button" class="btn-remove" onclick="this.parentElement.remove()">🗑️</button>' : ''}
            `;
            container.appendChild(div);
        }
        function uploadFile() {
            if(!IS_ADMIN){
                alert("Vous n'avez pas le droit de gérer les documents. Veuillez contacter l'administrateur.");
                closeUploadModal();
                return;
            }
            const file = document.getElementById('fileInput').files[0];
            const desc = document.getElementById('descInput').value.trim();
            if (!file) return;
            if (!desc) {
                document.getElementById('descError').style.display = 'block';
                return;
            }
            document.getElementById('descError').style.display = 'none';
            const formData = new FormData();
            formData.append('fileToUpload', file);
            formData.append('ajax', '1');
            formData.append('description', desc);
            fetch('upload_docs.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if(res.success && res.already_exists && res.existing_file_name) {
                    let public_url = "/fetch_doc.php?file=" + encodeURIComponent(res.existing_file_name);
                    if (confirm(res.message + "\nCliquez sur OK pour l'utiliser.")) {
                        addResourcePair(currentUploadType, desc, public_url);
                    }
                    closeUploadModal();
                } else if (res.success && res.new_file_name) {
                    let public_url = "/fetch_doc.php?file=" + encodeURIComponent(res.new_file_name);
                    addResourcePair(currentUploadType, desc, public_url);
                    closeUploadModal();
                } else {
                    alert(res.message || 'Erreur upload');
                    closeUploadModal();
                }
            })
            .catch(() => {
                alert('Erreur upload');
                closeUploadModal();
            });
        }
    </script>
</body>
</html>