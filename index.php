<?php
require __DIR__ . '/config.php';

// 1) Creo le cartelle se non esistono
foreach ([
    getConfig('paths.uploads'),
    getConfig('paths.temp'),
    getConfig('paths.output')
] as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0755, true);
    }
}

$maxMB    = getConfig('system.max_upload_size', 100);
$maxBytes = $maxMB * 1024 * 1024;
$results  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['videos'])) {
        die('Nessun file video caricato.');
    }

    $videos   = $_FILES['videos'];
    $count    = count($videos['name']);
    $minutes  = max(1, (int)($_POST['duration'] ?? 3));
    $secs     = $minutes * 60;
    $aiPrompt = trim($_POST['ai_instructions'] ?? '');

    for ($i = 0; $i < $count; $i++) {
        if ($videos['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        if ($videos['size'][$i] > $maxBytes) {
            continue;
        }

        // Salvo il file in uploads/
        $ext        = pathinfo($videos['name'][$i], PATHINFO_EXTENSION);
        $uniq       = uniqid('vid_');
        $inPath     = getConfig('paths.uploads') . "/{$uniq}.{$ext}";
        move_uploaded_file($videos['tmp_name'][$i], $inPath);

        // Comando FFmpeg: vertical 9:16, taglio a durata scelta
        $outPath    = getConfig('paths.output') . "/processed_{$uniq}.mp4";
        $vf         = 'scale=720:1280,setsar=1:1';
        $cmd        = sprintf(
            'ffmpeg -i %s -t %d -vf "%s" -c:v %s -preset veryfast -crf %s -c:a %s %s 2>&1',
            escapeshellarg($inPath),
            $secs,
            $vf,
            getConfig('ffmpeg.video_codec'),
            getConfig('ffmpeg.video_quality'),
            getConfig('ffmpeg.audio_codec'),
            escapeshellarg($outPath)
        );

        exec($cmd, $log, $r);
        if ($r === 0 && file_exists($outPath)) {
            $results[] = $outPath;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>VideoAssembly – Montaggio Video Automatico</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h1>VideoAssembly</h1>
  <form method="post" enctype="multipart/form-data">
    <label>Seleziona video (max <?php echo $maxMB; ?> MB ciascuno, puoi caricarne più di uno):</label><br>
    <input type="file" name="videos[]" multiple accept="video/*" required><br><br>

    <label>Durata desiderata (minuti):</label><br>
    <input type="number" name="duration" value="3" min="1" required><br><br>

    <label>Istruzioni AI (opzionale):</label><br>
    <input type="text" name="ai_instructions" placeholder="Es. migliora luminosità o stabilizza"><br><br>

    <button type="submit">Carica e Monta</button>
  </form>

  <?php if (!empty($results)): ?>
    <h2>Video montati:</h2>
    <ul>
      <?php foreach ($results as $file): ?>
        <li>
          <a href="<?php echo basename($file); ?>" download>
            <?php echo basename($file); ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</body>
</html>
