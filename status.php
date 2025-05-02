<?php
// status.php
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain');

$job  = preg_replace('/[^a-z0-9]/','', $_GET['job'] ?? '');
$path = getConfig('paths.temp') . "/progress_{$job}.log";

if (file_exists($path)) {
    echo file_get_contents($path);
} else {
    echo "Nessun job trovato: $job\n";
}
