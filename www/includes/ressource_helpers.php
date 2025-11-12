<?php
/**
 * ressource_helpers.php
 * Helpers partagés pour extraire et afficher les ressources situées dans /data/docs.
 * Utilisation : require_once __DIR__ . '/ressource_helpers.php';
 */

if (!function_exists('extractFilenamesFromField')) {
    /**
     * Extrait les noms de fichiers depuis le contenu d'un champ (Markdown table ou liste newline).
     * Retourne un tableau de basenames nettoyés et uniques.
     */
    function extractFilenamesFromField($fieldValue) {
        $result = [];
        if (!$fieldValue) return $result;

        // Détection d'un tableau Markdown contenant un lien (ex: (../data/docs/IMG_...))
        if (strpos($fieldValue, '|') !== false && (strpos($fieldValue, 'Télécharger') !== false || strpos($fieldValue, '[Télécharger]') !== false)) {
            $lines = preg_split('/\r\n|\r|\n/', $fieldValue);
            foreach ($lines as $line) {
                if (preg_match('/\(([^)]+)\)/', $line, $m)) {
                    $path = trim($m[1]);
                    if ($path !== '') $result[] = basename($path);
                }
            }
        } else {
            // liste simple : un nom par ligne
            $lines = preg_split('/\r\n|\r|\n/', trim($fieldValue));
            foreach ($lines as $l) {
                $l = trim($l);
                if ($l !== '') $result[] = basename($l);
            }
        }

        // nettoyage : trim, unique, enlever vides
        $result = array_map('trim', $result);
        $result = array_values(array_filter($result, function($v){ return $v !== ''; }));
        $result = array_values(array_unique($result));
        return $result;
    }
}

if (!function_exists('renderRessourceCard')) {
    /**
     * Rend une "carte" HTML pour une ressource (image ou document).
     * @param string $filename basename (ex: IMG_250101120000.jpg)
     * @param int $thumbWidth largeur du thumb en px (0 pour pas de thumb)
     * @return string fragment HTML
     */
    function renderRessourceCard($filename, $thumbWidth = 160) {
        $filename = (string)$filename;
        $safeName = htmlspecialchars(basename($filename), ENT_QUOTES | ENT_SUBSTITUTE);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);

        // utilisation d'un chemin absolu vers acces_docs pour fonctionner depuis n'importe quelle page
        $fileUrl = '/acces_docs.php?f=' . rawurlencode($filename);
        $downloadUrl = $fileUrl . '&download=1';
        $thumbParam = ($thumbWidth > 0 && $isImage) ? '&thumb=' . intval($thumbWidth) : '';

        ob_start();
        ?>
        <li class="resource-item" data-name="<?= $safeName ?>" style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
            <div class="resource-thumb" style="flex:0 0 auto;">
                <?php if ($isImage): ?>
                    <a href="<?= $fileUrl ?>" target="_blank" rel="noopener noreferrer" title="<?= $safeName ?>">
                        <img src="<?= $fileUrl . $thumbParam ?>" alt="<?= $safeName ?>" style="max-height:<?= intval($thumbWidth/2) ?>px; display:block;"/>
                    </a>
                <?php else: ?>
                    <a href="<?= $fileUrl ?>" target="_blank" rel="noopener noreferrer" title="<?= $safeName ?>" style="display:inline-block;width:60px;height:60px;text-align:center;line-height:60px;border:1px solid #ddd;border-radius:4px;background:#f8f9fa;">
                        <?php
                            if ($ext === 'pdf') echo '📄';
                            elseif ($ext === 'txt') echo '📝';
                            elseif ($ext === 'zip') echo '📦';
                            else echo '📎';
                        ?>
                    </a>
                <?php endif; ?>
            </div>

            <div class="resource-meta" style="flex:1 1 auto;">
                <div class="resource-name" style="font-size:0.95em;">
                    <?= $safeName ?>
                </div>
                <div class="resource-actions" style="margin-top:6px;">
                    <a class="btn-download" href="<?= $downloadUrl ?>" target="_blank" rel="noopener noreferrer" style="margin-right:8px;">Télécharger</a>
                    <button type="button" class="btn-insert" onclick="if(window.insertResourceToForm) window.insertResourceToForm('<?= addslashes($safeName) ?>'); else alert('insertResourceToForm non défini');" style="margin-right:8px;">Insérer</button>
                    <button type="button" class="btn-remove" onclick="(function(btn){ var li = btn.closest('li'); if(li) { li.remove(); updateHiddenListFor(li); } })(this);">Supprimer</button>
                </div>
            </div>
        </li>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('renderRessourceGrid')) {
    function renderRessourceGrid(array $filenames, $thumbWidth = 160) {
        ob_start();
        echo '<ul class="resource-list" style="list-style:none;padding:0;margin:0;">';
        foreach ($filenames as $f) {
            echo renderRessourceCard($f, $thumbWidth);
        }
        echo '</ul>';
        return ob_get_clean();
    }
}

// NOTE: les pages appelantes doivent définir ces fonctions JS si elles veulent que les boutons Insérer/Supprimer fonctionnent :
// - updateHiddenListFor(li) : reconstruit la textarea cachée associée à l'ul parent
// - insertResourceToForm(filename) : insère le nom de fichier dans le formulaire (ex: Photo ou liste Iconographie)

?>