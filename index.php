<?php
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

function convertToTs($inputFile, $outputTs) {
    $cmd = "ffmpeg -i \"$inputFile\" -c copy -bsf:v h264_mp4toannexb -f mpegts \"$outputTs\"";
    shell_exec($cmd);
}

--- index.php
@@
-function concatenateTsFiles($tsFiles, $outputFile, $audioPath = null) {
-    $tsList = implode('|', $tsFiles);
-    $tempMerged = "temp/merged_" . uniqid() . ".mp4";
-    $cmd = "ffmpeg -i \"concat:$tsList\" -c copy -bsf:a aac_adtstoasc \"$tempMerged\"";
-    shell_exec($cmd);
-
-    if ($audioPath && file_exists($audioPath)) {
-        $result = process_video($tempMerged, $audioPath);
-        if ($result['success']) {
-            copy($result['video_url'], $outputFile);
-        } else {
-            copy($tempMerged, $outputFile);
-        }
-    } else {
-        copy($tempMerged, $outputFile);
-    }
-
-    unlink($tempMerged);
-}
+function concatenateTsFiles($tsFiles, $outputFile, $audioPath = null) {
+    global $targetDuration;
+
+    // Crea un file lista per il demuxer concat
+    $listFile = tempnam(sys_get_temp_dir(), 'concat_') . '.txt';
+    $fp = fopen($listFile, 'w');
+    foreach ($tsFiles as $ts) {
+        fwrite($fp, "file '" . str_replace("'", "'\\\\''", $ts) . "'\n");
+    }
+    fclose($fp);
+
+    // Prepara l'opzione durata (in secondi), se specificata
+    $durationOption = !empty($targetDuration) ? ' -t ' . intval($targetDuration) : '';
+
+    if ($audioPath && file_exists($audioPath)) {
+        // Mix audio di sottofondo in loop
+        $cmd = sprintf(
+            'ffmpeg -f concat -safe 0 -i %s -stream_loop -1 -i %s -filter_complex "[0:a][1:a]amix=inputs=2:duration=first:dropout_transition=3[aout]" -map 0:v -map "[aout]" -c:v libx264 -c:a aac%s %s',
+            escapeshellarg($listFile),
+            escapeshellarg($audioPath),
+            $durationOption,
+            escapeshellarg($outputFile)
+        );
+    } else {
+        // Concatenazione semplice senza audio di sottofondo
+        $cmd = sprintf(
+            'ffmpeg -f concat -safe 0 -i %s -c:v libx264 -c:a aac%s %s',
+            escapeshellarg($listFile),
+            $durationOption,
+            escapeshellarg($outputFile)
+        );
+    }
+
+    // Esegui e pulisci il file temporaneo
+    shell_exec($cmd);
+    @unlink($listFile);
+}


