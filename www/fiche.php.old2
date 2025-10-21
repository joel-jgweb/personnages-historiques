<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connexion à la base et récupération de la fiche selon l'ID transmis (par GET ou autre)
require_once __DIR__ . '/config.php';
$dbPath = __DIR__ . '/../data/portraits.sqlite';
$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$fiche = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM personnages WHERE ID_fiche = ?');
    $stmt->execute([$_GET['id']]);
    $fiche = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$fiche) {
    echo "<div style='color:red;font-weight:bold;'>Fiche introuvable !</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($fiche['Nom']) ?> — Fiche personnage</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f7f7fc; margin: 0; }
        .container { max-width: 960px; margin: 40px auto 40px auto; background: #fff; padding: 40px 30px 30px 30px; border-radius: 14px; box-shadow: 0 6px 24px rgba(0,0,0,0.08);}
        h1 { color: #2c2c2c; font-size:2.3em; text-align:center; font-weight:600; margin-bottom:12px;}
        h2, h3 { color: #333; margin-top: 36px; margin-bottom:12px; font-weight:500;}
        .meta { font-size:1.08em; margin-bottom:22px; color:#555; background:#f5f7fa; padding:10px 18px; border-radius:8px; box-shadow:0 2px 10px #e2e7fa;}
        .section { margin-bottom:28px; }
        .icono-row { display:flex;gap:22px;flex-wrap:wrap;margin-top:12px;}
        .icono-col { text-align:center; margin-bottom:22px;}
        .icono-col img { max-width:120px;max-height:120px;border:1.5px solid #aaa;border-radius:10px;cursor:pointer;transition:box-shadow .2s;}
        .icono-col img:hover { box-shadow: 0 0 12px #0052cc; }
        .icono-desc { font-size:0.99em;color:#444;margin-top:6px;background:#f7f7fa;border-radius:4px;padding:2px 4px;}
        #modalLightbox { display:none; position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(0,0,0,0.85);align-items:center;justify-content:center;}
        #modalLightbox .modal-content { position:relative;text-align:center;}
        #modalImage { max-width:90vw;max-height:80vh;border-radius:14px;box-shadow:0px 0px 32px #000;}
        #modalDesc { color:#fff;font-size:1.18em;margin-top:16px;}
        #modalClose { position:absolute;top:10px;right:10px;background:#fff;color:#333;border-radius:50%;width:38px;height:38px;border:none;font-size:2em;cursor:pointer; }
        table.docs { border-collapse:collapse;width:100%;margin-top:10px;background:#f7f7fa;}
        table.docs th,table.docs td { border:1px solid #ddd;padding:10px 8px; }
        table.docs th { background:#eaeaea;font-weight:500;text-align:left; }
        table.docs td { font-size:1em; }
        .doc-ico { vertical-align:middle; margin-right:8px;}
        @media (max-width: 700px) {
            .container { padding: 10px; }
            .icono-row {gap:10px;}
            table.docs th,table.docs td { padding:6px 2px; }
        }
    </style>
</head>
<body>
<div class="container">

    <h1><?= htmlspecialchars($fiche['Nom']) ?></h1>
    <div class="meta">
        <strong>Métier :</strong> <?= htmlspecialchars($fiche['Metier']) ?><br>
        <strong>Auteur :</strong> <?= htmlspecialchars($fiche['auteur']) ?>
        <?php if (!empty($fiche['valideur'])): ?>
            <br><strong>Validée par :</strong> <?= htmlspecialchars($fiche['valideur']) ?>
        <?php endif; ?>
        <br><strong>Dernière modification :</strong> <?= htmlspecialchars($fiche['derniere_modif']) ?>
    </div>

    <?php if (!empty($fiche['Photo'])): ?>
        <div class="section">
            <h3>Photo principale</h3>
            <img src="<?= htmlspecialchars($fiche['Photo']) ?>" alt="Photo principale" style="max-width:220px;max-height:220px;border:2px solid #bbb;border-radius:12px;">
        </div>
    <?php endif; ?>

    <?php if (!empty($fiche['Iconographie'])): ?>
        <div class="section">
            <h3>Iconographie</h3>
            <div class="icono-row">
            <?php
            $iconos = explode("\n", $fiche['Iconographie']);
            foreach ($iconos as $chemin) {
                $filename = basename($chemin);
                $desc = '';
                try {
                    $stmt = $pdo->prepare("SELECT description FROM gesdoc WHERE nom_fichier = ?");
                    $stmt->execute([$filename]);
                    $desc = $stmt->fetchColumn();
                } catch (Exception $e) {}
                $public_url = "/fetch_doc.php?file=" . urlencode($filename);
                echo '<div class="icono-col">';
                echo '<a href="#" onclick="openModal(\'' . $public_url . '\', \'' . htmlspecialchars(addslashes($desc)) . '\');return false;">';
                echo '<img src="' . $public_url . '" alt="' . htmlspecialchars($desc) . '"></a>';
                echo '<div class="icono-desc">' . htmlspecialchars($desc) . '</div>';
                echo '</div>';
            }
            ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($fiche['Documents'])): ?>
        <div class="section">
            <h3>Documents associés</h3>
            <table class="docs">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Fichier</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $docs = explode("\n", $fiche['Documents']);
                foreach ($docs as $chemin) {
                    $filename = basename($chemin);
                    $desc = '';
                    try {
                        $stmt = $pdo->prepare("SELECT description FROM gesdoc WHERE nom_fichier = ?");
                        $stmt->execute([$filename]);
                        $desc = $stmt->fetchColumn();
                    } catch (Exception $e) {}
                    $public_url = "/fetch_doc.php?file=" . urlencode($filename);
                    // Icône selon le type
                    if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $chemin)) {
                        $icon = '<img src="' . $public_url . '" class="doc-ico" style="max-width:32px;max-height:32px;border-radius:6px;border:1px solid #bbb;">';
                    } elseif (preg_match('/\.pdf$/i', $chemin)) {
                        $icon = '<span class="doc-ico" style="font-size:20px;">📄</span>';
                    } elseif (preg_match('/\.txt$/i', $chemin)) {
                        $icon = '<span class="doc-ico" style="font-size:20px;">📄</span>';
                    } else {
                        $icon = '<span class="doc-ico" style="font-size:20px;">📎</span>';
                    }
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($desc) . '</td>';
                    echo '<td>' . $icon . ' <a href="' . $public_url . '" target="_blank">' . htmlspecialchars($filename) . '</a></td>';
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($fiche['Engagements'])): ?>
        <div class="section">
            <h3>Engagements</h3>
            <div><?= nl2br(htmlspecialchars($fiche['Engagements'])) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($fiche['Details'])): ?>
        <div class="section">
            <h3>Détails</h3>
            <div><?= nl2br(htmlspecialchars($fiche['Details'])) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($fiche['Sources'])): ?>
        <div class="section">
            <h3>Sources</h3>
            <div><?= nl2br(htmlspecialchars($fiche['Sources'])) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($fiche['Donnees_genealogiques'])): ?>
        <div class="section">
            <h3>Données généalogiques</h3>
            <div><?= nl2br(htmlspecialchars($fiche['Donnees_genealogiques'])) ?></div>
        </div>
    <?php endif; ?>

</div>
<!-- Modal lightbox pour les photos -->
<div id="modalLightbox">
  <div class="modal-content">
    <img id="modalImage" src="" alt="">
    <div id="modalDesc"></div>
    <button id="modalClose" onclick="closeModal()">×</button>
  </div>
</div>
<script>
function openModal(src, desc) {
    document.getElementById("modalImage").src = src;
    document.getElementById("modalDesc").textContent = desc;
    document.getElementById("modalLightbox").style.display = "flex";
}
function closeModal() {
    document.getElementById("modalLightbox").style.display = "none";
    document.getElementById("modalImage").src = "";
}
document.getElementById("modalLightbox").onclick = function(e) {
    if(e.target === this) closeModal();
};
</script>
</body>
</html>