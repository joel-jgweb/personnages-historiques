<?php
// valider_fiches.php - Outil de validation pour publier les fiches en brouillon
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- Vérification des permissions ---
$roles_autorises = [1, 2, 4, 6]; // Super-Admin, Admin Fiches, Valideur, Admin Simple
if (!in_array($_SESSION['user_statut'], $roles_autorises)) {
    die("<h1 style='color: #dc3545; text-align: center; padding: 50px;'>⛔ Accès refusé</h1><p style='text-align: center;'>Vous n'avez pas les permissions nécessaires pour valider les fiches.</p>");
}

require_once '../../www/config.php';
$dbPath = '../../data/portraits.sqlite';
$message = '';
$fichesNonValidees = [];

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
        die("<h2 style='color: #dc3545;'>❌ La colonne <code>est_en_ligne</code> n'existe pas.</h2><p>Veuillez d'abord exécuter le script de migration.</p>");
    }

    // --- Action : Publier les fiches sélectionnées ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publier_selection') {
        $fiches_a_publier = $_POST['fiches_a_publier'] ?? [];

        if (empty($fiches_a_publier)) {
            $message = "<div class='alert alert-warning'>⚠️ Aucune fiche sélectionnée.</div>";
        } else {
            // Préparer la requête
            $placeholders = str_repeat('?,', count($fiches_a_publier) - 1) . '?';
            $sql = "UPDATE personnages SET est_en_ligne = 1 WHERE ID_fiche IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($fiches_a_publier);

            $count = $stmt->rowCount();
            $message = "<div class='alert alert-success'>🎉 <strong>$count</strong> fiche(s) publiée(s) avec succès !</div>";
        }
    }

    // --- Récupérer toutes les fiches non publiées ---
    $stmt = $pdo->query("SELECT ID_fiche, Nom, Metier, Engagements, Details FROM personnages WHERE est_en_ligne = 0 ORDER BY Nom ASC");
    $fichesNonValidees = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = "<div class='alert alert-danger'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>✅ Valider et Publier les Fiches</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 2.2rem;
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
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .stats {
            text-align: center;
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 2rem;
            font-size: 1.2rem;
            font-weight: bold;
            color: #1565c0;
        }
        .fiches-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        .fiche-item {
            background: #fafafa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            transition: all 0.3s;
        }
        .fiche-item:hover {
            border-color: #2196f3;
            background: #f5f9ff;
            transform: translateY(-2px);
        }
        .checkbox-cell {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            min-width: 50px;
        }
        .checkbox-cell input[type="checkbox"] {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        .fiche-content {
            flex: 1;
        }
        .fiche-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .fiche-title {
            font-size: 1.4rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }
        .fiche-id {
            background: #e0e0e0;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        .fiche-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            color: #555;
        }
        .fiche-meta div {
            display: flex;
            gap: 0.5rem;
        }
        .fiche-excerpt {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #eee;
            font-size: 0.95rem;
            line-height: 1.5;
            color: #333;
            max-height: 120px;
            overflow: hidden;
            position: relative;
        }
        .fiche-excerpt::after {
            content: '...';
            position: absolute;
            bottom: 5px;
            right: 10px;
            background: white;
            padding: 0 5px;
        }
        .actions-bar {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px dashed #e0e0e0;
        }
        .btn {
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: bold;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #4caf50;
            color: white;
        }
        .btn-primary:hover {
            background: #388e3c;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 1rem;
        }
        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-3px);
        }
        .no-fiches {
            text-align: center;
            padding: 4rem 2rem;
            background: #f8f9fa;
            border-radius: 15px;
            color: #6c757d;
            font-style: italic;
            font-size: 1.2rem;
        }
        @media (max-width: 768px) {
            .fiche-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .fiche-meta {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Valider et Publier les Fiches</h1>

        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <div class="stats">
            📋 <?= count($fichesNonValidees) ?> fiche(s) en attente de validation
        </div>

        <?php if (empty($fichesNonValidees)): ?>
            <div class="no-fiches">
                <h2>🎉 Félicitations !</h2>
                <p>Toutes les fiches sont déjà publiées.</p>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="publier_selection">

                <div class="fiches-list">
                    <?php foreach ($fichesNonValidees as $fiche): ?>
                        <div class="fiche-item">
                            <div class="checkbox-cell">
                                <input type="checkbox" name="fiches_a_publier[]" value="<?= $fiche['ID_fiche'] ?>" id="fiche_<?= $fiche['ID_fiche'] ?>">
                            </div>
                            <div class="fiche-content">
                                <div class="fiche-header">
                                    <h3 class="fiche-title"><?= htmlspecialchars($fiche['Nom']) ?></h3>
                                    <span class="fiche-id">ID #<?= $fiche['ID_fiche'] ?></span>
                                </div>
                                <div class="fiche-meta">
                                    <?php if (!empty($fiche['Metier'])): ?>
                                        <div><strong>👷 Métier :</strong> <?= htmlspecialchars($fiche['Metier']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($fiche['Engagements'])): ?>
                                        <div><strong>✊ Engagement :</strong> <?= htmlspecialchars($fiche['Engagements']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="fiche-excerpt">
                                    <?= nl2br(htmlspecialchars(substr($fiche['Details'] ?? '', 0, 300))) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="actions-bar">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Êtes-vous sûr de vouloir publier les fiches sélectionnées ?');">
                        ✅ Publier les fiches sélectionnées
                    </button>
                    <a href="index.php" class="btn btn-secondary">← Annuler / Retour</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>