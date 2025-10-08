<?php 
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL); 

// ========================================================================= 
// FONCTION DE CONVERSION MARKDOWN → HTML (sans liens)
// ========================================================================= 
function markdown_to_html($text) {
    if (empty($text)) return '';

    // Supprimer les liens Markdown : [texte](url) → texte
    $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text);

    // Échappement HTML
    $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

    // Retours à la ligne
    $text = preg_replace('/\\\\n/', "\n", $text);
    $text = nl2br($text);

    // Titres
    $text = preg_replace('/^#{1,6}\s*(.+)$/m', '<h3>$1</h3>', $text);

    // Gras
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);

    // Italique
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);

    // Listes
    $text = preg_replace('/^-\s+(.+)$/m', '<ul><li>$1</li></ul>', $text);
    $text = preg_replace('/(<\/ul>)(\s*<ul>)/', '', $text);

    return $text;
}

// ========================================================================= 
// CONFIGURATION
// ========================================================================= 
$DB_PATH = __DIR__ . '/../../data/portraits.sqlite'; 
$TABLE_NAME = 'personnages'; 
$WKHTMLTOPDF_PATH = 'wkhtmltopdf';
$FICHE_ID = $_GET['id'] ?? die('Erreur: ID manquant dans l\'URL.');

$FOOTER_HTML_PATH = __DIR__ . '/footer.html'; 
$HEADER_MULTI_HTML_PATH = __DIR__ . '/header_multipage.html'; 
$STYLES_CSS = __DIR__ . '/styles_pdf.css';
$MODELE_PAGE1_PATH = __DIR__ . '/modele_page1.html';
$MODELE_OVERFLOW_PATH = __DIR__ . '/modele_overflow.html';

// Vérifications
$required_files = [
    $DB_PATH => 'Base de données (portraits.sqlite)',
    $FOOTER_HTML_PATH => 'footer.html',
    $HEADER_MULTI_HTML_PATH => 'header_multipage.html',
    $MODELE_PAGE1_PATH => 'modele_page1.html',
    $MODELE_OVERFLOW_PATH => 'modele_overflow.html',
    $STYLES_CSS => 'styles_pdf.css'
];

foreach ($required_files as $path => $name) {
    if (!file_exists($path)) {
        die("<h2 style='color:red;'>ERREUR CRITIQUE : {$name} introuvable à :<br><code>" . htmlspecialchars($path) . "</code></h2>");
    }
}

// ========================================================================= 
// ACCÈS AUX DONNÉES
// ========================================================================= 
try { 
    $db = new PDO('sqlite:' . $DB_PATH); 
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
    $stmt = $db->prepare("SELECT * FROM {$TABLE_NAME} WHERE ID_fiche = :id"); 
    $stmt->execute([':id' => $FICHE_ID]); 
    $fiche_data = $stmt->fetch(PDO::FETCH_ASSOC); 
    if (!$fiche_data) die("Aucune fiche trouvée avec ID_fiche = {$FICHE_ID}.");
} catch (PDOException $e) { 
    die("Erreur BDD: " . htmlspecialchars($e->getMessage())); 
} 

$nom_fiche = $fiche_data['Nom'] ?? 'Fiche sans nom';
$details_markdown = $fiche_data['Details'] ?? '';
$details_html = markdown_to_html($details_markdown);

$date_modif = !empty($fiche_data['derniere_modif']) 
    ? date('d/m/Y', strtotime($fiche_data['derniere_modif'])) 
    : '';

// Gestion de la photo
$photo_url = trim($fiche_data['Photo'] ?? '');
if ($photo_url) {
    $photo_html = '<img src="' . htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') . '" alt="Photo" class="bandeau-photo-img">';
} else {
    $photo_html = '<div class="bandeau-photo-placeholder">Pas de photo</div>';
}

// ========================================================================= 
// CHARGEMENT DE LA CONFIGURATION DU SITE
// ========================================================================= 
try {
    $stmt_config = $db->prepare("SELECT * FROM configuration WHERE id = 1");
    $stmt_config->execute();
    $config = $stmt_config->fetch(PDO::FETCH_ASSOC);
    
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
    $config = [
        'association_name' => 'Association d\'Histoire Sociale',
        'association_address' => '123 Rue de l\'Histoire, 75000 Paris',
        'logo_path' => null,
        'site_title' => 'Portraits des Militants',
        'site_subtitle' => 'Explorez les parcours de ceux qui ont marqué l\'histoire.'
    ];
}

