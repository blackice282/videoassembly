<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['file'])) {
    http_response_code(400);
    echo "Errore: nessun file richiesto.";
    exit;
}

$filename = basename($_GET['file']); // Sicurezza
$path = __DIR__ . '/uploads/' . $filename;

if (!file_exists($path)) {
    http_response_code(404);
    echo "<h2 style='font-family:sans-serif;color:red;'>❌ File non trovato: $filename</h2>";
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
