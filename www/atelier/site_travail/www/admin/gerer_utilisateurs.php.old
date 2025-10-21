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

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action : Ajouter un nouvel utilisateur
    if ($action === 'ajouter') {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $id_statut = $_POST['id_statut'] ?? 1; // Valeur par défaut : Administrateur

        if (empty($login) || empty($password)) {
            $message = "<div class='alert alert-warning'>Le login et le mot de passe sont obligatoires.</div>";
        } else {
            // Vérifier si le login existe déjà
            $stmt = $pdo->prepare("SELECT ID_utilisateur FROM utilisateurs WHERE login = ?");
            $stmt->execute([$login]);
            if ($stmt->fetch()) {
                $message = "<div class='alert alert-warning'>Ce nom d'utilisateur existe déjà.</div>";
            } else {
                // Hacher le mot de passe
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (login, mot_de_passe, ID_statut) VALUES (?, ?, ?)");
                $stmt->execute([$login, $hashedPassword, $id_statut]);
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

        if ($id && !empty($login)) {
            $updates = ["login = ?", "ID_statut = ?"];
            $params = [$login, $id_statut];

            // Mettre à jour le mot de passe uniquement s'il est fourni
            if (!empty($new_password)) {
                $updates[] = "mot_de_passe = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            $params[] = $id; // Pour la clause WHERE

            $sql = "UPDATE utilisateurs SET " . implode(', ', $updates) . " WHERE ID_utilisateur = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $message = "<div class='alert alert-success'>✅ Utilisateur <strong>" . htmlspecialchars($login) . "</strong> modifié avec succès !</div>";
        } else {
            $message = "<div class='alert alert-warning'>❌ L'ID de l'utilisateur ou le login est manquant.</div>";
        }
    }

    // Action : Supprimer un utilisateur
    if ($action === 'supprimer') {
        $id = $_POST['ID_utilisateur'] ?? null;
        if ($id) {
            // Vérifier qu'on ne supprime pas l'utilisateur actuel
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

// Récupérer la liste complète des utilisateurs avec leurs rôles
$stmt = $pdo->query("SELECT u.ID_utilisateur, u.login, u.date_creation, u.dernier_login, s.nom_statut 
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
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 1.5rem;
        }
        .alert {
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 5px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #555;
        }
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        button {
            background-color: #007BFF;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            margin-top: 1rem;
        }
        button:hover {
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-edit:hover {
            background-color: #e0a800;
        }
        .btn-delete {
            background-color: #dc3545;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .section-title {
            border-bottom: 2px solid #007BFF;
            padding-bottom: 0.5rem;
            margin: 2rem 0 1rem 0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>👥 Gestion des Utilisateurs</h1>

        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <!-- Section : Ajouter un nouvel utilisateur -->
        <h2 class="section-title">➕ Ajouter un nouvel utilisateur</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="ajouter">
            <div class="form-group">
                <label for="login">Nom d'utilisateur *</label>
                <input type="text" name="login" id="login" required>
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

        <!-- Section : Liste des utilisateurs -->
        <h2 class="section-title">📋 Liste des utilisateurs (<?= count($utilisateurs) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Login</th>
                    <th>Statut</th>
                    <th>Date de création</th>
                    <th>Dernière connexion</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($utilisateurs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem;">Aucun utilisateur trouvé.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($utilisateurs as $user): ?>
                        <tr>
                            <td><?= $user['ID_utilisateur'] ?></td>
                            <td><?= htmlspecialchars($user['login']) ?></td>
                            <td><?= htmlspecialchars($user['nom_statut']) ?></td>
                            <td><?= $user['date_creation'] ?></td>
                            <td><?= $user['dernier_login'] ?? '<em>Jamais</em>' ?></td>
                            <td class="action-buttons">
                                <!-- Formulaire de modification -->
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Voulez-vous vraiment modifier cet utilisateur ?');">
                                    <input type="hidden" name="action" value="modifier">
                                    <input type="hidden" name="ID_utilisateur" value="<?= $user['ID_utilisateur'] ?>">
                                    <input type="hidden" name="login" value="<?= htmlspecialchars($user['login']) ?>">
                                    <!-- Pour un vrai formulaire de modification, vous devriez avoir un modal ou une page dédiée.
                                         Ici, on modifie directement avec le même login et un champ de mot de passe vide.
                                         Pour une version plus avancée, créez un modal. -->
                                    <button type="submit" class="btn-edit">Modifier</button>
                                </form>
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