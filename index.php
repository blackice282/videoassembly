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

function convertToTs($inputFile, $outputTs) {
    $cmd = "ffmpeg -i " . escapeshellarg($inputFile) . " -c copy -bsf:v h264_mp4toannexb -f mpegts " . escapeshellarg($outputTs);
    shell_exec($cmd);
}

function concatenateTsFiles($tsFiles, $outputFile, $audioPath = null, $tickerText = null) {
    $tsList = implode('|', $tsFiles);
    $tempMerged = "temp/merged_" . uniqid() . ".mp4";
    $cmd = "ffmpeg -i \"concat:$tsList\" -c copy -bsf:a aac_adtstoasc \"$tempMerged\"";
    shell_exec($cmd);

    if ($audioPath && file_exists($audioPath)) {
        $result = process_video($tempMerged, $audioPath, $tickerText);
        if ($result['success']) {
            copy($result['video_url'], $outputFile);
        } else {
            copy($tempMerged, $outputFile);
        }
    } else {
        copy($tempMerged, $outputFile);
    }

    unlink($tempMerged);
}

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
    $targetDuration = (!empty($_POST['duration']) && is_numeric($_POST['duration']))
        ? intval($_POST['duration']) * 60
        : 0;

    $audioPath = !empty($_POST['audio']) && file_exists(__DIR__ . '/musica/' . basename($_POST['audio']))
        ? realpath(__DIR__ . '/musica/' . basename($_POST['audio']))
        : null;
    $tickerText = !empty($_POST['ticker_text']) ? trim($_POST['ticker_text']) : null;


    if ($mode === 'detect_people') {
        $deps = checkDependencies();
        if (!$deps['ffmpeg']) {
            echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; color: #721c24;'>";
            echo "<strong>⚠️ Errore: FFmpeg non disponibile</strong><br>Il rilevamento persone richiede FFmpeg.";
            echo "</div>";
            exit;
        }
    }

    if (isset($_FILES['files'])) {
        $uploaded_files = [];
        $uploaded_ts_files = [];
        $segments_to_process = [];

        echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>🔄 Elaborazione video...</strong><br>";

        foreach ($_FILES['files']['tmp_name'] as $i => $tmp_name) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $name = basename($_FILES['files']['name'][$i]);
                $destination = getConfig('paths.uploads', 'uploads') . '/' . $name;

                if (move_uploaded_file($tmp_name, $destination)) {
                    echo "✅ File caricato: $name<br>";
                    $uploaded_files[] = $destination;

                    if ($mode === 'detect_people') {
                        echo "🔍 Analisi del video: $name<br>";
                        $res = detectMovingPeople($destination);
                        if ($res['success']) {
                            foreach ($res['segments'] as $seg) {
                                $segments_to_process[] = $seg;
                            }
                        } else {
                            echo "⚠️ {$res['message']}<br>";
                        }
                    } else {
                        $tsPath = getConfig('paths.uploads', 'uploads') . '/' . pathinfo($name, PATHINFO_FILENAME) . '.ts';
                        convertToTs($destination, $tsPath);
                        $uploaded_ts_files[] = $tsPath;
                    }
                } else {
                    echo "❌ Errore nel salvataggio del file: $name<br>";
                }
            }
        }
        echo "</div>";

        $uploadDir = __DIR__ . '/' . getConfig('paths.uploads', 'uploads');

        if ($mode === 'detect_people' && count($segments_to_process) > 0) {
             $segment_ts = [];
 foreach ($segments_to_process as $seg) {
       $tsPath = $uploadDir . '/' . pathinfo($seg, PATHINFO_FILENAME) . '.ts';
       convertToTs($seg, $tsPath);
       if (file_exists($tsPath)) {
           $segment_ts[] = $tsPath;
       }
   }
   $segment_ts = [];
   foreach ($segments_to_process as $idx => $seg) {
       // genero un nome unico per ogni segmento
       $tsPath = sprintf(
           '%s/segment_%02d_%s.ts',
           $uploadDir,
           $idx,
           uniqid()
      );
       convertToTs($seg, $tsPath);
       if (file_exists($tsPath)) {
           $segment_ts[] = $tsPath;
      }
  }
            if (empty($segment_ts)) {
                echo "<br>⚠️ Nessun segmento .ts generato.";
                cleanupTempFiles($segments_to_process);
                return;
            }

            $out = $uploadDir . '/video_montato_' . date('Ymd_His') . '.mp4';
            concatenateTsFiles($segment_ts, $out, $audioPath, $tickerText);
        } elseif (count($uploaded_ts_files) > 1) {
            $out = $uploadDir . '/final_video_' . date('Ymd_His') . '.mp4';
            concatenateTsFiles($uploaded_ts_files, $out, $audioPath);
        } else {
            echo "<br>⚠️ Carica almeno due video.";
            return;
        }

        $fileName = basename($out);
        $relativeDir = getConfig('paths.uploads', 'uploads');
        echo "<br><strong>✅ Video pronto:</strong> <a href=\"{$relativeDir}/{$fileName}\" download>Scarica il video</a>";

        cleanupTempFiles(array_merge($uploaded_ts_files, $segments_to_process));
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
                        if (is_dir($musicaDir)) {
                            foreach (scandir($musicaDir) as $file) {
                                if (preg_match('/\.(mp3|wav)$/i', $file)) {
                                    echo "<option value=\"$file\">$file</option>";
                                }
                            }
                        }
                        ?>
                    </select>
                    <audio id="audioPreview" controls style="display:none;"></audio>
                </div>
            </div>

    <h3>📝 Testo Ticker (opzionale):</h3>
    <input type="text" name="ticker_text" placeholder="Inserisci un messaggio che scorre nel video">
</div>

<button type="submit">🚀 Carica e Monta</button>
        </form>
    </div>
</body>
</html>
