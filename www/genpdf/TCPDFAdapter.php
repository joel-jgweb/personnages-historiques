<?php
/**
 * TCPDFAdapter - wrapper pour TCPDF (version mutualisée, sans Composer)
 *
 * Modifications :
 * - public function processHtmlImages($html) : encodage data:URI et styles pour images locales
 * - prise en charge explicite des <pagebreak /> pour créer plusieurs pages
 * - marges/hauteur d'en-tête augmentées pour éviter chevauchement
 */

if (!class_exists('TCPDF')) {
    $tcpdf_path = __DIR__ . '/../lib/tcpdf/tcpdf.php';
    if (!file_exists($tcpdf_path)) {
        die("<h2 style='color:red;'>Erreur: tcpdf.php introuvable à : " . htmlspecialchars($tcpdf_path) . "</h2>");
    }
    require_once $tcpdf_path;
}

class PDFWithHeaderFooter extends TCPDF
{
    public $headerHtml = '';
    public $footerHtml = '';

    // Header
    public function Header()
    {
        if ($this->headerHtml) {
            // positionner l'entête en haut et écrire le HTML
            $this->SetY(6);
            $this->writeHTMLCell(0, 0, '', '', $this->headerHtml, 0, 1, false, true, 'L', true);
            // laisser suffisamment d'espace sous l'entête
            $this->SetY(36);
        }
    }

    // Footer
    public function Footer()
    {
        if ($this->footerHtml) {
            $this->SetY(-28);
            $this->writeHTMLCell(0, 0, '', '', $this->footerHtml, 0, 0, false, true, 'C', true);
        } else {
            $this->SetY(-15);
            $this->SetFont('dejavusans', '', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }
}

class TCPDFAdapter
{
    private $pdf;

    public function __construct(array $options = [])
    {
        $orientation = $options['orientation'] ?? 'P';
        $unit = $options['unit'] ?? 'mm';
        $format = $options['format'] ?? 'A4';
        $unicode = true;
        $encoding = 'UTF-8';
        $diskcache = false;

        $this->pdf = new PDFWithHeaderFooter($orientation, $unit, $format, $unicode, $encoding, $diskcache);

        // marges (left, top, right) ; top laisse place au header
        $this->pdf->SetMargins(15, 36, 15); // top augmenté pour éviter chevauchement
        $this->pdf->SetAutoPageBreak(true, 28);
        $this->pdf->setPrintHeader(true);
        $this->pdf->setPrintFooter(true);

        // font UTF-8 fiable
        $this->pdf->SetFont('dejavusans', '', 10);
    }

    /**
     * Convertit les images locales trouvées dans le HTML en data:URI et injecte un style non déformant.
     * Rend la balise <img ...> avec styles : max-width:4cm; max-height:4cm; width:auto; height:auto;
     * Renvoie le HTML transformé.
     */
    public function processHtmlImages(string $html): string
    {
        return preg_replace_callback('#<img\s+[^>]*src=["\']([^"\']+)["\'][^>]*>#i', function ($m) {
            $src = $m[1];

            // si déjà data:, laisser
            if (strpos($src, 'data:') === 0) {
                // ajouter styles si absent
                if (strpos($m[0], 'style=') === false) {
                    return '<img src="' . $src . '" style="max-width:4cm;max-height:4cm;width:auto;height:auto;display:block;" />';
                }
                return $m[0];
            }

            // si URL distante, conserver mais ajouter style
            if (preg_match('#^https?://#i', $src)) {
                return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" style="max-width:4cm;max-height:4cm;width:auto;height:auto;display:block;" />';
            }

            // candidats de chemins locaux
            $candidates = [
                $src,
                __DIR__ . '/../../' . ltrim($src, '/'),
                __DIR__ . '/../' . ltrim($src, '/'),
                __DIR__ . '/../../www/' . ltrim($src, '/'),
                __DIR__ . '/../../../' . ltrim($src, '/'),
            ];

            foreach ($candidates as $p) {
                if (file_exists($p) && is_file($p) && filesize($p) > 0) {
                    $mime = @mime_content_type($p) ?: 'application/octet-stream';
                    $data = base64_encode(file_get_contents($p));
                    return '<img src="data:' . $mime . ';base64,' . $data . '" style="max-width:4cm;max-height:4cm;width:auto;height:auto;display:block;" />';
                }
            }

            // fallback : placeholder
            return '<div style="width:4cm;height:4cm;background:#f9f9f9;color:#999;text-align:center;line-height:4cm;border:1px solid #eee;">Image</div>';
        }, $html);
    }

    /**
     * Injecte le contenu CSS dans le <head> si nécessaire.
     */
    protected function injectCss(string $html, ?string $cssContent): string
    {
        if (!$cssContent) return $html;

        if (stripos($html, '<head') !== false) {
            return preg_replace('/(<head[^>]*>)/i', '$1' . "\n<style>\n" . $cssContent . "\n</style>\n", $html, 1);
        }
        return "<html><head><meta charset=\"UTF-8\"><style>\n{$cssContent}\n</style></head><body>{$html}</body></html>";
    }

    /**
     * Génère le PDF. Support explicite des <pagebreak /> : on splitte et on appelle AddPage() pour chaque segment.
     * Si $outPath est null, envoie inline.
     */
    public function generateFromHtml(string $html, ?string $outPath = null, ?string $headerHtml = null, ?string $footerHtml = null, ?string $cssContent = null): bool
    {
        if ($cssContent) {
            $html = $this->injectCss($html, $cssContent);
        }

        // convertir les images locales dans le corps
        $html = $this->processHtmlImages($html);

        // header/footer images aussi si fournis (leur encodage doit être fait en amont si nécessaire)
        if ($headerHtml) {
            $headerHtml = $this->processHtmlImages($headerHtml);
        }
        if ($footerHtml) {
            $footerHtml = $this->processHtmlImages($footerHtml);
        }

        $this->pdf->headerHtml = $headerHtml ?? '';
        $this->pdf->footerHtml = $footerHtml ?? '';

        // split sur balises pagebreak (<pagebreak /> ou <pagebreak>)
        $parts = preg_split('/<\s*pagebreak\s*\/?\s*>/i', $html);

        foreach ($parts as $i => $part) {
            // première page : AddPage() doit être appelé (même si déjà)
            $this->pdf->AddPage();
            // écrire le segment
            $this->pdf->writeHTML($part, true, false, true, false, '');
            // la boucle ajoute autant de pages que nécessaire
        }

        // sortie
        if ($outPath) {
            $this->pdf->Output($outPath, 'F');
            return file_exists($outPath) && filesize($outPath) > 0;
        } else {
            $this->pdf->Output('fiche_biographique.pdf', 'I');
            return true;
        }
    }
}