<?php
// search.php — Page de résultats en grille de cartes (avec option de recherche dans Nom uniquement)
require_once __DIR__ . '/config.php';

$fiches = [];
try {
    $pdo = get_sqlite_pdo();
    $config = loadSiteConfig($pdo);

    $query = $_GET['q'] ?? '';
    $metier = $_GET['metier'] ?? '';
    $engagement = $_GET['engagement'] ?? '';
    $lieu = $_GET['lieu'] ?? '';
    $periode = $_GET['periode'] ?? '';
    $mode = $_GET['mode'] ?? 'all';

    // Valider le mode
    if (!in_array($mode, ['all', 'name'])) {
        $mode = 'all';
    }

    // Requête de base : seulement les fiches en ligne
    $sql = "SELECT ID_fiche, Nom, Metier, Engagements, Details, Photo FROM personnages WHERE est_en_ligne = 1";
    $params = [];

    // Recherche principale
    if (!empty($query)) {
        if ($mode === 'name') {
            $sql .= " AND Nom LIKE ?";
            $params[] = '%' . $query . '%';
        } else {
            $sql .= " AND (Nom LIKE ? OR Metier LIKE ? OR Engagements LIKE ? OR Details LIKE ?)";
            $likeQuery = '%' . $query . '%';
            $params = array_merge($params, [$likeQuery, $likeQuery, $likeQuery, $likeQuery]);
        }
    }

    // Filtres avancés
    if (!empty($metier)) {
        $sql .= " AND Metier LIKE ?";
        $params[] = '%' . $metier . '%';
    }
    if (!empty($engagement)) {
        $sql .= " AND Engagements LIKE ?";
        $params[] = '%' . $engagement . '%';
    }
    if (!empty($lieu)) {
        $sql .= " AND Lieu LIKE ?"; // ← Assure-toi que ta table a une colonne `Lieu`
        $params[] = '%' . $lieu . '%';
    }
    if (!empty($periode)) {
        // Tu devras adapter cette partie selon la structure de ta base
        // Exemple si tu as une colonne `Periode` ou `Annee_naissance`
        // Ici, on suppose une colonne `Periode` au format "1890-1910"
        $sql .= " AND Periode = ?";
        $params[] = $periode;
    }

    // Tri insensible à la casse et aux accents
    $sql .= " ORDER BY LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Nom, 'É','e'), 'È','e'), 'Ê','e'), 'Ë','e'), 'é','e'), 'è','e'), 'ê','e'), 'ë','e')) COLLATE NOCASE ASC";

    // Exécution
    if (!empty($params)) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query($sql);
    }
    $fiches = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("❌ Erreur de base de données : " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats de la recherche — <?= htmlspecialchars($config['site_title']) ?></title>
    <style>
        body {
            font-family: 'Georgia', serif;
            background: #f0f3f7;
            color: #333;
            padding: 2rem;
            margin: 0;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .results-title {
            font-size: 1.8rem;
            color: <?= htmlspecialchars($config['primary_color']) ?>;
        }
        .results-count {
            font-size: 1.1rem;
            color: #7f8c8d;
        }
        .back-home {
            text-decoration: none;
            color: <?= htmlspecialchars($config['secondary_color']) ?>;
            font-weight: bold;
            padding: 8px 16px;
            border: 2px solid <?= htmlspecialchars($config['secondary_color']) ?>;
            border-radius: 30px;
            transition: all 0.3s;
        }
        .back-home:hover {
            background: <?= htmlspecialchars($config['secondary_color']) ?>;
            color: white;
        }
        .fiches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 2.5rem;
        }
        .fiche-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .fiche-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        .card-photo {
            height: 220px;
            background-color: #f8f9fa;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .card-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .no-photo {
            color: #adb5bd;
            font-style: italic;
            text-align: center;
            padding: 1rem;
        }
        .card-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .card-title {
            font-size: 1.4rem;
            font-weight: bold;
            color: <?= htmlspecialchars($config['primary_color']) ?>;
            margin: 0 0 0.8rem 0;
            line-height: 1.3;
        }
        .card-meta {
            font-size: 0.95rem;
            color: #555;
            margin-bottom: 1rem;
            line-height: 1.5;
        }
        .card-excerpt {
            font-size: 0.95rem;
            color: #333;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        .btn-view {
            padding: 12px 24px;
            background: <?= darkenColor($config['secondary_color'], 15) ?>;
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            text-align: center;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-top: auto;
        }
        .btn-view:hover {
            background: <?= htmlspecialchars($config['secondary_color']) ?>;
        }
        @media (max-width: 768px) {
            .fiches-grid {
                grid-template-columns: 1fr;
            }
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 class="results-title">Résultats de la recherche</h1>
                <p class="results-count">Trouvé <?= count($fiches) ?> fiche(s)</p>
            </div>
            <a href="index.php" class="back-home">← Retour à l'accueil</a>
        </div>
        <div class="fiches-grid">
            <?php if (empty($fiches)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 4rem; background: white; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                    <h3>Aucun résultat trouvé</h3>
                    <p>Essayez d'élargir vos critères de recherche ou de corriger les termes saisis.</p>
                    <a href="index.php" class="back-home" style="margin-top: 1.5rem;">Retourner à la page d'accueil</a>
                </div>
            <?php else: ?>
                <?php foreach ($fiches as $fiche): ?>
                    <div class="fiche-card">
                        <?php if (!empty($fiche['Photo'])): ?>
                            <div class="card-photo">
                                <img src="<?= htmlspecialchars($fiche['Photo']) ?>" alt="Photo de <?= htmlspecialchars($fiche['Nom']) ?>" loading="lazy">
                            </div>
                        <?php endif; ?>
                        <div class="card-content">
                            <h3 class="card-title"><?= htmlspecialchars($fiche['Nom']) ?></h3>
                            <div class="card-meta">
                                <strong>Métier :</strong> <?= htmlspecialchars($fiche['Metier'] ?? '—') ?><br>
                                <strong>Engagement :</strong> <?= htmlspecialchars($fiche['Engagements'] ?? '—') ?>
                            </div>
                            <div class="card-excerpt">
                                <?= htmlspecialchars(substr(strip_tags($fiche['Details'] ?? ''), 0, 250)) ?>...
                            </div>
                            <a href="fiche.php?id=<?= (int)$fiche['ID_fiche'] ?>" class="btn-view">Voir le détail</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>