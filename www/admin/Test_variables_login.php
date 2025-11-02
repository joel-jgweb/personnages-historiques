<?php
// test_prenom_nom.php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test prénom/nom en session</title>
    <link rel="stylesheet" href="admin.css">
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
