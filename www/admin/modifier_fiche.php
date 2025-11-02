<?php
// modifier_fiche.php - Modification avec éditeur restreint et saisie structurée pour les tableaux
session_start();

// --- Vérification de session complète ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_statut']) || !isset($_SESSION['user_login'])) {
    header("Location: login.php");
    exit;
}

require_once 'permissions.php';

// Autorise : Rédacteur Fiches (3), Administrateur Fiches (2), Super-Administrateur (1), Administrateur Simple (6)
$roles_autorises = [1, 2, 3, 6];
checkUserPermission($roles_autorises);

// --- FONCTION DE VÉRIFICATION DE PROPRIÉTÉ (Sécurité Rédacteur) ---
/**
 * Vérifie si le Rédacteur (statut 3) est bien l'auteur de la fiche.
 * Cette fonction est utilisée avec 'die()' uniquement pour la VÉRIFICATION FINALE lors de l'UPDATE (action=modifier).
 */
function checkFicheOwnership($fiche, $userStatut, $userLogin) {
    // Les rôles supérieurs sont autorisés
    if ($userStatut != 3) {
        return true; 
    }
    
    // Si l'utilisateur est Rédacteur (3), il doit être l'auteur.
    if (isset($fiche['auteur']) && $fiche['auteur'] === $userLogin) {
        return true; 
    }
    
    // Accès refusé : Interrompt l'exécution
    die("<h1 style='color: #dc3545; text-align: center; padding: 50px;'>⛔ Accès refusé</h1><p style='text-align: center;'>En tant que Rédacteur, vous ne pouvez modifier que les fiches dont vous êtes l'auteur.</p>");
}
// --- FIN DE LA FONCTION DE VÉRIFICATION DE PROPRIÉTÉ ---

require_once '../config.php'; // Ce fichier est censé contenir urlToMarkdownLink()

$dbPath = __DIR__ . '/../../data/portraits.sqlite';
$message = '';
$fiche = null;
$resultats = [];
$recherche_effectuee = false;
$userStatut = $_SESSION['user_statut'];
$userLogin = $_SESSION['user_login'];

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Erreur de connexion à la base de données : " . $e->getMessage());
}

// Action : Rechercher une fiche
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rechercher') {
    $recherche_effectuee = true;
    $recherche = trim($_POST['recherche'] ?? '');
    if (empty($recherche)) {
        $message = "<div class='alert alert-warning'>Veuillez entrer un nom ou un ID.</div>";
    } else {
        $stmt = null;
        if (is_numeric($recherche)) {
            $stmt = $pdo->prepare("SELECT * FROM personnages WHERE ID_fiche = ?");
            $stmt->execute([$recherche]);
            $fiche = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fiche) {
                $message = "<div class='alert alert-info'>Aucune fiche trouvée pour l'ID '$recherche'.</div>";
            }
        } else {
            $stmt = $pdo->prepare("SELECT ID_fiche, Nom, Metier, Engagements FROM personnages WHERE Nom LIKE ? ORDER BY Nom ASC");
            $stmt->execute(["%$recherche%"]);
            $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($resultats)) {
                $message = "<div class='alert alert-info'>Aucune fiche trouvée pour '$recherche'.</div>";
            } elseif (count($resultats) == 1) {
                $fiche_id = $resultats[0]['ID_fiche'];
                $stmt = $pdo->prepare("SELECT * FROM personnages WHERE ID_fiche = ?");
                $stmt->execute([$fiche_id]);
                $fiche = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        
        // --- GESTION DE LA VISIBILITÉ DANS LA RECHERCHE (pour le Rédacteur) ---
        if ($fiche && $userStatut == 3 && $fiche['auteur'] !== $userLogin) {
            // Si c'est un Rédacteur (3) et que la fiche n'est pas la sienne
            $message = "<div class='alert alert-danger'>⛔ Accès refusé : En tant que Rédacteur, vous ne pouvez pas modifier cette fiche.</div>";
            $fiche = null; // Annule l'affichage de la fiche
            $resultats = []; // Annule l'affichage des résultats s'il y en avait
        }
        // -----------------------------------------------------------------------
    }
}

