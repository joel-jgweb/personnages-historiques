<?php
// config.local.php — configuration locale (NE PAS COMMITTER)

return [
    'database_path'      => __DIR__ . '/../data/portraits.sqlite',
    'logo_path'          => '../data/docs/logo.png',           // ou URL absolue
    'background_image'   => '../data/docs/background.jpg',     // ou URL absolue
    'background_color'   => '#2a2a2a',
    'site_title'         => 'Nom du site',
    'site_subtitle'      => 'Le sous-titre du site',
    'primary_color'      => '#2c3e50',
    'secondary_color'    => '#6c757d',
    'default_search_mode' => 'all', // 'all' ou 'name'
    'association_name'   => "Nom de l'association",
    'association_address'=> "123 rue Exemple\n75000 Ville",
    'docs_path'          => __DIR__ . '/../data/docs/',
];