<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

function createDirs() {
    if (!file_exists(getConfig('paths.uploads', UPLOAD_DIR))) {
        mkdir(getConfig('paths.uploads', UPLOAD_DIR), 0777, true);
    }
    if (!file_exists(getConfig('paths.temp', TEMP_DIR))) {
        mkdir(getConfig('paths.temp', TEMP_DIR), 0777, true);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    createDirs();
    $duration = isset($_POST['duration']) && is_numeric($_POST['duration']) ? intval($_POST['duration']) * 60 : 0;

    $uploaded = $_FILES['videos'];
    $tsFiles = [];

    for ($i = 0; $i < count($uploaded['name']); $i++) {
        if ($uploaded['error'][$i] === UPLOAD_ERR_OK) {
            $tmpName = $uploaded['tmp_name'][$i];
            $dest = getConfig('paths.uploads', UPLOAD_DIR) . '/' . uniqid('video_') . '_' . basename($uploaded['name'][$i]);
            move_uploaded_file($tmpName, $dest);
            $ts = getConfig('paths.temp', TEMP_DIR) . '/' . uniqid('ts_') . '.ts';
            $cmd = FFMPEG_PATH . ' -i ' . escapeshellarg($dest) . ' -c copy -bsf:v h264_mp4toannexb -f mpegts ' . escapeshellarg($ts);
            exec($cmd);
            $tsFiles[] = $ts;
        }
    }

    if (count($tsFiles) >= 1) {
        $concatStr = implode('|', $tsFiles);
        $output = getConfig('paths.uploads', UPLOAD_DIR) . '/final_' . date('Ymd_His') . '.mp4';
        $cmd2 = FFMPEG_PATH . ' -i "concat:' . $concatStr . '" -c copy -bsf:a aac_adtstoasc ' . escapeshellarg($output);
        exec($cmd2);
        if ($duration > 0) {
            $trimmed = str_replace('.mp4', '_trim.mp4', $output);
            $cmd3 = FFMPEG_PATH . ' -i ' . escapeshellarg($output) . ' -t ' . $duration . ' -c copy ' . escapeshellarg($trimmed);
            exec($cmd3);
            $output = $trimmed;
        }
        echo "<h2>Video pronto</h2>";
        echo "<p><a href="" . basename($output) . "" download>Scarica il video</a></p>";
    } else {
        echo "<p>Carica almeno un video valido.</p>";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>VideoAssembly</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<h1>VideoAssembly - Montaggio Video Automatico</h1>
<form method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label>Seleziona video (uno o più):</label>
        <input type="file" name="videos[]" multiple accept="video/*" required>
    </div>
    <div class="form-group">
        <label>Durata desiderata (minuti, 0 = nessun limite):</label>
        <input type="number" name="duration" min="0" value="3">
    </div>
    <button type="submit">Carica e Monta</button>
</form>
</body>
</html>
