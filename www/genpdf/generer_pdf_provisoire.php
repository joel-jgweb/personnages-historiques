<?php
// 🚨 DÉBOGAGE CRITIQUE : Afficher toutes les erreurs PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =========================================================================
// CLASSE DE CONVERSION MARKDOWN LITE (INTÉGRÉE)
// =========================================================================
class MarkdownConverter {
    public static function convert(string $text): string {
        // 1. Nettoyage des images et liens (pour le PDF)
        $text = preg_replace('/\!\[(.*?)\]\((.*?)\)/', '', $text);
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $text);

        // 2. Conversion du Gras et de l'Italique en balises HTML
        $text = preg_replace('/\*\*([^\*]+)\*\*|__([^_]+)__/', '<b>$1$2</b>', $text);
        $text = preg_replace('/(?<!\*)\*([^\*]+)\*(?!\*)|(?<!_)_([^_]+)(?!_)/', '<i>$1$2</i>', $text);

        // 3. Conversion des Titres H1, H2, H3
        $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);
        
        // 4. Listes non ordonnées (Approximation simple)
        $text = preg_replace('/^\* (.*?)$/m', '<li>$1</li>', $text);
        if (str_contains($text, '<li>')) {
             $text = '<ul style="margin-left: 15px; padding-left: 0;">' . $text . '</ul>';
             $text = preg_replace('/<\/ul><li>/', '<li>', $text);
        }

        // 5. Blocs de lignes doubles -> Paragraphes simples <p>
        $text = '<p>' . str_replace("\n\n", '</p><p>', trim($text)) . '</p>';
        // 6. Conversion des sauts de ligne simples en <br>
        $text = str_replace("\n", '<br>', $text);
        
        // Nettoyage final
        $text = str_replace('<p></p>', '', $text);
        $text = str_replace('<br></p>', '</p>', $text);
        
        return $text;
    }
}
// =========================================================================

// =========================================================================
// 1. CONFIGURATION DES CHEMINS & CONSTANTES
// =========================================================================

$DB_PATH = __DIR__ . '/../../data/portraits.sqlite'; 
$TABLE_NAME = 'personnages';
$ID_COLUMN = 'ID_fiche'; 
$WKHTMLTOPDF_PATH = 'wkhtmltopdf'; 
$FICHE_ID_VALEUR = $_GET['id'] ?? die('Erreur: ID de fiche manquant dans l\'URL.'); 

$MODELE_HTML = __DIR__ . '/modele_fiche_provisoire.html'; 

const SEUIL_MULTI_COLONNES = 600; 

// =========================================================================
// 2. ACCÈS AUX DONNÉES (SQLite)
// =========================================================================

if (!file_exists($DB_PATH)) {
    die("<h2 style='color:red;'>ERREUR CRITIQUE: Le fichier BDD n'a pas été trouvé : " . htmlspecialchars($DB_PATH) . "</h2>");
}
if (!file_exists($MODELE_HTML)) {
    die("<h2 style='color:red;'>ERREUR CRITIQUE: Le fichier Modèle HTML n'a pas été trouvé : " . htmlspecialchars($MODELE_HTML) . "</h2>");
}

try {
    $db = new PDO('sqlite:' . $DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("SELECT * FROM {$TABLE_NAME} WHERE {$ID_COLUMN} = :id_valeur AND est_en_ligne = 1");
    $stmt->execute([':id_valeur' => $FICHE_ID_VALEUR]);
    $fiche_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fiche_data) {
        die("<h2>Erreur: Fiche ID {$FICHE_ID_VALEUR} non trouvée ou non en ligne.</h2>");
    }
} catch (PDOException $e) {
    die("<h2>Erreur de connexion/requête à la base de données: " . $e->getMessage() . "</h2>");
}

// =========================================================================
// 3. PRÉPARATION DES DONNÉES (Conversion Markdown & Photo)
// =========================================================================

$TEXTE_PRINCIPAL = MarkdownConverter::convert($fiche_data['Details'] ?? '');
$SOURCES_CLEANED = MarkdownConverter::convert($fiche_data['Sources'] ?? '');

$nombre_de_mots = str_word_count(strip_tags($TEXTE_PRINCIPAL));

$nom_fiche = $fiche_data['Nom'] ?? 'Fiche Inconnue'; 
$auteur_fiche = $fiche_data['auteur'] ?? '—';
$modif_fiche = $fiche_data['derniere_modif'] ?? '—';
$date_edition = date('Y-m-d');
$lien_photo = $fiche_data['Photo'] ?? null; 


// Détermination du chemin de la photo
$photo_path = '';
if ($lien_photo) {
    if (version_compare(PHP_VERSION, '8.0.0') >= 0 ? str_starts_with($lien_photo, '/') : strpos($lien_photo, '/') === 0) {
        $root_dir = __DIR__ . '/../../..'; 
        $photo_path = $root_dir . $lien_photo;
    } else {
        $photo_path = $lien_photo;
    }
}

// Construction de l'HTML de la photo
$photo_html = '<div style="width: 100%; height: 150px; background: #f9f9f9; text-align: center; padding-top: 50px; font-size: 10pt; color: #aaa;">Photo non disponible ou chemin incorrect</div>';

