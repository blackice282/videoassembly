<?php
// status.php
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain');

// Sanitize job ID
$job = isset($_GET['job']) 
    ? preg_replace('/[^a-z0-9]/','', $_GET['job']) 
    : '';

$path = getConfig('paths.temp') . "/progress_{$job}.log";

if (file_exists($path)) {
    // Stampa il contenuto del log
    readfile($path);
} else {
    echo "Nessun job trovato: {$job}\n";
}