// ========================================================================= 
// DÉCOUPAGE DU TEXTE POUR PAGE 1 (~25 lignes)
// ========================================================================= 
$page1_content = $details_html;
$overflow_content = '';

if (strlen($details_html) > 1200) {
    $cutoff = 1200;
    $snippet = substr($details_html, 0, $cutoff);
    
    if (preg_match('/.*[.!?]<\/(p|h3|li)>/s', $snippet, $matches)) {
        $page1_content = $matches[0];
        $overflow_content = substr($details_html, strlen($page1_content));
    } else {
        $last_tag = strrpos($snippet, '<');
        if ($last_tag !== false && $last_tag > 1000) {
            $page1_content = substr($details_html, 0, $last_tag);
            $overflow_content = substr($details_html, $last_tag);
        } else {
            $page1_content = $snippet;
            $overflow_content = substr($details_html, $cutoff);
        }
    }
    $page1_content .= '<p class="suite-indication">(Suite page suivante)</p>';
}

// ========================================================================= 
// GÉNÉRATION HTML PAGE 1
// ========================================================================= 
$page1_template = file_get_contents($MODELE_PAGE1_PATH);
$replacements_page1 = [
    '[Nom]' => htmlspecialchars($nom_fiche, ENT_QUOTES, 'UTF-8'),
    '[Donnees_genealogiques]' => htmlspecialchars($fiche_data['Donnees_genealogiques'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[Metier]' => htmlspecialchars($fiche_data['Metier'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[Engagements]' => htmlspecialchars($fiche_data['Engagements'] ?? '', ENT_QUOTES, 'UTF-8'),
    '[Sources]' => markdown_to_html($fiche_data['Sources'] ?? ''),
    '[auteur]' => htmlspecialchars($fiche_data['auteur'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8'),
    '[derniere_modif]' => $date_modif,
    '[date_du_jour]' => date('d/m/Y'),
    '[Photo]' => $photo_html,
    '[DetailsPage1]' => $page1_content,
    '[NOM DE L\'ASSOCIATION]' => htmlspecialchars($config['association_name'], ENT_QUOTES, 'UTF-8'),
    '[SLOGAN/SOUS-TITRE]' => htmlspecialchars($config['site_subtitle'], ENT_QUOTES, 'UTF-8'),
];

$html_page1 = str_replace(array_keys($replacements_page1), array_values($replacements_page1), $page1_template);
$html_page1 = '<!DOCTYPE html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="' . $STYLES_CSS . '"></head><body>' . $html_page1 . '</body></html>';

// ========================================================================= 
// GÉNÉRATION HTML PAGES 2+
// ========================================================================= 
$html_overflow = '';
if (!empty($overflow_content)) {
    $overflow_template = file_get_contents($MODELE_OVERFLOW_PATH);
    $html_overflow = str_replace('[DetailsOverflow]', $overflow_content, $overflow_template);
    $html_overflow = '<!DOCTYPE html><html><head><meta charset="UTF-8"><link rel="stylesheet" href="' . $STYLES_CSS . '"></head><body>' . $html_overflow . '</body></html>';
}

// ========================================================================= 
// ÉCRITURE FICHIERS TEMPORAIRES
// ========================================================================= 
$temp_dir = '/tmp'; // ✅ Compatible avec Alternc
$suffix = time();

$file_page1 = "{$temp_dir}/page1_{$suffix}.html";
file_put_contents($file_page1, $html_page1) || die("Erreur: impossible d'écrire page1.html temporaire.");

// === GÉNÉRATION DE L'EN-TÊTE AVEC LOGO (via file://) ===
$header_template = file_get_contents($HEADER_MULTI_HTML_PATH);

$logo_html = '';
if (!empty($config['logo_path'])) {
    $logo_path = trim($config['logo_path']);
    
    if (preg_match('#^https?://#i', $logo_path)) {
        $logo_url = $logo_path;
    } else {
        $logo_abs_path = realpath(__DIR__ . '/..' . $logo_path);
        if ($logo_abs_path && file_exists($logo_abs_path)) {
            $logo_url = 'file://' . $logo_abs_path;
        } else {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $siteUrl = $protocol . '://' . $host;
            $logo_url = rtrim($siteUrl, '/') . '/' . ltrim($logo_path, '/');
        }
    }
    
    $logo_html = '<img src="' . htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') . '" alt="Logo" class="header-logo-img">';
} else {
    $logo_html = '<div class="header-logo-placeholder">Logo</div>';
}

$header_content = str_replace('[LOGO]', $logo_html, $header_template);
$header_file = $temp_dir . "/header_{$FICHE_ID}_{$suffix}.html";
if (file_put_contents($header_file, $header_content) === false) {
    die("<h2 style='color:red;'>ERREUR: Impossible d'écrire l'en-tête temporaire.</h2>");
}

// === GÉNÉRATION DU PIED DE PAGE TEMPORAIRE ===
$footer_template = file_get_contents($FOOTER_HTML_PATH);

// Ligne 1 : Biographie : « Nom »
$bibliographie = 'Biographie : « ' . $nom_fiche . ' »';

// Ligne 2 : © Association, année, adresse, Tous droits réservés.
$copyright_line = '© ' . $config['association_name'] . ', ' . date('Y') . ', ' . $config['association_address'] . ', Tous droits réservés.';

$footer_content = str_replace('[BIBLIOGRAPHIE]', $bibliographie, $footer_template);
$footer_content = str_replace('[COPYRIGHT_LINE]', $copyright_line, $footer_content);

$footer_file = $temp_dir . "/footer_{$FICHE_ID}_{$suffix}.html";
if (file_put_contents($footer_file, $footer_content) === false) {
    die("<h2 style='color:red;'>ERREUR: Impossible d'écrire le pied de page temporaire.</h2>");
}

// ========================================================================= 
// FICHIERS À CONVERTIR
// ========================================================================= 
$files_to_convert = [$file_page1];
if (!empty($overflow_content)) {
    $file_overflow = "{$temp_dir}/overflow_{$suffix}.html";
    file_put_contents($file_overflow, $html_overflow) || die("Erreur: impossible d'écrire overflow.html temporaire.");
    $files_to_convert[] = $file_overflow;
}

$pdf_file = "{$temp_dir}/fiche_{$FICHE_ID}_{$suffix}.pdf";

// ========================================================================= 
// COMMANDE WKHTMLTOPDF
// ========================================================================= 
$cmd = escapeshellarg($WKHTMLTOPDF_PATH) . " ";
$cmd .= "--page-size A4 ";
$cmd .= "--margin-top 25mm --margin-bottom 15mm ";
$cmd .= "--header-html " . escapeshellarg($header_file) . " ";
$cmd .= "--footer-html " . escapeshellarg($footer_file) . " ";
$cmd .= "--disable-smart-shrinking --no-stop-slow-scripts --enable-local-file-access ";
$cmd .= implode(' ', array_map('escapeshellarg', $files_to_convert)) . " ";
$cmd .= escapeshellarg($pdf_file) . " 2>&1";

$output = shell_exec($cmd); 

if (file_exists($pdf_file)) { 
    ob_clean(); 
    header('Content-Type: application/pdf'); 
    header('Content-Disposition: attachment; filename="fiche_biographique_' . $FICHE_ID . '.pdf"'); 
    readfile($pdf_file); 
    
    // Nettoyage
    $temp_files = [$file_page1, $header_file, $footer_file, $pdf_file];
    if (!empty($file_overflow)) $temp_files[] = $file_overflow;
    foreach ($temp_files as $f) if (file_exists($f)) unlink($f);
    exit();
} else { 
    $debug = "
        <div style='border:2px solid red; padding:15px; font-family:monospace; background:#ffebeb;'>
            <h2 style='color:red;'>❌ ÉCHEC DE GÉNÉRATION PDF</h2>
            <p><strong>Commande :</strong></p>
            <pre>" . htmlspecialchars($cmd) . "</pre>
            <p><strong>Sortie :</strong></p>
            <pre style='color:#c00;'>" . (empty($output) ? "(aucune sortie)" : htmlspecialchars($output)) . "</pre>
        </div>
    ";
    die($debug);
}
?>