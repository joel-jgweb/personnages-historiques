<?php
// fiche.php — Affiche le détail d'une fiche
// Mise à jour : affiche uniquement "Iconographie" + miniatures et "Documents" + tableau description / bouton Télécharger
//             : pour les fichiers associés, récupère la description depuis la table gesdoc.nom_fichier si présente

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

// ----------------- Helpers pour les fichiers associés -----------------
function parseFilesFromField(?string $fieldValue): array {
    $result = [];
    if (!$fieldValue) return $result;
    if (strpos($fieldValue, '|') !== false) {
        $lines = preg_split('/\r\n|\r|\n/', $fieldValue);
        foreach ($lines as $line) {
            if (preg_match('/\(([^)]+)\)/', $line, $m)) {
                $path = trim($m[1]);
                if ($path !== '') $result[] = basename($path);
            }
        }
    } else {
        $lines = preg_split('/\r\n|\r|\n/', trim($fieldValue));
        foreach ($lines as $l) {
            $l = trim($l);
            if ($l !== '') $result[] = basename($l);
        }
    }
    $result = array_map('trim', $result);
    $result = array_filter($result, function($v){ return $v !== ''; });
    $result = array_values(array_unique($result));
    return $result;
}

/**
 * Parse les entrées Documents et retourne un tableau d'éléments ['desc' => ..., 'file' => ...].
 * Amélioration : si la colonne description (col1) est vide, on tente d'extraire le libellé du lien Markdown
 * présent dans la colonne 2 (ex. [Mon document](../data/docs/fichier.pdf)). On ne met le nom de fichier
 * qu'en dernier recours.
 */
function parseDocumentEntries(?string $fieldValue): array {
    $entries = [];
    if (!$fieldValue) return $entries;

    if (strpos($fieldValue, '|') !== false) {
        $lines = preg_split('/\r\n|\r|\n/', $fieldValue);
        foreach ($lines as $line) {
            if (preg_match('/^\|\s*(.*?)\s*\|\s*(.*?)\s*\|$/', $line, $m)) {
                $desc = trim($m[1]);
                $cell2 = trim($m[2]);
                $file = '';
                $linkLabel = '';

                // Si la cellule 2 contient un lien Markdown [label](url)
                if (preg_match('/\[(.*?)\]\(([^)]+)\)/', $cell2, $mm)) {
                    $linkLabel = trim($mm[1]);
                    $file = basename(trim($mm[2]));
                } else {
                    // sinon, si la cellule 2 contient une URL ou un nom de fichier
                    if ($cell2 !== '') {
                        $file = basename($cell2);
                        $linkLabel = $cell2;
                    }
                }

                // Choix de la description : priorité colonne 1, puis label du lien (sauf si c'est juste "Télécharger"), puis nom de fichier
                $chosenDesc = '';
                if ($desc !== '') {
                    $chosenDesc = $desc;
                } elseif ($linkLabel !== '') {
                    $low = mb_strtolower($linkLabel);
                    if (!in_array($low, ['télécharger', 'telecharger', 'download', 'downloader'])) {
                        $chosenDesc = $linkLabel;
                    } else {
                        $chosenDesc = $file ? pathinfo($file, PATHINFO_FILENAME) : $linkLabel;
                    }
                } elseif ($file !== '') {
                    $chosenDesc = pathinfo($file, PATHINFO_FILENAME);
                } else {
                    continue;
                }

                $entries[] = ['desc' => $chosenDesc, 'file' => $file];
            }
        }
    } else {
        // simple liste : chaque ligne est un nom de fichier
        $lines = preg_split('/\r\n|\r|\n/', trim($fieldValue));
        foreach ($lines as $l) {
            $l = trim($l);
            if ($l === '') continue;
            $bn = basename($l);
            $entries[] = ['desc' => pathinfo($bn, PATHINFO_FILENAME), 'file' => $bn];
        }
    }

    // normalisation : trim, unique by file (keep first desc)
    $map = [];
    foreach ($entries as $e) {
        $f = trim($e['file']);
        if ($f === '') continue;
        if (!isset($map[$f])) $map[$f] = ['desc' => trim($e['desc']), 'file' => $f];
    }
    return array_values($map);
}

// Construire base URL vers acces_docs.php en tenant compte du chemin (fonctionne depuis /www/)
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = ($scriptDir === '/' || $scriptDir === '\\') ? '' : $scriptDir;
$accesDocsBase = $scriptDir . '/acces_docs.php';

// Extraire fichiers
$iconoFiles = parseFilesFromField($fiche['Iconographie'] ?? '');
$docEntries = parseDocumentEntries($fiche['Documents'] ?? '');

// ----------------- Photo handling (use acces_docs.php for local files) -----------------
$photoSrc = '';
$photoHref = '';
$hasPhoto = false;
$photoVal = trim((string)($fiche['Photo'] ?? ''));

