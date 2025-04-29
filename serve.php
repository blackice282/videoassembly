<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['file'])) {
    http_response_code(400);
    echo "Errore: file non specificato.";
    exit;
}

$filename = basename($_GET['file']); // evita accessi arbitrari
$path = __DIR__ . '/uploads/' . $filename;

if (!file_exists($path)) {
    http_response_code(404);
    echo "Il file richiesto non esiste.";
    exit;
}

header('Content-Description: File Transfer');
header('Content-Type: ' . mime_content_type($path));
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
?>
