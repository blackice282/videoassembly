<?php
// Includi i file necessari
require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';
require_once 'transitions.php';
require_once 'duration_editor.php';
// Audio di sottofondo
$musicaDir = __DIR__ . '/musica';
$filesAudio = glob($musicaDir.'/*.mp3');
$emozionali = $divertenti = [];
foreach ($filesAudio as $f) {
    $b = basename($f);
    if (stripos($b, 'emozionale') === 0) {
        $emozionali[] = $b;
    } elseif (stripos($b, 'divertente') === 0) {
        $divertenti[] = $b;
    }
}


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

function concatenateTsFiles($tsFiles, $outputFile) {
    $tsList = implode('|', $tsFiles);
    $cmd = "ffmpeg -i \"concat:$tsList\" -c copy -bsf:a aac_adtstoasc \"$outputFile\"";
    shell_exec($cmd);
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
    
    // Imposta timeout più lungo per operazioni pesanti
    set_time_limit(300); // 5 minuti
    
    // Modalità di elaborazione: 'simple', 'detect_people'
    $mode = isset($_POST['mode']) ? $_POST['mode'] : 'simple';
    
    // Durata desiderata in secondi (converte da minuti)
    $targetDuration = isset($_POST['duration']) && is_numeric($_POST['duration']) ? 
                     intval($_POST['duration']) * 60 : 0;  // 0 significa nessun limite
    
    // Metodo di adattamento della durata
    $durationMethod = isset($_POST['duration_method']) ? $_POST['duration_method'] : 'trim';
    setConfig('duration_editor.method', $durationMethod);
    
    // Verifica FFmpeg (unica dipendenza richiesta)
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
                        // Rileva persone in movimento e ottieni segmenti
                        echo "🔍 Analisi del video: $name<br>";
                        $start_time = microtime(true);
                        $detectionResult = detectMovingPeople($destination);
                        $detection_time = round(microtime(true) - $start_time, 1);
                        
                        if ($detectionResult['success']) {
                            $num_segments = count($detectionResult['segments']);
                            echo "👥 Rilevate " . $num_segments . " scene interessanti in $detection_time secondi<br>";
                            
                            // Aggiungi tutti i segmenti all'array da processare
                            foreach ($detectionResult['segments'] as $segment) {
                                $segments_to_process[] = $segment;
                            }
                        } else {
                            echo "⚠️ " . $detectionResult['message'] . " nel file: $name<br>";
                        }
                    } else {
                        // Modalità semplice: converti in .ts per la concatenazione
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
        
        // Procedi con la concatenazione appropriata in base alla modalità
        if ($mode === 'detect_people' && count($segments_to_process) > 0) {
            echo "<br>⏳ <strong>Finalizzazione del montaggio...</strong><br>";
            
            // Converti i segmenti rilevati in formato .ts
            $segment_ts_files = [];
            
            // Ottimizzazione: processa solo i primi 20 segmenti massimo
            $segments_to_process = array_slice($segments_to_process, 0, 20);
            
            foreach ($segments_to_process as $idx => $segment) {
                $tsFile = pathinfo($segment, PATHINFO_DIRNAME) . '/' . pathinfo($segment, PATHINFO_FILENAME) . '.ts';
                convertToTs($segment, $tsFile);
                if (file_exists($tsFile)) {
                    $segment_ts_files[] = $tsFile;
                }
            }
            
            if (count($segment_ts_files) > 0) {
                // Ordina i segmenti per nome file
                sort($segment_ts_files);
                
                // Verifica se è richiesto l'adattamento della durata
                $segmentsToUse = $segments_to_process;
                
                if ($targetDuration > 0) {
                    echo "⏱️ Adattamento alla durata di " . gmdate("H:i:s", $targetDuration) . "...<br>";
                    
                    // Adatta i segmenti alla durata desiderata
                    $tempDir = getConfig('paths.temp', 'temp') . '/duration_' . uniqid();
                    
                    // Ottimizzazione: usa direttamente le informazioni sui segmenti dal rilevatore
                    $segmentsDetailedInfo = isset($detectionResult['segments_info']) ? 
                                          $detectionResult['segments_info'] : [];
                    
                    $adaptedSegments = adaptSegmentsToDuration($segmentsToUse, $targetDuration, $tempDir, $segmentsDetailedInfo);
                    
                    if (!empty($adaptedSegments)) {
                        $segmentsToUse = $adaptedSegments;
                        
                        // Converti i segmenti adattati in formato .ts
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
                
                // Concatena i segmenti rilevati
                $timestamp = date('Ymd_His');
                $outputFinal = getConfig('paths.uploads', 'uploads') . '/video_montato_' . $timestamp . '.mp4';
                
                echo "🔄 Creazione del video finale con " . count($segment_ts_files) . " segmenti...<br>";
                $start_time = microtime(true);
                concatenateTsFiles($segment_ts_files, $outputFinal);
                $concatenation_time = round(microtime(true) - $start_time, 1);
                
                // Genera una miniatura per il video finale (ottimizzato)
                $thumbnailPath = getConfig('paths.uploads', 'uploads') . '/thumbnail_' . $timestamp . '.jpg';
                $thumbnailCmd = "ffmpeg -ss 00:00:03 -i " . escapeshellarg($outputFinal) . " -vframes 1 -q:v 2 " . escapeshellarg($thumbnailPath);
                shell_exec($thumbnailCmd);
                
                echo "<br>🎉 <strong>Montaggio completato in $concatenation_time secondi!</strong><br><br>";
                
                echo "<div style='display: flex; align-items: center; gap: 20px;'>";
                if (file_exists($thumbnailPath)) {
                    echo "<img src='$thumbnailPath' style='max-width: 200px; max-height: 120px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);'>";
                }
                echo "<a href='$outputFinal' download style='display: inline-block; background: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                      <svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' style='vertical-align: text-bottom; margin-right: 5px;' viewBox='0 0 16 16'>
                        <path d='M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z'/>
                        <path d='M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z'/>
                      </svg>
                      Scarica il video</a>";
                echo "</div>";
                
                // Informazioni base sul video (ottimizzato)
                $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height,duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($outputFinal);
                $videoInfo = shell_exec($cmd);
                
                if ($videoInfo) {
                    $infoLines = explode("\n", $videoInfo);
                    if (count($infoLines) >= 3) {
                        $width = $infoLines[0];
                        $height = $infoLines[1];
                        $duration = round(floatval($infoLines[2]), 1);
                        
                        echo "<div style='margin-top: 15px; font-size: 14px; color: #666;'>";
                        echo "ℹ️ <strong>Informazioni video:</strong> ";
                        echo "{$width}x{$height} | ";
                        echo "Durata: " . gmdate("H:i:s", $duration);
                        echo "</div>";
                    }
                }
            } else {
                echo "<br>⚠️ <strong>Nessun segmento valido</strong> da unire nel video finale.";
            }
            
            // Pulizia dei file temporanei
            if (getConfig('system.cleanup_temp', true)) {
                cleanupTempFiles(array_merge($segment_ts_files, $segments_to_process), getConfig('system.keep_original', true));
            }
        } else if (count($uploaded_ts_files) > 1) {
            // Modalità semplice: concatena tutti i video
            $outputFinal = getConfig('paths.uploads', 'uploads') . '/final_video_' . date('Ymd_His') . '.mp4';
            concatenateTsFiles($uploaded_ts_files, $outputFinal);
            echo "<br>🎉 <strong>Montaggio completato!</strong> <a href='$outputFinal' download>Clicca qui per scaricare il video</a>";
            
            // Pulizia dei file temporanei
            cleanupTempFiles($uploaded_ts_files, getConfig('system.keep_original', true));
        } else {
            echo "<br>⚠️ Carica almeno due video per generare un montaggio.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VideoAssembly - Montaggio Video Automatico</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        h1 {
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .upload-container {
            border: 2px dashed #ccc;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        input[type="file"] {
            display: block;
            margin: 15px auto;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
            max-width: 400px;
        }
        .options {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .option-group {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .option-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background 0.3s;
        }
        button:hover {
            background: #45a049;
        }
        label {
            display: block;
            margin: 8px 0;
            cursor: pointer;
        }
        label input[type="radio"] {
            margin-right: 8px;
        }
        select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            width: 100%;
            max-width: 200px;
            margin-top: 5px;
        }
        .instructions {
            background: #f1f8e9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 30px;
        }
        .instructions h3 {
            margin-top: 0;
            color: #2e7d32;
        }
        .duration-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        .duration-controls select {
            max-width: 150px;
        }
    </style>
    <script>
        // Mostra/nascondi le opzioni di adattamento della durata
        document.addEventListener('DOMContentLoaded', function() {
            const durationSelect = document.querySelector('select[name="duration"]');
            const durationMethodOptions = document.getElementById('durationMethodOptions');
            
            // Funzione per mostrare/nascondere le opzioni in base alla selezione
            function toggleDurationOptions() {
                if (durationSelect.value === '0') {
                    durationMethodOptions.style.display = 'none';
                } else {
                    durationMethodOptions.style.display = 'block';
                }
            }
            
            // Inizializza lo stato al caricamento
            toggleDurationOptions();
            
            // Aggiungi event listener per cambi futuri
            durationSelect.addEventListener('change', toggleDurationOptions);
        });
    </script>
</head>
<body>
    <h1>🎬 VideoAssembly - Montaggio Video Automatico</h1>
    
    <div class="upload-container">
        <form method="POST" enctype="multipart/form-data">
            <h3>Carica i tuoi video</h3>
            <input type="file" name="files[]" multiple accept="video/mp4,video/quicktime,video/x-msvideo">
            
            <div class="options">
                <div class="option-group">
                    <h3>Modalità di elaborazione:</h3>
                    <label>
                        <input type="radio" name="mode" value="simple" checked> 
                        Montaggio semplice (concatena i video)
                    </label>
                    <label>
                        <input type="radio" name="mode" value="detect_people"> 
                        Rilevamento persone (estrae solo parti con persone in movimento)
                    </label>
                </div>
                
                <div class="option-group">
                    <h3>Durata del video finale:</h3>
                    <div class="duration-controls">
                        <select name="duration">
                            <option value="0">Durata originale</option>
                            <option value="1">1 minuto</option>
                            <option value="3">3 minuti</option>
                            <option value="5">5 minuti</option>
                            <option value="10">10 minuti</option>
                            <option value="15">15 minuti</option>
                            <option value="30">30 minuti</option>
                        </select>
                    </div>
                    
                    <div id="durationMethodOptions" style="margin-top: 10px; display: none;">
                        <h4>Metodo di adattamento:</h4>
                        <label>
                            <input type="radio" name="duration_method" value="select_interactions" checked> 
                            Interazioni tra persone (seleziona scene con più persone che interagiscono)
                        </label>
                        <label>
                            <input type="radio" name="duration_method" value="select"> 
                            Selezione migliori (sceglie solo le scene più importanti)
                        </label>
                        <label>
                            <input type="radio" name="duration_method" value="trim"> 
                            Taglio proporzionale (mantiene tutte le scene, riduce la loro durata)
                        </label>
                        <label>
                            <input type="radio" name="duration_method" value="speed"> 
                            Modifica velocità (accelera il video per mantenere tutte le scene)
                        </label>
                    </div>
                </div>
            </div>
            
    <!-- Selezione audio di sottofondo -->
    <div class="option-group">
      <label for="background_file"><strong>Audio di sottofondo:</strong></label>
      <select id="background_file" name="background_file">
        <optgroup label="Emozionale">
          <?php foreach ($emozionali as $a): ?>
            <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
          <?php endforeach; ?>
        </optgroup>
        <optgroup label="Divertente">
          <?php foreach ($divertenti as $a): ?>
            <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
          <?php endforeach; ?>
        </optgroup>
      </select>
    </div>

    <!-- Anteprima audio -->
    <div class="option-group">
      <label><strong>Anteprima audio:</strong></label>
      <audio controls id="audioPreview">
        <source id="audioSource" src="" type="audio/mpeg">
        Il tuo browser non supporta l'elemento audio.
      </audio>
    </div>

            <button type="submit">Carica e Monta</button>
        </form>
    </div>
    
    <div class="instructions">
        <h3>📋 Istruzioni</h3>
        <ol>
            <li><strong>Carica i tuoi video</strong> - Seleziona uno o più file video dal tuo dispositivo</li>
            <li><strong>Scegli la modalità</strong> - Concatenazione semplice o rilevamento automatico di scene interessanti</li>
            <li><strong>Imposta la durata</strong> - Scegli quanto dovrà durare il video finale (opzionale)</li>
            <li><strong>Avvia il montaggio</strong> - Clicca su "Carica e Monta" e attendi il completamento</li>
            <li><strong>Scarica il risultato</strong> - Una volta completato, scarica il video finale</li>
        </ol>
        <p><em>Nota: L'elaborazione è ottimizzata per la velocità. Per risultati ancora migliori con video più lunghi, considera di caricare video più brevi o di limitare la durata finale.</em></p>
    </div>
<script>
  document.getElementById('background_file').addEventListener('change', function() {
    var src = 'musica/' + this.value;
    var audio = document.getElementById('audioPreview');
    audio.src = src;
    audio.load();
  });
  // Carica un'anteprima iniziale
  var sel = document.getElementById('background_file');
  if (sel.value) sel.dispatchEvent(new Event('change'));
</script>
</body>
</html>