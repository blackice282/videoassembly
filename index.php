// index.php
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Carica configurazione generale
require_once __DIR__ . '/config.php';

// Include script opzionali solo se presenti
$scripts = ['ffmpeg_script.php', 'people_detection.php', 'transitions.php', 'duration_editor.php'];
foreach ($scripts as $script) {
    $path = __DIR__ . '/' . $script;
    if (file_exists($path)) {
        require_once $path;
    }
}

// Funzione per creare directory di upload e temp
function createUploadsDir() {
    $uploads = getConfig('paths.uploads', 'uploads');
    $temp = getConfig('paths.temp', 'temp');
    if (!file_exists($uploads)) {
        mkdir($uploads, 0777, true);
    }
    if (!file_exists($temp)) {
        mkdir($temp, 0777, true);
    }
}

// Definizione protetta di cleanupTempFiles
if (!function_exists('cleanupTempFiles')) {
    /**
     * Rimuove file temporanei
     *
     * @param string[] $files
     * @param bool $keepOriginals
     */
    function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
        foreach ($files as $file) {
            if (file_exists($file) && (!$keepOriginals || strpos($file, 'uploads/') === false)) {
                @unlink($file);
            }
        }
    }
}

// Gestione richiesta POST
if ($_SERVER["REQUEST_METHOD"] === 'POST') {
    createUploadsDir();
    set_time_limit(300);

    $mode = $_POST['mode'] ?? 'simple';
    $targetDuration = (!empty($_POST['duration']) && is_numeric($_POST['duration']))
        ? intval($_POST['duration']) * 60
        : 0;

    // Scegli audio di sottofondo
    if (!empty($_POST['audio'])) {
        $sel = basename($_POST['audio']);
        $audioPath = __DIR__ . '/musica/' . $sel;
        if (!file_exists($audioPath)) {
            $audioPath = null;
        }
    } else {
        $audioPath = null;
    }

    $uploaded_files = [];
    $segments_to_process = [];

    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>🔄 Caricamento file...</strong><br>";
    foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $name = basename($_FILES['files']['name'][$i]);
            $dest = getConfig('paths.uploads', 'uploads') . '/' . $name;
            if (move_uploaded_file($tmp, $dest)) {
                echo "✅ File caricato: {$name}<br>";
                $uploaded_files[] = $dest;
            } else {
                echo "❌ Errore nel salvataggio: {$name}<br>";
            }
        }
    }
    echo "</div>";

    // Modalità rilevamento persone
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
            echo "⚠️ Nessuna persona rilevata: interrompo il montaggio.";
            echo "</div>";
            return;
        }
    }

    // Conversione in .ts
    $uploaded_ts_files = [];
    echo "<div style='background: #f0fff4; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>🔄 Conversione in .ts...</strong><br>";
    $targets = ($mode === 'detect_people') ? $segments_to_process : $uploaded_files;
    foreach ($targets as $idx => $src) {
        $basename = pathinfo($src, PATHINFO_FILENAME);
        $tsPath = getConfig('paths.uploads', 'uploads') . "/{$basename}_{$idx}.ts";
        convertToTs($src, $tsPath);
        if (file_exists($tsPath)) {
            echo "  – {$basename}.ts convertito<br>";
            $uploaded_ts_files[] = $tsPath;
        }
    }
    echo "</div>";

    // Montaggio finale
    $outDir = getConfig('paths.uploads', 'uploads');
    $fileLabel = ($mode === 'detect_people') ? 'video_montato' : 'final_video';
    $outFile = "{$outDir}/{$fileLabel}_" . date('Ymd_His') . ".mp4";
    echo "<div style='background: #e8f0fe; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>🔗 Montaggio finale...</strong><br>";
    concatenateTsFiles($uploaded_ts_files, $outFile, $audioPath);
    echo "</div>";

    // Link download
    $rel = getConfig('paths.uploads', 'uploads');
    echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; color: #155724;'>";
    echo "✅ Montaggio completato: <a href=\"{$rel}/" . basename($outFile) . "\" download>Scarica il video</a>";
    echo "</div>";

    // Pulizia temp
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

// ffmpeg_script.php
<?php
// Protezione ridefinizione cleanupTempFiles
if (!function_exists('cleanupTempFiles')) {
    /**
     * Rimuove file temporanei specifici FFmpeg
     */
    function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
        foreach ($files as $file) {
            if (file_exists($file) && (!$keepOriginals || strpos($file, 'uploads/') === false)) {
                @unlink($file);
            }
        }
    }
}

/**
 * Esegue il comando FFmpeg
 */
function runFfmpegCommand(string $cmd): void {
    exec($cmd, $output, $returnCode);
    // gestione output ed error handling...
}

// Aggiungi qui convertToTs, concatenateTsFiles, etc.
?>
