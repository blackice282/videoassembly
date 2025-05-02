<?php
header('Content-Type:text/plain');
$job = preg_replace('/[^a-z0-9]/','',$_GET['job']??'');
$file = getConfig('paths.temp')."/progress_{$job}.log";
echo file_exists($file) ? file_get_contents($file) : "Nessun job $job\n";
