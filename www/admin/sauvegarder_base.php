<?php
/**
 * sauvegarder_base.php
 *
 * Crée une sauvegarde de la base de données et l'enregistre dans ../../sauv/
 * Usage: depuis www/admin/index.php ou accès direct (contrôles d'accès légers inclus)
 *
 * Le script tente d'inclure les fichiers de configuration communs pour récupérer
 * les identifiants de la base de données. Ensuite il essaie d'utiliser mysqldump
 * si disponible, sinon il génère une sauvegarde via PDO.
 *
 * Attention: adaptez l'inclusion du fichier de config si votre projet utilise un
 * autre chemin/format.
 */

session_start();

// Contrôle d'accès basique — adaptez selon votre application
$allowedStatuts = [1,2,6];
if (isset($_SESSION['statut'])) {
    if (!in_array((int)$_SESSION['statut'], $allowedStatuts, true)) {
        http_response_code(403);
        echo "Accès refusé\n";
        exit;
    }
} else {
    // si vous préférez empêcher l'accès sans session
    // http_response_code(403); echo "Accès non authentifié\n"; exit;
}

// Emplacements possibles du fichier de configuration (depuis www/admin)
$configCandidates = [
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../../config/database.php',
    __DIR__ . '/../../config/config.php',
    __DIR__ . '/../../config/parameters.php',
    __DIR__ . '/../../config/connection.php',
];

$db = [
    'host' => null,
    'user' => null,
    'pass' => null,
    'name' => null,
    'port' => null,
    'charset' => 'utf8mb4',
];

// Try to include config files and extract common variables
foreach ($configCandidates as $cfgFile) {
    if (!file_exists($cfgFile)) continue;
    // Attempt to include without overwriting existing variables
    $maybe = @include $cfgFile;
    // If the included file returns an array, inspect it
    if (is_array($maybe)) {
        $arr = $maybe;
    } else {
        // Otherwise, try to collect variables defined by the include
        // This is best-effort; include may define constants or variables
        $arr = [];
    }

    // Common keys mapping
    $candidatesMap = [
        ['host','db_host','DB_HOST','hostname','server'],
        ['user','db_user','DB_USER','username'],
        ['pass','db_pass','DB_PASS','password'],
        ['name','db_name','DB_NAME','database'],
        ['port','db_port','DB_PORT'],
    ];

    // Check returned array first
    foreach ($candidatesMap as $map) {
        foreach ($map as $k) {
            if (isset($arr[$k]) && $arr[$k]) {
                $targetKey = $map[0];
                $db[$targetKey] = $arr[$k];
                break 2;
            }
        }
    }

    // Try global variables and constants
    foreach ($candidatesMap as $map) {
        $targetKey = $map[0];
        if ($db[$targetKey]) continue;
        foreach ($map as $k) {
            if (isset($GLOBALS[$k]) && $GLOBALS[$k]) {
                $db[$targetKey] = $GLOBALS[$k];
                break 2;
            }
            if (defined($k)) {
                $val = constant($k);
                if ($val) { $db[$targetKey] = $val; break 2; }
            }
        }
    }

    // If the include created variables like $config, try to inspect them
    if (isset($config) && is_array($config)) {
        foreach ($candidatesMap as $map) {
            $targetKey = $map[0];
            foreach ($map as $k) {
                if (isset($config[$k]) && $config[$k]) {
                    $db[$targetKey] = $config[$k]; break 2;
                }
                if (isset($config['db'][$k]) && $config['db'][$k]) {
                    $db[$targetKey] = $config['db'][$k]; break 2;
                }
            }
        }
    }

    // Quick sanity check
    if ($db['host'] || $db['user'] || $db['name']) break;
}

// If we still don't have credentials, try environment variables
$db['host'] = $db['host'] ?? getenv('DB_HOST') ?: getenv('MYSQL_HOST');
$db['user'] = $db['user'] ?? getenv('DB_USER') ?: getenv('MYSQL_USER');
$db['pass'] = $db['pass'] ?? getenv('DB_PASS') ?: getenv('MYSQL_PASSWORD');
$db['name'] = $db['name'] ?? getenv('DB_NAME') ?: getenv('MYSQL_DATABASE');
$db['port'] = $db['port'] ?? getenv('DB_PORT');

// Final basic validation
if (empty($db['name']) || empty($db['user'])) {
    http_response_code(500);
    echo "Impossible de déterminer les identifiants de la base de données.\n";
    echo "Vérifiez vos fichiers de configuration dans ../config/ ou passez les variables d'environnement.\n";
    exit;
}

// Destination directory (depuis www/admin -> ../../sauv)
$destDir = realpath(__DIR__ . '/../../sauv');
if ($destDir === false) {
    // Try to create if absent
    $try = __DIR__ . '/../../sauv';
    if (!is_dir($try)) {
        if (!mkdir($try, 0755, true)) {
            http_response_code(500);
            echo "Impossible de créer le répertoire de sauvegarde: $try\n";
            exit;
        }
    }
    $destDir = realpath($try);
}

