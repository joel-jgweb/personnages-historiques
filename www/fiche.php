<?php
// fiche.php — Page de détail d'une fiche (photo alignée sur la première ligne sous le nom)
require_once __DIR__ . '/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$databasePath = __DIR__ . '/../data/portraits.sqlite';
$fiche = null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die("ID de fiche invalide.");
}

try {
    $pdo = new PDO("sqlite:$databasePath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Chargement de la configuration du site (couleurs, titre, association...)
    $config = loadSiteConfig($pdo);

    $stmt = $pdo->prepare("SELECT * FROM personnages WHERE ID_fiche = ? AND est_en_ligne = 1");
    $stmt->execute([(int)$_GET['id']]);
    $fiche = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fiche) {
        http_response_code(404);
        die("Fiche non trouvée.");
    }
} catch (Exception $e) {
    // En production, logger $e->getMessage() puis afficher un message générique
    die("❌ Erreur lors de la récupération de la fiche.");
}

// Helpers: normalization des listes de fichiers (ligne par ligne)
function explode_lines($text) {
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $out = [];
    foreach ($lines as $l) {
        $t = trim($l);
        if ($t !== '') $out[] = $t;
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($fiche['Nom']) ?> — <?= htmlspecialchars($config['site_title'] ?? 'Fiche') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Georgia', serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.8;
            padding: 2rem;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }

        /* Header reformaté en grid :
           - la première ligne contient le nom (h1) sur toute la largeur
           - la deuxième ligne contient, à gauche, la généalogie / métier / engagements
           - la deuxième colonne contient la photo, alignée en haut de cette deuxième ligne
           Ainsi la photo débute au niveau de la première ligne de texte située sous le nom. */
        .header {
            margin-bottom: 2.5rem;
        }
        .header-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            grid-template-rows: auto auto;
            gap: 1rem 2rem;
            align-items: start;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 0.75rem;
            text-decoration: none;
            color: <?= htmlspecialchars($config['secondary_color'] ?? '#007BFF') ?>;
            font-weight: bold;
        }
        .header-title {
            grid-column: 1 / -1;
            margin: 0;
        }
        .fiche-title {
            font-size: 2.5rem;
            color: <?= htmlspecialchars($config['primary_color'] ?? '#2c3e50') ?>;
            margin: 0.5rem 0 0.25rem 0;
        }

        .header-left {
            grid-column: 1 / 2;
        }
        .header-right {
            grid-column: 2 / 3;
            align-self: start; /* top of row 2 */
            text-align: right;
        }

        /* Style partagé pour tout ce qui est situé entre le Nom et la photo */
        .lead-info {
            font-size: 1.05rem;
            color: #5a5a5a;
            margin-bottom: 0.6rem;
            line-height: 1.5;
        }
        .lead-info .genealogie {
            margin-bottom: 0.35rem;
            color: #6a6a6a;
        }
        .fiche-metier {
            font-size: 1.15rem;
            color: #555;
            margin-bottom: 0.35rem;
            font-style: normal;
        }
        .engagements-inline {
            margin-top: 0.45rem;
            font-size: 1.03rem;
            color: #444;
        }

        .fiche-photo {
            width: 100%;
            max-width: 300px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: inline-block;
        }

        .section {
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #ecf0f1;
        }
        .section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .section-title {
            font-size: 1.5rem;
            color: <?= htmlspecialchars($config['primary_color'] ?? '#2c3e50') ?>;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid <?= htmlspecialchars($config['secondary_color'] ?? '#007BFF') ?>;
            display: inline-block;
        }
        .section-content {
            font-size: 1.1rem;
            line-height: 1.7;
        }
        .section-content a {
            color: <?= darkenColor($config['secondary_color'] ?? '#007BFF', 20) ?>;
            text-decoration: none;
            border-bottom: 1px dotted <?= htmlspecialchars($config['secondary_color'] ?? '#007BFF') ?>;
        }
        .section-content a:hover {
            border-bottom: 1px solid <?= darkenColor($config['secondary_color'] ?? '#007BFF', 20) ?>;
        }

        /* Iconographie (vignettes + lightbox) */
        .icono-row { display:flex;gap:22px;flex-wrap:wrap;margin-top:12px;}
        .icono-col { text-align:center; margin-bottom:22px;}
        .icono-col img { max-width:120px;max-height:120px;border:1.5px solid #aaa;border-radius:10px;cursor:pointer;transition:box-shadow .2s; display:block; margin:0 auto;}
        .icono-col img:hover { box-shadow: 0 0 12px <?= htmlspecialchars($config['secondary_color'] ?? '#0052cc') ?>; }
        .icono-desc { font-size:0.99em;color:#444;margin-top:6px;background:#f7f7fa;border-radius:4px;padding:2px 4px;}

        /* Documents table */
        .resource-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        .resource-table th, .resource-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .resource-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        /* Métadonnées centrées en bas, séparées visuellement de la fiche */
        .meta-bottom {
            text-align: center;
            font-size: 0.95rem;
            color: #6c757d;
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
            font-style: italic;
        }
        .meta-bottom .meta-values { font-weight: bold; font-style: normal; color: #495057; display:block; margin-top:0.45rem; }

        footer {
            text-align: center;
            padding: 1.5rem;
            background: rgba(0,0,0,0.05);
            margin-top: 1.25rem;
            font-size: 0.9rem;
            border-radius: 10px;
        }

        /* Modal */
        #modalLightbox { display:none; position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(0,0,0,0.85);align-items:center;justify-content:center;}
        #modalLightbox .modal-content { position:relative;text-align:center;}
        #modalImage { max-width:90vw;max-height:80vh;border-radius:14px;box-shadow:0px 0px 32px #000;}
        #modalDesc { color:#fff;font-size:1.18em;margin-top:16px;}
        #modalClose { position:absolute;top:10px;right:10px;background:#fff;color:#333;border-radius:50%;width:38px;height:38px;border:none;font-size:2em;cursor:pointer; }

        @media (max-width: 768px) {
            .container { padding: 1.5rem; }
            .fiche-title { font-size: 2rem; }
            .header-grid { grid-template-columns: 1fr; grid-template-rows: auto auto auto; }
            .header-right { grid-column: 1 / -1; text-align: center; order: -1; margin-bottom: 1rem; }
            .header-left { grid-column: 1 / -1; }
            .icono-row { gap: 12px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="search.php" class="back-link">← Retour aux résultats</a>

            <div class="header-grid">
                <div class="header-title">
                    <h1 class="fiche-title"><?= htmlspecialchars($fiche['Nom']) ?></h1>
                </div>

                <div class="header-left">
                    <!-- Données généalogiques (sans étiquette), rendues en Markdown pour conserver formatage -->
                    <?php if (!empty($fiche['Donnees_genealogiques'])): ?>
                        <div class="lead-info genealogie">
                            <?= markdownToHtml($fiche['Donnees_genealogiques']) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Métier (sans étiquette), directement sous la généalogie -->
                    <?php if (!empty($fiche['Metier'])): ?>
                        <div class="fiche-metier"><?= htmlspecialchars($fiche['Metier']) ?></div>
                    <?php endif; ?>

                    <!-- Engagements (sans étiquette), rendus en Markdown et même style -->
                    <?php if (!empty($fiche['Engagements'])): ?>
                        <div class="engagements-inline">
                            <?= markdownToHtml($fiche['Engagements']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Photo en haut à droite : elle commencera au même niveau que la première ligne de texte sous le nom -->
                <?php if (!empty($fiche['Photo'])): ?>
                    <div class="header-right">
                        <img src="<?= htmlspecialchars($fiche['Photo']) ?>" alt="Photo de <?= htmlspecialchars($fiche['Nom']) ?>" class="fiche-photo" loading="lazy">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Iconographie : vignettes + lightbox -->
        <?php if (!empty($fiche['Iconographie'])):
            $iconos = explode_lines($fiche['Iconographie']);
            if (count($iconos) > 0): ?>
            <div class="section">
                <h2 class="section-title">Iconographie</h2>
                <div class="section-content">
                    <div class="icono-row">
                        <?php foreach ($iconos as $chemin):
                            $filename = basename($chemin);
                            $desc = '';
                            try {
                                $stmt = $pdo->prepare("SELECT description FROM gesdoc WHERE nom_fichier = ?");
                                $stmt->execute([$filename]);
                                $desc = $stmt->fetchColumn() ?: $filename;
                            } catch (Exception $e) {
                                $desc = $filename;
                            }
                            $public_url = '/fetch_doc.php?file=' . rawurlencode($filename);
                        ?>
                            <div class="icono-col">
                                <a href="#" onclick="openModal(<?= json_encode($public_url) ?>, <?= json_encode($desc) ?>);return false;">
                                    <img src="<?= htmlspecialchars($public_url) ?>" alt="<?= htmlspecialchars($desc) ?>" loading="lazy">
                                </a>
                                <div class="icono-desc"><?= htmlspecialchars($desc) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; endif; ?>

        <!-- Documents associés : table -->
        <?php if (!empty($fiche['Documents'])):
            $docs = explode_lines($fiche['Documents']);
            if (count($docs) > 0): ?>
            <div class="section">
                <h2 class="section-title">Documents</h2>
                <div class="section-content">
                    <table class="resource-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Fichier</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($docs as $chemin):
                            $filename = basename($chemin);
                            $desc = '';
                            try {
                                $stmt = $pdo->prepare("SELECT description FROM gesdoc WHERE nom_fichier = ?");
                                $stmt->execute([$filename]);
                                $desc = $stmt->fetchColumn() ?: $filename;
                            } catch (Exception $e) {
                                $desc = $filename;
                            }
                            $public_url = '/fetch_doc.php?file=' . rawurlencode($filename);
                            if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $filename)) {
                                $icon = '<img src="' . htmlspecialchars($public_url) . '" class="doc-ico" style="max-width:32px;max-height:32px;border-radius:6px;border:1px solid #bbb;">';
                            } elseif (preg_match('/\.pdf$/i', $filename)) {
                                $icon = '<span class="doc-ico" style="font-size:20px;">📄</span>';
                            } elseif (preg_match('/\.txt$/i', $filename)) {
                                $icon = '<span class="doc-ico" style="font-size:20px;">📄</span>';
                            } else {
                                $icon = '<span class="doc-ico" style="font-size:20px;">📎</span>';
                            }
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($desc) ?></td>
                                <td><?= $icon ?> <a href="<?= htmlspecialchars($public_url) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($filename) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; endif; ?>

        <!-- Détails et Sources (rendu Markdown) -->
        <?php if (!empty($fiche['Details'])): ?>
            <div class="section">
                <h2 class="section-title">Détails</h2>
                <div class="section-content">
                    <?= markdownToHtml($fiche['Details']) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($fiche['Sources'])): ?>
            <div class="section">
                <h2 class="section-title">Sources</h2>
                <div class="section-content">
                    <?= markdownToHtml($fiche['Sources']) ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- MÉTADONNÉES centrées en bas, séparées visuellement de la fiche
         Affiche uniquement "Rédigé par" (auteur) et "Dernière modification" -->
    <div class="meta-bottom" role="contentinfo" aria-label="Métadonnées de rédaction">
        <?php if (!empty($fiche['auteur']) || !empty($fiche['derniere_modif'])): ?>
            <?php if (!empty($fiche['auteur'])): ?>
                <div class="meta-values">Rédigé par : <?= htmlspecialchars($fiche['auteur']) ?></div>
            <?php endif; ?>
            <?php if (!empty($fiche['derniere_modif'])): ?>
                <div class="meta-values">Dernière modification : <?= htmlspecialchars($fiche['derniere_modif']) ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer>
        <p>
            <?= htmlspecialchars($config['association_name'] ?? '') ?><br>
            <?= nl2br(htmlspecialchars($config['association_address'] ?? '')) ?>
        </p>
    </footer>

    <!-- Modal lightbox pour les photos -->
    <div id="modalLightbox" aria-hidden="true">
      <div class="modal-content" role="dialog" aria-modal="true" aria-label="Lightbox image">
        <img id="modalImage" src="" alt="">
        <div id="modalDesc"></div>
        <button id="modalClose" onclick="closeModal()" aria-label="Fermer la fenêtre">×</button>
      </div>
    </div>

    <script>
    function openModal(src, desc) {
        var modal = document.getElementById("modalLightbox");
        document.getElementById("modalImage").src = src;
        document.getElementById("modalImage").alt = desc || '';
        document.getElementById("modalDesc").textContent = desc || '';
        modal.style.display = "flex";
        modal.setAttribute('aria-hidden', 'false');
    }
    function closeModal() {
        var modal = document.getElementById("modalLightbox");
        modal.style.display = "none";
        modal.setAttribute('aria-hidden', 'true');
        document.getElementById("modalImage").src = "";
    }
    document.getElementById("modalLightbox").onclick = function(e) {
        if(e.target === this) closeModal();
    };
    </script>
</body>
</html>