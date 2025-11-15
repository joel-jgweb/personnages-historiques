<?php
// Endpoint AJAX pour renvoyer la bio d'un Nom (tout en majuscules)
header('Content-Type: text/html; charset=utf-8');
if (!isset($_GET['nom']) || !$_GET['nom']) {
    echo "<em>Aucune biographie trouvée.</em>";
    exit;
}
require_once __DIR__ . '/lib/Parsedown.php';
$db = new SQLite3('../portraits.sqlite');
$nom = trim($_GET['nom']);
$stmt = $db->prepare("SELECT Details FROM personnages WHERE upper(Nom) = :nom");
$stmt->bindValue(':nom', mb_strtoupper($nom), SQLITE3_TEXT);
$res = $stmt->execute();
$row = $res->fetchArray(SQLITE3_ASSOC);

if ($row && $row['Details']) {
    $pd = new Parsedown();
    echo $pd->text($row['Details']);
} else {
    echo '<em>Aucune biographie trouvée pour ce nom.</em>';
}
