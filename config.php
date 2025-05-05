<?php
require_once __DIR__ . '/config.php';

// Crea directory se non esistono
function createDirs() {
    $dirs = [
        getConfig('paths.uploads'),
        getConfig('paths.temp'),
        getConfig('paths.output')
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

createDirs();

$outputFiles = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['videos'])) {
    $uploaded = $_FILES['videos'];
    $count = count($uploaded['name']);
    $duration = intval($_POST['duration'] ?? 0);
    $aiInstructions = trim($_POST['ai_instructions'] ?? '');

    for ($i = 0; $i < $count; $i++) {
        if ($uploaded['error'][$i] === UPLOAD_ERR_OK) {
            $tmpName = $uploaded['tmp_name'][$i];
            $originalName = basename($uploaded['name'][$i]);
            $uploadDir = getConfig('paths.uploads');
            $outputDir = getConfig('paths.output');
            $uniqueName = uniqid() . '_' . $originalName;
            $targetPath = $uploadDir . '/' . $uniqueName;

            if (move_uploaded_file($tmpName, $targetPath)) {
                // Esegui il montaggio video con FFmpeg
                $resolution = getConfig('ffmpeg.resolution');
                $ffmpegCfg = getConfig('ffmpeg');
                $outputName = uniqid('processed_') . '.mp4';
                $outputPath = $outputDir . '/' . $outputName;

                $cmd = sprintf(
                    "ffmpeg -i %s -vf \"scale=%s\" -c:v %s -preset fast -crf %s -c:a %s %s",
                    escapeshellarg($targetPath),
                    escapeshellarg($resolution),
                    $ffmpegCfg['video_codec'],
                    $ffmpegCfg['video_quality'],
                    $ffmpegCfg['audio_codec'],
                    escapeshellarg($outputPath)
                );
                shell_exec($cmd);

                $outputFiles[] = $outputName;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Montaggio Video Automatico</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Montaggio Video Automatico</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <label for="videos">Seleziona i video:</label>
        <input type="file" name="videos[]" id="videos" multiple accept="video/*">
        <br><br>
        <label for="duration">Durata desiderata (minuti):</label>
        <input type="number" id="duration" name="duration" min="1" value="3">
        <br><br>
        <label for="ai_instructions">Istruzioni AI (opzionale):</label>
        <textarea id="ai_instructions" name="ai_instructions" placeholder="Es. Regola luminosità, aggiungi logo, ecc."></textarea>
        <br><br>
        <button type="submit">Carica e Monta</button>
    </form>

    <?php if (!empty($outputFiles)): ?>
        <h2>Video montati:</h2>
        <ul>
            <?php foreach ($outputFiles as $video): ?>
                <li><a href="<?php echo getConfig('paths.output') . '/' . $video; ?>" download><?php echo $video; ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
