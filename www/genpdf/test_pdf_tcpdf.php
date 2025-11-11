<?php
/**
 * test_tcpdf.php
 * Script de test minimal pour vérifier l'installation TCPDF + adapter.
 *
 * Usage : /www/genpdf/test_tcpdf.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// inclusion adapter (qui inclut tcpdf)
require_once __DIR__ . '/TCPDFAdapter.php';

$html = '<h1>Test TCPDF</h1><p>Si vous voyez ce PDF, TCPDF est correctement installé et l\'adapter fonctionne.</p>';

$adapter = new TCPDFAdapter();
$adapter->generateFromHtml($html, null, '<div style="font-size:10pt">Logo</div>', '<div style="font-size:8pt">© Test</div>', null, true);
exit;