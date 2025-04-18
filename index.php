<?php
// Avvia la sessione per gestire l'ID sessione per la privacy
session_start();

// Includi i file necessari
require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';
require_once 'transitions.php';
require_once 'duration_editor.php';
require_once 'audio_manager.php';
require_once 'video_effects.php';
require_once 'privacy_manager.php';

// Imposta una policy di privacy predefinita
if (!getConfig('privacy.retention_hours', false)) {
    setPrivacyPolicy(48, true);
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

// Effettua una pulizia automatica dei file temporanei vecchi ad ogni caricamento
$cleanupResult = cleanupFiles('temp', 3, false);

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
    $durationMethod = isset($_POST['duration_method']) ? $_POST['duration_method'] : 'select_interactions';
    setConfig('duration_editor.method', $durationMethod);
    
    // Audio di sottofondo
    $audioCategory = isset($_POST['audio_category']) ? $_POST['audio_category'] : '';
    $audioVolume = isset($_POST['audio_volume']) ? floatval($_POST['audio_volume']) : 0.3;
    
    // Effetto video
    $videoEffect = isset($_POST['video_effect']) ? $_POST['video_effect'] : '';
    
    // Verifica FFmpeg (unica dipendenza richiesta)
    if (!checkDependencies()['ffmpeg']) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; color: #721c24;'>";
        echo "<strong>⚠️ Errore: FFmpeg non disponibile</strong><br>";
        echo "L'elaborazione video richiede FFmpeg. Il sistema non può funzionare senza questa dipendenza.";
        echo "</div>";
        exit;
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
                    
                    // Traccia il file caricato per la privacy
                    trackFile($destination, $name, 'upload');
                    
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
                            
                            // Mostra informazioni sulle persone rilevate
                            if (isset($detectionResult['segments_info']) && count($detectionResult['segments_info']) > 0) {
                                echo "<details>";
                                echo "<summary>Dettagli delle scene rilevate</summary>";
                                echo "<div style='max-height: 150px; overflow-y: auto; margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 5px;'>";
                                echo "<ul>";
                                
                                foreach ($detectionResult['segments_info'] as $idx => $info) {
                                    $start = gmdate("H:i:s", $info['start']);
                                    $people = isset($info['people_count']) ? $info['people_count'] : '?';
                                    $importance = isset($info['importance']) ? round($info['importance'] * 100) : '?';
                                    
                                    echo "<li>Scena " . ($idx + 1) . ": a $start - ";
                                    if ($people >= 2) {
                                        echo "<strong style='color: green;'>$people persone</strong>";
                                    } else {
                                        echo "$people persona";
                                    }
                                    echo " (priorità: $importance%)</li>";
                                }
                                
                                echo "</ul>";
                                echo "</div>";
                                echo "</details>";
                            }
                            
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
                    
                    // Traccia il file elaborato
                    trackFile($tsFile, basename($segment), 'processing');
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
                
                // Applica effetto video se richiesto
                if (!empty($videoEffect)) {
                    $effects = getVideoEffects();
                    if (isset($effects[$videoEffect])) {
                        echo "🎨 Applicazione effetto " . $effects[$videoEffect]['name'] . "...<br>";
                        $outputWithEffect = getConfig('paths.uploads', 'uploads') . '/video_effect_' . $timestamp . '.mp4';
                        if (applyVideoEffect($outputFinal, $outputWithEffect, $videoEffect)) {
                            // Se l'effetto è stato applicato con successo, usa il nuovo file
                            unlink($outputFinal); // Rimuovi il file senza effetto
                            $outputFinal = $outputWithEffect;
                        } else {
                            echo "⚠️ Non è stato possibile applicare l'effetto video.<br>";
                        }
                    }
                }
                
                // Applica audio di sottofondo se richiesto
                if (!empty($audioCategory)) {
                    echo "🔊 Aggiunta audio di sottofondo " . ucfirst($audioCategory) . "...<br>";
                    $audio = getRandomAudioFromCategory($audioCategory);
                    
                    if ($audio) {
                        // Scarica l'audio se necessario
                        $audioDir = getConfig('paths.temp', 'temp') . '/audio';
                        if (!file_exists($audioDir)) {
                            mkdir($audioDir, 0777, true);
                        }
                        
                        $audioFile = $audioDir . '/' . basename($audio['url']);
                        if (!file_exists($audioFile)) {
                            downloadAudio($audio['url'], $audioFile);
                        }
                        
                        // Applica l'audio al video
                        $outputWithAudio = getConfig('paths.uploads', 'uploads') . '/video_audio_' . $timestamp . '.mp4';
                        if (applyBackgroundAudio($outputFinal, $audioFile, $outputWithAudio, $audioVolume)) {
                            // Se l'audio è stato applicato con successo, usa il nuovo file
                            unlink($outputFinal); // Rimuovi il file senza audio
                            $outputFinal = $outputWithAudio;
                            echo "✅ Audio aggiunto: " . $audio['name'] . "<br>";
                        } else {
                            echo "⚠️ Non è stato possibile aggiungere l'audio.<br>";
                        }
                    }
                }
                
                // Traccia il file finale
                trackFile($outputFinal, 'video_finale.mp4', 'output');
                
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
                
                // Informazioni sulla privacy
                echo "<div style='margin-top: 15px; font-size: 14px; color: #666;'>";
                echo "🔒 <strong>Informazioni sulla privacy:</strong> I file verranno eliminati automaticamente dopo " . 
                     getConfig('privacy.retention_hours', 48) . " ore dal caricamento.";
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
            $timestamp = date('Ymd_His');
            $outputFinal = getConfig('paths.uploads', 'uploads') . '/final_video_' . $timestamp . '.mp4';
            
            echo "🔄 Creazione del video finale...<br>";
            concatenateTsFiles($uploaded_ts_files, $outputFinal);
            
            // Applica effetto video se richiesto
            if (!empty($videoEffect)) {
                $effects = getVideoEffects();
                if (isset($effects[$videoEffect])) {
                    echo "🎨 Applicazione effetto " . $effects[$videoEffect]['name'] . "...<br>";
                    $outputWithEffect = getConfig('paths.uploads', 'uploads') . '/video_effect_' . $timestamp . '.mp4';
                    if (applyVideoEffect($outputFinal, $outputWithEffect, $videoEffect)) {
                        // Se l'effetto è stato applicato con successo, usa il nuovo file
                        unlink($outputFinal); // Rimuovi il file senza effetto
                        $outputFinal = $outputWithEffect;
                    }
                }
            }
            
            // Applica audio di sottofondo se richiesto
            if (!empty($audioCategory)) {
                echo "🔊 Aggiunta audio di sottofondo " . ucfirst($audioCategory) . "...<br>";
                $audio = getRandomAudioFromCategory($audioCategory);
                
                if ($audio) {
                    // Scarica l'audio se necessario
                    $audioDir = getConfig('paths.temp', 'temp') . '/audio';
                    if (!file_exists($audioDir)) {
                        mkdir($audioDir, 0777, true);
                    }
                    
                    $audioFile = $audioDir . '/' . basename($audio['url']);
                    if (!file_exists($audioFile)) {
                        downloadAudio($audio['url'], $audioFile);
                    }
                    
                    // Applica l'audio al video
                    $outputWithAudio = getConfig('paths.uploads', 'uploads') . '/video_audio_' . $timestamp . '.mp4';
                    if (applyBackgroundAudio($outputFinal, $audioFile, $outputWithAudio, $audioVolume)) {
                        // Se l'audio è stato applicato con successo, usa il nuovo file
                        unlink($outputFinal); // Rimuovi il file senza audio
                        $outputFinal = $outputWithAudio;
                    }
                }
            }
            
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
        .privacy-info {
            background: #e8f4fd;
            padding: 10px;
            border-radius: 5px;
            margin-top: 30px;
            font-size: 0.9em;
        }
        .range-slider {
            width: 100%;
            max-width: 200px;
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
            
            // Visualizza il valore dello slider del volume
            const volumeSlider = document.querySelector('input[name="audio_volume"]');
            const volumeValue = document.getElementById('volume-value');
            
            if (volumeSlider && volumeValue) {
                // Mostra il valore iniziale
                volumeValue.textContent = volumeSlider.value;
                
                // Aggiorna quando cambia
                volumeSlider.addEventListener('input', function() {
