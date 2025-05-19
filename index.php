<?php
require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/people_detection.php';
require __DIR__ . '/ffmpeg_script.php';

$config = require __DIR__ . '/config.php';

// GET: show form
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo <<<HTML
<!DOCTYPE html>
<html lang="it">
<head><meta charset="UTF-8"><title>Video Montaggio AI</title></head>
<body>
<h1>Carica i video</h1>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="videos[]" accept="video/*" multiple><br><br>
  <label><input type="checkbox" name="detect_people"> Rilevamento persone</label><br><br>
  <button type="submit">Avvia</button>
</form>
</body></html>
HTML;
    exit;
}

// POST: process
ensureDir($config['paths']['upload_dir']);
ensureDir($config['paths']['temp_dir']);
ensureDir($config['paths']['output_dir']);

$uploaded = handleUploads($config['paths']['upload_dir'], $config['system']['max_upload_size']);
if (empty($uploaded)) {
    http_response_code(400);
    echo 'Nessun file caricato';
    exit;
}

if (!empty($_POST['detect_people'])) {
    $segments = detectMovingPeople($uploaded, $config['detection'], $config['paths']['temp_dir']);
} else {
    $segments = array_map(fn($f)=>convertToTs($f,$config), $uploaded);
}

$combinedTs = concatTsSegments($segments, $config['paths']['temp_dir']);

$outputMp4 = $config['paths']['output_dir'] . 'final_' . time() . '.mp4';
processVideo($combinedTs, $outputMp4, $config['paths']['output_dir']);

// Cleanup
cleanupTemp(array_merge($segments, [$combinedTs]), $config['paths']['temp_dir']);

echo "Video creato: " . $config['system']['base_url'] . '/output/' . basename($outputMp4);
?>