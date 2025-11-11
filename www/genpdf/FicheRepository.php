<?php
/**
 * FicheRepository : centralise l'accès à la base SQLite pour fiches et configuration.
 *
 * Usage :
 *   require_once __DIR__ . '/../bootstrap.php';
 *   require_once __DIR__ . '/FicheRepository.php';
 *   $repo = new FicheRepository(getDatabasePath());
 *   $fiche = $repo->getFicheById($id);
 *   $config = $repo->getConfig();
 */

class FicheRepository
{
    private $pdo;
    private $tableName = 'personnages';

    /**
     * Constructeur.
     * $sqlitePath : chemin absolu vers portraits.sqlite (si null, on tente getDatabasePath())
     */
    public function __construct(?string $sqlitePath = null)
    {
        // si bootstrap fournit getDatabasePath(), l'utiliser préférentiellement
        if (empty($sqlitePath) && function_exists('getDatabasePath')) {
            $sqlitePath = getDatabasePath();
        }

        if (empty($sqlitePath) || !file_exists($sqlitePath)) {
            throw new \RuntimeException("Fichier BDD introuvable : " . var_export($sqlitePath, true));
        }

        $this->pdo = new PDO('sqlite:' . $sqlitePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Retourne la fiche sous forme de tableau associatif ou null si introuvable.
     */
    public function getFicheById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE ID_fiche = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Retourne le tableau de configuration (table configuration id=1) ou valeurs par défaut.
     */
    public function getConfig(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM configuration WHERE id = 1 LIMIT 1");
            $stmt->execute();
            $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cfg) return $cfg;
        } catch (PDOException $e) {
            // fallback silencieux
            error_log("FicheRepository::getConfig() SQL error: " . $e->getMessage());
        }

        return [
            'association_name' => 'Association d\'Histoire Sociale',
            'association_address' => '123 Rue de l\'Histoire, 75000 Paris',
            'logo_path' => null,
            'site_title' => 'Portraits des Militants',
            'site_subtitle' => 'Explorez les parcours de ceux qui ont marqué l\'histoire.'
        ];
    }

    /**
     * Expose la PDO si nécessaire.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}