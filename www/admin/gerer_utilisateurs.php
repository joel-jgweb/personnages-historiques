<?php
// gerer_utilisateurs.php - Gestion complète des utilisateurs et de leurs rôles
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'permissions.php';
checkUserPermission([1]);

require_once __DIR__ . '/../config.php';
$dbPath = '../../data/portraits.sqlite';
$message = '';

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Erreur de connexion à la base de données : " . $e->getMessage());
}

// Récupérer la liste des statuts pour les formulaires
$stmt = $pdo->query("SELECT ID_statut, nom_statut FROM statuts ORDER BY ID_statut");
$statuts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Détecter si on veut modifier un utilisateur
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : null;
$user_to_edit = null;
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE ID_utilisateur = ?");
    $stmt->execute([$edit_id]);
    $user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action : Ajouter un nouvel utilisateur
    if ($action === 'ajouter') {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $id_statut = $_POST['id_statut'] ?? 1;
        $prenom_nom = trim($_POST['prenom_nom'] ?? '');

        if (empty($login) || empty($password)) {
            $message = "<div class='alert alert-warning'>Le login et le mot de passe sont obligatoires.</div>";
        } else {
            $stmt = $pdo->prepare("SELECT ID_utilisateur FROM utilisateurs WHERE login = ?");
            $stmt->execute([$login]);
            if ($stmt->fetch()) {
                $message = "<div class='alert alert-warning'>Ce nom d'utilisateur existe déjà.</div>";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (login, mot_de_passe, ID_statut, prenom_nom) VALUES (?, ?, ?, ?)");
                $stmt->execute([$login, $hashedPassword, $id_statut, $prenom_nom]);
                $message = "<div class='alert alert-success'>✅ Utilisateur <strong>" . htmlspecialchars($login) . "</strong> ajouté avec succès !</div>";
            }
        }
    }

    // Action : Modifier un utilisateur existant
    if ($action === 'modifier') {
        $id = $_POST['ID_utilisateur'] ?? null;
        $login = trim($_POST['login'] ?? '');
        $id_statut = $_POST['id_statut'] ?? 1;
        $new_password = $_POST['new_password'] ?? '';
        $prenom_nom = trim($_POST['prenom_nom'] ?? '');

        if ($id && !empty($login)) {
            $updates = ["login = ?", "ID_statut = ?", "prenom_nom = ?"];
            $params = [$login, $id_statut, $prenom_nom];

            if (!empty($new_password)) {
                $updates[] = "mot_de_passe = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            $params[] = $id;
            $sql = "UPDATE utilisateurs SET " . implode(', ', $updates) . " WHERE ID_utilisateur = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $message = "<div class='alert alert-success'>✅ Utilisateur <strong>" . htmlspecialchars($login) . "</strong> modifié avec succès !</div>";
            // Pour éviter de garder le formulaire de modification affiché après modif
            $user_to_edit = null;
        } else {
            $message = "<div class='alert alert-warning'>❌ L'ID de l'utilisateur ou le login est manquant.</div>";
        }
    }

    // Action : Supprimer un utilisateur
    if ($action === 'supprimer') {
        $id = $_POST['ID_utilisateur'] ?? null;
        if ($id) {
            if ($id == $_SESSION['user_id']) {
                $message = "<div class='alert alert-danger'>❌ Vous ne pouvez pas vous supprimer vous-même.</div>";
            } else {
                $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE ID_utilisateur = ?");
                $stmt->execute([$id]);
                $message = "<div class='alert alert-success'>✅ Utilisateur supprimé avec succès !</div>";
            }
        }
    }
}

// Récupérer la liste complète des utilisateurs avec leurs rôles et prénom_nom
$stmt = $pdo->query("SELECT u.ID_utilisateur, u.login, u.prenom_nom, u.date_creation, u.dernier_login, s.nom_statut 
                     FROM utilisateurs u 
                     JOIN statuts s ON u.ID_statut = s.ID_statut 
                     ORDER BY u.ID_utilisateur");
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>👥 Gestion des Utilisateurs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="container">
        <h1>👥 Gestion des Utilisateurs</h1>

        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <!-- Section : Ajouter ou Modifier un utilisateur -->
        <?php if ($user_to_edit): ?>
        <h2 class="section-title">✏️ Modifier l'utilisateur #<?= $user_to_edit['ID_utilisateur'] ?></h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="modifier">
            <input type="hidden" name="ID_utilisateur" value="<?= $user_to_edit['ID_utilisateur'] ?>">
            <div class="form-group">
                <label for="login">Nom d'utilisateur *</label>
                <input type="text" name="login" id="login" required value="<?= htmlspecialchars($user_to_edit['login']) ?>">
            </div>
            <div class="form-group">
                <label for="prenom_nom">Prénom Nom *</label>
                <input type="text" name="prenom_nom" id="prenom_nom" required value="<?= htmlspecialchars($user_to_edit['prenom_nom']) ?>">
            </div>
            <div class="form-group">
                <label for="new_password">Nouveau mot de passe</label>
                <input type="password" name="new_password" id="new_password" placeholder="Laisser vide pour ne pas changer">
            </div>
            <div class="form-group">
                <label for="id_statut">Statut</label>
                <select name="id_statut" id="id_statut">
                    <?php foreach ($statuts as $statut): ?>
                        <option value="<?= $statut['ID_statut'] ?>" <?= ($statut['ID_statut'] == $user_to_edit['ID_statut']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statut['nom_statut']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Valider la modification</button>
            <a href="gerer_utilisateurs.php" style="margin-left:2em;">Annuler</a>
        </form>
        <?php else: ?>
        <h2 class="section-title">➕ Ajouter un nouvel utilisateur</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="ajouter">
            <div class="form-group">
                <label for="login">Nom d'utilisateur *</label>
                <input type="text" name="login" id="login" required>
            </div>
            <div class="form-group">
                <label for="prenom_nom">Prénom Nom *</label>
                <input type="text" name="prenom_nom" id="prenom_nom" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe *</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group">
                <label for="id_statut">Statut</label>
                <select name="id_statut" id="id_statut">
                    <?php foreach ($statuts as $statut): ?>
                        <option value="<?= $statut['ID_statut'] ?>" <?= ($statut['ID_statut'] == 1) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statut['nom_statut']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Ajouter l'utilisateur</button>
        </form>
        <?php endif; ?>

        <!-- Section : Liste des utilisateurs -->
        <h2 class="section-title">📋 Liste des utilisateurs (<?= count($utilisateurs) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Login</th>
                    <th>Prénom Nom</th>
                    <th>Statut</th>
                    <th>Date de création</th>
                    <th>Dernière connexion</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($utilisateurs)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem;">Aucun utilisateur trouvé.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($utilisateurs as $user): ?>
                        <tr>
                            <td><?= $user['ID_utilisateur'] ?></td>
                            <td><?= htmlspecialchars($user['login']) ?></td>
                            <td><?= htmlspecialchars($user['prenom_nom']) ?></td>
                            <td><?= htmlspecialchars($user['nom_statut']) ?></td>
                            <td><?= $user['date_creation'] ?></td>
                            <td><?= $user['dernier_login'] ?? '<em>Jamais</em>' ?></td>
                            <td class="action-buttons">
                                <!-- Lien d'édition -->
                                <a href="gerer_utilisateurs.php?edit=<?= $user['ID_utilisateur'] ?>" class="btn-edit">Modifier</a>
                                <!-- Formulaire de suppression -->
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.');">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="ID_utilisateur" value="<?= $user['ID_utilisateur'] ?>">
                                    <button type="submit" class="btn-delete">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
