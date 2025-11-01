<?php
// permissions.php - Gestion centralisée des droits d'accès

/**
 * Vérifie si l'utilisateur a l'un des statuts autorisés.
 * @param array $allowedStatuts Liste des ID_statut autorisés (ex: [1, 2, 6])
 * @param string $redirectUrl URL de redirection en cas de refus (optionnel)
 * @return bool true si autorisé, false sinon
 */
function checkUserPermission($allowedStatuts, $redirectUrl = 'index.php') {
    if (!isset($_SESSION['user_statut'])) {
        header("Location: login.php");
        exit;
    }

    $userStatut = $_SESSION['user_statut'];

    if (!in_array($userStatut, $allowedStatuts)) {
        // Journaliser l'accès non autorisé (optionnel mais recommandé)
        error_log("Accès refusé : Utilisateur ID " . $_SESSION['user_id'] . " (Statut: $userStatut) a tenté d'accéder à " . $_SERVER['REQUEST_URI']);

        // Afficher un message d'erreur et rediriger
        echo "<!DOCTYPE html><html><head>
    <link rel="stylesheet" href="admin.css"><title>Accès refusé</title></head><body>";
        echo "<div style='padding: 50px; text-align: center; font-family: Arial, sans-serif;'>";
        echo "<h2 style='color: #dc3545;'>⛔ Accès refusé</h2>";
        echo "<p>Vous n'avez pas les permissions nécessaires pour accéder à cette page.</p>";
        echo "<a href='$redirectUrl' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007BFF; color: white; text-decoration: none; border-radius: 5px;'>Retour à l'accueil</a>";
        echo "</div></body></html>";
        exit;
    }
    return true;
}
?>