// Action : Charger une fiche spécifique depuis la liste
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'charger_fiche') {
    $fiche_id = $_POST['fiche_id'] ?? null;
    if ($fiche_id) {
        $stmt = $pdo->prepare("SELECT * FROM personnages WHERE ID_fiche = ?");
        $stmt->execute([$fiche_id]);
        $fiche = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fiche) {
            $message = "<div class='alert alert-warning'>Fiche non trouvée.</div>";
        } else {
            // --- GESTION DE LA VISIBILITÉ AU CHARGEMENT (pour le Rédacteur) ---
            if ($userStatut == 3 && $fiche['auteur'] !== $userLogin) {
                $message = "<div class='alert alert-danger'>⛔ Accès refusé : En tant que Rédacteur, vous ne pouvez pas modifier cette fiche.</div>";
                $fiche = null; // Annule l'affichage de la fiche
            }
            // -------------------------------------------------------------------
        }
    }
}

// Action : Modifier une fiche existante
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier') {
    $id_fiche = $_POST['ID_fiche'] ?? null;
    if ($id_fiche) {
        
        // --- SÉCURITÉ : Vérification de propriété AVANT l'UPDATE (utilise die() si accès non autorisé) ---
        $stmt = $pdo->prepare("SELECT auteur, est_en_ligne FROM personnages WHERE ID_fiche = ?"); 
        $stmt->execute([$id_fiche]);
        $fiche_actuelle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fiche_actuelle) {
             die("<h1 style='color: #dc3545; text-align: center; padding: 50px;'>❌ Erreur de sécurité</h1><p style='text-align: center;'>La fiche n'existe pas ou l'accès est refusé.</p>");
        }
        checkFicheOwnership($fiche_actuelle, $userStatut, $userLogin);
        // ---------------------------------------------------------
        
        $nom = $_POST['Nom'] ?? '';
        $metier = $_POST['Metier'] ?? '';
        $engagements = $_POST['Engagements'] ?? '';
        $details = $_POST['Details'] ?? '';
        $sources = $_POST['Sources'] ?? '';
        $donnees_genealogiques = $_POST['Donnees_genealogiques'] ?? '';
        $photo = $_POST['Photo'] ?? '';
        $est_en_ligne = $_POST['est_en_ligne'] ?? '0'; // Valeur soumise par le formulaire
        $auteur = $_POST['auteur'] ?? '';
        
        // --- LOGIQUE DE SÉCURITÉ : MISE HORS LIGNE AUTOMATIQUE POUR LE RÉDACTEUR ---
        $statut_actuel = $fiche_actuelle['est_en_ligne'] ?? '0';

        // Si l'utilisateur est rédacteur (3) ET la fiche était en ligne (1)
        if ($userStatut == 3 && $statut_actuel == 1) {
            // FORCE la mise hors ligne, quelle que soit la valeur soumise par le formulaire
            $est_en_ligne = '0';
            $message = "<div class='alert alert-warning'>⚠️ Fiche mise hors ligne. En tant que Rédacteur, vos modifications nécessitent une validation par une personne habilitée.</div>";
        }
        // ------------------------------------------------------------------

      // --- Génération du tableau pour Iconographie ---
        $icono_descs = $_POST['iconographie_description'] ?? [];
        $icono_links = $_POST['iconographie_lien'] ?? [];
        $iconographie = '';
        if (!empty($icono_descs)) {
            $iconographie_lines = ["| Description | Télécharger |", "|-------------|-------------|"];
            foreach ($icono_descs as $i => $desc) {
                if (!empty($desc) || !empty($icono_links[$i])) {
                    $desc_clean = str_replace(['|', "\n"], '', $desc);
                    $link_clean = str_replace(['|', "\n"], '', $icono_links[$i] ?? '');
                    $link_markdown = urlToMarkdownLink($link_clean, 'Télécharger');
                    $iconographie_lines[] = "| $desc_clean | $link_markdown |";
                }
            }
            if (count($iconographie_lines) > 2) {
                $iconographie = implode("\n", $iconographie_lines);
            }
        }
        
       // --- Génération du tableau pour Documents ---
        $doc_descs = $_POST['documents_description'] ?? [];
        $doc_links = $_POST['documents_lien'] ?? [];
        $documents = ''; // Valeur par défaut : champ vide
        if (!empty($doc_descs)) {
            $documents_lines = ["| Description | Télécharger |", "|-------------|-------------|"];
            foreach ($doc_descs as $i => $desc) {
                $link = $doc_links[$i] ?? '';
                if (empty($desc) && empty($link)) {
                    continue;
                }
                $desc_clean = str_replace(['|', "\n"], '', $desc);
                $link_clean = str_replace(['|', "\n"], '', $link);
                
                $link_markdown = urlToMarkdownLink($link_clean, 'Télécharger');
                
                $documents_lines[] = "| $desc_clean | $link_markdown |";
            }
            if (count($documents_lines) > 2) {
                $documents = implode("\n", $documents_lines);
            }
        }

        // --- Gestion des métadonnées ---
        $derniere_modif = date('Y-m-d H:i:s');
        // Le valideur est enregistré seulement si on publie
        $valideur = ($est_en_ligne == '1') ? ($_SESSION['nom_prenom'] ?? 'Système') : null;

        $sql = "UPDATE personnages SET 
                    Nom = ?, Metier = ?, Engagements = ?, Details = ?, Sources = ?, 
                    Donnees_genealogiques = ?, Iconographie = ?, Photo = ?, Documents = ?,
                    est_en_ligne = ?, auteur = ?, valideur = ?, derniere_modif = ?
                WHERE ID_fiche = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nom, $metier, $engagements, $details, $sources,
            $donnees_genealogiques, $iconographie, $photo, $documents,
            $est_en_ligne, $auteur, $valideur, $derniere_modif, $id_fiche
        ]);
        
        // Afficher le message d'alerte spécifique ou le message de succès générique
        if (!isset($message) || empty($message)) {
            $message = "<div class='alert alert-success'>✅ Fiche modifiée avec succès !</div>";
        }
        
        // Recharger la fiche après modification pour l'affichage
        $stmt = $pdo->prepare("SELECT * FROM personnages WHERE ID_fiche = ?");
        $stmt->execute([$id_fiche]);
        $fiche = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?><!DOCTYPE html><html lang="fr"><head>
    <meta charset="UTF-8">
    <title>Modifier une fiche personnage</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/admin/js/simplemde/simplemde.min.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="container">
        <a href="download_db.php" class="btn-download">📥 Télécharger la base</a>
        <h1>🔍 Rechercher et Modifier une Fiche</h1>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="action" value="rechercher">
            <div class="form-group">
                <label for="recherche">Rechercher par Nom ou ID_fiche :</label>
                <input type="text" name="recherche" id="recherche" placeholder="Ex: Jean Dupont ou 123" required>
            </div>
            <button type="submit">Rechercher</button>
        </form>

        <?php if (!empty($resultats) && count($resultats) > 1): ?>
            <div class="results-list">
                <h2>📋 Plusieurs fiches trouvées (<?= count($resultats) ?>)</h2>
                <p>Veuillez sélectionner la fiche que vous souhaitez modifier :</p>
                <?php foreach ($resultats as $result): ?>
                    <div class="result-item">
                        <div>
                            <strong><?= htmlspecialchars($result['Nom']) ?></strong><br>
                            <span class="result-meta">
                                ID: <?= $result['ID_fiche'] ?> • 
                                <?= !empty($result['Metier']) ? htmlspecialchars($result['Metier']) : '—' ?> • 
                                <?= !empty($result['Engagements']) ? htmlspecialchars($result['Engagements']) : '—' ?>
                            </span>
                        </div>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="action" value="charger_fiche">
                            <input type="hidden" name="fiche_id" value="<?= $result['ID_fiche'] ?>">
                            <button type="submit">Modifier</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($fiche): ?>
            <hr>
            <h2>📝 Modification de la fiche #<?= htmlspecialchars($fiche['ID_fiche']) ?></h2>
            <p class="info-text">Auteur actuel : <strong><?= htmlspecialchars($fiche['auteur'] ?? 'Non spécifié') ?></strong> (dernière modification : <?= htmlspecialchars($fiche['derniere_modif'] ?? 'N/A') ?>)</p>
            
            <form method="POST" action="" id="modificationForm" 
                  data-user-statut="<?= $userStatut ?>"
                  data-fiche-enligne="<?= $fiche['est_en_ligne'] ?? '0' ?>">
                
                <input type="hidden" name="action" value="modifier">
                <input type="hidden" name="ID_fiche" value="<?= htmlspecialchars($fiche['ID_fiche']) ?>">
                <div class="form-group">
                    <label for="Nom">Nom :</label>
                    <input type="text" name="Nom" id="Nom" value="<?= htmlspecialchars($fiche['Nom'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="Metier">Métier :</label>
                    <textarea name="Metier" id="Metier"><?= htmlspecialchars($fiche['Metier'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="Engagements">Engagements :</label>
                    <textarea name="Engagements" id="Engagements"><?= htmlspecialchars($fiche['Engagements'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="Details">Détails :</label>
                    <textarea name="Details" id="Details"><?= htmlspecialchars($fiche['Details'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="Sources">Sources :</label>
                    <textarea name="Sources" id="Sources"><?= htmlspecialchars($fiche['Sources'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="Donnees_genealogiques">Données généalogiques :</label>
                    <textarea name="Donnees_genealogiques" id="Donnees_genealogiques"><?= htmlspecialchars($fiche['Donnees_genealogiques'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Iconographie</label>
                    <div id="iconographie-container">
                        <?php
                        // Parser le tableau Markdown existant
                        $icono_lines = explode("\n", trim($fiche['Iconographie'] ?? ''));
                        for ($i = 2; $i < count($icono_lines); $i++) {
                            $line = trim($icono_lines[$i]);
                            if (preg_match('/^\|\s*(.*?)\s*\|\s*(.*?)\s*\|$/', $line, $matches)) {
                                $desc_value = htmlspecialchars($matches[1]);
                                $link_value = htmlspecialchars($matches[2]);
                                
                                if (preg_match('/\[Télécharger\]\((.*?)\)/', $link_value, $link_matches)) {
                                    $link_value = $link_matches[1];
                                }
                                
                                echo '<div class="resource-pair">';
                                echo '<input type="text" name="iconographie_description[]" value="' . $desc_value . '" placeholder="Description" required>';
                                echo '<input type="url" name="iconographie_lien[]" value="' . $link_value . '" placeholder="https://...">';
                                echo '<button type="button" class="btn-remove" onclick="this.parentElement.remove()">🗑️</button>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="btn-add" onclick="addResourcePair('iconographie')">➕ Ajouter une ligne</button>
                </div>

                <div class="form-group">
                    <label>Documents</label>
                    <div id="documents-container">
                        <?php
                        // Parser le tableau Markdown existant
                        $doc_lines = explode("\n", trim($fiche['Documents'] ?? ''));
                        for ($i = 2; $i < count($doc_lines); $i++) {
                            $line = trim($doc_lines[$i]);
                            if (preg_match('/^\|\s*(.*?)\s*\|\s*(.*?)\s*\|$/', $line, $matches)) {
                                $desc_value = htmlspecialchars($matches[1]);
                                $link_value = htmlspecialchars($matches[2]);
                                
                                if (preg_match('/\[Télécharger\]\((.*?)\)/', $link_value, $link_matches)) {
                                    $link_value = $link_matches[1];
                                }
                                
                                echo '<div class="resource-pair">';
                                echo '<input type="text" name="documents_description[]" value="' . $desc_value . '" placeholder="Description" required>';
                                echo '<input type="url" name="documents_lien[]" value="' . $link_value . '" placeholder="https://...">';
                                echo '<button type="button" class="btn-remove" onclick="this.parentElement.remove()">🗑️</button>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="btn-add" onclick="addResourcePair('documents')">➕ Ajouter une ligne</button>
                </div>

                <div class="form-group">
                    <label for="Photo">Photo (URL ou chemin relatif) :</label>
                    <input type="text" name="Photo" id="Photo" value="<?= htmlspecialchars($fiche['Photo'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="auteur">Auteur :</label>
                    <input type="text" name="auteur" id="auteur" value="<?= htmlspecialchars($fiche['auteur'] ?? $userLogin) ?>" 
                           <?= ($userStatut == 3 && $fiche['auteur'] !== ($userLogin ?? '')) ? 'readonly' : '' ?>
                           title="Normalement non modifiable par les Rédacteurs (3).">
                    <p class="info-text"><em>⚠️ Le champ Auteur est rempli automatiquement au moment de la création.</em></p>
                </div>

                <div class="form-group">
                    <label for="est_en_ligne">Statut de publication :</label>
                    <?php
                    $roles_valideurs = [1, 2, 4, 6];
                    $peut_modifier_pub = in_array($userStatut, $roles_valideurs);
                    $valeur_actuelle = $fiche['est_en_ligne'] ?? '0';
                    ?>
                    <select name="est_en_ligne" id="est_en_ligne" <?= $peut_modifier_pub ? '' : 'disabled title="Seuls les valideurs et administrateurs peuvent publier."' ?>>
                        <option value="0" <?= ($valeur_actuelle == 0) ? 'selected' : '' ?>>🔴 Hors ligne (brouillon)</option>
                        <option value="1" <?= ($valeur_actuelle == 1) ? 'selected' : '' ?>>✅ En ligne (publique)</option>
                    </select>
                    <?php if (!$peut_modifier_pub): ?>
                        <input type="hidden" name="est_en_ligne" value="<?= $valeur_actuelle ?>">
                        <p class="info-text"><em>ℹ️ Seuls les valideurs et administrateurs peuvent publier une fiche.</em></p>
                    <?php endif; ?>
                </div>

                <button type="submit" id="saveButton">💾 Enregistrer les modifications</button>
            </form>
        <?php elseif ($recherche_effectuee && empty($resultats) && !$fiche): ?>
            <p>Aucune fiche à afficher.</p>
        <?php endif; ?>
    </div>

    <script src="/admin/js/simplemde/simplemde.min.js"></script>
    <script>
        // Initialiser SimpleMDE pour les champs Markdown
        const simplemdeDetails = new SimpleMDE({
            element: document.getElementById("Details"),
            toolbar: ["bold", "italic", "link"],
            spellChecker: false,
            status: false
        });
        const simplemdeSources = new SimpleMDE({
            element: document.getElementById("Sources"),
            toolbar: ["bold", "italic", "link"],
            spellChecker: false,
            status: false
        });

        // Gestion dynamique des ressources
        function addResourcePair(type) {
            const container = document.getElementById(type + '-container');
            const div = document.createElement('div');
            div.className = 'resource-pair';
            div.innerHTML = `
                <input type="text" name="${type}_description[]" placeholder="Description" required>
                <input type="url" name="${type}_lien[]" placeholder="https://...">
                <button type="button" class="btn-remove" onclick="this.parentElement.remove()">🗑️</button>
            `;
            container.appendChild(div);
        }
        
        // --- LOGIQUE DE CONFIRMATION AVEC MISE HORS LIGNE (Rédacteur) ---
        const form = document.getElementById('modificationForm');

        if (form) {
            const userStatut = parseInt(form.getAttribute('data-user-statut'));
            const ficheEnLigne = form.getAttribute('data-fiche-enligne') === '1';

            // Intercepter la soumission SEULEMENT si c'est un Rédacteur (3) et la fiche est en ligne (1)
            if (userStatut === 3 && ficheEnLigne) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault(); // Empêche l'envoi immédiat du formulaire
                    
                    const confirmationMessage = 
                        "Pour des raisons de sécurité, l'enregistrement de vos modifications entraînera la mise hors ligne de votre fiche. " +
                        "Il vous faudra contacter le valideur pour une remise en ligne.";

                    // Utilisation du pop-up de confirmation natif
                    if (confirm(confirmationMessage)) {
                        // Si l'utilisateur clique sur "Accepter"
                        this.submit(); // Envoie le formulaire (où la logique PHP forcera est_en_ligne à 0)
                    } else {
                        // Si l'utilisateur clique sur "Abandonner la modification"
                        window.location.href = 'index.php'; // Redirige vers index.php
                    }
                });
            }
        }
    </script></body></html>
