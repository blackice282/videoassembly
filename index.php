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
    $up = getConfig('paths.uploads', 'uploads');
    $tmp = getConfig('paths.temp', 'temp');
    if (!file_exists($up)) mkdir($up, 0777, true);
    if (!file_exists($tmp)) mkdir($tmp, 0777, true);
}

function convertToTs($inputFile, $outputTs) {
    $cmd = sprintf(
        'ffmpeg -i %s -c copy -bsf:v h264_mp4toannexb -f mpegts %s',
        escapeshellarg($inputFile),
        escapeshellarg($outputTs)
    );
    shell_exec($cmd);
}

function convertImageToTs($imageFile, $outputTs) {
    $cmd = sprintf(
        'ffmpeg -loop 1 -i %s -c:v libx264 -t 3 -pix_fmt yuv420p -f mpegts %s',
        escapeshellarg($imageFile),
        escapeshellarg($outputTs)
    );
    shell_exec($cmd);
}

function concatenateTsFiles($tsFiles, $outputFile, $audioPath = null, $tickerText = null) {
    $listFile = tempnam(sys_get_temp_dir(), 'concat_') . '.txt';
    $fp = fopen($listFile, 'w');
    foreach ($tsFiles as $ts) {
        fwrite($fp, "file '" . str_replace("'", "'\\\\''", $ts) . "'\n");
    }
    fclose($fp);

    $tickerOption = '';
    if ($tickerText) {
        $safe = addslashes($tickerText);
        $tickerOption = "-vf \"drawtext=text='$safe':fontcolor=white:fontsize=24:x=w-mod(t*100\,w+tw):y=h-th-30:box=1:boxcolor=black@0.5:boxborderw=5\"";
    }

    $merged = getConfig('paths.temp', 'temp') . '/merged_' . uniqid() . '.mp4';
    $cmd = sprintf(
        'ffmpeg -f concat -safe 0 -i %s -c copy -bsf:a aac_adtstoasc %s %s',
        escapeshellarg($listFile),
        $tickerOption,
        escapeshellarg($merged)
    );
    exec($cmd . ' 2>&1', $out, $code);
    if ($code !== 0 || !file_exists($merged)) {
        echo "<div style='background:#f8d7da;padding:10px;margin:10px;'><strong>❌ Errore in concat:</strong><br>"
             . nl2br(htmlspecialchars(implode("\n", $out))) . "</div>";
        @unlink($listFile);
        exit;
    }

    if ($audioPath && file_exists($audioPath)) {
        $result = process_video($merged, $audioPath, $tickerText);
        if ($result['success']) {
            copy($result['video_url'], $outputFile);
        } else {
            copy($merged, $outputFile);
        }
    } else {
        copy($merged, $outputFile);
    }

    @unlink($merged);
    @unlink($listFile);
}

