<?php
// fiche.php — Affiche le détail d'une fiche
// Mise à jour : suppression du séparateur "•" ; chaque item s'affiche sur sa propre ligne.
// - si photo présente : photo à droite, bloc d'items (italique, gris) à gauche
// - si pas de photo : bloc centré sur toute la largeur sous le nom (italique, gris)
// - affichage intégral de chaque item (aucune troncature)

$localConfig = file_exists(__DIR__ . '/../config/config.local.php') ? require __DIR__ . '/../config/config.local.php' : [];
require_once __DIR__ . '/bootstrap.php';

// DB path et docs dir
$databasePath = $localConfig['database_path'] ?? (__DIR__ . '/../data/portraits.sqlite');
$docsDir = rtrim($localConfig['docs_path'] ?? __DIR__ . '/../data/docs/', '/\\') . '/';

try {
    $pdo = new PDO("sqlite:$databasePath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $config = loadSiteConfig($pdo);

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) throw new Exception("ID invalide.");

    $stmt = $pdo->prepare("SELECT * FROM personnages WHERE ID_fiche = ?");
    $stmt->execute([$id]);
    $fiche = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fiche) throw new Exception("Fiche introuvable.");
} catch (Exception $e) {
    $error = $e->getMessage();
    $fiche = null;
}

// ----------------- RENDERERS -----------------
function inline_markdown_safe(string $text): string {
    if (class_exists('Parsedown')) {
        $pd = new Parsedown();
        if (method_exists($pd, 'setSafeMode')) $pd->setSafeMode(true);
        $html = $pd->text($text);
    } elseif (class_exists('ParsedownExtra')) {
        $pd = new ParsedownExtra();
        if (method_exists($pd, 'setSafeMode')) $pd->setSafeMode(true);
        $html = $pd->text($text);
    } else {
        $s = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $s = preg_replace_callback('!\[([^\]]+)\]\(([^)]+)\)!', function($m){
            $label = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $url = trim($m[2]);
            if (preg_match('#^(https?://|/|data:image/)#i', $url)) {
                $urlEsc = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return "<a href=\"$urlEsc\" rel=\"noopener noreferrer\" target=\"_blank\">$label</a>";
            }
            return $label;
        }, $s);
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
        $s = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $s);
        $s = preg_replace_callback('/`([^`]+)`/s', function($m){
            return '<code>' . htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
        }, $s);
        $html = preg_replace('/\s+/', ' ', str_replace(["\r","\n"], ' ', $s));
    }

    $html = preg_replace('#^<p>(.*)</p>$#s', '$1', trim($html));
    $html = str_replace(['<br />','<br>','<br/>'], ' ', $html);
    $html = preg_replace('/\s+/', ' ', $html);
    return trim($html);
}

function block_markdown_safe(string $text): string {
    if (class_exists('Parsedown')) {
        $pd = new Parsedown();
        if (method_exists($pd, 'setSafeMode')) $pd->setSafeMode(true);
        return $pd->text($text);
    }
    if (class_exists('ParsedownExtra')) {
        $pd = new ParsedownExtra();
        if (method_exists($pd, 'setSafeMode')) $pd->setSafeMode(true);
        return $pd->text($text);
    }
    $s = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $s = preg_replace_callback('!\[([^\]]+)\]\(([^)]+)\)!', function($m){
        $label = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url = trim($m[2]);
        if (preg_match('#^(https?://|/|data:image/)#i', $url)) {
            $urlEsc = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return "<a href=\"$urlEsc\" rel=\"noopener noreferrer\" target=\"_blank\">$label</a>";
        }
        return $label;
    }, $s);
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $s);
    $paragraphs = preg_split("/\r?\n\s*\r?\n/", trim($s));
    $out = '';
    foreach ($paragraphs as $p) {
        $out .= '<p>' . nl2br(trim($p)) . '</p>';
    }
    return $out;
}

// ----------------- Préparation contenus -----------------
$detailsHtml = isset($fiche['Details']) ? block_markdown_safe($fiche['Details']) : '';
$sourcesHtml = isset($fiche['Sources']) && trim($fiche['Sources']) !== '' ? block_markdown_safe($fiche['Sources']) : '';

$donneesGenealogiques = trim((string)($fiche['Donnees_genealogiques'] ?? ''));
$metier = trim((string)($fiche['Metier'] ?? ''));
$engagements = trim((string)($fiche['Engagements'] ?? ''));

$auteur = trim((string)($fiche['auteur'] ?? ''));
$derniere_modif = trim((string)($fiche['derniere_modif'] ?? ''));
$formattedDate = $derniere_modif ? date('d/m/Y H:i', strtotime($derniere_modif)) : null;

