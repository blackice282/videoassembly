<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';
require_once 'transitions.php';
require_once 'duration_editor.php';

function createUploadsDir() {
    if (!file_exists(getConfig('paths.uploads', 'uploads'))) {
        mkdir(getConfig('paths.uploads', 'uploads'), 0777, true);
    }
    if (!file_exists(getConfig('paths.temp', 'temp'))) {
        mkdir(getConfig('paths.temp', 'temp'), 0777, true);
    }
}

function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
    foreach ($files as $file) {
        if (file_exists($file) && (!$keepOriginals || strpos($file, 'uploads/') === false)) {
            unlink($file);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    createUploadsDir();
    set_time_limit(300);

    $mode = $_POST['mode'] ?? 'simple';
    // Durata massima in secondi (0 = originale)
    $targetDuration = (!empty($_POST['duration']) && is_numeric($_POST['duration']))
        ? intval($_POST['duration']) * 60
        : 0;

    // Percorso assoluto dell’audio di sottofondo (se selezionato)
    if (!empty($_POST['audio'])) {
        $sel = basename($_POST['audio']);
        $audioPath = __DIR__ . '/musica/' . $sel;
        if (!file_exists($audioPath)) {
            $audioPath = null;
        }
    } else {
        $audioPath = null;
    }

    // Array per tracciare upload e .ts generati
    $uploaded_files    = [];
    $segments_to_process = [];

    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>🔄 Caricamento file...</strong><br>";

    // 1) Carico i file
    foreach ($_FILES['files']['tmp_name'] as $i => $tmp_name) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $name = basename($_FILES['files']['name'][$i]);
            $dest = getConfig('paths.uploads', 'uploads') . '/' . $name;
            if (move_uploaded_file($tmp_name, $dest)) {
                echo "✅ File caricato: {$name}<br>";
                $uploaded_files[] = $dest;
            } else {
                echo "❌ Errore nel salvataggio: {$name}<br>";
            }
        }
    }
    echo "</div>";

    // 2) Pre-analisi in modalità detect_people
    if ($mode === 'detect_people') {
        echo "<div style='background: #eef7ff; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>🔍 Pre-analisi rilevamento persone...</strong><br>";
        foreach ($uploaded_files as $file) {
            $res = detectMovingPeople($file);
            if ($res['success']) {
                $segments_to_process = array_merge($segments_to_process, $res['segments']);
                echo "  – Trovati " . count($res['segments']) . " segmenti in " . basename($file) . "<br>";
            } else {
                echo "  ⚠️ Errore analisi " . basename($file) . ": " . htmlspecialchars($res['message']) . "<br>";
            }
        }
        echo "</div>";

        if (empty($segments_to_process)) {
            echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; color: #856404;'>";
            echo "⚠️ Nessuna persona rilevata in nessun video: interrompo il montaggio.";
            echo "</div>";
            return;
        }
    }

    // 3) Conversione in .ts
    $uploaded_ts_files = [];
    echo "<div style='background: #f0fff4; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>🔄 Conversione in .ts...</strong><br>";

    if ($mode === 'detect_people') {
        // converto solo i segmenti trovati
        foreach ($segments_to_process as $idx => $segment) {
            $tsPath = getConfig('paths.uploads', 'uploads')
                    . '/segment_' . $idx . '_' . date('His') . '.ts';
            convertToTs($segment, $tsPath);
            if (file_exists($tsPath)) {
                echo "  – Segmento {$idx} convertito<br>";
                $uploaded_ts_files[] = $tsPath;
            }
        }
    } else {
        // semplice: converto tutti i video caricati
        foreach ($uploaded_files as $file) {
            $basename = pathinfo($file, PATHINFO_FILENAME);
            $tsPath = getConfig('paths.uploads', 'uploads') . "/{$basename}.ts";
            convertToTs($file, $tsPath);
            if (file_exists($tsPath)) {
                echo "  – " . basename($file) . " → {$basename}.ts<br>";
                $uploaded_ts_files[] = $tsPath;
            }
        }
    }
    echo "</div>";

    // 4) Concatenazione e mix audio/ticker
    $outDir = getConfig('paths.uploads', 'uploads');
    $outFile = $mode === 'detect_people'
        ? "{$outDir}/video_montato_" . date('Ymd_His') . ".mp4"
        : "{$outDir}/final_video_"   . date('Ymd_His') . ".mp4";

    echo "<div style='background: #e8f0fe; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>🔗 Montaggio finale...</strong><br>";
    concatenateTsFiles($uploaded_ts_files, $outFile, $audioPath);
    echo "</div>";

    // 5) Link di download
    $fileName    = basename($outFile);
    $relativeDir = getConfig('paths.uploads', 'uploads');
    echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; color: #155724;'>";
    echo "✅ Montaggio completato: <a href=\"{$relativeDir}/{$fileName}\" download>Scarica il video</a>";
    echo "</div>";

    // 6) Pulizia temp
    cleanupTempFiles($uploaded_ts_files, getConfig('system.keep_original', true));
    cleanupTempFiles($segments_to_process, getConfig('system.keep_original', true));
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>VideoAssembly</title>
    <style>
      body { font-family: Arial,sans-serif; max-width:800px; margin:20px auto; }
      .upload-container { border:2px dashed #ccc; padding:20px; border-radius:5px; }
      .upload-container h3 { margin-top:0; }
      button { background:#4CAF50; color:#fff; padding:12px 20px; border:none; border-radius:4px; cursor:pointer; }
      button:hover { background:#45a049; }
    </style>
</head>
<body>
    <h1>🎬 VideoAssembly</h1>
    <div class="upload-container">
      <form method="POST" enctype="multipart/form-data">
        <h3>📂 Carica i tuoi video</h3>
        <input type="file" name="files[]" multiple required accept="video/*"><br><br>

        <h3>⚙️ Modalità</h3>
        <label><input type="radio" name="mode" value="simple" checked> Montaggio semplice</label><br>
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
        <select name="audio">
          <option value="">-- Nessuna --</option>
          <?php
          $musicaDir = __DIR__ . '/musica';
          foreach (is_dir($musicaDir) ? scandir($musicaDir) : [] as $f) {
              if (preg_match('/\.(mp3|wav)$/i', $f)) {
                  echo "<option value=\"" . htmlspecialchars($f) . "\">$f</option>";
              }
          }
          ?>
        </select><br><br>

        <h3>📝 Testo Ticker (opzionale)</h3>
        <input type="text" name="ticker_text" placeholder="Testo che scorre sul video"><br><br>

        <button type="submit">🚀 Carica e Monta</button>
      </form>
    </div>
</body>
</html>