function cleanupTempFiles($files, $keepOriginals = false) {
    foreach ($files as $f) {
        if (file_exists($f) && (!$keepOriginals || strpos($f, 'uploads/') === false)) {
            @unlink($f);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    createUploadsDir();
    set_time_limit(0);

    $mode           = $_POST['mode'] ?? 'simple';
    $targetDuration = (!empty($_POST['duration']) && is_numeric($_POST['duration']))
                      ? intval($_POST['duration']) * 60
                      : 0;
    $audioPath      = !empty($_POST['audio']) && file_exists(__DIR__ . '/musica/' . basename($_POST['audio']))
                      ? realpath(__DIR__ . '/musica/' . basename($_POST['audio']))
                      : null;
    $tickerText     = trim($_POST['ticker_text'] ?? '');

    if ($mode === 'detect_people') {
        $deps = checkDependencies();
        if (!$deps['ffmpeg']) {
            echo "<div style='background:#f8d7da;padding:10px;border-radius:5px;margin:10px;color:#721c24;'>"
               . "<strong>⚠️ FFmpeg non disponibile</strong><br>Rilevamento persone impossibile.</div>";
            exit;
        }
    }

    if (!empty($_FILES['files'])) {
        $uploaded_ts = [];
        $segments    = [];

        echo "<div style='background:#f8f9fa;padding:10px;border-radius:5px;margin:10px;'><strong>🔄 Elaborazione...</strong><br>";

        foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $name = basename($_FILES['files']['name'][$i]);
            $dest = getConfig('paths.uploads','uploads') . '/' . $name;
            if (!move_uploaded_file($tmp, $dest)) {
                echo "❌ Errore salvataggio: $name<br>";
                continue;
            }
            echo "✅ Caricato: $name<br>";

            if ($mode === 'detect_people') {
                echo "🔍 Analisi: $name<br>";
                $res = detectMovingPeople($dest);
                if (!empty($res['success'])) {
                    foreach ($res['segments'] as $seg) {
                        $segments[] = $seg;
                    }
                } else {
                    echo "⚠️ {$res['message']}<br>";
                }
                // fallback TS
                $ts = getConfig('paths.uploads','uploads') . '/' . pathinfo($name, PATHINFO_FILENAME) . '.ts';
                convertToTs($dest, $ts);
                $uploaded_ts[] = $ts;

            } else {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $ts  = getConfig('paths.uploads','uploads') . '/' . pathinfo($name, PATHINFO_FILENAME) . '.ts';
                if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                    convertImageToTs($dest, $ts);
                } else {
                    convertToTs($dest, $ts);
                }
                $uploaded_ts[] = $ts;
            }
        }

        echo "</div>";

        $up = getConfig('paths.uploads','uploads');

        if ($mode === 'detect_people' && count($segments) > 0) {
            $seg_ts = [];
            foreach ($segments as $idx => $seg) {
                $tsPath = sprintf("%s/segment_%02d_%s.ts", $up, $idx, uniqid());
                convertToTs($seg, $tsPath);
                if (file_exists($tsPath)) $seg_ts[] = $tsPath;
            }
            if (empty($seg_ts)) {
                echo "<br>⚠️ Nessun segmento TS generato.<br>";
                cleanupTempFiles($segments);
                return;
            }
            $out = "$up/video_montato_" . date('Ymd_His') . ".mp4";
            concatenateTsFiles($seg_ts, $out, $audioPath, $tickerText);

        } elseif (count($uploaded_ts) > 1) {
            $out = "$up/final_video_" . date('Ymd_His') . ".mp4";
            concatenateTsFiles($uploaded_ts, $out, $audioPath, $tickerText);

        } else {
            echo "<br>⚠️ Carica almeno due file.<br>";
            cleanupTempFiles($uploaded_ts);
            return;
        }

        $fn = basename($out);
        echo "<br><strong>✅ Video pronto:</strong> "
           . "<a href=\"" . getConfig('paths.uploads','uploads') . "/$fn\" download>Scarica</a>";
        // alert
        echo '<audio src="/musica/alert.mp3" autoplay></audio>';
        echo '<script>alert("🎉 Montaggio completato!");</script>';

        cleanupTempFiles(array_merge($uploaded_ts, $segments), false);
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
        h1 { color: #333; text-align: center; }
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
        .option-group { margin-bottom: 20px; }
        .option-group h3 { margin-bottom: 8px; }
        select,input[type="file"],button { display:block; margin-top:10px; }
        button {
            background: #4CAF50;
            color: white;
            padding: 12px 20px;
            border:none;
            border-radius:4px;
            cursor:pointer;
            font-size:16px;
        }
        button:hover { background: #45a049; }
        audio { margin-top:10px; }
    </style>
    <script>
        function previewAudio(file){
            const a=document.getElementById('audioPreview');
            if(file){ a.src='musica/'+encodeURIComponent(file);a.style.display='block';a.load(); }
            else{ a.src='';a.style.display='none'; }
        }
    </script>
</head>
<body>
    <h1>🎬 VideoAssembly</h1>
    <div class="upload-container">
        <form method="POST" enctype="multipart/form-data">
            <h3>📂 Carica file (video o immagine)</h3>
            <input type="file" name="files[]" multiple accept="video/*,image/*" required>

            <div class="options">
                <div class="option-group">
                    <h3>⚙️ Modalità:</h3>
                    <label><input type="radio" name="mode" value="simple" checked> Semplice</label>
                    <label><input type="radio" name="mode" value="detect_people"> Detect People</label>
                </div>

                <div class="option-group">
                    <h3>⏱️ Durata (minuti):</h3>
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
                                if (preg_match('/\.(mp3|wav)$/i',$f)) echo "<option>$f</option>";
                            }
                        }
                        ?>
                    </select>
                    <audio id="audioPreview" controls style="display:none"></audio>
                </div>
            </div>

            <h3>📝 Ticker (opzionale):</h3>
            <input type="text" name="ticker_text" style="width:100%" placeholder="Testo scorrevole"><br>

            <button type="submit">🚀 Carica e Monta</button>
        </form>
    </div>
</body>
</html>
