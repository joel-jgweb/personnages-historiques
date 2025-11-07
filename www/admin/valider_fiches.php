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

require_once '../../www/bootstrap.php';
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
            $sql = "UPDATE personnages SET est_en_ligne = 1, valideur = ? WHERE ID_fiche IN ($placeholders)";
            $params = array_merge([$_SESSION['nom_prenom'] ?? 'Système'], $fiches_a_publier);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

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
    <link rel="stylesheet" href="admin.css">
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