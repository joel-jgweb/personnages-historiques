require_once __DIR__ . '/config.php';
$sqlitePath = $CONFIG['db']['sqlite_path'] ?? (rtrim($CONFIG['data_path'], '/') . '/portraits.sqlite');
$pdo = get_sqlite_pdo($sqlitePath);