$date = (new DateTime())->format('Ymd_His');
$baseName = 'sauvegarde_db_' . $date . '.sql';
$gzName = $baseName . '.gz';
$pathSql = $destDir . DIRECTORY_SEPARATOR . $baseName;
$pathGz = $destDir . DIRECTORY_SEPARATOR . $gzName;

// First attempt: use mysqldump if available
$useMysqldump = false;
$mysqldump = trim(shell_exec('which mysqldump 2>/dev/null'));
if ($mysqldump) {
    $useMysqldump = true;
}

// Build command safely
if ($useMysqldump) {
    $cmdParts = [];
    $cmdParts[] = escapeshellcmd($mysqldump);
    if (!empty($db['host'])) $cmdParts[] = '-h ' . escapeshellarg($db['host']);
    if (!empty($db['port'])) $cmdParts[] = '-P ' . escapeshellarg($db['port']);
    if (!empty($db['user'])) $cmdParts[] = '-u ' . escapeshellarg($db['user']);
    // Avoid putting password on command line when possible — use env var
    if (!empty($db['pass'])) {
        // Many systems support MYSQL_PWD env var (though it's insecure)
        $env = 'MYSQL_PWD=' . escapeshellarg($db['pass']);
    } else {
        $env = '';
    }
    $cmdParts[] = escapeshellarg($db['name']);
    $cmd = ($env ? $env . ' ' : '') . implode(' ', $cmdParts) . ' 2>&1';

    // Execute and capture output
    $output = [];
    $returnVar = 0;
    // Write directly to gz file if gzip available
    $gzip = trim(shell_exec('which gzip 2>/dev/null'));
    if ($gzip) {
        // pipe mysqldump to gzip
        $cmdGz = ($env ? $env . ' ' : '') . implode(' ', $cmdParts) . ' | ' . escapeshellcmd($gzip) . ' > ' . escapeshellarg($pathGz) . ' 2>&1';
        exec($cmdGz, $output, $returnVar);
        if ($returnVar === 0) {
            echo "Sauvegarde créée: $pathGz\n";
            exit;
        }
    }

    // Fallback: write plain sql file
    exec($cmd . ' > ' . escapeshellarg($pathSql), $output, $returnVar);
    if ($returnVar === 0 && file_exists($pathSql)) {
        echo "Sauvegarde créée: $pathSql\n";
            exit;
    }
    // If mysqldump failed, continue to PDO fallback
    error_log("mysqldump failed: " . implode("\n", $output));
}

// Fallback: dump via PDO
try {
    $dsn = 'mysql:host=' . ($db['host'] ?: '127.0.0.1');
    if (!empty($db['port'])) $dsn .= ';port=' . $db['port'];
    $dsn .= ';dbname=' . $db['name'] . ';charset=' . $db['charset'];
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]);

    $fh = fopen($pathSql, 'w');
    if (!$fh) throw new RuntimeException('Impossible d ouvrir le fichier de sortie');

    // Write header
    fwrite($fh, '-- Sauvegarde SQL\n');
    fwrite($fh, '-- Base: ' . $db['name'] . '\n');
    fwrite($fh, '-- Date: ' . date('c') . '\n\n');

    // Get tables
    $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        // CREATE TABLE
        $row = $pdo->query('SHOW CREATE TABLE ' . $pdo->quote($table))->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $create = array_values($row)[1] ?? null;
            if ($create) {
                fwrite($fh, "-- Structure de la table $table\n");
                fwrite($fh, "DROP TABLE IF EXISTS `$table`;
");
                fwrite($fh, $create . ";\n\n");
            }
        }

        // DATA
        fwrite($fh, "-- Données de la table $table\n");
        $stmt = $pdo->query('SELECT * FROM ' . $pdo->quote($table));
        $cols = [];
        $first = true;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($first) {
                $cols = array_keys($row);
                $first = false;
            }
            $values = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote($v);
            }, array_values($row));
            $line = "INSERT INTO `$table` (`" . implode('`,`', $cols) . `) VALUES (` . implode(',', $values) . `);\n";
            fwrite($fh, $line);
        }
        fwrite($fh, "\n");
    }
    fclose($fh);

    // Optionally gzip
    $gzip = trim(shell_exec('which gzip 2>/dev/null'));
    if ($gzip) {
        $cmdGzip = escapeshellcmd($gzip) . ' ' . escapeshellarg($pathSql);
        exec($cmdGzip, $o, $rv);
        if ($rv === 0 && file_exists($pathGz)) {
            // Remove uncompressed file if gzip succeeded
            if (file_exists($pathSql)) @unlink($pathSql);
            echo "Sauvegarde créée: $pathGz\n";
            exit;
        }
    }

    echo "Sauvegarde créée: $pathSql\n";
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo "Erreur lors de la sauvegarde: " . $e->getMessage() . "\n";
    exit;
}