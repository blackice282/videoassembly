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
                concatenateTsFiles($segment_ts_files, $outputFinal
