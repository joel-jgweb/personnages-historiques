<?php
// import_csv_update.php - Mise à jour en masse depuis un CSV
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_statut'] != 1) {
    die("<h1 style='color: #dc3545; text-align: center; padding: 50px;'>⛔ Accès refusé</h1>");
}

require_once '../../www/config.php';
$dbPath = '../../data/portraits.sqlite';
$backupDir = '../../data/';
$csvFile = __DIR__ . '/update_data.csv';
$message = '';
$fichesModifiees = [];

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Erreur de connexion à la base de données : " . $e->getMessage());
}

// Fonction de journalisation
function logAction($msg) {
    $logFile = '../../data/import_log.txt';
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $_SESSION['user_login'] . " : $msg\n";
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

// Lire le CSV et comparer avec la base
function chargerFichesModifiees($pdo, $csvFile) {
    if (!file_exists($csvFile)) {
        throw new Exception("Fichier CSV 'update_data.csv' non trouvé dans le dossier admin.");
    }

    $fichesCsv = [];
    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
        // Lire l'en-tête
        $header = fgetcsv($handle, 0, ';');
        if (!$header || count($header) < 8) {
            throw new Exception("Format CSV invalide. L'en-tête doit contenir 8 colonnes.");
        }

        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            if (count($data) < 8) continue;
            $fichesCsv[] = [
                'ID_fiche' => $data[0],
                'Nom' => $data[1],
                'Metier' => $data[2],
                'Engagements' => $data[3],
                'Details' => $data[4],
                'Sources' => $data[5],
                'Donnees_genealogiques' => $data[6],
                'Iconographie' => $data[7]
            ];
        }
        fclose($handle);
    }

    $fichesModifiees = [];
    foreach ($fichesCsv as $ficheCsv) {
        $id = $ficheCsv['ID_fiche'];
        if (!is_numeric($id)) continue;

        // Récupérer la fiche actuelle en base
        $stmt = $pdo->prepare("SELECT * FROM personnages WHERE ID_fiche = ?");
        $stmt->execute([$id]);
        $ficheDb = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ficheDb) {
            // Fiche non trouvée en base → on l'ignore (on ne gère que les mises à jour)
            continue;
        }

        // Comparer les champs pertinents
        $champs = ['Nom', 'Metier', 'Engagements', 'Details', 'Sources', 'Donnees_genealogiques', 'Iconographie'];
        $estModifiee = false;
        foreach ($champs as $champ) {
            if (($ficheCsv[$champ] ?? '') !== ($ficheDb[$champ] ?? '')) {
                $estModifiee = true;
                break;
            }
        }

        if ($estModifiee) {
            $fichesModifiees[] = array_merge($ficheCsv, ['actuel' => $ficheDb]);
        }
    }

    return $fichesModifiees;
}

