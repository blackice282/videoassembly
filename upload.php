<?php
// upload.php - Backend streaming real-time debug per montaggio video
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('PROCESSED_DIR', __DIR__ . '/processed/');
// Headers per streaming
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache');
// Disabilita buffering di Nginx/Proxy
header('X-Accel-Buffering: no');
// Disabilita buffering PHP e abilita flush immediato
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);

// Funzione di debug streaming
function debug($msg) {
    echo "<p>{$msg}</p>\n";
    flush();
}

// Dump dati ricevuti
debug('Ricevuti dati: ' . print_r($_POST, true));

debug('Inizio upload di ' . count($_FILES['videos']['name']) . ' file...');

// Crea directory se non esiste
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
    debug('Cartella uploads creata.');
}

// Carica i file video
$files = $_FILES['videos'];
$total = count($files['name']);
for ($i = 0; $i < $total; $i++) {
    $name = $files['name'][$i];
    debug("Upload file {$i}: {$name}...");
    if (move_uploaded_file($files['tmp_name'][$i], UPLOAD_DIR . $name)) {
        debug('OK');
    } else {
        debug('ERRORE');
    }
}

debug('Montaggio in corso...');

// Simula o richiama qui la funzione di montaggio
// include 'video_assembly.php';
// $montageSuccess = assembleVideos(UPLOAD_DIR, PROCESSED_DIR, $_POST['duration'], $_POST['instructions']);
$montageSuccess = true; // placeholder

if ($montageSuccess) {
    debug('Montaggio completato.');
    // Assumiamo nome output fisso
    $finalName = 'video_final.mp4';
    $url = dirname($_SERVER['REQUEST_URI']) . '/processed/' . $finalName;
    debug("URL finale: <a href=\"{$url}\" target=\"_blank\">{$url}</a>");
} else {
    debug('Errore nel montaggio.');
}

exit;
