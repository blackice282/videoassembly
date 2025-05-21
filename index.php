v<?php
// Includi i file necessari
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

function concatenateTsFiles($tsFiles, $outputFile, $audioPath = null) {
    $tsList = implode('|', $tsFiles);
    $tempMerged = "temp/merged_" . uniqid() . ".mp4";
    $cmd = "ffmpeg -i \"concat:$tsList\" -c copy -bsf:a aac_adtstoasc \"$tempMerged\"";
    shell_exec($cmd);

    if ($audioPath && file_exists($audioPath)) {
        $result = process_video($tempMerged, $audioPath);
        if ($result['success']) {
            copy(parse_url($result['video_url'], PHP_URL_PATH), $outputFile);
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
// Gestione dell'invio del form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    createUploadsDir();
    set_time_limit(300); // 5 minuti

    $mode = $_POST['mode'] ?? 'simple';
    $targetDuration = (isset($_POST['duration']) && is_numeric($_POST['duration'])) ? intval($_POST['duration']) * 60 : 0;
    $durationMethod = $_POST['duration_method'] ?? 'trim';
    setConfig('duration_editor.method', $durationMethod);

    $selectedAudio = isset($_POST['audio']) ? trim($_POST['audio']) : '';
    $audioPath = $selectedAudio ? realpath(__DIR__ . "/musica/" . $selectedAudio) : null;

    if ($mode === 'detect_people') {
        $deps = checkDependencies();
        if (!$deps['ffmpeg']) {
            echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; color: #721c24;'>";
            echo "<strong>⚠️ Errore: FFmpeg non disponibile</strong><br>";
            echo "Il rilevamento persone richiede FFmpeg. Il sistema non può funzionare senza questa dipendenza.";
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
                        $start_time = microtime(true);
                        $detectionResult = detectMovingPeople($destination);
                        $detection_time = round(microtime(true) - $start_time, 1);

                        if ($detectionResult['success']) {
                            $num_segments = count($detectionResult['segments']);
                            echo "👥 Rilevate $num_segments scene in $detection_time secondi<br>";
                            foreach ($detectionResult['segments'] as $segment) {
                                $segments_to_process[] = $segment;
                            }
                        } else {
                            echo "⚠️ " . $detectionResult['message'] . " nel file: $name<br>";
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
            echo "<br>⏳ <strong>Finalizzazione del montaggio...</strong><br>";

            $segments_to_process = array_slice($segments_to_process, 0, 20);
            $segment_ts_files = [];

            foreach ($segments_to_process as $segment) {
                $tsFile = pathinfo($segment, PATHINFO_DIRNAME) . '/' . pathinfo($segment, PATHINFO_FILENAME) . '.ts';
                convertToTs($segment, $tsFile);
                if (file_exists($tsFile)) {
                    $segment_ts_files[] = $tsFile;
                }
            }

            if (count($segment_ts_files) > 0) {
                sort($segment_ts_files);
                $segmentsToUse = $segments_to_process;

                if ($targetDuration > 0) {
                    echo "⏱️ Adattamento alla durata di " . gmdate("H:i:s", $targetDuration) . "...<br>";
                    $tempDir = getConfig('paths.temp', 'temp') . '/duration_' . uniqid();
                    $segmentsDetailedInfo = $detectionResult['segments_info'] ?? [];
                    $adaptedSegments = adaptSegmentsToDuration($segmentsToUse, $targetDuration, $tempDir, $segmentsDetailedInfo);

                    if (!empty($adaptedSegments)) {
                        $segmentsToUse = $adaptedSegments;
                        $segment_ts_files = [];
                        foreach ($adaptedSegments as $segment) {
                            $tsFile = pathinfo($segment, PATHINFO_DIRNAME) . '/' . pathinfo($segment, PATHINFO_FILENAME) . '.ts';
                            convertToTs($segment, $tsFile);
                            if (file_exists($tsFile)) {
                                $segment_ts_files[] = $tsFile;
                            }
                        }
                    }
                }

                $timestamp = date('Ymd_His');
                $outputFinal = getConfig('paths.uploads', 'uploads') . '/video_montato_' . $timestamp . '.mp4';
                echo "🔄 Creazione del video finale con " . count($segment_ts_files) . " segmenti...<br>";
                $start_time = microtime(true);
                concatenateTsFiles($segment_ts_files, $outputFinal, $audioPath);
                $time = round(microtime(true) - $start_time, 1);

                echo "<br>🎉 <strong>Montaggio completato in $time secondi!</strong><br>";
                echo "<a href='$outputFinal' download>📥 Scarica il video</a><br>";
            }
        } else if (count($uploaded_ts_files) > 1) {
            $outputFinal = getConfig('paths.uploads', 'uploads') . '/final_video_' . date('Ymd_His') . '.mp4';
            concatenateTsFiles($uploaded_ts_files, $outputFinal, $audioPath);
            echo "<br>🎉 <strong>Montaggio completato!</strong> <a href='$outputFinal' download>Scarica il video</a>";
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
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="files[]" multiple required>

        <br><br>
        <label>🎵 Musica di sottofondo (opzionale):</label>
        <select name="audio" onchange="previewAudio(this.value)">
            <option value="">-- Nessuna --</option>
            <?php
            foreach (scandir(__DIR__ . '/musica') as $file) {
                if (preg_match('/\.(mp3|wav)$/i', $file)) {
                    echo "<option value=\"$file\">$file</option>";
                }
            }
            ?>
        </select>
        <br>
        <audio id="audioPreview" controls style="display:none; margin-top:10px;"></audio>

        <br><br>
        <label>Modalità:</label><br>
        <input type="radio" name="mode" value="simple" checked> Montaggio semplice
        <input type="radio" name="mode" value="detect_people"> Rilevamento persone

        <br><br>
        <label>Durata massima (minuti):</label>
        <select name="duration">
            <option value="0">Durata originale</option>
            <option value="1">1 minuto</option>
            <option value="3">3 minuti</option>
            <option value="5">5 minuti</option>
            <option value="10">10 minuti</option>
        </select>

        <br><br>
        <label>Metodo durata:</label><br>
        <input type="radio" name="duration_method" value="trim" checked> Trim
        <input type="radio" name="duration_method" value="select"> Selezione
        <input type="radio" name="duration_method" value="speed"> Velocità

        <br><br>
        <button type="submit">Carica e Monta</button>
    </form>
</body>
</html>
