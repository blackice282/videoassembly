<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['file'])) {
  http_response_code(400);
  echo "Errore: nessun file richiesto."; exit;
}
$file = basename($_GET['file']);
$path = __DIR__ . '/uploads/' . $file;
if (!file_exists($path)) {
  http_response_code(404);
  echo "<h2 style='color:red;'>❌ File non trovato: $file</h2>";
  exit;
}
header('Content-Description: File Transfer');
header('Content-Type: '.mime_content_type($path));
header('Content-Disposition: attachment; filename="'.$file.'"');
header('Content-Length: '.filesize($path));
readfile($path);
exit;
?>
