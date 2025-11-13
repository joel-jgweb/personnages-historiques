<?php
/**
 * sauvegarder_base.php
 *
 * Sauvegarde de la base de données (adaptée au projet : priorité SQLite, fallback MySQL)
 * Affiche un résultat avec mise en page cohérente avec l'admin et propose un bouton "Retour".
 *
 * Emplacement d'écriture : ../../sauv/ (au même niveau que config/ data/ www/)
 *
 * NOTE : cette version n'affiche PAS les chemins absolus du serveur pour des raisons de sécurité.
 */

session_start();

// --- Contrôle d'accès (accepte user_statut ou statut) ---
$allowedStatuts = [1, 2, 6];
$sessionStatut = null;
if (isset($_SESSION['user_statut'])) {
    $sessionStatut = $_SESSION['user_statut'];
} elseif (isset($_SESSION['statut'])) {
    $sessionStatut = $_SESSION['statut'];
}

// Autoriser en CLI pour tests rapides
if (php_sapi_name() === 'cli' && $sessionStatut === null) {
    $sessionStatut = 1;
}

if ($sessionStatut === null || !in_array((int)$sessionStatut, $allowedStatuts, true)) {
    // Affichage HTML minimal pour les cas d'accès refusé
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Accès refusé</title>';
    echo '<link rel="stylesheet" href="admin.css">';
    echo '</head><body class="index-page"><div class="container">';
    echo '<div class="header"><h1>🔐 Administration</h1></div>';
    echo '<div class="result error"><h2>⛔ Accès refusé</h2><p>Vous n êtes pas autorisé à effectuer cette opération.</p></div>';
    echo '<p><a class="button" href="index.php">← Retour</a></p>';
    echo '</div></body></html>';
    exit;
}

