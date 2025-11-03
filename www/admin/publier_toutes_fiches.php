<?php
// publier_toutes_fiches.php - Script d'urgence pour publier toutes les fiches
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'permissions.php';
checkUserPermission([1]);

// --- Vérification du rôle : Seul un Super-Admin (ID_statut = 1) peut exécuter ce script ---
if ($_SESSION['user_statut'] != 1) {
    die("<h1 style='color: #dc3545; text-align: center; padding: 50px;'>⛔ Accès refusé</h1><p style='text-align: center;'>Seul le Super-Administrateur peut exécuter cette action.</p>");
}

// --- Chemin vers la base de données ---
$dbPath = '../../data/portraits.sqlite';
$message = '';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Vérifier que la colonne 'est_en_ligne' existe ---
    $stmt = $pdo->query("PRAGMA table_info(personnages)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasEstEnLigne = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'est_en_ligne') {
            $hasEstEnLigne = true;
            break;
        }
    }

    if (!$hasEstEnLigne) {
        die("<h2 style='color: #dc3545;'>❌ La colonne <code>est_en_ligne</code> n'existe pas dans la table <code>personnages</code>.</h2><p>Veuillez d'abord exécuter le script de migration.</p>");
    }

    // --- Compter le nombre de fiches actuellement hors ligne ---
    $stmt = $pdo->query("SELECT COUNT(*) FROM personnages WHERE est_en_ligne = 0");
    $countHorsLigne = $stmt->fetchColumn();

    // --- Compter le nombre total de fiches ---
    $stmt = $pdo->query("SELECT COUNT(*) FROM personnages");
    $countTotal = $stmt->fetchColumn();

    // --- Exécuter la mise à jour si le formulaire est soumis ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer']) && $_POST['confirmer'] === 'oui') {
        $stmt = $pdo->prepare("UPDATE personnages SET est_en_ligne = 1 WHERE est_en_ligne = 0");
        $stmt->execute();
        $rowsAffected = $stmt->rowCount();
        $message = "<div class='alert alert-success'>
            ✅ <strong>$rowsAffected fiche(s)</strong> ont été passée(s) en ligne avec succès.<br>
            Il y a maintenant <strong>$countTotal</strong> fiche(s) en ligne au total.
        </div>";
    }

} catch (Exception $e) {
    $message = "<div class='alert alert-danger'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>🚀 Publier toutes les fiches</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="container">
        <h1>🚀 Publier toutes les fiches</h1>

        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <div class="stats">
            <div>📁 <strong>Fiches totales :</strong> <?= $countTotal ?></div>
            <div>🔴 <strong>Fiches hors ligne :</strong> <?= $countHorsLigne ?></div>
            <div>✅ <strong>Fiches en ligne :</strong> <?= $countTotal - $countHorsLigne ?></div>
        </div>

        <?php if ($countHorsLigne > 0): ?>
            <div class="warning">
                ⚠️ Cette action va publier <strong>toutes les fiches actuellement hors ligne</strong>.<br>
                Elles seront immédiatement visibles sur le site public.
            </div>

            <form method="POST" action="" onsubmit="return confirm('Êtes-vous ABSOLUMENT sûr de vouloir publier toutes les fiches ? Cette action est irréversible.');">
                <input type="hidden" name="confirmer" value="oui">
                <button type="submit" class="btn btn-primary">✅ Oui, publier toutes les fiches</button>
            </form>
        <?php else: ?>
            <div class="alert alert-success">
                🎉 Toutes les fiches sont déjà en ligne !
            </div>
        <?php endif; ?>

        <a href="index.php" class="btn btn-secondary">← Retour au tableau de bord</a>
    </div>
</body>
</html>
