<?php
// index.php — Page d'accueil de présentation
require_once __DIR__ . '/config.php';

try {
    $pdo = get_sqlite_pdo();
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
            position: relative;
            font-family: 'Georgia', serif;
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        body::before {
            content: '';
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
            opacity: 0.45;
            z-index: -1;
        }

        .hero {
            text-align: center;
            padding: 6rem 2rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: <?= htmlspecialchars($config['secondary_color']) ?>;
        }

        .logo-placeholder {
            max-height: 250px;
            margin-bottom: 2rem;
            border-radius: 0;
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
            margin: 0 auto 2.5rem;
            line-height: 1.6;
            opacity: 0.95;
        }

        .search-container {
            width: 100%;
            max-width: 700px;
            position: relative;
            margin-bottom: 2rem;
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
            font-size: 1.6rem;
            color: #666;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .search-icon:hover {
            background: rgba(0,0,0,0.05);
            color: #333;
        }

        /* === Popup modal === */
        #mode-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .popup-content {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: left;
        }

        .popup-content h3 {
            margin-bottom: 1.2rem;
            color: <?= htmlspecialchars($config['primary_color']) ?>;
            font-size: 1.4rem;
        }

        .popup-option {
            display: block;
            padding: 14px;
            margin-bottom: 12px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.05rem;
            color: #222;
            transition: all 0.2s;
        }

        .popup-option:hover {
            background: #e9f7fe;
            border-color: <?= htmlspecialchars($config['secondary_color']) ?>;
        }

        .popup-option input {
            margin-right: 10px;
        }

        footer {
            text-align: center;
            padding: 1.5rem;
            background: rgba(0,0,0,0.2);
            font-size: 0.9rem;
            color: #ffffff;
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .hero p { font-size: 1.1rem; }
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
            <input type="text" id="search-bar" placeholder="Saisissez un nom ou un mot-clé..." autocomplete="off">
            <button class="search-icon" onclick="showModePopup()" aria-label="Lancer la recherche">🔍</button>
        </div>
    </div>

    <!-- Popup modal -->
    <div id="mode-popup" onclick="closePopup(event)">
        <div class="popup-content" onclick="event.stopPropagation()">
            <h3>Comment souhaitez-vous rechercher ?</h3>
            <label class="popup-option">
                <input type="radio" name="search-mode" value="all" checked>
                Recherche sur toute la fiche
            </label>
            <label class="popup-option">
                <input type="radio" name="search-mode" value="name">
                Recherche uniquement sur le Nom
            </label>
            <button class="popup-option" style="background: <?= htmlspecialchars($config['secondary_color']) ?>; color: white; border-color: <?= htmlspecialchars($config['secondary_color']) ?>; margin-top: 1rem;" onclick="confirmSearch()">
                Valider et lancer la recherche
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
        let currentQuery = '';

        function showModePopup() {
            const query = document.getElementById('search-bar').value.trim();
            if (!query) {
                alert("Veuillez saisir un terme de recherche.");
                document.getElementById('search-bar').focus();
                return;
            }
            currentQuery = query;
            document.getElementById('mode-popup').style.display = 'flex';
        }

        function closePopup(event) {
            if (event.target.id === 'mode-popup') {
                document.getElementById('mode-popup').style.display = 'none';
            }
        }

        function confirmSearch() {
            const mode = document.querySelector('input[name="search-mode"]:checked').value;
            window.location.href = `search.php?q=${encodeURIComponent(currentQuery)}&mode=${encodeURIComponent(mode)}`;
        }

        document.getElementById('search-bar').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                showModePopup();
            }
        });
    </script>
</body>
</html>