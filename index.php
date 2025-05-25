<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'ffmpeg_script.php';      // contiene convertToTs, convertImageToTs, concatenateTsFiles, process_video
require_once 'people_detection.php';   // contiene detectMovingPeople()
require_once 'duration_editor.php';    // contiene adaptSegmentsToDuration()

function createUploadsDir() {
    $u = getConfig('paths.uploads','uploads');
    $t = getConfig('paths.temp','temp');
    if (!file_exists($u)) mkdir($u, 0777, true);
    if (!file_exists($t)) mkdir($t, 0777, true);
}

function cleanupAll(array $files) {
    cleanupTempFiles($files, getConfig('system.keep_original', true));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    createUploadsDir();
    set_time_limit(0);

    // -- Parametri utente --
    $mode           = $_POST['mode'] ?? 'simple';
    $targetDuration = (!empty($_POST['duration']) && is_numeric($_POST['duration']))
                      ? intval($_POST['duration']) * 60
                      : 0;
    $audioPath = !empty($_POST['audio'])
                 && file_exists(__DIR__.'/musica/'.basename($_POST['audio']))
                 ? realpath(__DIR__.'/musica/'.basename($_POST['audio']))
                 : null;
    $tickerText = trim($_POST['ticker_text'] ?? '');

    // -- Verifica FFmpeg per rilevamento persone --
    if ($mode === 'detect_people') {
        $deps = checkDependencies();
        if (empty($deps['ffmpeg'])) {
            echo "<div style='background:#f8d7da; padding:10px; color:#721c24;'>
                    <strong>⚠️ FFmpeg non disponibile</strong><br>
                  </div>";
            exit;
        }
    }

    $uploadsDir = getConfig('paths.uploads','uploads');
    $uploadedFiles     = [];
    $uploadedTsFiles   = [];
    $segmentsToProcess = [];

    echo "<div style='background:#f8f9fa; padding:10px; margin-bottom:10px;'><strong>🔄 Elaborazione in corso…</strong><br>";

    // -- Caricamento e conversione iniziale --
    foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $origName = basename($_FILES['files']['name'][$i]);
        $dest     = "$uploadsDir/$origName";
        if (!move_uploaded_file($tmp, $dest)) {
            echo "❌ Errore salvataggio: $origName<br>";
            continue;
        }
        echo "✅ Caricato: $origName<br>";
        $uploadedFiles[] = $dest;

        if ($mode === 'detect_people') {
            echo "🔍 Analisi persone: $origName<br>";
            $res = detectMovingPeople($dest);
            if ($res['success']) {
                foreach ($res['segments'] as $seg) {
                    $segmentsToProcess[] = $seg;
                }
            } else {
                echo "⚠️ {$res['message']}<br>";
            }
        } else {
            // video o immagine?
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $ts  = pathinfo($origName, PATHINFO_FILENAME).'.ts';
            $tsPath = "$uploadsDir/$ts";
            if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                convertImageToTs($dest, $tsPath);
            } else {
                convertToTs($dest, $tsPath);
            }
            $uploadedTsFiles[] = $tsPath;
        }
    }
    echo "</div>";

    // -- Preparo lista file da concatenare --
    if ($mode === 'detect_people' && count($segmentsToProcess) > 0) {
        $toConcat = [];
        foreach ($segmentsToProcess as $idx => $seg) {
            $tsPath = sprintf("%s/segment_%02d_%s.ts",
                      $uploadsDir, $idx, uniqid());
            convertToTs($seg, $tsPath);
            if (file_exists($tsPath)) $toConcat[] = $tsPath;
        }
        if (empty($toConcat)) {
            echo "<br>⚠️ Nessun segmento .ts generato.";
            cleanupAll($segmentsToProcess);
            return;
        }
    } elseif (count($uploadedTsFiles) > 1) {
        $toConcat = $uploadedTsFiles;
    } else {
        echo "<br>⚠️ Carica almeno due file.";
        cleanupAll(array_merge($uploadedTsFiles, $segmentsToProcess));
        return;
    }

    // -- Applico durata massima se richiesta --
    if ($targetDuration > 0) {
        $toConcat = adaptSegmentsToDuration($toConcat, $targetDuration);
    }

    // -- Concatenazione finale --
    $out = "$uploadsDir/" . ($mode==='detect_people'
           ? 'video_montato_'.date('Ymd_His').'.mp4'
           : 'final_video_'.date('Ymd_His').'.mp4');
    concatenateTsFiles($toConcat, $out, $audioPath, $tickerText);

    // -- Link di download e alert --
    $fileName    = basename($out);
    $relativeDir = getConfig('paths.uploads','uploads');
    echo "<br><strong>✅ Video pronto:</strong> "
       . "<a href=\"{$relativeDir}/{$fileName}\" download>Scarica il video</a>";
    echo '<audio src="/musica/divertente2.mp3" autoplay></audio>';
    echo '<script>alert("🎉 Montaggio completato!");</script>';

    // -- Pulizia --
    cleanupAll(array_merge($toConcat, $segmentsToProcess));
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>VideoAssembly</title>
  <style>
    body { font-family: Arial; max-width:800px; margin:20px auto; background:#f4f4f4; }
    .upload-container { background:#fff; padding:20px; border:2px dashed #ccc; border-radius:5px; }
    button { background:#4CAF50; color:#fff; padding:12px 20px; border:none; border-radius:4px; cursor:pointer; }
    button:hover { background:#45a049; }
  </style>
</head>
<body>
  <h1>🎬 VideoAssembly</h1>
  <div class="upload-container">
    <form method="POST" enctype="multipart/form-data">
      <h3>📂 Carica video o immagini</h3>
      <input type="file" name="files[]" multiple required accept="video/*,image/*"><br><br>

      <h3>⚙️ Modalità</h3>
      <label><input type="radio" name="mode" value="simple" checked> Montaggio semplice</label>
      <label><input type="radio" name="mode" value="detect_people"> Rilevamento persone</label><br><br>

      <h3>⏱️ Durata (minuti)</h3>
      <select name="duration">
        <option value="0">Originale</option>
        <option value="1">1</option>
        <option value="3">3</option>
        <option value="5">5</option>
        <option value="10">10</option>
        <option value="15">15</option>
      </select><br><br>

      <h3>🎵 Musica di sottofondo</h3>
      <select name="audio" onchange="document.getElementById('audioPreview').src='musica/'+this.value; document.getElementById('audioPreview').style.display='block';">
        <option value="">-- Nessuna --</option>
        <?php
        $md = __DIR__.'/musica';
        if (is_dir($md)) foreach (scandir($md) as $f)
          if (preg_match('/\.(mp3|wav)$/i',$f)) echo "<option>$f</option>";
        ?>
      </select>
      <audio id="audioPreview" controls style="display:none;"></audio><br><br>

      <h3>📝 Ticker (opzionale)</h3>
      <input type="text" name="ticker_text" placeholder="Testo che scorre sul video"><br><br>

      <button type="submit">🚀 Carica e Monta</button>
    </form>
  </div>
</body>
</html>
