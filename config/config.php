<?php
// config/config.php
// Fonctions utilitaires pour la configuration de l'application.
// Ce fichier doit rester versionné. Les valeurs locales par instance doivent être placées
// dans config/config.local.php (non versionné) — voir config/config.local.php.dist.

if (!function_exists('loadSiteConfig')) {
    /**
     * Récupère la configuration du site depuis la base de données.
     * @param PDO|null $pdo Si fourni, lit la table `configuration`. Sinon retourne des valeurs par défaut.
     * @return array
     */
    function loadSiteConfig(PDO $pdo = null): array
    {
        // Valeurs par défaut
        $defaults = [
            'site_title' => 'Personnages Historiques',
            'site_subtitle' => '',
            'association_name' => '',
            'association_address' => '',
            'primary_color' => '#2c3e50',
            'secondary_color' => '#6c757d',
            'background_color' => '#ffffff',
            'logo_path' => null,
            'background_image' => null,
        ];

        // Si pas de PDO, retourner les valeurs par défaut
        if ($pdo === null) {
            return $defaults;
        }

        try {
            $stmt = $pdo->query("SELECT * FROM configuration LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $defaults;
            }

            // Normaliser les clés : on garde les clés utiles
            $config = $defaults;
            foreach ($row as $k => $v) {
                if (array_key_exists($k, $config)) {
                    $config[$k] = $v;
                }
            }
            return $config;
        } catch (Exception $e) {
            // En cas d'erreur, on retourne les valeurs par défaut
            error_log("loadSiteConfig error: " . $e->getMessage());
            return $defaults;
        }
    }
}

if (!function_exists('darkenColor')) {
    /**
     * Assombrit une couleur hex de x % (ex: 15)
     * @param string $hex
     * @param int $percent
     * @return string
     */
    function darkenColor(string $hex, int $percent = 10): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, (int)($r * (100 - $percent) / 100)));
        $g = max(0, min(255, (int)($g * (100 - $percent) / 100)));
        $b = max(0, min(255, (int)($b * (100 - $percent) / 100)));

        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}

if (!function_exists('urlToMarkdownLink')) {
    /**
     * Transforme une URL en lien Markdown avec un libellé, si valide.
     * @param string $url
     * @param string|null $label
     * @return string
     */
    function urlToMarkdownLink(string $url, string $label = null): string
    {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $label = $label ?? $url;
            return "[" . $label . "](" . $url . ")";
        }
        return $url;
    }
}
?>