// Helper pour afficher le résultat avec mise en page admin
function renderPage(string $title, string $statusHtml, string $detailsHtml = ''): void {
    // NOTE: on suppose que admin.css fournit les styles .container, .header, .result, .success, .error, .button
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . ' — Administration</title>';
    echo '<link rel="stylesheet" href="admin.css">';
    // petit ajustement local si admin.css n a pas de bouton : garantir un rendu propre
    echo '<style>
        .result { padding: 1rem; border-radius: 6px; margin: 1rem 0; }
        .result.success { background:#e6ffed; border:1px solid #b9f0c6; color:#064e2d; }
        .result.error { background:#ffecec; border:1px solid #f0b6b6; color:#601010; }
        .result pre { background: #f7f7f7; padding: .75rem; border-radius:4px; overflow:auto; }
        .actions { margin-top:1rem; }
        .button, .btn { display:inline-block; padding:.5rem .9rem; border-radius:4px; background:#2c3e50; color:#fff; text-decoration:none; }
        .button.secondary { background:#6c757d; }
    </style>';
    echo '</head><body class="index-page"><div class="container">';
    echo '<div class="header"><h1>🔐 Administration</h1><p>Résultat de la sauvegarde</p></div>';
    echo $statusHtml;
    if ($detailsHtml) {
        echo '<div class="details">' . $detailsHtml . '</div>';
    }
    echo '<div class="actions"><a class="button" href="index.php">← Retour à l administration</a></div>';
    echo '</div></body></html>';
    exit;
}

// helper pour fournir des infos sûres sur un fichier (sans afficher de chemin absolu)
function safeFileDetails(string $filepath): string {
    if (!file_exists($filepath)) return '';
    $name = basename($filepath);
    $size = @filesize($filepath);
    $sizeStr = $size !== false ? number_format($size / 1024, 2) . ' Ko' : 'N/A';
    $mtime = @filemtime($filepath);
    $dateStr = $mtime !== false ? date('Y-m-d H:i:s', $mtime) : 'N/A';
    return '<p>Fichier : ' . htmlspecialchars($name) . '</p><p>Taille : ' . htmlspecialchars($sizeStr) . '</p><p>Créé : ' . htmlspecialchars($dateStr) . '</p>';
}

// Préparer répertoire de destination
$destDirCandidate = __DIR__ . '/../../sauv';
if (!is_dir($destDirCandidate)) {
    if (!@mkdir($destDirCandidate, 0755, true)) {
        renderPage('Erreur', '<div class="result error"><h2>❌ Erreur</h2><p>Impossible de créer le répertoire de sauvegarde.</p></div>');
    }
}
$destDir = realpath($destDirCandidate);
if ($destDir === false) {
    renderPage('Erreur', '<div class="result error"><h2>❌ Erreur</h2><p>Répertoire de sauvegarde invalide.</p></div>');
}

date_default_timezone_set('UTC');
$date = (new DateTime())->format('Ymd_His');

// ----------------- Partie SQLite -----------------
function findSqlitePath(): ?string {
    $candidate = __DIR__ . '/../../data/portraits.sqlite';
    if (file_exists($candidate)) return $candidate;

    $cfgLocal = __DIR__ . '/../../config/config.local.php';
    if (file_exists($cfgLocal)) {
        $maybe = @include $cfgLocal;
        if (is_array($maybe)) {
            if (!empty($maybe['database_path']) && file_exists($maybe['database_path'])) return $maybe['database_path'];
            if (!empty($maybe['database']) && file_exists($maybe['database'])) return $maybe['database'];
        }
    }

    $cfg = __DIR__ . '/../../config/config.php';
    if (file_exists($cfg)) {
        $maybe = @include $cfg;
        if (is_array($maybe)) {
            if (!empty($maybe['database_path']) && file_exists($maybe['database_path'])) return $maybe['database_path'];
        }
    }

    return null;
}

$sqlitePath = findSqlitePath();
if ($sqlitePath !== null && file_exists($sqlitePath)) {
    $baseName = 'portraits_' . $date . '.sqlite';
    $destFile = $destDir . DIRECTORY_SEPARATOR . $baseName;

    if (!@copy($sqlitePath, $destFile)) {
        // don't display absolute paths; show safe info only
        renderPage('Erreur', '<div class="result error"><h2>❌ Échec</h2><p>Impossible de copier le fichier SQLite. Vérifiez les permissions du dossier de sauvegarde.</p></div>',
            '<pre>Source: ' . htmlspecialchars(basename($sqlitePath)) . "\nDestination: " . htmlspecialchars($baseName) . "</pre>");
    }

    // tenter gzip via gzencode (portable)
    if (function_exists('gzencode')) {
        $data = @file_get_contents($destFile);
        if ($data !== false) {
            $gz = @gzencode($data, 9);
            if ($gz !== false && @file_put_contents($destFile . '.gz', $gz) !== false) {
                @unlink($destFile);
                $filename = basename($destFile . '.gz');
                $relative = '../../sauv/' . rawurlencode($filename);
                $status = '<div class="result success"><h2>✅ Sauvegarde créée</h2><p>La sauvegarde SQLite a été créée et compressée.</p></div>';
                $details = safeFileDetails($destFile . '.gz');
                $details .= '<p>Télécharger : <a href="' . htmlspecialchars($relative) . '">' . htmlspecialchars($filename) . '</a></p>';
                renderPage('Sauvegarde réussie', $status, $details);
            } else {
                // gzip échoué, garder le .sqlite
                $status = '<div class="result success"><h2>✅ Sauvegarde créée</h2><p>La sauvegarde SQLite a été créée (compression impossible).</p></div>';
                $details = safeFileDetails($destFile);
                $details .= '<p>Le fichier est disponible dans le dossier de sauvegarde.</p>';
                renderPage('Sauvegarde réussie', $status, $details);
            }
        } else {
            $status = '<div class="result success"><h2>✅ Sauvegarde créée</h2><p>La sauvegarde SQLite a été copiée mais la compression a échoué.</p></div>';
            $details = safeFileDetails($destFile);
            renderPage('Sauvegarde partielle', $status, $details);
        }
    } else {
        $status = '<div class="result success"><h2>✅ Sauvegarde créée</h2><p>La sauvegarde SQLite a été copiée (compression non disponible).</p></div>';
        $details = safeFileDetails($destFile);
        renderPage('Sauvegarde réussie', $status, $details);
    }
}

// ----------------- Partie MySQL (fallback) -----------------
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

$candidatesMap = [
    ['host','db_host','DB_HOST','hostname','server'],
    ['user','db_user','DB_USER','username'],
    ['pass','db_pass','DB_PASS','password'],
    ['name','db_name','DB_NAME','database'],
    ['port','db_port','DB_PORT'],
];

foreach ($configCandidates as $cfgFile) {
    if (!file_exists($cfgFile)) continue;
    $maybe = @include $cfgFile;
    $arr = is_array($maybe) ? $maybe : [];

    foreach ($candidatesMap as $map) {
        foreach ($map as $k) {
            if (isset($arr[$k]) && $arr[$k]) {
                $db[$map[0]] = $arr[$k];
                break 2;
            }
        }
    }

    if (isset($config) && is_array($config)) {
        foreach ($candidatesMap as $map) {
            foreach ($map as $k) {
                if (isset($config[$k]) && $config[$k]) {
                    $db[$map[0]] = $config[$k];
                    break 2;
                }
                if (isset($config['db'][$k]) && $config['db'][$k]) {
                    $db[$map[0]] = $config['db'][$k];
                    break 2;
                }
            }
        }
    }
    if (isset($localConfig) && is_array($localConfig)) {
        foreach ($candidatesMap as $map) {
            foreach ($map as $k) {
                if (isset($localConfig[$k]) && $localConfig[$k]) {
                    $db[$map[0]] = $localConfig[$k];
                    break 2;
                }
            }
        }
    }

    if ($db['host'] || $db['user'] || $db['name']) break;
}

// environment fallback
$db['host'] = $db['host'] ?? (getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: null);
$db['user'] = $db['user'] ?? (getenv('DB_USER') ?: getenv('MYSQL_USER') ?: null);
$db['pass'] = $db['pass'] ?? (getenv('DB_PASS') ?: getenv('MYSQL_PASSWORD') ?: null);
$db['name'] = $db['name'] ?? (getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: null);
$db['port'] = $db['port'] ?? getenv('DB_PORT');

if (empty($db['name']) || empty($db['user'])) {
    renderPage('Erreur', '<div class="result error"><h2>❌ Impossible</h2><p>Impossible de déterminer les identifiants de la base MySQL.</p></div>',
        '<pre>Vérifiez vos fichiers de configuration dans ../config/ ou les variables d environnement.</pre>');
}

// attempt mysqldump
$mysqldump = trim(@shell_exec('which mysqldump 2>/dev/null'));
$gzip = trim(@shell_exec('which gzip 2>/dev/null'));
$baseName = 'sauvegarde_db_' . $date . '.sql';
$gzName = $baseName . '.gz';
$pathSql = $destDir . DIRECTORY_SEPARATOR . $baseName;
$pathGz = $destDir . DIRECTORY_SEPARATOR . $gzName;

if ($mysqldump) {
    $cmdParts = [];
    $cmdParts[] = escapeshellcmd($mysqldump);
    if (!empty($db['host'])) $cmdParts[] = '-h ' . escapeshellarg($db['host']);
    if (!empty($db['port'])) $cmdParts[] = '-P ' . escapeshellarg($db['port']);
    if (!empty($db['user'])) $cmdParts[] = '-u ' . escapeshellarg($db['user']);

    if (!empty($db['pass'])) {
        $env = 'MYSQL_PWD=' . escapeshellarg($db['pass']);
    } else {
        $env = '';
    }
    $cmdParts[] = escapeshellarg($db['name']);

    if ($gzip) {
        $cmdGz = ($env ? $env . ' ' : '') . implode(' ', $cmdParts) . ' | ' . escapeshellcmd($gzip) . ' > ' . escapeshellarg($pathGz) . ' 2>&1';
        exec($cmdGz, $output, $returnVar);
        if ($returnVar === 0 && file_exists($pathGz)) {
            $filename = basename($pathGz);
            $relative = '../../sauv/' . rawurlencode($filename);
            $status = '<div class="result success"><h2>✅ Sauvegarde MySQL créée</h2><p>Fichier : ' . htmlspecialchars($filename) . '</p></div>';
            $details = safeFileDetails($pathGz);
            $details .= '<p>Télécharger : <a href="' . htmlspecialchars($relative) . '">' . htmlspecialchars($filename) . '</a></p>';
            renderPage('Sauvegarde MySQL réussie', $status, $details);
        }
    }

    // fallback plain sql
    $cmd = ($env ? $env . ' ' : '') . implode(' ', $cmdParts) . ' 2>&1';
    exec($cmd . ' > ' . escapeshellarg($pathSql), $output, $returnVar);
    if ($returnVar === 0 && file_exists($pathSql)) {
        $filename = basename($pathSql);
        $relative = '../../sauv/' . rawurlencode($filename);
        $status = '<div class="result success"><h2>✅ Sauvegarde MySQL créée</h2><p>Fichier : ' . htmlspecialchars($filename) . '</p></div>';
        $details = safeFileDetails($pathSql);
        $details .= '<p>Télécharger : <a href="' . htmlspecialchars($relative) . '">' . htmlspecialchars($filename) . '</a></p>';
        renderPage('Sauvegarde MySQL réussie', $status, $details);
    }
    // continue to PDO fallback if mysqldump failed
}

// PDO MySQL fallback
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

    fwrite($fh, "-- Sauvegarde SQL\n");
    fwrite($fh, "-- Base: " . $db['name'] . "\n");
    fwrite($fh, "-- Date: " . date('c') . "\n\n");

    $tablesStmt = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $row = $pdo->query('SHOW CREATE TABLE ' . $pdo->quote($table))->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $create = array_values($row)[1] ?? null;
            if ($create) {
                fwrite($fh, "-- Structure de la table $table\n");
                fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n");
                fwrite($fh, $create . ";\n\n");
            }
        }

        fwrite($fh, "-- Données de la table $table\n");
        $stmt = $pdo->query('SELECT * FROM ' . $pdo->quote($table));
        $cols = [];
        $first = true;
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($first) {
                $cols = array_keys($r);
                $first = false;
            }
            $values = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote($v);
            }, array_values($r));
            $line = "INSERT INTO `$table` (`" . implode('`,`', $cols) . `) VALUES (" . implode(',', $values) . ");\n";
            fwrite($fh, $line);
        }
        fwrite($fh, "\n");
    }

    fclose($fh);

    // gzip if possible
    if (function_exists('gzencode')) {
        $data = @file_get_contents($pathSql);
        if ($data !== false) {
            $gz = @gzencode($data, 9);
            if ($gz !== false && @file_put_contents($pathGz, $gz) !== false) {
                @unlink($pathSql);
                $filename = basename($pathGz);
                $relative = '../../sauv/' . rawurlencode($filename);
                $status = '<div class="result success"><h2>✅ Sauvegarde MySQL créée</h2><p>Fichier : ' . htmlspecialchars($filename) . '</p></div>';
                $details = safeFileDetails($pathGz);
                $details .= '<p>Télécharger : <a href="' . htmlspecialchars($relative) . '">' . htmlspecialchars($filename) . '</a></p>';
                renderPage('Sauvegarde MySQL réussie', $status, $details);
            }
        }
    }

    $filename = basename($pathSql);
    $relative = '../../sauv/' . rawurlencode($filename);
    $status = '<div class="result success"><h2>✅ Sauvegarde MySQL créée</h2><p>Fichier : ' . htmlspecialchars($filename) . '</p></div>';
    $details = safeFileDetails($pathSql);
    $details .= '<p>Télécharger : <a href="' . htmlspecialchars($relative) . '">' . htmlspecialchars($filename) . '</a></p>';
    renderPage('Sauvegarde MySQL réussie', $status, $details);

} catch (Exception $e) {
    renderPage('Erreur', '<div class="result error"><h2>❌ Erreur</h2><p>Erreur lors de la sauvegarde MySQL : ' . htmlspecialchars($e->getMessage()) . '</p></div>',
        '<pre>Trace : ' . htmlspecialchars($e->getTraceAsString()) . '</pre>');
}