// Action : Afficher la liste des fiches modifiées
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $fichesModifiees = chargerFichesModifiees($pdo, $csvFile);
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>❌ " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Action : Mettre à jour les fiches sélectionnées
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_selected') {
    try {
        // Créer une sauvegarde
        $timestamp = date('Ymd_His');
        $backupFile = $timestamp . '_pre_import_portraits.sqlite';
        if (!copy($dbPath, $backupDir . $backupFile)) {
            throw new Exception("Échec de la création de la sauvegarde.");
        }
        logAction("Sauvegarde créée avant import CSV : $backupFile");

        $idsToUpdate = $_POST['fiches_a_mettre_a_jour'] ?? [];
        $count = 0;

        foreach ($idsToUpdate as $id) {
            if (!is_numeric($id)) continue;

            // Recharger les données depuis le CSV (sécurité)
            $fichesCsv = [];
            if (($handle = fopen($csvFile, 'r')) !== FALSE) {
                fgetcsv($handle, 0, ';'); // Skip header
                while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                    if (count($data) >= 8 && $data[0] == $id) {
                        $stmt = $pdo->prepare("
                            UPDATE personnages SET
                                Nom = ?,
                                Metier = ?,
                                Engagements = ?,
                                Details = ?,
                                Sources = ?,
                                Donnees_genealogiques = ?,
                                Iconographie = ?,
                                derniere_modif = ?
                            WHERE ID_fiche = ?
                        ");
                        $stmt->execute([
                            $data[1], $data[2], $data[3], $data[4],
                            $data[5], $data[6], $data[7],
                            date('Y-m-d H:i:s'),
                            $id
                        ]);
                        $count++;
                        break;
                    }
                }
                fclose($handle);
            }
        }

        logAction("Mise à jour de $count fiches depuis CSV.");
        $message = "<div class='alert alert-success'>✅ $count fiche(s) mise(s) à jour avec succès !</div>";
        $fichesModifiees = []; // Rafraîchir la liste

    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>🔄 Mise à jour depuis CSV</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f9f9f9; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #2c3e50; margin-bottom: 1.5rem; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .fiche-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #fafafa;
        }
        .fiche-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .fiche-title { font-weight: bold; font-size: 1.2rem; color: #2c3e50; }
        .fiche-id { background: #e9ecef; padding: 2px 8px; border-radius: 10px; font-size: 0.9rem; }
        .diff { background: #fff3cd; padding: 8px; margin: 8px 0; border-radius: 5px; font-size: 0.95rem; }
        .diff-label { font-weight: bold; color: #856404; }
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        .btn-primary { background: #007BFF; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .checkbox-cell { margin-right: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Mise à jour depuis CSV</h1>
        <p style="text-align: center; color: #6c757d;">
            Ce script compare le fichier <code>update_data.csv</code> avec la base actuelle et propose de mettre à jour les fiches modifiées.
        </p>

        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <?php if (empty($fichesModifiees)): ?>
            <div style="text-align: center; padding: 2rem; color: #6c757d;">
                <?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($message)): ?>
                    <p>✅ Aucune fiche modifiée détectée.</p>
                <?php endif; ?>
                <a href="index.php" class="btn" style="background: #6c757d; color: white; text-decoration: none;">← Retour au tableau de bord</a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_selected">
                <p><strong><?= count($fichesModifiees) ?> fiche(s) modifiée(s) détectée(s) :</strong></p>

                <?php foreach ($fichesModifiees as $fiche): ?>
                    <div class="fiche-item">
                        <div class="fiche-header">
                            <div>
                                <span class="fiche-title"><?= htmlspecialchars($fiche['Nom']) ?></span>
                                <span class="fiche-id">ID #<?= $fiche['ID_fiche'] ?></span>
                            </div>
                            <div class="checkbox-cell">
                                <input type="checkbox" name="fiches_a_mettre_a_jour[]" value="<?= $fiche['ID_fiche'] ?>" id="fiche_<?= $fiche['ID_fiche'] ?>" checked>
                            </div>
                        </div>

                        <?php
                        $champs = ['Nom', 'Metier', 'Engagements', 'Details', 'Sources', 'Donnees_genealogiques', 'Iconographie'];
                        foreach ($champs as $champ) {
                            $csvVal = $fiche[$champ] ?? '';
                            $dbVal = $fiche['actuel'][$champ] ?? '';
                            if ($csvVal !== $dbVal) {
                                echo '<div class="diff">';
                                echo '<span class="diff-label">' . htmlspecialchars($champ) . ' :</span><br>';
                                echo '<strong>CSV :</strong> ' . htmlspecialchars($csvVal ?: '—') . '<br>';
                                echo '<strong>Base :</strong> ' . htmlspecialchars($dbVal ?: '—');
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary" onclick="return confirm('⚠️ Confirmer la mise à jour des fiches sélectionnées ?\nUne sauvegarde sera créée automatiquement.');">
                    🔄 Mettre à jour les fiches sélectionnées
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>