$photoSrc = '';
$hasPhoto = false;
if (!empty($fiche['Photo'])) {
    $candidate = $docsDir . basename($fiche['Photo']);
    if (file_exists($candidate)) {
        $photoSrc = $candidate;
        $hasPhoto = true;
    } else {
        $photoSrc = $fiche['Photo'];
        $hasPhoto = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiche — <?= htmlspecialchars($fiche['Nom'] ?? '—') ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{ font-family: Georgia, serif; background:#f7f7f7; color:#222; margin:0; padding:2rem; }
        .container{ max-width:980px; margin:0 auto; background:white; padding:1.5rem; border-radius:10px; }
        .header{ display:flex; gap:1rem; align-items:flex-start; flex-wrap:wrap; }
        .main-info { flex:1 1 0; min-width:0; } /* allow shrinking */
        .photo { width:220px; height:260px; object-fit:cover; border:1px solid #eee; border-radius:8px; flex:0 0 220px; margin-left:8px; }
        .btn { padding:.5rem .9rem; background:<?= htmlspecialchars($config['secondary_color'] ?? '#6c757d') ?>; color:white; border-radius:22px; text-decoration:none; }
        pre { background:#f6f6f6; padding:1rem; border-radius:6px; overflow:auto; }
        .sources { background:#fcfcfc; border-left:4px solid #eee; padding:12px; border-radius:6px; }

        /* Top items block: each item on its own line, italic + slightly gray */
        .top-items {
            display:block;
            margin-top:0.6rem;
            color:#666;
            font-style:italic;
            font-size:1rem;
            max-width:100%;
        }
        .top-item {
            display:block;
            margin: 6px 0;
            white-space:normal; /* allow wrapping */
            color: #666;
        }

        /* When there is no photo: center the main-info block content */
        .no-photo .header { justify-content:center; }
        .no-photo .main-info { text-align:center; }

        /* Small screens: stack naturally */
        @media (max-width:600px) {
            .header { flex-direction:column; }
            .photo { order:2; margin-top:1rem; width:100%; height:auto; max-height:260px; object-fit:cover; }
            .main-info { text-align:center; }
        }

        .metadata { margin-top:1.25rem; font-size:0.9rem; color:#666; border-top:1px dashed #eaeaea; padding-top:0.75rem; display:flex; gap:1rem; flex-wrap:wrap; justify-content:space-between; align-items:center; }
    </style>
</head>
<body>
    <div class="container <?= $hasPhoto ? '' : 'no-photo' ?>">
        <?php if (!empty($error)): ?>
            <div style="padding:1rem;background:#fdecea;color:#721c24;border-radius:8px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif ($fiche): ?>
            <div class="header">
                <div class="main-info">
                    <h1 style="margin:0;color:<?= htmlspecialchars($config['primary_color'] ?? '#2c3e50') ?>"><?= htmlspecialchars($fiche['Nom']) ?></h1>

                    <div class="top-items" role="group" aria-label="Informations principales">
                        <?php
                        $inlineItems = [];
                        if ($donneesGenealogiques !== '') $inlineItems[] = inline_markdown_safe($donneesGenealogiques);
                        if ($metier !== '') $inlineItems[] = inline_markdown_safe($metier);
                        if ($engagements !== '') $inlineItems[] = inline_markdown_safe($engagements);

                        foreach ($inlineItems as $item): ?>
                            <div class="top-item"><?= $item ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($hasPhoto): ?>
                    <img class="photo" src="<?= htmlspecialchars($photoSrc) ?>" alt="Photo de <?= htmlspecialchars($fiche['Nom']) ?>">
                <?php endif; ?>
            </div>

            <section style="margin-top:1rem">
                <h2>Détails</h2>
                <div><?= $detailsHtml ?></div>
            </section>

            <?php if (!empty($fiche['Iconographie'])): ?>
                <section style="margin-top:1rem">
                    <h3>Iconographie</h3>
                    <pre style="white-space:pre-wrap"><?= htmlspecialchars($fiche['Iconographie']) ?></pre>
                </section>
            <?php endif; ?>

            <?php if (!empty($sourcesHtml)): ?>
                <section style="margin-top:1rem">
                    <h3>Sources</h3>
                    <div class="sources"><?= $sourcesHtml ?></div>
                </section>
            <?php endif; ?>

            <?php if (!empty($fiche['Documents'])): ?>
                <section style="margin-top:1rem">
                    <h3>Documents</h3>
                    <pre style="white-space:pre-wrap"><?= htmlspecialchars($fiche['Documents']) ?></pre>
                </section>
            <?php endif; ?>

            <p style="margin-top:1.5rem">
                <a class="btn" href="index.php">← Retour</a>
                <a class="btn" href="genpdf/generer_pdf_tcpdf.php?id=<?= (int)$fiche['ID_fiche'] ?>" target="_blank">Télécharger PDF</a>
            </p>

            <div class="metadata" aria-hidden="false">
                <div class="left">
                    <?php if (!empty($auteur)): ?>Auteur : <strong><?= htmlspecialchars($auteur) ?></strong><?php endif; ?>
                </div>
                <div class="right">
                    <?php if (!empty($formattedDate)): ?>Dernière modification : <?= htmlspecialchars($formattedDate) ?><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>