<?php
/**
 * generer_pdf_tcpdf.php (version corrigée)
 *
 * - Remplacement des placeholders [NOM DE L'ASSOCIATION], [SITE_TITLE], [SITE_SUBTITLE], [SLOGAN/SOUS-TITRE]
 * - Conversion d'images header/footer via $adapter->processHtmlImages()
 * - Saut de page garanti via <pagebreak />
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/TCPDFAdapter.php';

// Parsedown si présent
$parsedown_available = false;
$parsedown_path = __DIR__ . '/../lib/Parsedown.php';
if (file_exists($parsedown_path)) {
    require_once $parsedown_path;
    if (class_exists('Parsedown')) $parsedown_available = true;
}

function markdown_to_html_fallback($text) {
    if (empty($text)) return '';
    $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = nl2br($text);
    return $text;
}

function image_path_to_imgtag($path, $attr = '') {
    if (empty($path)) {
        return '<div style="width:100px;height:30px;background:#f1f1f1;color:#777;text-align:center;line-height:30px;">Logo</div>';
    }
    if (preg_match('#^https?://#i', $path)) {
        return '<img src="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '"' . $attr . ' />';
    }
    $candidates = [
        $path,
        __DIR__ . '/../../' . ltrim($path, '/'),
        __DIR__ . '/../' . ltrim($path, '/'),
        __DIR__ . '/../../www/' . ltrim($path, '/'),
    ];
    foreach ($candidates as $p) {
        if (file_exists($p) && is_file($p) && filesize($p) > 0) {
            $mime = @mime_content_type($p) ?: 'application/octet-stream';
            $data = base64_encode(file_get_contents($p));
            // ajouter style pour éviter déformation (max côté 4cm)
            return '<img src="data:' . $mime . ';base64,' . $data . '" style="max-width:4cm;max-height:4cm;width:auto;height:auto;display:block;" />';
        }
    }
    return '<div style="width:100px;height:30px;background:#f9f9f9;color:#999;text-align:center;line-height:30px;border:1px solid #eee;">Logo introuvable</div>';
}

// ========== CONFIG ==========
$dbPath = null;
// UTILISER ici votre méthode sécurisée pour récupérer le chemin BDD (voir get_secure_db_path en exemple)
try {
    // replace this with your secured approach; temporary fallback:
    $dbPath = __DIR__ . '/../../data/portraits.sqlite';
    if (!file_exists($dbPath)) throw new Exception("BDD introuvable");
} catch (Exception $e) {
    die('<h2 style="color:red;">Erreur BDD: ' . htmlspecialchars($e->getMessage()) . '</h2>');
}

$TABLE_NAME = 'personnages';
$ID_PARAM = $_GET['id'] ?? null;
$FICHE_ID = ($ID_PARAM !== null) ? filter_var($ID_PARAM, FILTER_VALIDATE_INT) : false;
if ($FICHE_ID === false || $FICHE_ID <= 0) {
    die('<h2 style="color:red;">Erreur : ID manquant ou invalide.</h2>');
}

$MODELE_PAGE1 = __DIR__ . '/modele_page1.html';
$MODELE_OVERFLOW = __DIR__ . '/modele_overflow.html';
$FOOTER_HTML = __DIR__ . '/footer.html';
$HEADER_HTML = __DIR__ . '/header_multipage.html';
$STYLES_CSS = __DIR__ . '/styles_pdf.css';

$required = [$dbPath, $MODELE_PAGE1, $FOOTER_HTML, $HEADER_HTML, $STYLES_CSS];
foreach ($required as $f) {
    if (!file_exists($f)) {
        die("<h2 style='color:red;'>Fichier requis introuvable : " . htmlspecialchars($f) . "</h2>");
    }
}

// ========== LECTURE BDD ==========
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("SELECT * FROM {$TABLE_NAME} WHERE ID_fiche = :id");
    $stmt->execute([':id' => $FICHE_ID]);
    $fiche = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fiche) die("<h2>Fiche ID {$FICHE_ID} non trouvée.</h2>");

    $stmtc = $db->prepare("SELECT * FROM configuration WHERE id = 1");
    $stmtc->execute();
    $config = $stmtc->fetch(PDO::FETCH_ASSOC);
    if (!$config) {
        $config = [
            'association_name' => 'Association d\'Histoire Sociale',
            'association_address' => '123 Rue de l\'Histoire, 75000 Paris',
            'logo_path' => null,
            'site_title' => 'Portraits des Militants',
            'site_subtitle' => 'Explorez les parcours de ceux qui ont marqué l\'histoire.'
        ];
    }
} catch (PDOException $e) {
    die("<h2>Erreur BDD: " . htmlspecialchars($e->getMessage()) . "</h2>");
}

// ========== Préparation contenu ==========
$nom = $fiche['Nom'] ?? 'Fiche sans nom';
$details_raw = $fiche['Details'] ?? '';
$sources_raw = $fiche['Sources'] ?? '';

if ($parsedown_available) {
    $pd = new Parsedown();
    $details_html = $pd->text($details_raw);
    $sources_html = $pd->text($sources_raw);
} else {
    $details_html = markdown_to_html_fallback($details_raw);
    $sources_html = markdown_to_html_fallback($sources_raw);
}

$date_modif = !empty($fiche['derniere_modif']) ? date('d/m/Y', strtotime($fiche['derniere_modif'])) : '';
$photo_field = trim($fiche['Photo'] ?? '');

if ($photo_field) {
    // mettre une img sans attributs de taille pour que l'adapter applique le style de max côté
    $photo_html = '<img src="' . htmlspecialchars($photo_field, ENT_QUOTES, 'UTF-8') . '" class="bandeau-photo-img" alt="Photo">';
} else {
    $photo_html = '<div class="bandeau-photo-placeholder">Pas de photo</div>';
}

// découpage : si long, on insère un <pagebreak /> entre page1 et overflow
$page1_content = $details_html;
$html_overflow = '';
if (strlen(strip_tags($details_html)) > 1500) {
    // trouver une coupure lisible au point/fin de paragraphe si possible
    $cut = 1400;
    $snippet = substr($details_html, 0, $cut);
    if (preg_match('/.*[.!?]<\\/(p|h[1-6]|li)>/s', $snippet, $m)) {
        $page1_content = substr($details_html, 0, strlen($m[0]));
        $html_overflow = substr($details_html, strlen($page1_content));
    } else {
        // fallback simple
        $page1_content = substr($details_html, 0, $cut) . '<p class="suite-indication">(Suite page suivante)</p>';
        $html_overflow = substr($details_html, $cut);
    }
}

// remplir template page1
$page1_tpl = file_get_contents($MODELE_PAGE1);
$repl = [
    '[Nom]' => htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'),
    '[Donnees_genealogiques]' => htmlspecialchars($fiche['Donnees_genealogiques'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[Metier]' => htmlspecialchars($fiche['Metier'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[Engagements]' => htmlspecialchars($fiche['Engagements'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[Sources]' => $sources_html,
    '[auteur]' => htmlspecialchars($fiche['auteur'] ?? '—', ENT_QUOTES, 'UTF-8'),
    '[derniere_modif]' => $date_modif,
    '[date_du_jour]' => date('d/m/Y'),
    '[Photo]' => $photo_html,
    '[DetailsPage1]' => $page1_content,
    '[NOM DE L\'ASSOCIATION]' => htmlspecialchars($config['association_name'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[SITE_TITLE]' => htmlspecialchars($config['site_title'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[SITE_SUBTITLE]' => htmlspecialchars($config['site_subtitle'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[SLOGAN/SOUS-TITRE]' => htmlspecialchars($config['site_subtitle'] ?? '', ENT_QUOTES, 'UTF-8')
];
$html_page1 = str_replace(array_keys($repl), array_values($repl), $page1_tpl);

// overflow template
if (!empty($html_overflow)) {
    $overflow_tpl = file_get_contents($MODELE_OVERFLOW);
    $html_overflow = str_replace('[DetailsOverflow]', $html_overflow, $overflow_tpl);
}

// assembler
$full_html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>";
$full_html .= $html_page1;
if (!empty($html_overflow)) {
    $full_html .= '<pagebreak />' . $html_overflow;
}
$full_html .= "</body></html>";

// charger CSS
$cssContent = file_get_contents($STYLES_CSS);

// --- header/footer préparation et remplacement placeholders ---
$headerHtml = file_get_contents($HEADER_HTML);

// construire logo_html
$logo_html = '';
if (!empty($config['logo_path'])) {
    $logo_html = image_path_to_imgtag($config['logo_path'], '');
} else {
    $logo_html = '<div class="header-logo-placeholder">Logo</div>';
}

// remplacer placeholders multiples
$headerHtml = str_replace('[LOGO]', $logo_html, $headerHtml);
$headerHtml = str_replace('[NOM DE L\'ASSOCIATION]', htmlspecialchars($config['association_name'] ?? '', ENT_QUOTES, 'UTF-8'), $headerHtml);
$headerHtml = str_replace('[SITE_TITLE]', htmlspecialchars($config['site_title'] ?? '', ENT_QUOTES, 'UTF-8'), $headerHtml);
$headerHtml = str_replace('[SITE_SUBTITLE]', htmlspecialchars($config['site_subtitle'] ?? '', ENT_QUOTES, 'UTF-8'), $headerHtml);
$headerHtml = str_replace('[SLOGAN/SOUS-TITRE]', htmlspecialchars($config['site_subtitle'] ?? '', ENT_QUOTES, 'UTF-8'), $headerHtml);

// footer
$footerHtml = file_get_contents($FOOTER_HTML);
$bibliographie = 'Biographie : « ' . $nom . ' »';
$copyright_line = '© ' . ($config['association_name'] ?? 'Association') . ', ' . date('Y') . ', ' . ($config['association_address'] ?? '') . ', Tous droits réservés.';
$footerHtml = str_replace('[BIBLIOGRAPHIE]', $bibliographie, $footerHtml);
$footerHtml = str_replace('[COPYRIGHT_LINE]', $copyright_line, $footerHtml);

// ===== Génération via adapter =====
$adapter = new TCPDFAdapter();

// s'assurer que header/footer ont leurs images encodées
$headerHtml = $adapter->processHtmlImages($headerHtml);
$footerHtml = $adapter->processHtmlImages($footerHtml);

// générer
$ok = $adapter->generateFromHtml($full_html, null, $headerHtml, $footerHtml, $cssContent);

if (!$ok) {
    die("<h2 style='color:red;'>Échec de génération du PDF.</h2>");
}
exit;