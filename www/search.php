<?php
// search.php — Version V3 corrigée et robuste
// - construit la requête SQL uniquement avec les conditions présentes
// - utilise prepared statements seulement si nécessaire (params non vides)
// - respecte le default_search_mode (config.local.php ou BDD) : 'all' | 'name'
// - journalise la requête SQL + params via error_log() pour debug léger

// Charger la configuration locale
$localConfig = file_exists(__DIR__ . '/../config/config.local.php')
    ? require __DIR__ . '/../config/config.local.php'
    : [];

// Inclure helpers (getDatabasePath etc.)
require_once __DIR__ . '/config.php';

// DB path
$databasePath = isset($localConfig['database_path']) ? $localConfig['database_path'] : (__DIR__ . '/../data/portraits.sqlite');

try {
    $pdo = new PDO("sqlite:$databasePath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $config = function_exists('loadSiteConfig') ? loadSiteConfig($pdo) : [];
} catch (Exception $e) {
    // Si la connexion échoue, on garde une config par défaut
    $config = loadSiteConfig(null);
}

// default search mode from config (priority: config.local.php > DB config > 'all')
$cfgDefault = $localConfig['default_search_mode'] ?? ($config['default_search_mode'] ?? null);
$defaultSearchMode = in_array($cfgDefault, ['all', 'name']) ? $cfgDefault : 'all';

// Récupérer paramètres de recherche (trim)
$rawQuery = trim((string)($_GET['q'] ?? ''));
$metier = trim((string)($_GET['metier'] ?? ''));
$engagement = trim((string)($_GET['engagement'] ?? ''));
$lieu = trim((string)($_GET['lieu'] ?? ''));
$periode = trim((string)($_GET['periode'] ?? ''));
$mode = isset($_GET['mode']) ? (($_GET['mode'] === 'name') ? 'name' : 'all') : $defaultSearchMode;

// Construction robuste de la requête
$sql = "SELECT ID_fiche, Nom, Metier, Engagements, Details, Photo FROM personnages WHERE est_en_ligne = 1";
$params = [];

// Fonction utilitaire pour ajouter un filtre LIKE compatible SQLite (NOCASE)
$addLikeCondition = function(&$sql, &$params, $column, $value) {
    // Utiliser COLLATE NOCASE pour insensibilité à la casse sur SQLite
    $sql .= " AND {$column} LIKE ? COLLATE NOCASE";
    $params[] = '%' . $value . '%';
};

// Champ de recherche principal
if ($rawQuery !== '') {
    if ($mode === 'name') {
        $addLikeCondition($sql, $params, 'Nom', $rawQuery);
    } else {
        // Rechercher dans plusieurs colonnes
        $sql .= " AND (Nom LIKE ? COLLATE NOCASE OR Metier LIKE ? COLLATE NOCASE OR Engagements LIKE ? COLLATE NOCASE OR Details LIKE ? COLLATE NOCASE)";
        $like = '%' . $rawQuery . '%';
        // ajouter dans l'ordre des placeholders
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
}

// Filtres optionnels (ajoutés seulement s'ils ne sont pas vides)
if ($metier !== '') {
    $addLikeCondition($sql, $params, 'Metier', $metier);
}
if ($engagement !== '') {
    $addLikeCondition($sql, $params, 'Engagements', $engagement);
}
if ($lieu !== '') {
    $addLikeCondition($sql, $params, 'Lieu', $lieu);
}
if ($periode !== '') {
    $sql .= " AND Periode = ?";
    $params[] = $periode;
}

// Tri
$sql .= " ORDER BY LOWER(Nom) ASC";

// Debug log : enregistrer la requête et les paramètres pour diagnostic (sera visible dans error log)
// Retirer ou commenter cette ligne en production si nécessaire
error_log("search.php SQL: " . $sql . " ; params=" . json_encode($params));

// Exécution : utiliser query() si pas de params, sinon prepare/execute
try {
    if (count($params) === 0) {
        $stmt = $pdo->query($sql);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    $fiches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // En cas d'erreur SQL on affiche une erreur lisible (mais pas le stack)
    $errMsg = "Erreur lors de la recherche : " . $e->getMessage();
    error_log("search.php ERROR: " . $e->getMessage());
    $fiches = [];
}

// --- Affichage HTML des résultats (grille de cartes) ---
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Résultats de la recherche — <?= htmlspecialchars($config['site_title'] ?? 'Personnages Historiques') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Georgia, serif; background: #f0f3f7; color: #333; padding:2rem; margin:0; }
        .container { max-width:1200px; margin:0 auto; }
        .header { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; }
        .results-title { font-size:1.5rem; color: <?= htmlspecialchars($config['primary_color'] ?? '#2c3e50') ?>; margin:0 }
        .back-home { text-decoration:none; color: <?= htmlspecialchars($config['secondary_color'] ?? '#6c757d') ?>; font-weight:bold; padding:8px 12px; border:2px solid <?= htmlspecialchars($config['secondary_color'] ?? '#6c757d') ?>; border-radius:24px; }
        .fiches-grid { display:grid; grid-template-columns: repeat(auto-fill,minmax(360px,1fr)); gap:1.5rem; margin-top:1rem; }
        .fiche-card { background:white; border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,0.06); overflow:hidden; display:flex; flex-direction:column; height:100%; }
        .card-photo { height:220px; background:#f8f9fa; display:flex; align-items:center; justify-content:center; }
        .card-photo img { width:100%; height:100%; object-fit:cover; }
        .card-content { padding:1.25rem; display:flex; flex-direction:column; flex:1 }
        .card-title { font-size:1.2rem; margin:0 0 .6rem 0; color: <?= htmlspecialchars($config['primary_color'] ?? '#2c3e50') ?> }
        .card-meta { color:#666; margin-bottom:.8rem }
        .card-excerpt { color:#333; line-height:1.6; flex:1; margin-bottom:1rem }
        .btn-view { padding:.6rem 1rem; background: <?= htmlspecialchars($config['secondary_color'] ?? '#6c757d') ?>; color:white; border-radius:24px; text-decoration:none; display:inline-block }
        .message { padding: 1rem; margin-top: 1rem; border-radius: 8px; }
        .message.error { background: #fdecea; color:#721c24; }
        .message.info { background: #e9f7ef; color:#155724; }
        @media(max-width:768px){ .fiches-grid { grid-template-columns:1fr } .header { flex-direction:column; align-items:flex-start } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 class="results-title">Résultats de la recherche</h1>
                <p style="margin:6px 0;color:#7f8c8d">Trouvé <?= count($fiches) ?> fiche(s)</p>
            </div>
            <a href="index.php" class="back-home">← Retour à l'accueil</a>
        </div>

        <?php if (!empty($errMsg)): ?>
            <div class="message error"><?= htmlspecialchars($errMsg) ?></div>
        <?php endif; ?>

        <div class="fiches-grid">
            <?php if (empty($fiches)): ?>
                <div style="grid-column:1/-1;background:white;padding:2.5rem;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,0.04);text-align:center">
                    <h3>Aucun résultat trouvé</h3>
                    <p>Essayez d'élargir vos critères de recherche.</p>
                    <a class="back-home" href="index.php" style="display:inline-block;margin-top:1rem;">Retour à l'accueil</a>
                </div>
            <?php else: ?>
                <?php foreach ($fiches as $fiche): ?>
                    <div class="fiche-card">
                        <?php if (!empty($fiche['Photo'])): ?>
                            <div class="card-photo">
                                <?php
                                // déterminer un chemin web pour la photo : si stockée dans data/docs/ on l'utilise
                                $photoSrc = $fiche['Photo'];
                                $candidate = __DIR__ . '/../data/docs/' . basename($fiche['Photo']);
                                if (file_exists($candidate)) {
                                    $photoSrc = '../data/docs/' . basename($fiche['Photo']);
                                }
                                ?>
                                <img src="<?= htmlspecialchars($photoSrc) ?>" alt="Photo de <?= htmlspecialchars($fiche['Nom']) ?>" loading="lazy">
                            </div>
                        <?php endif; ?>
                        <div class="card-content">
                            <h3 class="card-title"><?= htmlspecialchars($fiche['Nom']) ?></h3>
                            <div class="card-meta"><strong>Métier :</strong> <?= htmlspecialchars($fiche['Metier'] ?? '—') ?></div>
                            <div class="card-excerpt"><?= nl2br(htmlspecialchars(substr(strip_tags($fiche['Details'] ?? ''), 0, 250))) ?><?= (strlen(strip_tags($fiche['Details'] ?? '')) > 250 ? '...' : '') ?></div>
                            <a class="btn-view" href="fiche.php?id=<?= (int)$fiche['ID_fiche'] ?>">Voir le détail</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>