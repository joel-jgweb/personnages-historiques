<?php
// index.php — Page d'accueil de présentation
require_once __DIR__ . '/config.php';

$databasePath = __DIR__ . '/../data/portraits.sqlite';

try {
    $pdo = new PDO("sqlite:$databasePath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $config = loadSiteConfig($pdo);
} catch (Exception $e) {
    die("❌ Erreur de base de données : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['site_title']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

       body {
    position: relative; /* Indispensable pour que le pseudo-élément se positionne correctement */
    font-family: 'Georgia', serif;
    color: #fff;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

body::before {
    content: ''; /* Obligatoire pour les pseudo-éléments */
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: <?php
        if (!empty($config['background_image'])) {
            echo "url('" . htmlspecialchars($config['background_image']) . "') no-repeat center center fixed, ";
        }
        echo htmlspecialchars($config['background_color']);
    ?>;
    background-size: cover;
    opacity: 0.45; /* 45% d'opacité */
    z-index: -1; /* Place le calque en arrière-plan */
}
        .hero {
            text-align: center;
            padding: 6rem 2rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: <?= htmlspecialchars($config['secondary_color']) ?>; /* <-- Ajout de cette ligne */
        }

        .logo-placeholder {
            max-height: 250px;
            margin-bottom: 2rem;
            border-radius: 00px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            color: <?= htmlspecialchars($config['primary_color']) ?>;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.3);
            letter-spacing: 1px;
        }

        .hero p {
            font-size: 1.3rem;
            max-width: 700px;
            margin: 0 auto 3rem;
            line-height: 1.6;
            opacity: 0.95;
        }

        .search-container {
            width: 100%;
            max-width: 700px;
            position: relative;
        }

        #search-bar {
            width: 100%;
            padding: 20px 60px 20px 20px;
            font-size: 1.2rem;
            border: none;
            border-radius: 50px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            outline: none;
            font-family: inherit;
            background: rgba(255,255,255,0.95);
            color: #333;
        }

        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.5rem;
            color: #555;
        }

        .advanced-toggle {
            margin-top: 1.5rem;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: underline;
            opacity: 0.8;
        }

        .advanced-filters {
            display: none;
            margin-top: 2rem;
            padding: 2rem;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            width: 100%;
            max-width: 700px;
        }

        .filter-row {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 0.7rem;
            font-weight: bold;
            font-size: 0.95rem;
            opacity: 0.9;
            color: #fff;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 1rem;
        }

        input::placeholder, select {
            color: rgba(255,255,255,0.7);
        }

        .btn-search {
            padding: 15px 40px;
            font-size: 1.2rem;
            background: <?= htmlspecialchars($config['secondary_color']) ?>;
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
            margin-top: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-search:hover {
            background: <?= darkenColor($config['secondary_color'], 10) ?>;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }

        footer {
            text-align: center;
            padding: 1.5rem;
            background: rgba(0,0,0,0.2);
            font-size: 0.9rem;
            color: #ffffff; /* <-- Remplacez 'opacity' par 'color' */
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .hero p { font-size: 1.1rem; }
            .filter-row { flex-direction: column; gap: 1rem; }
        }
    </style>
</head>
<body>
    <div class="hero">
        <?php if (!empty($config['logo_path'])): ?>
            <img src="<?= htmlspecialchars($config['logo_path']) ?>" alt="Logo de l'association" class="logo-placeholder">
        <?php endif; ?>

        <h1><?= htmlspecialchars($config['site_title']) ?></h1>
        <p><?= htmlspecialchars($config['site_subtitle']) ?></p>

       <div class="search-container">
    <input type="text" id="search-bar" placeholder="Rechercher un nom, un métier, un engagement..." autocomplete="off">
    <button class="search-icon" onclick="performSearch()" aria-label="Lancer la recherche">🔍</button>
       </div>

        <div class="advanced-toggle" onclick="toggleAdvancedFilters()">
            Options de recherche avancée
        </div>

        <div class="advanced-filters" id="advanced-filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filter-metier">Métier</label>
                    <input type="text" id="filter-metier" placeholder="Ex: Facteur, Télégraphiste">
                </div>
                <div class="filter-group">
                    <label for="filter-engagement">Engagement</label>
                    <input type="text" id="filter-engagement" placeholder="Ex: CGT, Syndicat des PTT">
                </div>
            </div>
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filter-lieu">Lieu</label>
                    <input type="text" id="filter-lieu" placeholder="Ville ou département">
                </div>
                <div class="filter-group">
                    <label for="filter-periode">Période</label>
                    <select id="filter-periode">
                        <option value="">Toutes les périodes</option>
                        <option value="1890-1910">1890 - 1910</option>
                        <option value="1910-1930">1910 - 1930</option>
                        <option value="1930-1950">1930 - 1950</option>
                    </select>
                </div>
            </div>
            <button class="btn-search" onclick="performSearch()">
                Lancer la recherche
            </button>
        </div>
    </div>

    <footer>
        <p>
            <?= htmlspecialchars($config['association_name']) ?><br>
            <?= nl2br(htmlspecialchars($config['association_address'])) ?>
        </p>
    </footer>

    <script>
        function toggleAdvancedFilters() {
            const filters = document.getElementById('advanced-filters');
            filters.style.display = filters.style.display === 'block' ? 'none' : 'block';
        }

        function performSearch() {
            const query = document.getElementById('search-bar').value.trim();
            const metier = document.getElementById('filter-metier').value.trim();
            const engagement = document.getElementById('filter-engagement').value.trim();
            const lieu = document.getElementById('filter-lieu').value.trim();
            const periode = document.getElementById('filter-periode').value;

            let url = 'search.php?';
            const params = [];
            if (query) params.push('q=' + encodeURIComponent(query));
            if (metier) params.push('metier=' + encodeURIComponent(metier));
            if (engagement) params.push('engagement=' + encodeURIComponent(engagement));
            if (lieu) params.push('lieu=' + encodeURIComponent(lieu));
            if (periode) params.push('periode=' + encodeURIComponent(periode));

            window.location.href = url + params.join('&');
        }

        document.getElementById('search-bar').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    </script>
</body>
</html>