<?php
// index.php — Page d'accueil (V15) — same as your V15 but background image now at 30% opacity with a light blur
// Seule la façon dont l'image de fond est rendue a été modifiée (opacité + blur).

require_once __DIR__ . '/config.php';

$localConfig = file_exists(__DIR__ . '/../config/config.local.php')
    ? require __DIR__ . '/../config/config.local.php'
    : [];

$databasePath = $localConfig['database_path'] ?? (__DIR__ . '/../data/portraits.sqlite');

try {
    $pdo = new PDO("sqlite:$databasePath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $config = loadSiteConfig($pdo);
} catch (Exception $e) {
    die("❌ Erreur de base de données : " . $e->getMessage());
}

// Helper pour accepter un chemin vers data/docs si nécessaire
function assetFromDocsIfExists($path) {
    if (empty($path)) return null;
    $path = trim($path);
    if (preg_match('#^https?://#i', $path)) return $path;
    if (strpos($path, '/') === 0) return $path;
    $candidate = __DIR__ . '/../data/docs/' . basename($path);
    if (file_exists($candidate)) {
        return '../data/docs/' . basename($path);
    }
    return $path;
}

$logoSrc = assetFromDocsIfExists($config['logo_path'] ?? ($localConfig['logo_path'] ?? null));
$bgValue = $config['background_image'] ?? ($localConfig['background_image'] ?? null);
$bgColor = $config['background_color'] ?? ($localConfig['background_color'] ?? '#2a2a2a');

// --- Lecture sûre du nom et de l'adresse de l'association ---
$associationNameRaw = $config['association_name'] ?? ($localConfig['association_name'] ?? '');
$associationAddressRaw = $config['association_address'] ?? ($localConfig['association_address'] ?? '');

$associationName = trim($associationNameRaw) !== '' ? htmlspecialchars($associationNameRaw, ENT_QUOTES | ENT_SUBSTITUTE) : '';
$associationAddress = trim($associationAddressRaw) !== '' ? nl2br(htmlspecialchars($associationAddressRaw, ENT_QUOTES | ENT_SUBSTITUTE)) : '';

// DEFAULT SEARCH MODE
$cfgDefaultMode = $localConfig['default_search_mode'] ?? ($config['default_search_mode'] ?? null);
$defaultSearchMode = in_array($cfgDefaultMode, ['all', 'name']) ? $cfgDefaultMode : 'all';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['site_title'] ?? 'Personnages Historiques') ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            position: relative;
            font-family: 'Georgia', serif;
            color: #fff;
            min-height: 100vh;
            display:flex;
            flex-direction:column;
            /* fallback background color */
            background: <?= htmlspecialchars($bgColor, ENT_QUOTES | ENT_SUBSTITUTE) ?>;
        }

        /* Adaptation demandée : image de fond pleine page, opacité 30% et léger flou */
        <?php if (!empty($bgValue)): 
            $bgSrc = assetFromDocsIfExists($bgValue);
        ?>
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: url('<?= htmlspecialchars($bgSrc, ENT_QUOTES | ENT_SUBSTITUTE) ?>') no-repeat center center;
            background-size: cover;            /* ensure the image fills the viewport */
            background-position: center;
            background-attachment: fixed;      /* fixed during scroll */
            opacity: 0.30;                      /* 30% opacity as requested */
            filter: blur(2px);                  /* slight blur for a softer background */
            transform: scale(1.02);             /* avoid visible edges when blurred */
            z-index: -1;
        }
        <?php endif; ?>

        .hero {
            text-align: center;
            padding: 6rem 2rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: <?= htmlspecialchars($config['secondary_color'] ?? '#eaeaea', ENT_QUOTES | ENT_SUBSTITUTE) ?>;
        }

        .logo-placeholder {
            max-height: 250px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            color: <?= htmlspecialchars($config['primary_color'] ?? '#ffffff', ENT_QUOTES | ENT_SUBSTITUTE) ?>;
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

        /* Popup modal */
        #mode-popup {
            display: none;
            position: fixed;
            inset: 0;
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
            color: <?= htmlspecialchars($config['primary_color'] ?? '#2c3e50', ENT_QUOTES | ENT_SUBSTITUTE) ?>;
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
            border-color: <?= htmlspecialchars($config['secondary_color'] ?? '#6c757d', ENT_QUOTES | ENT_SUBSTITUTE) ?>;
        }

        .popup-option input {
            margin-right: 10px;
        }

        footer {
            text-align: center;
            padding: 1.5rem;
            background: rgba(0,0,0,0.2);
            font-size: 0.95rem;
            color: #ffffff;
        }

        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .hero p { font-size: 1.1rem; }
            .logo-placeholder { max-height: 180px; margin-bottom: 1.2rem; }
        }
    </style>
</head>
<body>
    <div class="hero" role="main" aria-labelledby="site-title">
        <?php if (!empty($logoSrc)): ?>
            <img src="<?= htmlspecialchars($logoSrc, ENT_QUOTES | ENT_SUBSTITUTE) ?>" alt="<?= htmlspecialchars($config['site_title'] ?? 'Logo', ENT_QUOTES | ENT_SUBSTITUTE) ?>" class="logo-placeholder">
        <?php endif; ?>

        <h1 id="site-title"><?= htmlspecialchars($config['site_title'] ?? 'Personnages Historiques', ENT_QUOTES | ENT_SUBSTITUTE) ?></h1>
        <?php if (!empty($config['site_subtitle'])): ?>
            <p><?= htmlspecialchars($config['site_subtitle'], ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
        <?php endif; ?>

        <div class="search-container">
            <input type="text" id="search-bar" placeholder="Saisissez un nom ou un mot-clé..." autocomplete="off" aria-label="Terme de recherche">
            <button class="search-icon" onclick="showModePopup()" aria-label="Lancer la recherche">🔍</button>
        </div>
    </div>

    <!-- Popup modal -->
    <div id="mode-popup" onclick="closePopup(event)">
        <div class="popup-content" onclick="event.stopPropagation()">
            <h3>Comment souhaitez-vous rechercher ?</h3>
            <label class="popup-option">
                <input type="radio" name="search-mode" value="all" <?= $defaultSearchMode === 'all' ? 'checked' : '' ?>>
                La totalité des données
            </label>
            <label class="popup-option">
                <input type="radio" name="search-mode" value="name" <?= $defaultSearchMode === 'name' ? 'checked' : '' ?>>
                Nom uniquement
            </label>
            <button class="popup-option" style="background: <?= htmlspecialchars($config['secondary_color'] ?? '#6c757d', ENT_QUOTES | ENT_SUBSTITUTE) ?>; color: white; border:none; margin-top:0.6rem;" onclick="confirmSearch()">
                Valider et lancer la recherche
            </button>
        </div>
    </div>

    <footer>
        <p>
            <?= $associationName ? $associationName . '<br>' : '' ?>
            <?= $associationAddress ? $associationAddress : '' ?>
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