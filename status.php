<?php
error_reporting(0);
header('Content-Type:text/plain');
$job = preg_replace('/[^a-z0-9]/','',$_GET['job'] ?? '');
$path = __DIR__ . '/temp/progress_' . $job . '.log';
if (file_exists($path)) echo file_get_contents($path);
else echo "Nessun job trovato: $job
";
?>