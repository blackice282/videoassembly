<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['file'])) {
    http_response_code(400);
    echo "Errore: file non specificato.";
    exit;
}

$file = $_GET['file'];

// Sanificazione del percorso per evitare attacchi di directory traversal
$file = str_replace('..', '', $file);

$path = __DIR__ . '/' . $file;

if (!file_exists($path)) {
    http_response_code(404);
    echo "File non trovato.";
    exit;
}

$mimeType = mime_content_type($path);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
?>
