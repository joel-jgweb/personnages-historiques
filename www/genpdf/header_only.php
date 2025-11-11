<?php
// header_only.php
// Affiche un en-tête simple (titre + sous-titre) récupérés depuis la BDD SQLite.
//
// Déposer ce fichier dans www/genpdf/ et appeler : /www/genpdf/header_only.php?id=NNN (id optionnel)
// Si une table `configuration` existe avec id=1, on l'utilise ; sinon on retourne des valeurs par défaut.

// afficher les erreurs en dev (retirer en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Chemin vers la BDD (même que vos autres scripts)
$DB_PATH = __DIR__ . '/../../data/portraits.sqlite';

// Valeurs par défaut
$defaults = [
    'site_title' => "Portraits des Militants",
    'site_subtitle' => "Explorez les parcours de ceux qui ont marqué l'histoire."
];

$site_title = $defaults['site_title'];
$site_subtitle = $defaults['site_subtitle'];

// Lecture de la configuration si la BDD existe
if (file_exists($DB_PATH)) {
    try {
        $db = new PDO('sqlite:' . $DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // tenter de lire la table configuration id=1 (si elle existe)
        $stmt = $db->query("SELECT name, value, association_name, site_title, site_subtitle FROM configuration WHERE id = 1");
        $config = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        if ($config) {
            // Priorité sur site_title/site_subtitle si présentes, sinon association_name -> site_title
            if (!empty($config['site_title'])) {
                $site_title = $config['site_title'];
            } elseif (!empty($config['association_name'])) {
                $site_title = $config['association_name'];
            }

            if (!empty($config['site_subtitle'])) {
                $site_subtitle = $config['site_subtitle'];
            } elseif (!empty($config['value'])) {
                // fallback si la table stocke une valeur générique
                $site_subtitle = $config['value'];
            }
        }
    } catch (PDOException $e) {
        // en cas d'erreur BDD, on garde les valeurs par défaut et on peut logger si besoin
        error_log("header_only.php PDOException: " . $e->getMessage());
    }
} else {
    error_log("header_only.php: BDD introuvable à {$DB_PATH}");
}

// Affichage HTML minimal de l'en-tête
// Échappement pour éviter tout problème d'injection
$site_title_esc = htmlspecialchars($site_title, ENT_QUOTES, 'UTF-8');
$site_subtitle_esc = htmlspecialchars($site_subtitle, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo $site_title_esc; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 20px; }
        .site-title { font-size: 20pt; font-weight: bold; color: #222; }
        .site-subtitle { font-size: 10pt; color: #666; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="site-title"><?php echo $site_title_esc; ?></div>
        <div class="site-subtitle"><?php echo $site_subtitle_esc; ?></div>
    </div>
</body>
</html>