<?php
// execute_sql.php - Console SQL sécurisée avec sauvegarde, restauration et suppression
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'permissions.php';
checkUserPermission([1]);

require_once '../../www/config.php';
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
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
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
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; font-weight: bold; margin-bottom: 0.5rem; color: #555; }
        textarea {
            width: 100%;
            height: 200px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 1rem;
            resize: vertical;
        }
        button {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            margin-top: 1rem;
        }
        .btn-execute { background-color: #dc3545; color: white; }
        .btn-execute:hover { background-color: #c82333; }
        .btn-restore { background-color: #fd7e14; color: white; }
        .btn-restore:hover { background-color: #e06c0c; }
        .btn-delete { background-color: #6c757d; color: white; }
        .btn-delete:hover { background-color: #5a6268; }
        .backup-list { margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 10px; }
        .backup-item {
            padding: 10px;
            margin-bottom: 5px;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .backup-actions { display: flex; gap: 8px; }
        .backup-item a, .backup-item button {
            padding: 6px 12px;
            font-size: 0.9rem;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-download { background-color: #28a745; color: white; }
        .btn-download:hover { background-color: #218838; }
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
            font-size: 0.9rem;
        }
        .result-table th, .result-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .result-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .result-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .section-title {
            border-bottom: 2px solid #007BFF;
            padding-bottom: 0.5rem;
            margin: 2rem 0 1rem 0;
            color: #333;
        }
    </style>
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