<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// login.php
session_start();
require_once __DIR__ . '/../config.php';

$dbPath = '../../data/portraits.sqlite';
$error = '';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        // Requête pour récupérer l'utilisateur
       $stmt = $pdo->prepare("SELECT ID_utilisateur, login, prenom_nom, mot_de_passe, ID_statut FROM utilisateurs WHERE login = ?");
       $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            // Connexion réussie
            $_SESSION['user_id'] = $user['ID_utilisateur'];
            $_SESSION['user_login'] = $user['login'];
            $_SESSION['user_statut'] = $user['ID_statut'];
            $_SESSION['nom_prenom'] = $user['prenom_nom'];
            
            // Mettre à jour la date de dernier login
            $stmt = $pdo->prepare("UPDATE utilisateurs SET dernier_login = CURRENT_TIMESTAMP WHERE ID_utilisateur = ?");
            $stmt->execute([$user['ID_utilisateur']]);

            // Rediriger vers la page d'accueil de l'admin
            header("Location: index.php");
            exit;
        } else {
            $error = "Identifiant ou mot de passe incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion à l'Administration</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="login-page">
    <div class="login-container">
        <h2>🔐 Connexion Admin</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="login" placeholder="Nom d'utilisateur" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>
</html>