if ($photoVal !== '') {
    if (preg_match('#^https?://#i', $photoVal)) {
        $photoHref = $photoVal;
        $photoSrc = $photoVal;
        $hasPhoto = true;
    } else {
        $bn = basename($photoVal);
        if (file_exists($docsDir . $bn)) {
            $photoHref = $accesDocsBase . '?f=' . rawurlencode($bn);
            $photoSrc = $photoHref . '&thumb=220';
            $hasPhoto = true;
        } else {
            $photoHref = $photoVal;
            $photoSrc = $photoVal;
            $hasPhoto = true;
        }
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

        /* Top items block */
        .top-items { display:block; margin-top:0.6rem; color:#666; font-style:italic; font-size:1rem; max-width:100%; }
        .top-item { display:block; margin: 6px 0; white-space:normal; color: #666; }

        .no-photo .header { justify-content:center; }
        .no-photo .main-info { text-align:center; }

        @media (max-width:600px) {
            .header { flex-direction:column; }
            .photo { order:2; margin-top:1rem; width:100%; height:auto; max-height:260px; object-fit:cover; }
            .main-info { text-align:center; }
        }

        .metadata { margin-top:1.25rem; font-size:0.9rem; color:#666; border-top:1px dashed #eaeaea; padding-top:0.75rem; display:flex; gap:1rem; flex-wrap:wrap; justify-content:space-between; align-items:center; }

        /* Thumbnails */
        .file-thumbs { margin-top:1.25rem; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-start; }
        .thumb-card { width:120px; text-align:center; font-size:0.85rem; color:#333; }
        .thumb-card .thumb { width:100%; height:80px; display:block; margin:0 auto 6px; object-fit:cover; border-radius:6px; border:1px solid #eaeaea; background:#fff; }
        .thumb-card .icon { display:inline-block; width:60px; height:60px; line-height:60px; border:1px solid #ddd; border-radius:6px; background:#f8f9fa; font-size:28px; text-decoration:none; color:#333; }
        .section-title { margin-top:1rem; margin-bottom:6px; }

        /* Documents table: two columns, no outer margins */
        .doc-table { width:100%; border-collapse:collapse; margin:0; }
        .doc-table td { padding:6px 8px; vertical-align:middle; }
        .doc-table td:first-child { border-right:0; }
        .doc-table tr + tr td { border-top:1px dashed #eee; }
        .doc-desc { color:#333; }
        .doc-download a { display:inline-block; padding:6px 10px; background:#007bff; color:#fff; border-radius:6px; text-decoration:none; font-size:0.9rem; }
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
                    <a href="<?= htmlspecialchars($photoHref) ?>" target="_blank" rel="noopener"><img class="photo" src="<?= htmlspecialchars($photoSrc) ?>" alt="Photo de <?= htmlspecialchars($fiche['Nom']) ?>"></a>
                <?php endif; ?>
            </div>

            <section style="margin-top:1rem">
                <h2>Détails</h2>
                <div><?= $detailsHtml ?></div>
            </section>

            <?php if (!empty($sourcesHtml)): ?>
                <section style="margin-top:1rem">
                    <h3>Sources</h3>
                    <div class="sources"><?= $sourcesHtml ?></div>
                </section>
            <?php endif; ?>

            <!-- Iconographie: only title + thumbnails -->
            <?php if (!empty($iconoFiles)): ?>
                <section style="margin-top:1rem" aria-label="Iconographie">
                    <h3>Iconographie</h3>
                    <div class="file-thumbs" role="list">
                        <?php foreach ($iconoFiles as $fn):
                            $full = $accesDocsBase . '?f=' . rawurlencode($fn);
                            $thumb = $full . '&thumb=160';
                            $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
                        ?>
                            <div class="thumb-card" role="listitem">
                                <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                                    <a href="<?= htmlspecialchars($full) ?>" target="_blank" rel="noopener">
                                        <img class="thumb" src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($fn) ?>">
                                    </a>
                                <?php else: ?>
                                    <a class="icon" href="<?= htmlspecialchars($full) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(pathinfo($fn, PATHINFO_EXTENSION)) ?></a>
                                <?php endif; ?>
                                <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($fn) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Documents: title + two-column table (description | download) -->
            <?php if (!empty($docEntries)): ?>
                <section style="margin-top:1rem" aria-label="Documents">
                    <h3>Documents</h3>
                    <table class="doc-table" role="table" aria-label="Liste des documents">
                        <tbody>
                        <?php
                        // préparer statement pour récupérer description depuis la table gesdoc
                        $descStmt = $pdo->prepare("SELECT description FROM gesdoc WHERE nom_fichier = ? LIMIT 1");
                        foreach ($docEntries as $entry):
                            $file = $entry['file'];
                            $dbDesc = '';
                            if ($file) {
                                try {
                                    $descStmt->execute([$file]);
                                    $dbDesc = $descStmt->fetchColumn();
                                } catch (Exception $e) {
                                    $dbDesc = '';
                                }
                            }
                            $descToShow = ($dbDesc !== false && trim((string)$dbDesc) !== '') ? $dbDesc : $entry['desc'];
                            $descHtml = inline_markdown_safe($descToShow);
                            $download = $accesDocsBase . '?f=' . rawurlencode($file) . '&download=1';
                        ?>
                            <tr role="row">
                                <td class="doc-desc" role="cell"><?= $descHtml ?></td>
                                <td class="doc-download" role="cell" style="white-space:nowrap;">
                                    <a href="<?= htmlspecialchars($download) ?>" target="_blank" rel="noopener">Télécharger le fichier</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            <?php endif; ?>

            <p style="margin-top:1.5rem">
                <a class="btn" href="index.php">← Retour</a>
       <!--         <a class="btn" href="genpdf/generer_pdf_tcpdf.php?id=<?= (int)$fiche['ID_fiche'] ?>" target="_blank">Télécharger PDF</a> -->
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