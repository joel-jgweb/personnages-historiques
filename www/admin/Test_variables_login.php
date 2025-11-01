<?php
// test_prenom_nom.php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="admin.css">
    <meta charset="UTF-8">
    <title>Test prénom/nom en session</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f0f0f0; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 500px; margin: auto; }
        h1 { color: #333; }
        .info { font-size: 1.2em; color: #007BFF; margin-top: 20px; }
        .error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test "Prénom Nom" dans la session</h1>
        <?php if (isset($_SESSION['nom_prenom']) && $_SESSION['nom_prenom']): ?>
            <div class="info">
                ✅ <strong>nom_prenom :</strong> <?= htmlspecialchars($_SESSION['nom_prenom']) ?>
            </div>
        <?php else: ?>
            <div class="error">
                ❌ Le champ <strong>nom_prenom</strong> n'est pas défini en session.<br>
                <em>Connecte-toi via le formulaire de login pour tester.</em>
            </div>
        <?php endif; ?>
        <hr>
        <pre>
<?php print_r($_SESSION); ?>
        </pre>
    </div>
</body>
</html>