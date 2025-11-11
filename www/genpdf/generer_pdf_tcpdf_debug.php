<?php
/**
 * generer_pdf_tcpdf_debug.php
 * Script de debug pour diagnostiquer pourquoi generer_pdf_tcpdf.php ne produit rien.
 *
 * Usage: /www/genpdf/generer_pdf_tcpdf_debug.php?id=NNN
 *
 * Ce script :
 * - inclut bootstrap, repository et adapter comme dans la version finale
 * - vérifie l'existence & permissions de la BDD
 * - tente de lire la fiche demandée
 * - vérifie templates et CSS
 * - construit header/footer transformés et les affiche
 * - génère un PDF de test dans www/genpdf/tmp/ et propose un lien de téléchargement
 *
 * IMPORTANT: retirez ce fichier après debug (ou laissez-le sécurisé).
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$logFile = __DIR__ . '/debug_log.txt';
function dbg($msg) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
    echo '<pre>' . htmlspecialchars($msg) . '</pre>';
}

// Start
dbg("==== START DEBUG generer_pdf_tcpdf_debug.php ====");

// Parametrage ID
$ID_PARAM = $_GET['id'] ?? null;
$FICHE_ID = ($ID_PARAM !== null) ? filter_var($ID_PARAM, FILTER_VALIDATE_INT) : false;
if ($FICHE_ID === false || $FICHE_ID <= 0) {
    dbg("ID manquant ou invalide. Appel avec ?id=NNN requis.");
    exit;
}
dbg("ID reçu : " . $FICHE_ID);

// 1) Inclusion bootstrap
$bootstrapPath = __DIR__ . '/../bootstrap.php';
if (!file_exists($bootstrapPath)) {
    dbg("ERREUR: bootstrap.php introuvable à: $bootstrapPath");
    exit;
}
require_once $bootstrapPath;
dbg("bootstrap.php inclus.");

// 2) Inclusion des fichiers requis (repository + adapter)
$repoPath = __DIR__ . '/FicheRepository.php';
$adapterPath = __DIR__ . '/TCPDFAdapter.php';

if (!file_exists($repoPath)) {
    dbg("ERREUR: FicheRepository.php introuvable à: $repoPath");
    exit;
}
require_once $repoPath;
dbg("FicheRepository.php inclus.");

if (!file_exists($adapterPath)) {
    dbg("ERREUR: TCPDFAdapter.php introuvable à: $adapterPath");
    exit;
}
require_once $adapterPath;
dbg("TCPDFAdapter.php inclus.");

// 3) Résolution chemin BDD via bootstrap helper (getDatabasePath)
$dbPath = null;
try {
    if (function_exists('getDatabasePath')) {
        $dbPath = getDatabasePath();
        dbg("getDatabasePath() renvoie: $dbPath");
    } else {
        dbg("getDatabasePath() indisponible dans bootstrap.php");
    }
} catch (Exception $e) {
    dbg("Exception lors appel getDatabasePath(): " . $e->getMessage());
}

// 4) Vérifier existence & permissions du fichier BDD
if (empty($dbPath) || !file_exists($dbPath)) {
    dbg("ERREUR: fichier BDD introuvable à: " . var_export($dbPath, true));
    dbg("Vérifiez la config locale (config.local.php) ou le placement du dossier data/");
    exit;
}
dbg("BDD trouvée. Taille: " . filesize($dbPath) . " bytes. is_readable: " . (is_readable($dbPath) ? 'yes' : 'no') . ", is_writable: " . (is_writable($dbPath) ? 'yes' : 'no'));
if (!is_readable($dbPath)) { dbg("ERREUR: Le fichier BDD n'est pas lisible par PHP."); exit; }

// 5) Instancier repository et récupérer fiche
try {
    $repo = new FicheRepository($dbPath);
    dbg("FicheRepository instancié.");
} catch (Exception $e) {
    dbg("Exception lors instanciation FicheRepository: " . $e->getMessage());
    exit;
}

try {
    $fiche = $repo->getFicheById($FICHE_ID);
    if (!$fiche) {
        dbg("Fiche non trouvée en base. ID: $FICHE_ID");
        // diagnostic supplémentaire : compter les fiches accessibles
        try {
            $pdo = $repo->getPdo();
            $count = $pdo->query("SELECT COUNT(*) FROM personnages")->fetchColumn();
            dbg("Nombre total de fiches table 'personnages' : " . $count);
        } catch (Exception $ex) {
            dbg("Impossible de compter les fiches: " . $ex->getMessage());
        }
        exit;
    }
    dbg("Fiche lue OK. Champs principaux:");
    $show = ['ID_fiche','Nom','Photo','derniere_modif','Donnees_genealogiques','Metier','Engagements'];
    foreach ($show as $k) {
        dbg("  $k => " . (isset($fiche[$k]) ? substr($fiche[$k], 0, 200) : '(absent)'));
    }
} catch (Exception $e) {
    dbg("Exception lors lecture fiche: " . $e->getMessage());
    exit;
}

// 6) Vérifier templates & CSS
$templates = [
    'MODELE_PAGE1' => __DIR__ . '/modele_page1.html',
    'MODELE_OVERFLOW' => __DIR__ . '/modele_overflow.html',
    'HEADER' => __DIR__ . '/header_multipage.html',
    'FOOTER' => __DIR__ . '/footer.html',
    'CSS' => __DIR__ . '/styles_pdf.css'
];
foreach ($templates as $name => $path) {
    if (!file_exists($path)) {
        dbg("ERREUR: template manquant: $name -> $path");
        exit;
    }
    dbg("$name trouvé: $path (taille " . filesize($path) . " bytes)");
}

// 7) Construire HTML (comme dans generer_pdf_tcpdf.php) et afficher un extrait
// Chargement raw
$page1_tpl = file_get_contents($templates['MODELE_PAGE1']);
$overflow_tpl = file_get_contents($templates['MODELE_OVERFLOW']);
$headerTpl = file_get_contents($templates['HEADER']);
$footerTpl = file_get_contents($templates['FOOTER']);
$cssContent = file_get_contents($templates['CSS']);

dbg("Templates et CSS chargés. CSS length: " . strlen($cssContent));

// Convertir Markdown
$parsedown_path = __DIR__ . '/../lib/Parsedown.php';
$parsedown_available = false;
if (file_exists($parsedown_path)) {
    require_once $parsedown_path;
    $parsedown_available = class_exists('Parsedown');
    dbg("Parsedown present: " . ($parsedown_available ? 'yes' : 'no'));
} else {
    dbg("Parsedown absent (ok, on utilisera fallback).");
}

// Apply simple conversion
if ($parsedown_available) {
    $pd = new Parsedown();
    $details_html = $pd->text($fiche['Details'] ?? '');
    $sources_html = $pd->text($fiche['Sources'] ?? '');
} else {
    $details_raw = $fiche['Details'] ?? '';
    $details_html = nl2br(htmlspecialchars($details_raw, ENT_NOQUOTES, 'UTF-8'));
    $sources_html = nl2br(htmlspecialchars($fiche['Sources'] ?? '', ENT_NOQUOTES, 'UTF-8'));
}
dbg("Conversion Markdown effectuée. details_html length: " . strlen($details_html));

$photo_field = trim($fiche['Photo'] ?? '');
$photo_html = $photo_field ? '<img src="' . htmlspecialchars($photo_field, ENT_QUOTES, 'UTF-8') . '" class="bandeau-photo-img" />' : '<div class="bandeau-photo-placeholder">Pas de photo</div>';

// remplir page1
$repl = [
    '[Nom]' => htmlspecialchars($fiche['Nom'] ?? 'Fiche sans nom', ENT_QUOTES, 'UTF-8'),
    '[Donnees_genealogiques]' => htmlspecialchars($fiche['Donnees_genealogiques'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[Metier]' => htmlspecialchars($fiche['Metier'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[Engagements]' => htmlspecialchars($fiche['Engagements'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[Sources]' => $sources_html,
    '[auteur]' => htmlspecialchars($fiche['auteur'] ?? '—', ENT_QUOTES, 'UTF-8'),
    '[derniere_modif]' => (!empty($fiche['derniere_modif']) ? date('d/m/Y', strtotime($fiche['derniere_modif'])) : ''),
    '[date_du_jour]' => date('d/m/Y'),
    '[Photo]' => $photo_html,
    '[DetailsPage1]' => $details_html,
    '[NOM DE L\'ASSOCIATION]' => htmlspecialchars($repo->getConfig()['association_name'] ?? 'Association', ENT_QUOTES, 'UTF-8'),
    '[SLOGAN/SOUS-TITRE]' => htmlspecialchars($repo->getConfig()['site_subtitle'] ?? '', ENT_QUOTES, 'UTF-8')
];
$html_page1 = str_replace(array_keys($repl), array_values($repl), $page1_tpl);
dbg("HTML page1 généré. Extrait (first 800 chars):\n" . substr(strip_tags($html_page1), 0, 800));

// header/footer placeholders replacement
$config = $repo->getConfig();
$logoHtml = '';
if (!empty($config['logo_path'])) {
    // tentative d'embed local (même logique que dans adapter)
    $candidate = __DIR__ . '/../' . ltrim($config['logo_path'], '/');
    if (file_exists($candidate)) {
        $mime = @mime_content_type($candidate) ?: 'image/png';
        $data = base64_encode(file_get_contents($candidate));
        $logoHtml = '<img src="data:' . $mime . ';base64,' . $data . '" class="header-logo-img" />';
        dbg("Logo local trouvé et encodé: $candidate");
    } else {
        dbg("Logo local non trouvé au chemin candidat: $candidate");
        $logoHtml = '<div class="header-logo-placeholder">Logo</div>';
    }
} else {
    dbg("Aucun logo_path dans config.");
    $logoHtml = '<div class="header-logo-placeholder">Logo</div>';
}
$headerHtml = str_replace('[LOGO]', $logoHtml, $headerTpl);

$biblio = 'Biographie : « ' . ($fiche['Nom'] ?? '') . ' »';
$copyright = '© ' . ($config['association_name'] ?? '') . ', ' . date('Y') . ', ' . ($config['association_address'] ?? '');
$footerHtml = str_replace('[BIBLIOGRAPHIE]', $biblio, $footerTpl);
$footerHtml = str_replace('[COPYRIGHT_LINE]', $copyright, $footerHtml);

dbg("Header HTML (excerpt):\n" . substr(strip_tags($headerHtml), 0, 500));
dbg("Footer HTML (excerpt):\n" . substr(strip_tags($footerHtml), 0, 500));

// 8) Tentative de génération PDF dans tmp/ (fichier sur disque)
$tmpDir = __DIR__ . '/tmp';
if (!file_exists($tmpDir)) {
    mkdir($tmpDir, 0755, true);
    dbg("tmp/ créé: $tmpDir");
}
$outFile = $tmpDir . '/fiche_' . $FICHE_ID . '_' . time() . '.pdf';

// assembler full html (page1 + overflow)
$full_html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>";
$full_html .= $html_page1;
if (!empty($overflow_tpl)) {
    $full_html .= '<pagebreak />' . $overflow_tpl;
}
$full_html .= "</body></html>";

dbg("Appel adapter->generateFromHtml() pour écrire dans: $outFile");

$adapter = new TCPDFAdapter();

try {
    $ok = $adapter->generateFromHtml($full_html, $outFile, $headerHtml, $footerHtml, $cssContent, false);
    dbg("Adapter generateFromHtml() retourné: " . ($ok ? 'true' : 'false'));
    if ($ok && file_exists($outFile)) {
        dbg("Fichier PDF généré: $outFile (taille: " . filesize($outFile) . " bytes)");
        // construire URL accessible au navigateur
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // construire base url (represente le dossier où est ce script)
        $scriptDir = rtrim(dirname($_SERVER['REQUEST_URI']), '/');
        $url = $protocol . '://' . $host . $scriptDir . '/tmp/' . basename($outFile);
        dbg("Télécharger le PDF depuis cette URL si accessible : " . $url);
        echo "<p><a href=\"" . htmlspecialchars($url) . "\" target=\"_blank\">Télécharger le PDF généré</a></p>";
    } else {
        dbg("ERREUR: le fichier PDF n'a pas été généré ou est vide.");
    }
} catch (Exception $e) {
    dbg("Exception lors de la génération PDF: " . $e->getMessage());
    dbg("Trace: " . $e->getTraceAsString());
}

dbg("==== END DEBUG ====");
?>