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

require_once '../bootstrap.php';
$dbPath = __DIR__ . '/../../data/portraits.sqlite';
$message = '';

// inclure le helper partagé si présent
if (file_exists(__DIR__ . '/../includes/ressource_helpers.php')) {
    require_once __DIR__ . '/../includes/ressource_helpers.php';
}

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
    $est_en_ligne = $_POST['est_en_ligne'] ?? '0';

    // Photo : simple nom de fichier (ex: monimage.jpg) — envoyé par la popup/upload_docs
    $photo = trim($_POST['Photo'] ?? '');

    // Iconographie : textarea cachée contenant une liste de noms de fichiers (une ligne = un fichier)
    $iconographie_list = trim($_POST['Iconographie'] ?? '');
    // Documents : idem
    $documents_list = trim($_POST['Documents'] ?? '');

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
                $donnees_genealogiques, $iconographie_list, $photo, $documents_list,
                $est_en_ligne, $auteur, $valideur, $derniere_modif
            ]);
            $new_id = $pdo->lastInsertId();
            $message = "<div class='alert alert-success'>🎉 Fiche #$new_id créée avec succès !</div>";
            // Clear POST values to avoid re-displaying stale data
            $_POST = [];
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// helper to parse incoming textarea value into filenames (use shared helper if available)
function _parseFieldToFiles($fieldValue) {
    if (function_exists('extractFilenamesFromField')) {
        return extractFilenamesFromField($fieldValue);
    }
    $result = [];
    if (!$fieldValue) return $result;
    $lines = preg_split('/\r\n|\r|\n/', trim($fieldValue));
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l !== '') $result[] = basename($l);
    }
    return $result;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>➕ Ajouter une fiche</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/admin/js/simplemde/simplemde.min.css">
    <link rel="stylesheet" href="admin.css">
    <style>
    .resource-list { list-style: none; padding-left: 0; }
    .resource-item { display:flex; gap:8px; align-items:center; margin-bottom:6px;}
    .resource-item button { margin-left:8px; }
    .resource-previews { margin-top:10px; }
    .resource-previews .preview { display:inline-block; margin:6px; text-align:center; width:110px; }
    .resource-previews img { max-height:80px; display:block; margin:0 auto; }
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

            <!-- Photo (un seul fichier) -->
            <div class="form-group">
                <label for="Photo">Photo (nom du fichier, stocké en data/docs) :</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input type="text" name="Photo" id="Photo" value="<?= htmlspecialchars($_POST['Photo'] ?? '') ?>" placeholder="ex: portrait.jpg" readonly>
                    <?php
                    // bouton accessible seulement aux administrateurs 1,2,6
                    $showUploadBtn = in_array($_SESSION['user_statut'] ?? 0, [1,2,6]);
                    if ($showUploadBtn):
                    ?>
                        <button type="button" onclick="openUploadPopup('Photo')">Choisir Photo</button>
                    <?php endif; ?>
                </div>
                <div>
                    <a id="Photo_link" href="#" target="_blank" style="display:none"><img id="Photo_preview" src="" alt="Aperçu" style="max-width:200px; margin-top:8px;"></a>
                    <img id="Photo_preview_empty" src="" alt="" style="max-width:200px; display:none; margin-top:8px;">
                </div>
            </div>

            <!-- Iconographie (liste de fichiers) -->
            <div class="form-group">
                <label>Iconographie (liste de noms de fichiers dans data/docs) :</label>
                <ul id="iconographie_list" class="resource-list">
                    <?php
                    // pré-remplir avec POST si présent (utilise parsing robuste)
                    $initial_icono = $_POST['Iconographie'] ?? '';
                    $icono_files = _parseFieldToFiles($initial_icono);
                    foreach ($icono_files as $f):
                        $thumb = '../acces_docs.php?f=' . urlencode($f) . '&thumb=120';
                        $full = '../acces_docs.php?f=' . urlencode($f);
                    ?>
                        <li class="resource-item" data-name="<?= htmlspecialchars($f) ?>">
                            <?php if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp'])): ?>
                                <a href="<?= $full ?>" target="_blank" rel="noopener"><img src="<?= $thumb ?>" alt="<?= htmlspecialchars($f) ?>" style="max-height:40px; margin-right:8px;"></a>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($f) ?></span>
                            <button type="button" onclick="this.parentElement.remove(); updateHiddenList(document.getElementById('Iconographie'), document.getElementById('iconographie_list'));">Supprimer</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <textarea id="Iconographie" name="Iconographie" style="display:none;"><?= htmlspecialchars($_POST['Iconographie'] ?? '') ?></textarea>
                <?php if ($showUploadBtn): ?>
                    <button type="button" onclick="openUploadPopup('Iconographie')">Choisir une image</button>
                <?php endif; ?>

                <?php if (!empty($icono_files)): ?>
                    <div class="resource-previews" aria-hidden="true">
                        <?php foreach ($icono_files as $fn):
                            $thumb = '../acces_docs.php?f=' . urlencode($fn) . '&thumb=120';
                            $full = '../acces_docs.php?f=' . urlencode($fn);
                        ?>
                            <div class="preview">
                                <a href="<?= $full ?>" target="_blank" rel="noopener"><img src="<?= $thumb ?>" alt="<?= htmlspecialchars($fn) ?>"></a>
                                <div style="font-size:0.8em;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($fn) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Documents (liste de fichiers) -->
            <div class="form-group">
                <label>Documents (liste de noms de fichiers dans data/docs) :</label>
                <ul id="documents_list" class="resource-list">
                    <?php
                    $initial_docs = $_POST['Documents'] ?? '';
                    $doc_files = _parseFieldToFiles($initial_docs);
                    foreach ($doc_files as $f):
                        $full = '../acces_docs.php?f=' . urlencode($f);
                        $thumb = $full . '&thumb=120';
                    ?>
                        <li class="resource-item" data-name="<?= htmlspecialchars($f) ?>">
                            <?php if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp'])): ?>
                                <a href="<?= $full ?>" target="_blank" rel="noopener"><img src="<?= $thumb ?>" alt="<?= htmlspecialchars($f) ?>" style="max-height:40px; margin-right:8px;"></a>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($f) ?></span>
                            <button type="button" onclick="this.parentElement.remove(); updateHiddenList(document.getElementById('Documents'), document.getElementById('documents_list'));">Supprimer</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <textarea id="Documents" name="Documents" style="display:none;"><?= htmlspecialchars($_POST['Documents'] ?? '') ?></textarea>
                <?php if ($showUploadBtn): ?>
                    <button type="button" onclick="openUploadPopup('Documents')">Choisir un document</button>
                <?php endif; ?>

                <?php if (!empty($doc_files)): ?>
                    <div class="resource-previews" aria-hidden="true">
                        <?php foreach ($doc_files as $fn):
                            $view = '../acces_docs.php?f=' . urlencode($fn);
                            $dl = $view . '&download=1';
                        ?>
                            <div class="preview">
                                <?php if (in_array(strtolower(pathinfo($fn, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp'])): ?>
                                    <a href="<?= $view ?>" target="_blank" rel="noopener"><img src="<?= $view ?>&thumb=120" alt="<?= htmlspecialchars($fn) ?>"></a>
                                <?php else:
                                    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
                                    $icon = ($ext === 'pdf') ? '📄' : (($ext === 'txt') ? '📝' : (($ext === 'zip') ? '📦' : '📎'));
                                ?>
                                    <a href="<?= $view ?>" target="_blank" rel="noopener" style="display:inline-block;width:60px;height:60px;line-height:60px;border:1px solid #ddd;border-radius:6px;background:#f8f9fa;text-decoration:none;"><?= $icon ?></a>
                                <?php endif; ?>
                                <div style="font-size:0.8em;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($fn) ?></div>
                                <div><a href="<?= $dl ?>" style="font-size:0.85em;">Télécharger</a></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="auteur">Auteur :</label>
                <input type="text" name="auteur" id="auteur" value="<?= htmlspecialchars($_SESSION['nom_prenom'] ?? '') ?>" readonly>
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
        // SimpleMDE
        try {
            const simplemdeDetails = new SimpleMDE({ element: document.getElementById("Details"), toolbar: ["bold", "italic", "link"], spellChecker: false, status: false });
            const simplemdeSources = new SimpleMDE({ element: document.getElementById("Sources"), toolbar: ["bold", "italic", "link"], spellChecker: false, status: false });
        } catch (e) { /* ignore if lib absent */ }

        // Ouvre la popup d'upload_docs.php et mémorise le champ visé
        function openUploadPopup(field) {
            const url = '/admin/upload_docs.php?field=' + encodeURIComponent(field);
            const w = 700, h = 500;
            const left = (screen.width/2)-(w/2);
            const top = (screen.height/2)-(h/2);
            window._uploadTargetField = field;
            window.open(url, 'uploadDocs', `width=${w},height=${h},left=${left},top=${top}`);
        }

        // Callback appelée par la popup après upload réussi (upload_docs.php doit appeler window.opener.uploadDocsCallback({...}))
        function uploadDocsCallback(data) {
            // data = { field: 'Photo'|'Iconographie'|'Documents', filename: 'fichier.ext' }
            if (!data || !data.field || !data.filename) return;
            const fname = String(data.filename).trim();

            if (data.field === 'Photo') {
                document.getElementById('Photo').value = fname;
                const preview = document.getElementById('Photo_preview');
                const link = document.getElementById('Photo_link');
                if (preview && link) {
                    const url = '../acces_docs.php?f=' + encodeURIComponent(fname) + '&thumb=220';
                    const full = '../acces_docs.php?f=' + encodeURIComponent(fname);
                    preview.src = url;
                    link.href = full;
                    link.style.display = 'inline-block';
                    preview.style.display = 'block';
                }
            } else if (data.field === 'Iconographie') {
                const list = document.getElementById('iconographie_list');
                addListItem(list, fname, true);
                updateHiddenList(document.getElementById('Iconographie'), list);
            } else if (data.field === 'Documents') {
                const list = document.getElementById('documents_list');
                addListItem(list, fname, true);
                updateHiddenList(document.getElementById('Documents'), list);
            }
        }

        function addListItem(list, fname, useAccessDocs) {
            // évite doublons
            const existing = Array.from(list.querySelectorAll('li')).map(li => li.dataset.name);
            if (existing.includes(fname)) return;
            const li = document.createElement('li');
            li.className = 'resource-item';
            li.dataset.name = fname;

            const ext = fname.split('.').pop().toLowerCase();

            if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
                const a = document.createElement('a');
                const thumb = (useAccessDocs ? '../acces_docs.php?f=' + encodeURIComponent(fname) + '&thumb=120' : 'data/docs/' + fname);
                const full = (useAccessDocs ? '../acces_docs.php?f=' + encodeURIComponent(fname) : 'data/docs/' + fname);
                a.href = full;
                a.target = '_blank';
                a.rel = 'noopener';
                const img = document.createElement('img');
                img.src = thumb;
                img.style.maxHeight = '40px';
                img.style.marginRight = '8px';
                a.appendChild(img);
                li.appendChild(a);
            }

            const span = document.createElement('span');
            span.textContent = fname;
            li.appendChild(span);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = 'Supprimer';
            btn.onclick = function(){ li.remove(); updateHiddenList(list === document.getElementById('iconographie_list') ? document.getElementById('Iconographie') : document.getElementById('Documents'), list); };
            li.appendChild(btn);

            list.appendChild(li);
        }

        function updateHiddenList(hiddenField, listContainer) {
            const items = Array.from(listContainer.querySelectorAll('li')).map(li => li.dataset.name);
            hiddenField.value = items.join("\n");
        }

        // Pré-remplir les listes si des valeurs POST existent (ou si l'utilisateur revient suite à erreur)
        document.addEventListener('DOMContentLoaded', () => {
            // Iconographie: valeurs dans textarea Iconographie si présentes
            const iconoTxt = document.getElementById('Iconographie').value.trim();
            if (iconoTxt) {
                const list = document.getElementById('iconographie_list');
                iconoTxt.split(/\r\n|\r|\n/).forEach(s=>{
                    const fname = s.trim();
                    if (fname) addListItem(list, fname, true);
                });
                updateHiddenList(document.getElementById('Iconographie'), list);
            }
            // Documents
            const docTxt = document.getElementById('Documents').value.trim();
            if (docTxt) {
                const list = document.getElementById('documents_list');
                docTxt.split(/\r\n|\r|\n/).forEach(s=>{
                    const fname = s.trim();
                    if (fname) addListItem(list, fname, true);
                });
                updateHiddenList(document.getElementById('Documents'), list);
            }
            // Photo preview si déjà renseignée
            const photoVal = document.getElementById('Photo').value.trim();
            if (photoVal) {
                const preview = document.getElementById('Photo_preview');
                const link = document.getElementById('Photo_link');
                if (preview && link) {
                    const url = '../acces_docs.php?f=' + encodeURIComponent(photoVal) + '&thumb=220';
                    const full = '../acces_docs.php?f=' + encodeURIComponent(photoVal);
                    preview.src = url;
                    link.href = full;
                    link.style.display = 'inline-block';
                    preview.style.display = 'block';
                } else if (preview) {
                    preview.src = 'data/docs/' + photoVal;
                    preview.style.display = 'block';
                }
            }
        });
    </script>
</body>
</html>