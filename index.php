<?php
$config = require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/ffmpeg_script.php';
require __DIR__ . '/people_detection.php';
require __DIR__ . '/transitions.php';
require __DIR__ . '/duration_editor.php';

// Se è una GET, mostro un form di upload
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Video Montaggio AI</title>
</head>
<body>
  <h1>Carica i tuoi video</h1>
  <form action="/" method="post" enctype="multipart/form-data">
    <label>Seleziona file (puoi caricare più file):</label><br>
    <input type="file" name="videos[]" multiple accept="video/*"><br><br>
    <label>Durata target (secondi, facoltativo):</label><br>
    <input type="number" name="target_duration" step="0.1"><br><br>
    <label><input type="checkbox" name="detect_people"> Abilita rilevamento persone</label><br><br>
    <button type="submit">Avvia Montaggio</button>
  </form>
</body>
</html>
HTML;
    exit;
}

// Altrimenti è POST: gestisco upload e montaggio
// Preparo le directory
ensureDir($config['paths']['upload_dir']);
ensureDir($config['paths']['temp_dir']);
ensureDir($config['paths']['output_dir']);

// 1. Upload: prendo l’array videos[]
$uploaded = [];
if (isset($_FILES['videos'])) {
    foreach ($_FILES['videos']['error'] as $idx => $err) {
        if ($err === UPLOAD_ERR_OK && $_FILES['videos']['size'][$idx] <= $config['system']['max_upload_size']) {
            $tmp = $_FILES['videos']['tmp_name'][$idx];
            $name = basename($_FILES['videos']['name'][$idx]);
            $dest = $config['paths']['upload_dir'] . $name;
            if (move_uploaded_file($tmp, $dest)) {
                $uploaded[] = $dest;
            }
        }
    }
}

if (empty($uploaded)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nessun file video valido caricato']);
    exit;
}

// 2. Rilevamento scene o conversione in .ts
$segments = [];
if (!empty($_POST['detect_people'])) {
    $segments = detectMovingPeople($uploaded, $config['detection'], $config['paths']['temp_dir']);
} else {
    foreach ($uploaded as $u) {
        $segments[] = convertToTs($u, $config);
    }
}

// 3. Transizioni e concatenazione
if ($config['transitions']['enabled']) {
    $combined = concatenateWithTransitions($segments, $config['transitions'], $config['paths']['temp_dir']);
} else {
    $combined = concatenateTsSegments($segments, $config['paths']['temp_dir']);
}

// 4. Adattamento durata
$target = floatval($_POST['target_duration'] ?? 0);
$adapted = adaptDuration($combined, $target, $config['paths']['temp_dir']);

// 5. Remux e thumbnail
$outMp4 = $config['paths']['output_dir'] . 'final_' . time() . '.mp4';
processVideo($adapted, $outMp4, $config['paths']['output_dir'], $config['system']['base_url']);

// 6. Pulizia
cleanupTempFiles(array_merge($segments, [$combined, $adapted]), $config['paths']['temp_dir']);

// 7. Risposta JSON
header('Content-Type: application/json');
echo json_encode([
    'video_url'     => $config['system']['base_url'] . '/output/' . basename($outMp4),
    'thumbnail_url' => $config['system']['base_url'] . '/output/' . basename($outMp4, '.mp4') . '.jpg'
]);
