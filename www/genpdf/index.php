<?php
// Configuration des chemins (doit être la même que dans generer_pdf.php)
$DB_PATH = __DIR__ . '/../../data/portraits.sqlite'; 
$TABLE_NAME = 'personnages';
$ID_COLUMN = 'ID_fiche'; 

$message_erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fiche_id'])) {
    $fiche_id = filter_var($_POST['fiche_id'], FILTER_VALIDATE_INT);

    if ($fiche_id === false || $fiche_id <= 0) {
        $message_erreur = "Veuillez entrer un numéro de fiche valide.";
    } else {
        // 1. VÉRIFICATION DE LA BASE DE DONNÉES
        if (!file_exists($DB_PATH)) {
            $message_erreur = "Erreur critique : Fichier de base de données non trouvé.";
        } else {
            try {
                $db = new PDO('sqlite:' . $DB_PATH);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Vérifie si la fiche existe et est marquée 'est_en_ligne = 1'
                $stmt = $db->prepare("SELECT COUNT(*) FROM {$TABLE_NAME} WHERE {$ID_COLUMN} = :id AND est_en_ligne = 1");
                $stmt->execute([':id' => $fiche_id]);
                $count = $stmt->fetchColumn();

                if ($count > 0) {
                    // 2. REDIRECTION VERS LE GÉNÉRATEUR
                    // Redirige l'utilisateur vers le script de génération du PDF
                    header("Location:generer_pdf_tcpdf.php?id=" . $fiche_id);
                    exit();
                } else {
                    $message_erreur = "Cette fiche n'existe pas ou n'est pas encore en ligne !";
                }
            } catch (PDOException $e) {
                // Pour le débogage, vous pouvez afficher l'erreur, sinon affichez un message générique
                // $message_erreur = "Erreur de base de données : " . $e->getMessage();
                $message_erreur = "Erreur lors de la vérification de la fiche. Veuillez réessayer.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Générateur de Fiche PDF Provisoire</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; background-color: #f4f4f4; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 400px; margin: auto; }
        h1 { color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="number"] { width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; }
        button:hover { background-color: #0056b3; }
        .error { color: red; background-color: #fee; border: 1px solid red; padding: 10px; margin-bottom: 20px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Générateur de Fiche PDF</h1>
        <p>Veuillez entrer le numéro d'ID de la fiche biographique à générer.</p>
        
        <?php if (!empty($message_erreur)): ?>
            <div class="error"><?php echo htmlspecialchars($message_erreur); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <label for="fiche_id">Numéro de Fiche (ID)</label>
            <input type="number" id="fiche_id" name="fiche_id" required min="1">
            <button type="submit">Générer le PDF</button>
        </form>
    </div>
</body>
</html>