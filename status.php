<?php
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain');
$job  = preg_replace('/[^a-z0-9]/','', $_GET['job'] ?? '');
$path = getConfig('paths.temp') . "/progress_{$job}.log";
echo file_exists($path) ? file_get_contents($path) : "Nessun job $job\n";
