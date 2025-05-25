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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    createUploadsDir();
    set_time_limit(300);

    $mode = $_POST['mode'] ?? 'simple';
    $targetDuration = (!empty($_POST['duration']) && is_numeric($_POST['duration']))
        ? intval($_POST['duration']) * 60
        : 0;

    $audioPath = !empty($_POST['audio']) && file_exists(__DIR__ . '/musica/' . basename($_POST['audio']))
        ? realpath(__DIR__ . '/musica/' . basename($_POST['audio']))
        : null;
    $tickerText = !empty($_POST['ticker_text']) ? trim($_POST['ticker_text']) : null;

    if ($mode === 'detect_people') {
        $deps = checkDependencies();
        if (empty($deps['ffmpeg'])) {
            echo "<div style='background:#f8d7da;padding:10px;border-radius:5px;margin:10px 0;color:#721c24;'>";
            echo "<strong>⚠️ Errore: FFmpeg non disponibile</strong><br>Il rilevamento persone richiede FFmpeg.";
            echo "</div>";
            exit;
        }
    }

    if (!empty($_FILES['files'])) {
        $uploaded_files      = [];
        $uploaded_ts_files   = [];
        $segments_to_process = [];

        echo "<div style='background:#f8f9fa;padding:10px;border-radius:5px;margin:10px 0;'>";
        echo "<strong>🔄 Elaborazione video e immagini...</strong><br>";

        foreach ($_FILES['files']['tmp_name'] as $i => $tmp_name) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $origName   = basename($_FILES['files']['name'][$i]);
                $destination = getConfig('paths.uploads','uploads') . '/' . $origName;
                move_uploaded_file($tmp_name, $destination);
                echo "✅ Caricato: $origName<br>";
                $uploaded_files[] = $destination;

                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if ($mode === 'detect_people') {
                    echo "🔍 Analisi persone in: $origName<br>";
                    $res = detectMovingPeople($destination);
                    if ($res['success']) {
                        foreach ($res['segments'] as $seg) {
                            $segments_to_process[] = $seg;
                        }
                    } else {
                        echo "⚠️ {$res['message']}<br>";
                    }
                } else {
                    // video o immagine?
                    $tsPath = getConfig('paths.uploads','uploads') . '/' . pathinfo($origName, PATHINFO_FILENAME) . '.ts';
                    if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                        convertImageToTs($destination, $tsPath);
                    } else {
                        convertToTs($destination, $tsPath);
                    }
                    $uploaded_ts_files[] = $tsPath;
                }
            }
        }
        echo "</div>";

        $uploadDir = getConfig('paths.uploads','uploads');
        $outFiles  = [];

        if ($mode === 'detect_people' && count($segments_to_process) > 0) {
            // genera TS per ogni segmento rilevato
            foreach ($segments_to_process as $idx => $seg) {
                $tsPath = "$uploadDir/segment_{$idx}_" . uniqid() . '.ts';
                convertToTs($seg, $tsPath);
                if (file_exists($tsPath)) {
                    $outFiles[] = $tsPath;
                }
            }
            if (empty($outFiles)) {
                echo "<br>⚠️ Nessun segmento .ts generato.";
                cleanupTempFiles($segments_to_process, getConfig('system.keep_original', true));
                exit;
            }
            $out = "$uploadDir/video_montato_" . date('Ymd_His') . ".mp4";
        } elseif (count($uploaded_ts_files) > 1) {
            // concatenazione semplice
            $outFiles = $uploaded_ts_files;
            $out = "$uploadDir/final_video_" . date('Ymd_His') . ".mp4";
        } else {
            echo "<br>⚠️ Carica almeno due file (video o immagini).";
            cleanupTempFiles(array_merge($uploaded_ts_files, $segments_to_process), getConfig('system.keep_original', true));
            exit;
        }

        // concatena e applica audio/ticker
        concatenateTsFiles($outFiles, $out, $audioPath, $tickerText);

        // mostra link di download e alert
        $fileName    = basename($out);
        $relativeDir = $uploadDir;
        echo "<br><strong>✅ Montaggio completato!</strong> ";
        echo "<a href=\"{$relativeDir}/{$fileName}\" download>Scarica il video</a>";
        echo '<audio src="/musica/divertente2.mp3" autoplay></audio>';
        echo '<script>alert("🎉 Montaggio completato! Scarica il tuo video.");</script>';

        // pulizia
        cleanupTempFiles(array_merge($uploaded_ts_files, $segments_to_process), false);
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>VideoAssembly</title>
    <style>
        body { font-family: Arial, sans-serif; max-width:800px; margin:0 auto; padding:20px; background:#f4f4f4; }
        h1 { color:#333; text-align:center; }
        .upload-container { border:2px dashed #ccc; padding:20px; border-radius:5px; background:#fff; margin-top:20px; }
        .options { margin:20px 0; padding:15px; background:#f8f9fa; border-radius:5px; }
        .option-group { margin-bottom:20px; }
        .option-group h3 { margin-bottom:8px; }
        select, input[type="file"], button, input[type="text"] { display:block; margin-top:10px; }
        button { background:#4CAF50; color:white; padding:12px 20px; border:none; border-radius:4px; cursor:pointer; font-size:16px; }
        button:hover { background:#45a049; }
        audio { margin-top:10px; }
    </style>
    <script>
        function previewAudio(file) {
            const audio = document.getElementById("audioPreview");
            if (file) { audio.src = "musica/" + encodeURIComponent(file); audio.style.display="block"; audio.load(); }
            else { audio.src=""; audio.style.display="none"; }
        }
    </script>
</head>
<body>
    <h1>🎬 VideoAssembly</h1>
    <div class="upload-container">
        <form method="POST" enctype="multipart/form-data">
            <h3>📂 Carica i tuoi file (video o immagini)</h3>
            <input type="file" name="files[]" multiple required accept="video/*,image/*">

            <div class="options">
                <div class="option-group">
                    <h3>⚙️ Modalità:</h3>
                    <label><input type="radio" name="mode" value="simple" checked> Montaggio semplice</label>
                    <label><input type="radio" name="mode" value="detect_people"> Rilevamento persone</label>
                </div>

                <div class="option-group">
                    <h3>⏱️ Durata max (minuti):</h3>
                    <select name="duration">
                        <option value="0">Originale</option>
                        <option value="1">1</option>
                        <option value="3">3</option>
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="15">15</option>
                    </select>
                </div>

                <div class="option-group">
                    <h3>🎵 Musica di sottofondo:</h3>
                    <select name="audio" onchange="previewAudio(this.value)">
                        <option value="">-- Nessuna --</option>
                        <?php
                        $musicaDir = __DIR__ . '/musica';
                        if (is_dir($musicaDir)) {
                            foreach (scandir($musicaDir) as $f) {
                                if (preg_match('/\.(mp3|wav)$/i',$f)) {
                                    echo "<option value=\"$f\">$f</option>";
                                }
                            }
                        }
                        ?>
                    </select>
                    <audio id="audioPreview" controls style="display:none;"></audio>
                </div>
            </div>

            <h3>📝 Testo Ticker (opzionale):</h3>
            <input type="text" name="ticker_text" placeholder="Scrivi un messaggio che scorre">

            <button type="submit">🚀 Carica e Monta</button>
        </form>
    </div>
</body>
</html>
