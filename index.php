<?php
// Configurazione directory di upload e output
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['videos'])) {
        $error = 'Nessun file video caricato.';
    } else {
        $desiredDuration = intval($_POST['duration']);
        $uploaded = $_FILES['videos'];
        $paths = [];
        for ($i = 0; $i < count($uploaded['name']); $i++) {
            if ($uploaded['error'][$i] === UPLOAD_ERR_OK) {
                $tmp = $uploaded['tmp_name'][$i];
                $name = basename($uploaded['name'][$i]);
                $target = UPLOAD_DIR . '/' . uniqid() . '_' . $name;
                move_uploaded_file($tmp, $target);
                $paths[] = $target;
            }
        }
        if (count($paths) === 0) {
            $error = 'Errore nel caricamento dei file.';
        } else {
            $concatList = tempnam(sys_get_temp_dir(), 'concat') . '.txt';
            $fp = fopen($concatList, 'w');
            foreach ($paths as $p) {
                fwrite($fp, "file '" . addslashes($p) . "'\n");
            }
            fclose($fp);
            $outputVideo = OUTPUT_DIR . '/' . uniqid() . '_montage.mp4';
            $cmd = "ffmpeg -y -f concat -safe 0 -i $concatList -t " . ($desiredDuration*60) . " -c copy " . escapeshellarg($outputVideo) . " 2>&1";
            exec($cmd, $output, $returnVar);
            if ($returnVar === 0) {
                header('Content-Type: video/mp4');
                header('Content-Disposition: attachment; filename="montage.mp4"');
                readfile($outputVideo);
                exit;
            } else {
                $error = 'Errore durante il montaggio del video.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Montaggio Video Automatico</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Montaggio Video Automatico</h1>
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <label for="videos">Seleziona video:</label>
            <input type="file" name="videos[]" id="videos" multiple accept="video/*" required>
            <label for="duration">Durata desiderata (minuti):</label>
            <input type="number" name="duration" id="duration" min="1" max="60" value="3" required>
            <button type="submit">Carica e Monta</button>
        </form>
    </div>
</body>
</html>