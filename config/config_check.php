<?php
// Anciennement un script de check — neutralisé volontairement.
// Retourne 410 Gone pour indiquer que la route n'est plus disponible.

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo "410 Gone — cette ressource de configuration a été déplacée ou supprimée.";
exit;
?>