<?php
require 'config.php';

// Percorsi definiti in config.php
$uploadDir = getConfig('paths.uploads');
$tempDir   = getConfig('paths.temp');
$outputDir = getConfig('paths.output');

// Creazione directory se non esistono
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
if (!is_dir($tempDir))   mkdir($tempDir,   0755, true);
if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['videos'])) {
        echo "<p>Nessun file video caricato.</p>";
        exit;
    }

    $files = $_FILES['videos'];
    $desiredMinutes = (int) ($_POST['duration'] ?? 3);
    $maxSizeMB = getConfig('system.max_upload_size', 200);
    $processed = [];

    foreach ($files['tmp_name'] as $i => $tmpName) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($files['size'][$i] > $maxSizeMB * 1024 * 1024) continue;

        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $baseName = uniqid('video_');
        $inputPath = "$uploadDir/{$baseName}.{$ext}";
        move_uploaded_file($tmpName, $inputPath);

        // Output verticale 9:16 a 720x1280 px, trim in base alla durata
        $resolution = '720x1280';
        $outputPath = "$outputDir/{$baseName}_out.mp4";
        $cmd = sprintf(
            'ffmpeg -i %s -vf "scale=%s:force_original_aspect_ratio=decrease,pad=%s:(ow-iw)/2:(oh-ih)/2,setsar=1" -t %d -c:v %s -c:a %s %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($resolution),
            escapeshellarg($resolution),
            $desiredMinutes * 60,
            getConfig('ffmpeg.video_codec', 'libx264'),
            getConfig('ffmpeg.audio_codec', 'aac'),
            escapeshellarg($outputPath)
        );
        exec($cmd, $output, $returnCode);
        if ($returnCode === 0) {
            $processed[] = $outputPath;
        }
    }

    // Mostra link per il download dei video montati
    echo "<h2>Video montati:</h2><ul>";
    foreach ($processed as $vid) {
        $url = rtrim(getConfig('system.base_url'), '/') . '/' . $vid;
        echo "<li><a href=\"{$url}\" download>" . basename($vid) . "</a></li>";
    }
    echo "</ul>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Montaggio Video Verticale 9:16</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container">
        <h1>Montaggio Video Automatico (Verticale 9:16)</h1>
        <form action="" method="post" enctype="multipart/form-data">
            <label>Seleziona video (più file):
                <input type="file" name="videos[]" multiple accept="video/*">
            </label><br>
            <label>Durata desiderata (minuti):
                <input type="number" name="duration" value="3" min="1">
            </label><br>
            <button type="submit">Carica e Monta</button>
        </form>
    </div>
</body>
</html>
