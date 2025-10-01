<?php
// DEBUG : Affichage et log des erreurs PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// supprimer_fiche.php - Suppression sécurisée d'une fiche
session_start();

// Permissions : seulement Super-Admin (1), Admin Fiches (2), Admin Simple (6)
require_once 'permissions.php';
checkUserPermission([1, 2, 6]);

require_once '../config.php';

$dbPath = __DIR__ . '/../../data/portraits.sqlite';
$message = '';
$resultats = [];
$fiche = null;

// Connexion à la base de données
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage()));
}

// Suppression si demandé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer' && isset($_POST['id_fiche'])) {
    $id = intval($_POST['id_fiche']);
    // Récupérer le nom pour affichage
    $stmt = $pdo->prepare("SELECT Nom FROM personnages WHERE ID_fiche = ?");
    $stmt->execute([$id]);
    $fiche = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fiche) {
        $stmtDel = $pdo->prepare("DELETE FROM personnages WHERE ID_fiche = ?");
        $stmtDel->execute([$id]);
        $message = "<div class='alert alert-success'>✅ Fiche n°$id \"".htmlspecialchars($fiche['Nom'] ?? '')."\" supprimée avec succès.</div>";
    } else {
        $message = "<div class='alert alert-danger'>❌ Fiche introuvable.</div>";
    }
}

// Recherche de fiche
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rechercher') {
    $recherche = trim($_POST['recherche'] ?? '');
    if ($recherche !== '') {
        $stmt = $pdo->prepare("SELECT ID_fiche, Nom, Metier FROM personnages WHERE Nom LIKE ? ORDER BY Nom ASC");
        $stmt->execute([$recherche . '%']);
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Si sélection d'une fiche
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM personnages WHERE ID_fiche = ?");
    $stmt->execute([$id]);
    $fiche = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>🗑️ Supprimer une fiche</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f9f9f9; }
        .container { max-width: 800px; margin: auto; background: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); padding: 30px; }
        h1 { text-align: center; color: #dc3545; }
        .alert { padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .fiche-list { margin: 20px 0; }
        .fiche-item { padding: 10px; border-bottom: 1px solid #eee; }
        .fiche-item a { color: #007bff; text-decoration: none; font-weight: bold; }
        .fiche-item a:hover { text-decoration: underline; }
        .fiche-summary { border: 1px solid #eee; border-radius: 6px; padding: 20px; background: #fafafa; margin-bottom: 20px; }
        .btn-supprimer { background: #dc3545; color: #fff; padding: 10px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        .btn-supprimer:hover { background: #b71c1c; }
        label { font-weight: bold; }
        input[type="text"] { padding: 8px; width: 100%; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
        form { margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🗑️ Supprimer une fiche</h1>

    <?php if ($message) echo $message; ?>

    <!-- Formulaire de recherche -->
    <form method="post">
        <label for="recherche">Rechercher une fiche par nom :</label>
        <input type="text" name="recherche" id="recherche" placeholder="Ex: Jean" value="<?= htmlspecialchars($_POST['recherche'] ?? '') ?>">
        <button type="submit" name="action" value="rechercher">🔍 Rechercher</button>
    </form>

    <?php
    // Affichage de la liste si plusieurs résultats
    if (!empty($resultats)) {
        echo "<div class='fiche-list'><strong>Sélectionnez une fiche à supprimer :</strong>";
        foreach ($resultats as $fiche) {
            echo "<div class='fiche-item'>
                    <a href='?id={$fiche['ID_fiche']}'>#{$fiche['ID_fiche']} ".htmlspecialchars($fiche['Nom'] ?? '')."</a>
                    <span style='color:#888;'>(".htmlspecialchars($fiche['Metier'] ?? '').")</span>
                  </div>";
        }
        echo "</div>";
    }
    ?>

    <?php
    // Affichage sommaire fiche + bouton suppression
    if ($fiche && !empty($fiche['ID_fiche'])): ?>
        <div class="fiche-summary">
            <h2>Fiche n°<?= $fiche['ID_fiche'] ?> - <?= htmlspecialchars($fiche['Nom'] ?? '') ?></h2>
            <p><strong>Métier :</strong> <?= htmlspecialchars($fiche['Metier'] ?? '') ?></p>
            <p><strong>Engagements :</strong> <?= htmlspecialchars($fiche['Engagements'] ?? '') ?></p>
            <p><strong>Détails :</strong> <?= htmlspecialchars(substr(strip_tags($fiche['Details'] ?? ''), 0, 120)) ?>...</p>
        </div>
        <form method="post"
            onsubmit="return confirm('⚠️ ATTENTION : la fiche n°<?= $fiche['ID_fiche'] ?> &quot;<?= htmlspecialchars($fiche['Nom'] ?? '') ?>&quot; va être DÉFINITIVEMENT supprimée.\n\nCette action est irréversible.\n\nConfirmez-vous la suppression ?');">
            <input type="hidden" name="id_fiche" value="<?= $fiche['ID_fiche'] ?>">
            <button type="submit" name="action" value="supprimer" class="btn-supprimer">🗑️ Supprimer la fiche</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
