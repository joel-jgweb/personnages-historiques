<?php
// execute_sql.php - Console SQL sécurisée avec sauvegarde, restauration et suppression
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'permissions.php';
checkUserPermission([1]);

require_once '../../www/bootstrap.php';
$dbPath = '../../data/portraits.sqlite';
$backupDir = '../../data/';
$logFile = $backupDir . 'sql_log.txt';
$message = '';
$result = null;
$backupFilename = '';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Erreur de connexion à la base de données : " . $e->getMessage());
}

function logAction($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] " . $_SESSION['user_login'] . " : $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// --- Action : Exécuter une requête SQL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'execute_sql') {
    $sql_query = trim($_POST['sql_query'] ?? '');
    if (empty($sql_query)) {
        $message = "<div class='alert alert-warning'>⚠️ Veuillez entrer une requête SQL.</div>";
    } else {
        try {
            $timestamp = date('Ymd_His');
            $backupFilename = $timestamp . '_portraits.sqlite';
            $backupPath = $backupDir . $backupFilename;

            if (!copy($dbPath, $backupPath)) {
                throw new Exception("Échec de la création de la sauvegarde.");
            }
            logAction("Sauvegarde créée : $backupFilename");

            if (stripos(trim($sql_query), 'select') === 0) {
                $stmt = $pdo->query($sql_query);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $rowCount = $stmt->rowCount();
                logAction("Requête SELECT exécutée : $sql_query (Résultats: $rowCount lignes)");
                $message = "<div class='alert alert-success'>✅ Requête SELECT exécutée avec succès. $rowCount ligne(s) retournée(s).</div>";
            } else {
                $rowCount = $pdo->exec($sql_query);
                logAction("Requête DML/DDL exécutée : $sql_query (Lignes affectées: $rowCount)");
                $message = "<div class='alert alert-success'>✅ Requête exécutée avec succès. $rowCount ligne(s) affectée(s).</div>";
            }
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
            logAction("Erreur lors de l'exécution : " . $e->getMessage());
        }
    }
}

// --- Action : Restaurer une sauvegarde ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_backup') {
    $backup_to_restore = $_POST['backup_file'] ?? '';
    if (empty($backup_to_restore) || !preg_match('/^\d{8}_\d{6}_portraits\.sqlite$/', $backup_to_restore)) {
        $message = "<div class='alert alert-warning'>⚠️ Sauvegarde invalide.</div>";
    } else {
        $backupPath = $backupDir . $backup_to_restore;
        if (!file_exists($backupPath)) {
            $message = "<div class='alert alert-danger'>❌ Le fichier de sauvegarde n'existe pas.</div>";
        } else {
            $preRestoreTimestamp = date('Ymd_His');
            $preRestoreBackup = $preRestoreTimestamp . '_pre_restore_portraits.sqlite';
            copy($dbPath, $backupDir . $preRestoreBackup);
            logAction("Sauvegarde de pré-restauration créée : $preRestoreBackup");

            if (!copy($backupPath, $dbPath)) {
                throw new Exception("Échec de la restauration.");
            }

            logAction("Base de données RESTAURÉE à partir de : $backup_to_restore");
            $message = "<div class='alert alert-success'>🎉 Base de données restaurée à partir de <strong>$backup_to_restore</strong> !</div>";
        }
    }
}

// --- Action : Supprimer une sauvegarde ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_backup') {
    $backup_to_delete = $_POST['backup_file'] ?? '';
    if (empty($backup_to_delete) || !preg_match('/^\d{8}_\d{6}_portraits\.sqlite$/', $backup_to_delete)) {
        $message = "<div class='alert alert-warning'>⚠️ Sauvegarde invalide.</div>";
    } else {
        $backupPath = $backupDir . $backup_to_delete;
        if (file_exists($backupPath) && unlink($backupPath)) {
            logAction("Sauvegarde supprimée : $backup_to_delete");
            $message = "<div class='alert alert-success'>🗑️ Sauvegarde <strong>$backup_to_delete</strong> supprimée avec succès.</div>";
        } else {
            $message = "<div class='alert alert-danger'>❌ Impossible de supprimer la sauvegarde.</div>";
        }
    }
}