if ($photo_path) {
    $photo_html = '<img src="' . htmlspecialchars($photo_path) . '" alt="Photo du personnage" style="width: 100%; height: 100%; object-fit: cover; display: block;">';
}


// =========================================================================
// 4. LOGIQUE D'ADAPTATION & GÉNÉRATION DU CONTENU HTML
// =========================================================================

// Construction de la Ligne Biographique Linéaire
$donnees = [];
// Ajout des données (sans étiquette)
$donnees[] = $fiche_data['Donnees_genealogiques'] ?? '';
$donnees[] = $fiche_data['Metier'] ?? '';
$donnees[] = $fiche_data['Engagements'] ?? '';

// Filtre les valeurs vides et concatène avec le gros point (bull)
$ligne_biographique = implode(' &bull; ', array_filter($donnees)); 
$ligne_biographique = trim($ligne_biographique, " \t\n\r\0\x0B\x20\xC2\xA0&bull;");


$multipage_message = '';
if ($nombre_de_mots > SEUIL_MULTI_COLONNES) {
    $multipage_message = '<div class="multipage-alert">(Le contenu est long et se poursuit sur une autre page)</div>'; 
}

// Création des mentions d'édition pour insertion sous Sources
$edition_mentions_html = "
<div class='edition-mentions-provisoire'>
    Fiche éditée le <strong>{$date_edition}</strong> | Auteur: <strong>{$auteur_fiche}</strong> | Dernière modif: <strong>{$modif_fiche}</strong>
</div>";


$html_template = file_get_contents($MODELE_HTML);

$replacements = [
    '[NOM]' => $nom_fiche,
    '[Sources]' => $SOURCES_CLEANED,
    '[auteur]' => $auteur_fiche,
    '[mise_en_ligne]' => $modif_fiche, 
    '[date_du_jour]' => $date_edition,
    '[edition_mentions]' => $edition_mentions_html, 
    '[photo_placeholder]' => $photo_html, 
    
    // ⚠️ LE REMPLACEMENT CRITIQUE
    '[ligne_biographique]' => $ligne_biographique, 
];

$final_html = str_replace(array_keys($replacements), array_values($replacements), $html_template);

$final_html = str_replace('[details]', $TEXTE_PRINCIPAL, $final_html);
$final_html = str_replace('[multipage_message]', $multipage_message, $final_html);


// Encapsulation finale
$html_content = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$nom_fiche}</title>
</head>
<body>
    {$final_html}
</body>
</html>
HTML;


// =========================================================================
// 5. APPEL À WKHTMLTOPDF (Minimaliste supporté)
// =========================================================================

$temp_dir = sys_get_temp_dir();
$time_suffix = time();
$html_file = $temp_dir . "/fiche_{$FICHE_ID_VALEUR}_{$time_suffix}.html";
$pdf_file = $temp_dir . "/fiche_{$FICHE_ID_VALEUR}_{$time_suffix}.pdf";

if (file_put_contents($html_file, $html_content) === false) {
    die("<h2 style='color:red;'>ERREUR CRITIQUE: Impossible d'écrire le fichier HTML temporaire. Vérifiez les droits sur '{$temp_dir}'.</h2>");
}

// --- Construction de la Commande (SANS header/footer non supportés) ---
$commande = escapeshellarg($WKHTMLTOPDF_PATH) . " ";
$commande .= "--page-size A4 --enable-local-file-access ";
$commande .= "--margin-top 15mm --margin-bottom 15mm "; 
$commande .= escapeshellarg($html_file) . " ";
$commande .= escapeshellarg($pdf_file) . " 2>&1"; 

$output = shell_exec($commande);

// =========================================================================
// 6. ENVOI ET DÉBOGAGE FINAL
// =========================================================================

if (file_exists($pdf_file)) {
    // Succès: Envoi du PDF
    ob_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="fiche_biographique_' . $FICHE_ID_VALEUR . '.pdf"');
    readfile($pdf_file);
    
    // Nettoyage
    unlink($html_file);
    unlink($pdf_file);
} else {
    // Échec: Affichage du débogage complet
    $debug_info = "
        <div style='border: 2px solid red; padding: 15px; background: #ffebeb; font-family: monospace;'>
            <h2 style='color: red;'>❌ ÉCHEC FINAL DE LA CONVERSION PDF - Informations de Débogage</h2>
            <p><strong>Fichier PDF non créé.</strong> Le script PHP fonctionne, mais l'exécution de wkhtmltopdf échoue. C'est le blocage final de la configuration serveur.</p>
            
            <h3>Commande Exécutée :</h3>
            <pre style='white-space: pre-wrap; word-break: break-all; background: #fff; padding: 10px; border: 1px solid #ccc;'>
                {$commande}
            </pre>
            
            <h3>Sortie de wkhtmltopdf :</h3>
            <pre style='white-space: pre-wrap; word-break: break-all; background: #fff; padding: 10px; border: 1px solid #ccc; color: #cc0000;'>
                " . (empty($output) ? "<strong>(Sortie vide)</strong>: Confirme un problème X11/Qt non patché sur le serveur." : htmlspecialchars($output)) . "
            </pre>
        </div>
    ";
    
    die($debug_info);
    
    if (file_exists($html_file)) unlink($html_file);
}
?>