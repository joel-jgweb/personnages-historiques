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

// Définition des champs qui seront mis à jour
// ASSUMÉ : Ces 7 colonnes correspondent aux colonnes 1 à 7 du CSV
$champsAUpdate = ['Nom', 'Metier', 'Engagements', 'Details', 'Sources', 'Donnees_genealogiques', 'Iconographie'];

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
function chargerFichesModifiees($pdo, $csvFile, $champsAUpdate) {
    if (!file_exists($csvFile)) {
        throw new Exception("Fichier CSV 'update_data.csv' non trouvé dans le dossier admin.");
    }

    $fichesCsv = [];
    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
        // Lire l'en-tête
        $header = fgetcsv($handle, 0, ';');
        // L'en-tête doit contenir 8 colonnes : ID_fiche (0) + les 7 champs à mettre à jour
        if (!$header || count($header) < 8) {
            throw new Exception("Format CSV invalide. L'en-tête doit contenir au moins 8 colonnes (ID_fiche, plus les 7 champs de mise à jour).");
        }

        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            if (count($data) < 8) continue;
            
            // On mappe les données aux noms de colonnes exacts de la table 'personnages'
            $ficheData = [
                'ID_fiche'              => $data[0],
                'Nom'                   => $data[1],
                'Parcours_professionnel'=> $data[2], // Anciennement 'Metier'
                'Parcours_syndical'     => $data[3], // Anciennement 'Engagements'
                'Details'               => $data[4],
                'Sources'               => $data[5],
                'Donnees_genealogiques' => $data[6],
                'Iconographie'          => $data[7]
            ];
            $fichesCsv[] = $ficheData;
        }
        fclose($handle);
    }

    $fichesModifiees = [];
    foreach ($fichesCsv as $ficheCsv) {
        $id = $ficheCsv['ID_fiche'];
        if (!is_numeric($id)) continue;

        // Récupérer la fiche actuelle en base
        // On sélectionne uniquement les champs qui nous intéressent pour optimiser
        $fieldsForSelect = 'ID_fiche, ' . implode(', ', $champsAUpdate);
        $stmt = $pdo->prepare("SELECT $fieldsForSelect FROM personnages WHERE ID_fiche = ?");
        $stmt->execute([$id]);
        $ficheDb = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ficheDb) {
            // Fiche non trouvée en base → on l'ignore (on ne gère que les mises à jour)
            continue;
        }

        // Comparer les champs pertinents
        $estModifiee = false;
        foreach ($champsAUpdate as $champ) {
            // Comparaison simple, en s'assurant que les clés existent
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
        $fichesModifiees = chargerFichesModifiees($pdo, $csvFile, $champsAUpdate);
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

        // Préparer la requête UPDATE en utilisant uniquement les champsAUpdate
        $setClauses = [];
        $paramMarkers = [];
        foreach ($champsAUpdate as $champ) {
            $setClauses[] = "$champ = ?";
            $paramMarkers[] = $champ;
        }
        $setClause = implode(', ', $setClauses);
        
        $sql = "
            UPDATE personnages SET
                $setClause,
                derniere_modif = ?
            WHERE ID_fiche = ?
        ";
        $stmtUpdate = $pdo->prepare($sql);

        foreach ($idsToUpdate as $id) {
            if (!is_numeric($id)) continue;

            // Recharger les données depuis le CSV (sécurité et fraîcheur)
            if (($handle = fopen($csvFile, 'r')) !== FALSE) {
                fgetcsv($handle, 0, ';'); // Skip header
                while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                    if (count($data) >= 8 && $data[0] == $id) {
                        
                        // Mappage des données du CSV
                        $dataMap = [
                            'ID_fiche'              => $data[0],
                            'Nom'                   => $data[1],
                            'Parcours_professionnel'=> $data[2],
                            'Parcours_syndical'     => $data[3],
                            'Details'               => $data[4],
                            'Sources'               => $data[5],
                            'Donnees_genealogiques' => $data[6],
                            'Iconographie'          => $data[7]
                        ];
                        
                        // Créer le tableau de paramètres pour l'exécution
                        $params = [];
                        foreach ($paramMarkers as $marker) {
                            $params[] = $dataMap[$marker];
                        }
                        
                        // Ajouter le timestamp et l'ID_fiche pour les deux derniers marqueurs '?'
                        $params[] = date('Y-m-d H:i:s');
                        $params[] = $id;

                        $stmtUpdate->execute($params);
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
    <link rel="stylesheet" href="admin.css">
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
                        // Les champs à afficher pour la différence sont maintenant pris de $champsAUpdate
                        foreach ($champsAUpdate as $champ) {
                            // On utilise les noms de colonnes corrects pour l'affichage
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
