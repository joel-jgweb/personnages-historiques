<?php
// diagnostic_sqlite.php - Script de diagnostic de base de données SQLite

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'permissions.php';
checkUserPermission([1]); // Seul le statut 1 (super-administrateur) est autorisé

// --- Paramètre de connexion à la base de données SQLite ---
// Chemin corrigé : ../../data/portraits.sqlite
$databaseFile = '../../data/portraits.sqlite';

// --- Vérification de l'existence du fichier ---
if (!file_exists($databaseFile)) {
    die("<h1>❌ ERREUR FATALE</h1><p>Le fichier de base de données <code>" . htmlspecialchars($databaseFile) . "</code> est introuvable.</p>");
}

// --- Connexion à la base de données ---
try {
    // Utilisation de l'objet PDO pour la connexion
    $pdo = new PDO("sqlite:$databaseFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h1>📊 Diagnostic de la base de données : " . htmlspecialchars(basename($databaseFile)) . "</h1>";
    echo "<p>✅ Connexion réussie à la base de données.</p>";
} catch (PDOException $e) {
    die("<h1>❌ ERREUR DE CONNEXION</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}

// --- Obtenir le schéma de la base de données (tables et colonnes) ---
echo "<h2>📖 Schéma des tables</h2>";
try {
    // Requête pour lister toutes les tables
    $query = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $query->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "<p>⚠️ Aucune table trouvée dans la base de données.</p>";
    } else {
        foreach ($tables as $tableName) {
            echo "<h3>🗃️ Table : <code>" . htmlspecialchars($tableName) . "</code></h3>";
            // Requête pour obtenir la définition des colonnes de chaque table
            $query = $pdo->query("PRAGMA table_info(" . $pdo->quote($tableName) . ")");
            $columns = $query->fetchAll(PDO::FETCH_ASSOC);

            if (empty($columns)) {
                echo "<p>Aucune colonne trouvée pour cette table.</p>";
                continue;
            }

            echo "<table border='1' cellpadding='8' cellspacing='0' style='width:100%; border-collapse: collapse; margin: 1rem 0;'>";
            echo "<thead><tr style='background-color: #f2f2f2;'>";
            echo "<th style='text-align: left; padding: 8px;'>Nom</th>";
            echo "<th style='text-align: left; padding: 8px;'>Type</th>";
            echo "<th style='text-align: left; padding: 8px;'>NOT NULL</th>";
            echo "<th style='text-align: left; padding: 8px;'>Clé Primaire</th>";
            echo "<th style='text-align: left; padding: 8px;'>Valeur par défaut</th>";
            echo "</tr></thead><tbody>";
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td style='padding: 8px;'>" . htmlspecialchars($column['name']) . "</td>";
                echo "<td style='padding: 8px;'>" . htmlspecialchars($column['type']) . "</td>";
                echo "<td style='padding: 8px;'>" . ($column['notnull'] ? '✅ Oui' : '❌ Non') . "</td>";
                echo "<td style='padding: 8px;'>" . ($column['pk'] ? '✅ Oui (Ordre: ' . $column['pk'] . ')' : '❌ Non') . "</td>";
                echo "<td style='padding: 8px;'>" . (isset($column['dflt_value']) ? '<code>' . htmlspecialchars($column['dflt_value']) . '</code>' : '<em>NULL</em>') . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
    }
} catch (PDOException $e) {
    echo "<p>❌ Erreur lors de la récupération du schéma : " . htmlspecialchars($e->getMessage()) . "</p>";
}

// --- Compter les enregistrements par table ---
echo "<h2>🔢 Nombre d'enregistrements par table</h2>";
try {
    // Requête pour lister toutes les tables
    $query = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;");
    $tables = $query->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "<p>⚠️ Aucune table trouvée pour le comptage.</p>";
    } else {
        echo "<table border='1' cellpadding='8' cellspacing='0' style='width:50%; border-collapse: collapse; margin: 1rem 0;'>";
        echo "<thead><tr style='background-color: #f2f2f2;'>";
        echo "<th style='text-align: left; padding: 8px;'>Table</th>";
        echo "<th style='text-align: left; padding: 8px;'>Nombre de lignes</th>";
        echo "</tr></thead><tbody>";
        foreach ($tables as $tableName) {
            // Correction : Ne PAS utiliser quote() pour les noms de table dans SELECT COUNT(*)
            $countQuery = $pdo->query("SELECT COUNT(*) as count FROM `" . str_replace('`', '``', $tableName) . "`");
            $count = $countQuery->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<tr>";
            echo "<td style='padding: 8px;'><code>" . htmlspecialchars($tableName) . "</code></td>";
            echo "<td style='padding: 8px;'><strong>" . number_format($count) . "</strong></td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    }
} catch (PDOException $e) {
    echo "<p>❌ Erreur lors du comptage des enregistrements : " . htmlspecialchars($e->getMessage()) . "</p>";
}

// --- Informations complémentaires sur la base ---
echo "<h2>ℹ️ Informations système</h2>";
echo "<ul>";
echo "<li><strong>Chemin absolu du fichier :</strong> " . realpath($databaseFile) . "</li>";
echo "<li><strong>Taille du fichier :</strong> " . number_format(filesize($databaseFile)) . " octets</li>";
echo "<li><strong>Date de dernière modification :</strong> " . date("d/m/Y H:i:s", filemtime($databaseFile)) . "</li>";
echo "<li><strong>Permissions du fichier :</strong> " . substr(sprintf('%o', fileperms($databaseFile)), -4) . "</li>";
echo "</ul>";
?>