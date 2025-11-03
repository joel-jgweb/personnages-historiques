<?php
require_once __DIR__ . '/../config.php';
session_start();

// Gestion de l'inactivité (30 minutes)
define('MAX_IDLE_TIME', 1800);
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > MAX_IDLE_TIME)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'permissions.php';

$userStatut = $_SESSION['user_statut'] ?? 7;
$userLogin = $_SESSION['user_login'] ?? 'Utilisateur';

// Définir les liens disponibles et leurs permissions
$menuItems = [
    [
        'title' => '🏠 Accueil',
        'url' => 'index.php',
        'icon' => '🏠',
        'allowedStatuts' => [1, 2, 3, 4, 5, 6],
        'description' => 'Tableau de bord'
    ],
    [
        'title' => '➕ Ajouter une fiche',
        'url' => 'ajouter_fiche.php',
        'icon' => '➕',
        'allowedStatuts' => [1, 2, 3, 6],
        'description' => 'Créer une nouvelle fiche de personnage'
    ],
    [
        'title' => '🔍 Modifier une fiche',
        'url' => 'modifier_fiche.php',
        'icon' => '🔍',
        'allowedStatuts' => [1, 2, 3, 4, 6],
        'description' => 'Rechercher et éditer une fiche existante'
    ],
    [
        'title' => '👥 Gérer les utilisateurs',
        'url' => 'gerer_utilisateurs.php',
        'icon' => '👥',
        'allowedStatuts' => [1],
        'description' => 'Créer, modifier ou supprimer des comptes'
    ],
    [
        'title' => '⚙️ Configurer le site',
        'url' => 'configurer_site.php',
        'icon' => '⚙️',
        'allowedStatuts' => [1],
        'description' => 'Modifier le logo, les couleurs et le texte du site'
    ],
    [
        'title' => '📥 Télécharger la base',
        'url' => 'download_db.php',
        'icon' => '📥',
        'allowedStatuts' => [1, 2, 6],
        'description' => 'Sauvegarde complète de la base de données'
    ],
    [
        'title' => '⚗️ Diagnostic de la base',
        'url' => 'diagnostic_base.php',
        'icon' => '⚗️',
        'allowedStatuts' => [1],
        'description' => 'Vérifie la base de données'
    ],
    // Ajouts demandés :
    [
        'title' => '⚡ Exécuter du SQL',
        'url' => 'execute_sql.php',
        'icon' => '⚡',
        'allowedStatuts' => [1], // Super-Admin uniquement
        'description' => 'Outil avancé pour requêtes SQL'
    ],
    [
        'title' => '🚀 Publier toutes les fiches',
        'url' => 'publier_toutes_fiches.php',
        'icon' => '🚀',
        'allowedStatuts' => [1], // Super-Admin uniquement
        'description' => 'Publication massive des fiches'
    ],
    [
        'title' => '🗑️ Supprimer une fiche',
        'url' => 'supprimer_fiche.php',
        'icon' => '🗑️',
        'allowedStatuts' => [1,2,6],
        'description' => 'Suppression sécurisée d’une fiche'
    ],
    [
        'title' => '📄 Gestion de documents',
        'url' => 'gestion_docs.php',
        'icon' => '📄',
        'allowedStatuts' => [1,2,6],
        'description' => 'Gérer les images et les documents associés'
    ],
    [
        'title' => '✅ Valider les fiches',
        'url' => 'valider_fiches.php',
        'icon' => '✅',
        'allowedStatuts' => [1,2,4,6],
        'description' => 'Valider les fiches en attente'
    ],
];

// Fonction utilitaire pour vérifier si l'utilisateur a accès à un élément de menu
function userCanAccess($allowedStatuts) {
    return in_array($_SESSION['user_statut'], $allowedStatuts);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — Administration</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="index-page">
    <div class="container">
        <div class="header">
            <h1>🔐 Administration</h1>
            <p>Gérez le contenu et la configuration du site</p>
        </div>

        <div class="user-info">
            👤 Connecté en tant que : <strong><?= htmlspecialchars($userLogin) ?></strong> (ID Statut: <?= $userStatut ?>)
        </div>

        <?php
        // Filtrer les éléments de menu accessibles à l'utilisateur
        $accessibleItems = array_filter($menuItems, function($item) {
            return userCanAccess($item['allowedStatuts']);
        });

        if (empty($accessibleItems)) {
            echo '<div class="no-access">';
            echo '<h3>⛔ Aucun accès autorisé</h3>';
            echo '<p>Votre rôle ne vous permet pas d\'accéder à aucune fonctionnalité d\'administration.</p>';
            echo '</div>';
        } else {
            echo '<div class="menu-grid">';
            foreach ($accessibleItems as $item) {
                echo '<a href="' . htmlspecialchars($item['url']) . '" class="menu-item">';
                echo '<span class="icon">' . $item['icon'] . '</span>';
                echo '<h3>' . htmlspecialchars($item['title']) . '</h3>';
                echo '<p>' . htmlspecialchars($item['description']) . '</p>';
                echo '</a>';
            }
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>