function cleanupTempFiles($files, $keepOriginals = false) {
    foreach ($files as $file) {
        if (file_exists($file) && (!$keepOriginals || strpos($file, 'uploads/') === false)) {
            unlink($file);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    createUploadsDir();
    set_time_limit(300);

    $mode = $_POST['mode'] ?? 'simple';
    $targetDuration = (isset($_POST['duration']) && is_numeric($_POST['duration'])) ? intval($_POST['duration']) * 60 : 0;
    $selectedAudio = isset($_POST['audio']) ? trim($_POST['audio']) : '';
    $audioPath = $selectedAudio ? realpath(__DIR__ . "/musica/" . $selectedAudio) : null;

    if ($mode === 'detect_people') {
        $deps = checkDependencies();
        if (!$deps['ffmpeg']) {
            echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; color: #721c24;'>";
            echo "<strong>⚠️ Errore: FFmpeg non disponibile</strong><br>";
            echo "Il rilevamento persone richiede FFmpeg.";
            echo "</div>";
            exit;
        }
    }

    if (isset($_FILES['files'])) {
        $uploaded_files = [];
        $uploaded_ts_files = [];
        $segments_to_process = [];

        $total_files = count($_FILES['files']['name']);
        echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>🔄 Elaborazione video...</strong><br>";

        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['files']['tmp_name'][$i];
                $name = basename($_FILES['files']['name'][$i]);
                $destination = getConfig('paths.uploads', 'uploads') . '/' . $name;

                if (move_uploaded_file($tmp_name, $destination)) {
                    echo "✅ File caricato: $name<br>";
                    $uploaded_files[] = $destination;

                    if ($mode === 'detect_people') {
                        echo "🔍 Analisi del video: $name<br>";
                        $detectionResult = detectMovingPeople($destination);
                        if ($detectionResult['success']) {
                            foreach ($detectionResult['segments'] as $segment) {
                                $segments_to_process[] = $segment;
                            }
                        } else {
                            echo "⚠️ " . $detectionResult['message'] . "<br>";
                        }
                    } else {
                        $tsFile = getConfig('paths.uploads', 'uploads') . '/' . pathinfo($name, PATHINFO_FILENAME) . '.ts';
                        convertToTs($destination, $tsFile);
                        $uploaded_ts_files[] = $tsFile;
                    }
                } else {
                    echo "❌ Errore nel salvataggio del file: $name<br>";
                }
            }
        }
        echo "</div>";

        if ($mode === 'detect_people' && count($segments_to_process) > 0) {
            $segment_ts_files = [];
            foreach ($segments_to_process as $segment) {
                $tsFile = pathinfo($segment, PATHINFO_DIRNAME) . '/' . pathinfo($segment, PATHINFO_FILENAME) . '.ts';
                convertToTs($segment, $tsFile);
                if (file_exists($tsFile)) {
                    $segment_ts_files[] = $tsFile;
                }
            }
            if (count($segment_ts_files) > 0) {
                $outputFinal = getConfig('paths.uploads', 'uploads') . '/video_montato_' . date('Ymd_His') . '.mp4';
                concatenateTsFiles($segment_ts_files, $outputFinal, $audioPath);
                echo "<br><strong>✅ Video pronto:</strong> <a href='$outputFinal' download>Scarica il video</a>";
            }
        } else if (count($uploaded_ts_files) > 1) {
            $outputFinal = getConfig('paths.uploads', 'uploads') . '/final_video_' . date('Ymd_His') . '.mp4';
            concatenateTsFiles($uploaded_ts_files, $outputFinal, $audioPath);
            echo "<br><strong>✅ Video pronto:</strong> <a href='$outputFinal' download>Scarica il video</a>";
        } else {
            echo "<br>⚠️ Carica almeno due video.";
        }

        cleanupTempFiles(array_merge($uploaded_ts_files, $segments_to_process), getConfig('system.keep_original', true));
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>VideoAssembly</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f4f4f4;
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .upload-container {
            border: 2px dashed #ccc;
            padding: 20px;
            border-radius: 5px;
            background: #fff;
            margin-top: 20px;
        }
        .options {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .option-group {
            margin-bottom: 20px;
        }
        .option-group h3 {
            margin-bottom: 8px;
        }
        select, input[type="file"], button {
            display: block;
            margin-top: 10px;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #45a049;
        }
        audio {
            margin-top: 10px;
        }
    </style>
    <script>
        function previewAudio(file) {
            const audio = document.getElementById("audioPreview");
            if (file) {
                audio.src = "musica/" + encodeURIComponent(file);
                audio.style.display = "block";
                audio.load();
            } else {
                audio.src = "";
                audio.style.display = "none";
            }
        }
    </script>
</head>
<body>
    <h1>🎬 VideoAssembly</h1>
    <div class="upload-container">
        <form method="POST" enctype="multipart/form-data">
            <h3>📂 Carica i tuoi video</h3>
            <input type="file" name="files[]" multiple required>

            <div class="options">
                <div class="option-group">
                    <h3>⚙️ Modalità:</h3>
                    <label><input type="radio" name="mode" value="simple" checked> Montaggio semplice</label>
                    <label><input type="radio" name="mode" value="detect_people"> Rilevamento persone</label>
                </div>

                <div class="option-group">
                    <h3>⏱️ Durata (minuti):</h3>
                    <select name="duration">
                        <option value="0">Durata originale</option>
                        <option value="1">1 minuto</option>
                        <option value="3">3 minuti</option>
                        <option value="5">5 minuti</option>
                        <option value="10">10 minuti</option>
                        <option value="15">15 minuti</option>
                    </select>
                </div>

                <div class="option-group">
                    <h3>🎵 Musica di sottofondo:</h3>
                    <select name="audio" onchange="previewAudio(this.value)">
                        <option value="">-- Nessuna --</option>
                        <?php
                        $musicaDir = __DIR__ . '/musica';
                        foreach (scandir($musicaDir) as $file) {
                            if (preg_match('/\.(mp3|wav)$/i', $file)) {
                                echo "<option value=\"$file\">$file</option>";
                            }
                        }
                        ?>
                    </select>
                    <audio id="audioPreview" controls style="display:none;"></audio>
                </div>
            </div>

            <button type="submit">🚀 Carica e Monta</button>
        </form>
    </div>
</body>
</html>