// --- Récupérer la liste des sauvegardes ---
$backupFiles = [];
if ($handle = opendir($backupDir)) {
    while (false !== ($entry = readdir($handle))) {
        if (preg_match('/^\d{8}_\d{6}_portraits\.sqlite$/', $entry)) {
            $backupFiles[] = $entry;
        }
    }
    closedir($handle);
    rsort($backupFiles);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>🛠️ Console SQL - Exécution de requêtes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="container">
        <h1>🛠️ Console SQL - Exécution de requêtes</h1>
        <p style="text-align: center; color: #dc3545; font-weight: bold;">
            ⚠️ ATTENTION : Cet outil est réservé aux Super-Administrateurs. Une sauvegarde est automatiquement créée avant chaque exécution.
        </p>

        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <!-- Exécution de requête -->
        <h2 class="section-title">📝 Exécuter une requête SQL</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="execute_sql">
            <div class="form-group">
                <label for="sql_query">Requête SQL :</label>
                <textarea name="sql_query" id="sql_query" placeholder="Ex: SELECT * FROM personnages LIMIT 5;"><?= htmlspecialchars($_POST['sql_query'] ?? '') ?></textarea>
            </div>
            <?php if (!empty($backupFilename)): ?>
                <div class="alert alert-success">
                    ✅ Sauvegarde créée : <strong><?= $backupFilename ?></strong><br>
                    <a href="download_backup.php?file=<?= urlencode($backupFilename) ?>" class="btn-download">📥 Télécharger la sauvegarde</a>
                </div>
            <?php endif; ?>
            <button type="submit" class="btn-execute" onclick="return confirm('⚠️ Êtes-vous ABSOLUMENT sûr ?\nUne sauvegarde a été créée, mais les changements peuvent être irréversibles.');">
                ⚡ Exécuter la requête
            </button>
        </form>

        <!-- Restauration -->
        <h2 class="section-title">🔙 Restaurer une sauvegarde</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="restore_backup">
            <div class="form-group">
                <label for="backup_file">Sélectionner une sauvegarde à restaurer :</label>
                <select name="backup_file" id="backup_file" required>
                    <option value="">-- Choisir une sauvegarde --</option>
                    <?php foreach ($backupFiles as $file): ?>
                        <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-restore" onclick="return confirm('⚠️ Êtes-vous ABSOLUMENT sûr de vouloir RESTAURER la base ?\nToutes les modifications récentes seront PERDUES.');">
                🔄 Restaurer la base de données
            </button>
        </form>

        <!-- Liste des sauvegardes avec suppression -->
        <h2 class="section-title">🗄️ Sauvegardes disponibles (<?= count($backupFiles) ?>)</h2>
        <div class="backup-list">
            <?php if (empty($backupFiles)): ?>
                <p>Aucune sauvegarde trouvée.</p>
            <?php else: ?>
                <?php foreach ($backupFiles as $file): ?>
                    <div class="backup-item">
                        <span><?= htmlspecialchars($file) ?></span>
                        <div class="backup-actions">
                            <a href="download_backup.php?file=<?= urlencode($file) ?>" class="btn-download">📥 Télécharger</a>
                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('🗑️ Supprimer définitivement cette sauvegarde ?');">
                                <input type="hidden" name="action" value="delete_backup">
                                <input type="hidden" name="backup_file" value="<?= htmlspecialchars($file) ?>">
                                <button type="submit" class="btn-delete">🗑️ Supprimer</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Résultats SELECT -->
        <?php if ($result !== null && !empty($result)): ?>
            <h2 class="section-title">📊 Résultats de la requête</h2>
            <table class="result-table">
                <thead>
                    <tr>
                        <?php foreach (array_keys($result[0]) as $column): ?>
                            <th><?= htmlspecialchars($column) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td><?= htmlspecialchars($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($result !== null && empty($result)): ?>
            <p>Aucun résultat à afficher.</p>
        <?php endif; ?>

        <a href="index.php" style="display: inline-block; margin-top: 2rem; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;">← Retour au tableau de bord</a>
    </div>
</body>
</html>