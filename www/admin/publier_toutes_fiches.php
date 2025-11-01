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
    <link rel="stylesheet" href="admin.css">
    <meta charset="UTF-8">
    <title>🚀 Publier toutes les fiches</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            font-size: 1.1rem;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 2rem 0;
            font-size: 1.2rem;
            text-align: left;
            display: inline-block;
        }
        .stats div {
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: bold;
            text-decoration: none;
            border-radius: 50px;
            margin: 10px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #007BFF;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-3px);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-3px);
        }
        .warning {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            margin: 2rem 0;
            border: 1px solid #ffeaa7;
            color: #856404;
            font-weight: bold;
        }
    </style>
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