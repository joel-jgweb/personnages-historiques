<?php
// config.php - Charge la configuration du site et fournit des fonctions utilitaires
function loadSiteConfig($pdo) {
    $config = [
        'site_title' => 'Portraits des Militants',
        'site_subtitle' => 'Explorez les parcours de ceux qui ont marqué l\'histoire.',
        'association_name' => 'Association d\'Histoire Sociale',
        'association_address' => '123 Rue de l\'Histoire, 75000 Paris',
        'logo_path' => null,
        'primary_color' => '#2c3e50',
        'secondary_color' => '#3498db',
        'background_color' => '#1e5799',
        'background_image' => null,
    ];
    try {
        $stmt = $pdo->query("SELECT * FROM configuration WHERE id = 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            foreach ($config as $key => $defaultValue) {
                if (isset($row[$key]) && $row[$key] !== null && $row[$key] !== '') {
                    $config[$key] = $row[$key];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erreur lors du chargement de la configuration : " . $e->getMessage());
    }
    return $config;
}

function darkenColor($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = max(0, $r - ($r * $percent / 100));
    $g = max(0, $g - ($g * $percent / 100));
    $b = max(0, $b - ($b * $percent / 100));
    return '#' . str_pad(dechex((int)$r), 2, '0', STR_PAD_LEFT) .
               str_pad(dechex((int)$g), 2, '0', STR_PAD_LEFT) .
               str_pad(dechex((int)$b), 2, '0', STR_PAD_LEFT);
}

/**
 * Convertit du Markdown de base en HTML sécurisé.
 */
function markdownToHtml($text) {
    if (empty($text)) return '';

    // Échapper le HTML de base pour la sécurité
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Convertir les liens Markdown [texte](url)
    $text = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)]+)\)/',
        function($matches) {
            $url = $matches[2];
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $matches[1] . '</a>';
            }
            return $matches[0];
        },
        $text
    );

    // Gras et italique
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);

    // Gérer les paragraphes et les sauts de ligne
    $text = preg_replace('/\n{2,}/', "</p><p>", $text);
    $text = str_replace("\n", "<br>", $text);
    $text = "<p>" . $text . "</p>";
    $text = str_replace('<p></p>', '', $text);

    return $text;
}
?>