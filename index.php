<?php
require_once 'config.php';
require_once 'video_processor.php';

// Funzione di logging
function debugLog($message, $level = "info") {
    if (ENABLE_DEBUG) {
        $logFile = LOG_DIR . '/app_' . date('Y-m-d') . '.log';
        if (!file_exists(LOG_DIR)) mkdir(LOG_DIR, 0777, true);
        $logMessage = "[" . date('Y-m-d H:i:s') . "] [$level] $message\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

// Gestione della richiesta POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video'])) {
    $file = $_FILES['video']['tmp_name'];
    $originalName = basename($_FILES['video']['name']);
    $destPath = UPLOAD_DIR . $originalName;

    if (!file_exists(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
    move_uploaded_file($file, $destPath);

    $options = [
        'privacy' => isset($_POST['privacy']),
        'people_detection' => isset($_POST['people']),
        'max_duration' => intval($_POST['max_duration'] ?? MAX_DURATION),
        'effect' => $_POST['effect'] ?? 'none',
        'audio' => $_POST['audio'] ?? 'emozionale'
    ];

    $result = process_uploaded_video($destPath, $options);

    echo "<h2>Video Elaborato</h2>";
    echo "<p><a href='\$result'>Scarica il video</a></p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>VideoAssembly</title>
</head>
<body>
    <h1>VideoAssembly</h1>
    <form method="post" enctype="multipart/form-data">
        <label>Carica un video: <input type="file" name="video" required></label><br>
        <label><input type="checkbox" name="privacy" checked> Offusca i volti</label><br>
        <label><input type="checkbox" name="people"> Rileva persone</label><br>
        <label>Durata max (sec): <input type="number" name="max_duration" value="180"></label><br>
        <label>Effetto video:
            <select name="effect">
                <option value="none">Nessuno</option>
                <option value="bw">Bianco e nero</option>
                <option value="vintage">Vintage</option>
            </select>
        </label><br>
        <label>Audio di sottofondo:
            <select name="audio">
                <option value="emozionale">Emozionale</option>
            </select>
        </label><br>
        <button type="submit">Carica e Monta</button>
    </form>
</body>
</html>
