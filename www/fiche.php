<?php
// fiche.php — Page de détail complète d'une fiche (mise à jour avec support Markdown)
require_once __DIR__ . '/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
$fiche = null;
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de fiche invalide.");
}
try {
    $pdo = get_sqlite_pdo();
    $config = loadSiteConfig($pdo);
    $stmt = $pdo->prepare("SELECT * FROM personnages WHERE ID_fiche = ? AND est_en_ligne = 1");
    $stmt->execute([$_GET['id']]);
    $fiche = $stmt->fetch();
    if (!$fiche) {
        die("Fiche non trouvée.");
    }
} catch (Exception $e) {
    die("❌ Erreur de base de données : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fiche['Nom']) ?> — <?= htmlspecialchars($config['site_title']) ?></title>
    <style>
        body {
            font-family: 'Georgia', serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.8;
            padding: 2rem;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }
        .back-link {
            position: absolute;
            left: 0;
            top: 0;
            text-decoration: none;
            color: <?= htmlspecialchars($config['secondary_color']) ?>;
            font-weight: bold;
        }
        .fiche-title {
            font-size: 2.5rem;
            color: <?= htmlspecialchars($config['primary_color']) ?>;
            margin: 0.5rem 0 1rem 0;
        }
        .fiche-subtitle {
            font-size: 1.2rem;
            color: #7f8c8d;
            margin-bottom: 2rem;
        }
        .fiche-photo {
            width: 100%;
            max-width: 300px;
            margin: 0 auto 2rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: block;
        }
        .section {
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #ecf0f1;
        }
        .section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .section-title {
            font-size: 1.5rem;
            color: <?= htmlspecialchars($config['primary_color']) ?>;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid <?= htmlspecialchars($config['secondary_color']) ?>;
            display: inline-block;
        }
        .section-content {
            font-size: 1.1rem;
            line-height: 1.7;
        }
        .section-content a {
            color: <?= darkenColor($config['secondary_color'], 20) ?>;
            text-decoration: none;
            border-bottom: 1px dotted <?= htmlspecialchars($config['secondary_color']) ?>;
        }
        .section-content a:hover {
            border-bottom: 1px solid <?= darkenColor($config['secondary_color'], 20) ?>;
        }
        /* On retire .metadata car supprimée */
        footer {
            text-align: center;
            padding: 1.5rem;
            background: rgba(0,0,0,0.05);
            margin-top: 2rem;
            font-size: 0.9rem;
            border-radius: 10px;
        }
        /* --- Style pour les tableaux --- */
        .resource-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        .resource-table th,
        .resource-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .resource-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .container { padding: 1.5rem; }
            .fiche-title { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="search.php" class="back-link">← Retour aux résultats</a>
            <h1 class="fiche-title"><?= htmlspecialchars($fiche['Nom']) ?></h1>
            <?php
            // Nouvelle fiche-subtitle : Données généalogiques, Métier, Engagements
            $subtitleParts = [];
            if (!empty($fiche['Donnees_genealogiques'])) {
                $subtitleParts[] = htmlspecialchars($fiche['Donnees_genealogiques']);
            }
            if (!empty($fiche['Metier'])) {
                $subtitleParts[] = htmlspecialchars($fiche['Metier']);
            }
            if (!empty($fiche['Engagements'])) {
                $subtitleParts[] = htmlspecialchars($fiche['Engagements']);
            }
            if (count($subtitleParts) > 0): ?>
                <div class="fiche-subtitle">
                    <?= implode(' • ', $subtitleParts) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($fiche['Photo'])): ?>
            <img src="<?= htmlspecialchars($fiche['Photo']) ?>" alt="Photo de <?= htmlspecialchars($fiche['Nom']) ?>" class="fiche-photo">
        <?php endif; ?>
        <!-- Zone .metadata supprimée -->
        <?php if (!empty($fiche['Details'])): ?>
            <div class="section">
                <h2 class="section-title">Détails</h2>
                <div class="section-content">
                    <?= markdownToHtml($fiche['Details']) ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($fiche['Sources'])): ?>
            <div class="section">
                <h2 class="section-title">Sources</h2>
                <div class="section-content">
                    <?= markdownToHtml($fiche['Sources']) ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($fiche['Iconographie']) || !empty($fiche['Documents'])): ?>
            <div class="section">
                <h2 class="section-title">Ressources complémentaires</h2>
                <?php if (!empty($fiche['Iconographie']) && trim($fiche['Iconographie']) !== "| Description | Télécharger |\n|-------------|-------------|"): ?>
                    <h3>Iconographie</h3>
                    <div class="section-content">
                        <?= markdownTableToHtml($fiche['Iconographie']) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($fiche['Documents']) && trim($fiche['Documents']) !== "| Description | Télécharger |\n|-------------|-------------|"): ?>
                    <h3>Documents</h3>
                    <div class="section-content">
                        <?= markdownTableToHtml($fiche['Documents']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <!-- Métadonnées de rédaction -->
    <div style="
        text-align: center;
        font-size: 0.9rem;
        color: #6c757d;
        margin-top: 2.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e9ecef;
        font-style: italic;
    ">
        <?php if (!empty($fiche['auteur']) || !empty($fiche['derniere_modif'])): ?>
            Rédaction : 
            <span style="font-weight: bold; font-style: normal; color: #495057;">
                <?= htmlspecialchars($fiche['auteur'] ?? '—') ?>
            </span>
            <?php if (!empty($fiche['derniere_modif'])): ?>
                — Dernière modification : 
                <span style="font-weight: bold; font-style: normal; color: #495057;">
                    <?= htmlspecialchars($fiche['derniere_modif']) ?>
                </span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <footer>
        <p>
            <?= htmlspecialchars($config['association_name']) ?><br>
            <?= nl2br(htmlspecialchars($config['association_address'])) ?>
        </p>
    </footer>
</body>
</html>