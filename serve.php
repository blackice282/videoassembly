<?php
// serve.php

require_once 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Recupera la directory di upload da config
$uploadDir = getConfig('paths.uploads');

if (!isset($_GET['file'])) {
    http_response_code(400);
    echo "Errore: nessun file richiesto.";
    exit;
}

$filename = basename($_GET['file']);
$path     = $uploadDir . '/' . $filename;

// Debug: stampa su schermo il percorso controllato
// (rimuovi queste righe in produzione)
echo "<pre style='color:gray;'>[DEBUG] uploadDir = {$uploadDir}\n[DEBUG] path = {$path}</pre>";

if (!file_exists($path)) {
    http_response_code(404);
    echo "<h2 style='font-family:sans-serif;color:red;'>❌ File non trovato:<br>{$path}</h2>";
    exit;
}

$mime = mime_content_type($path);
$size = filesize($path);

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $size);
readfile($path);
exit;
?>
