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
require_once 'face_detection.php';

// Imposta una policy di privacy predefinita
if (!getConfig('privacy.retention_hours', false)) {
    setPrivacyPolicy(48, true);
}

// Funzione migliorata per il debug
function debugLog($message, $level = 'info') {
    $logDir = 'logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/debug_' . date('Ymd') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp][$level] $message\n";
    
    // Assicurati che i messaggi vengano effettivamente scritti
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    
    // Anche mostrare in pagina se richiesto
    if (getConfig('system.debug', false) || $level === 'error') {
        echo "<small style='color: " . ($level === 'error' ? 'red' : '#666') . ";'>Debug: $message</small><br>";
    }
}

// Funzione sicura per applicare gli effetti video
function safeApplyVideoEffect($videoPath, $outputPath, $effectName, $timestamp) {
    if (!file_exists($videoPath) || filesize($videoPath) <= 0) {
        debugLog("File video non valido per applicare effetto: $videoPath", "error");
        return false;
    }
    
    $effects = getVideoEffects();
    if (!isset($effects[$effectName])) {
        debugLog("Effetto richiesto non trovato: $effectName", "error");
        return false;
    }
    
    debugLog("Applicazione effetto " . $effects[$effectName]['name'] . " al file " . filesize($videoPath) . " bytes");
    
    // Assicurati che la directory di output esista
    $outputDir = dirname($outputPath);
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    
    // Tenta di applicare l'effetto
    $result = applyVideoEffect($videoPath, $outputPath, $effectName);
    
    if ($result && file_exists($outputPath) && filesize($outputPath) > 0) {
        debugLog("Effetto applicato con successo: $outputPath (" . filesize($outputPath) . " bytes)");
        return true;
    }
    
    // Se fallisce, prova un approccio alternativo diretto
    debugLog("Tentativo alternativo per applicare l'effetto", "error");
    
    $effect = $effects[$effectName];
    $filter = $effect['filter'];
    
    // Utilizza un comando FFmpeg più diretto
    $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath) . 
           " -vf " . escapeshellarg($filter) . 
           " -c:v libx264 -preset ultrafast -crf 23 -c:a copy " . 
           escapeshellarg($outputPath);
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
        debugLog("Effetto applicato con successo (metodo alternativo): $outputPath");
        return true;
    }
    
    debugLog("Impossibile applicare l'effetto video", "error");
    return false;
}

// Funzione sicura per applicare l'audio di sottofondo
function safeApplyBackgroundAudio($videoPath, $audioCategory, $audioVolume, $timestamp) {
    if (!file_exists($videoPath) || filesize($videoPath) <= 0) {
        debugLog("File video non valido per applicare audio: $videoPath", "error");
        return false;
    }
    
    debugLog("Aggiunta audio di sottofondo " . ucfirst($audioCategory) . " al file " . filesize($videoPath) . " bytes");
    
    // Ottieni un audio dalla categoria
    $audio = getRandomAudioFromCategory($audioCategory);
    if (!$audio) {
        debugLog("Nessun audio trovato nella categoria: $audioCategory", "error");
        return false;
    }
    
    // Prepara directory e file
    $audioDir = 'temp/audio';
    if (!file_exists($audioDir)) {
        mkdir($audioDir, 0777, true);
    }
    
    $audioFile = $audioDir . '/' . basename($audio['url']);
    $outputWithAudio = getConfig('paths.uploads', 'uploads') . '/video_audio_' . $timestamp . '.mp4';
    
    // Scarica l'audio se necessario
    if (!file_exists($audioFile) || filesize($audioFile) <= 0) {
        debugLog("Scaricamento audio da " . $audio['url']);
        $downloadSuccess = downloadAudio($audio['url'], $audioFile);
        
        if (!$downloadSuccess || !file_exists($audioFile) || filesize($audioFile) <= 0) {
            debugLog("Scaricamento audio fallito, utilizzo backup", "error");
            
            // Usa audio backup
            $backupAudio = getBackupAudio($audioCategory);
            if ($backupAudio) {
                $audioFile = $backupAudio['url'];
                debugLog("Utilizzo audio di backup: $audioFile");
            } else {
                debugLog("Nessun audio di backup disponibile", "error");
                return false;
            }
        }
    }
    
    // Verifica che il file audio esista
    if (!file_exists($audioFile) || filesize($audioFile) <= 0) {
        debugLog("File audio non valido: $audioFile", "error");
        return false;
    }
    
    debugLog("Audio preparato: $audioFile (" . filesize($audioFile) . " bytes)");
    
    // Applica l'audio al video
    $result = applyBackgroundAudio($videoPath, $audioFile, $outputWithAudio, $audioVolume);
    
    if ($result && file_exists($outputWithAudio) && filesize($outputWithAudio) > 0) {
        debugLog("Audio aggiunto con successo: $outputWithAudio (" . filesize($outputWithAudio) . " bytes)");
        return $outputWithAudio;
    }
    
    // Se fallisce, prova con un comando FFmpeg più diretto
    debugLog("Tentativo alternativo per aggiungere audio", "error");
    
    // Metodo alternativo con comando diretto
    $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath) . 
           " -stream_loop -1 -i " . escapeshellarg($audioFile) . 
           " -filter_complex \"[1:a]volume=" . $audioVolume . "[music];[0:a][music]amix=inputs=2:duration=first\" " .
           " -c:v copy -c:a aac -b:a 128k -shortest " . 
           escapeshellarg($outputWithAudio);
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($outputWithAudio) && filesize($outputWithAudio) > 0) {
        debugLog("Audio aggiunto con successo (metodo alternativo): $outputWithAudio");
        return $outputWithAudio;
    }
    
    // Terzo tentativo con un comando ultra-semplificato
    debugLog("Tentativo ultra-semplificato per aggiungere audio", "error");
    
    $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath) . 
           " -i " . escapeshellarg($audioFile) . 
           " -c:v copy -c:a aac -b:a 128k -filter_complex \"[1:a]volume=" . $audioVolume . "[a1];[0:a][a1]amerge=inputs=2[a]\" -map 0:v -map \"[a]\" " . 
           escapeshellarg($outputWithAudio);
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($outputWithAudio) && filesize($outputWithAudio) > 0) {
        debugLog("Audio aggiunto con successo (metodo ultra-semplificato): $outputWithAudio");
        return $outputWithAudio;
    }
    
    debugLog("Impossibile aggiungere l'audio di sottofondo", "error");
    return false;
}

// Funzione sicura per applicare la privacy dei volti
function safeApplyFacePrivacy($videoPath, $timestamp, $excludeYellowVests) {
    if (!file_exists($videoPath) || filesize($videoPath) <= 0) {
        debugLog("File video non valido per applicare privacy volti: $videoPath", "error");
        return false;
    }
    
    // Verifica se OpenCV è disponibile
    if (!isOpenCVAvailable()) {
        debugLog("OpenCV non è disponibile per la privacy dei volti", "error");
        return false;
    }
    
    debugLog("Applicazione privacy volti al file " . filesize($videoPath) . " bytes");
    
    // Prepara il percorso di output
    $outputWithFacePrivacy = getConfig('paths.uploads', 'uploads') . '/video_privacy_' . $timestamp . '.mp4';
    
    // Assicurati che la directory di output esista
    $outputDir = dirname($outputWithFacePrivacy);
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    
    // Applica la privacy dei volti
    $result = applyFacePrivacy($videoPath, $outputWithFacePrivacy, $excludeYellowVests);
    
    if ($result && file_exists($outputWithFacePrivacy) && filesize($outputWithFacePrivacy) > 0) {
        debugLog("Privacy volti applicata con successo: $outputWithFacePrivacy (" . filesize($outputWithFacePrivacy) . " bytes)");
        return $outputWithFacePrivacy;
    }
    
    debugLog("Impossibile applicare la privacy dei volti", "error");
    return false;
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
    $cmd = "ffmpeg -i " . escapeshellarg($inputFile) . 
           " -c copy -bsf:v h264_mp4toannexb -f mpegts " . 
           escapeshellarg($outputTs);
    debugLog("Esecuzione comando TS: $cmd");
    shell_exec($cmd);
    
    // Verifica il risultato
    if (file_exists($outputTs) && filesize($outputTs) > 0) {
        debugLog("File TS creato con successo: $outputTs (" . filesize($outputTs) . " bytes)");
        return true;
    } else {
        debugLog("Errore nella creazione del file TS: $outputTs", "error");
        return false;
    }
}

function concatenateTsFiles($tsFiles, $outputFile) {
    if (empty($tsFiles)) {
        debugLog("Nessun file TS da concatenare", "error");
        return false;
    }
    
    $tsList = implode('|', $tsFiles);
    $cmd = "ffmpeg -i \"concat:$tsList\" -c copy -bsf:a aac_adtstoasc \"$outputFile\"";
    debugLog("Esecuzione comando concatenazione: $cmd");
    shell_exec($cmd);
    
    // Verifica il risultato
    if (file_exists($outputFile) && filesize($outputFile) > 0) {
        debugLog("File concatenato creato con successo: $outputFile (" . filesize($outputFile) . " bytes)");
        return true;
    } else {
        debugLog("Errore nella concatenazione dei file TS", "error");
        return false;
    }
}

function cleanupTempFiles($files, $keepOriginals = false) {
    foreach ($files as $file) {
        if (file_exists($file) && (!$keepOriginals || strpos($file, 'uploads/') === false)) {
            if (unlink($file)) {
                debugLog("File temporaneo rimosso: $file");
            } else {
                debugLog("Impossibile rimuovere file temporaneo: $file", "error");
            }
        }
    }
}

// Effettua una pulizia automatica dei file temporanei vecchi ad ogni caricamento
if (function_exists('cleanupFiles')) {
    $cleanupResult = cleanupFiles('temp', 3, false);
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
    $durationMethod = isset($_POST['duration_method']) ? $_POST['duration_method'] : 'select_interactions';
    setConfig('duration_editor.method', $durationMethod);
    
    // Audio di sottofondo
    $audioCategory = isset($_POST['audio_category']) ? $_POST['audio_category'] : '';
    $audioVolume = isset($_POST['audio_volume']) ? floatval($_POST['audio_volume']) : 0.3;
    
    // Effetto video
    $videoEffect = isset($_POST['video_effect']) ? $_POST['video_effect'] : '';
    
    // Privacy dei volti
    $applyFacePrivacy = isset($_POST['apply_face_privacy']);
    $excludeYellowVests = isset($_POST['exclude_yellow_vests']);
    
    // Verifica FFmpeg (unica dipendenza richiesta)
    if (!checkDependencies()['ffmpeg']) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; color: #721c24;'>";
        echo "<strong>⚠️ Errore: FFmpeg non disponibile</strong><br>";
        echo "L'elaborazione video richiede FFmpeg. Il sistema non può funzionare senza questa dipendenza.";
        echo "</div>";
        exit;
    }
    
    debugLog("Avvio elaborazione con parametri: mode=$mode, targetDuration=$targetDuration, audioCategory=$audioCategory, videoEffect=$videoEffect, applyFacePrivacy=" . ($applyFacePrivacy ? "sì" : "no"));
    
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
                    debugLog("File caricato: $destination (" . filesize($destination) . " bytes)");
                    
                    // Traccia il file caricato per la privacy
                    if (function_exists('trackFile')) {
                        trackFile($destination, $name, 'upload');
                    }
                    
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
                            debugLog("Rilevate $num_segments scene nel video $name");
                            
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
                                debugLog("Aggiunto segmento: $segment (" . (file_exists($segment) ? filesize($segment) : "non esiste") . " bytes)");
                            }
                        } else {
                            echo "⚠️ " . $detectionResult['message'] . " nel file: $name<br>";
                            debugLog("Errore rilevamento: " . $detectionResult['message'] . " nel file: $name", "error");
                        }
                    } else {
                        // Modalità semplice: converti in .ts per la concatenazione
                        $tsFile = getConfig('paths.uploads', 'uploads') . '/' . pathinfo($name, PATHINFO_FILENAME) . '.ts';
                        $tsResult = convertToTs($destination, $tsFile);
                        if ($tsResult && file_exists($tsFile)) {
                            $uploaded_ts_files[] = $tsFile;
                            debugLog("File TS creato: $tsFile");
                        } else {
                            debugLog("Errore nella creazione del file TS: $tsFile", "error");
                        }
                    }
                } else {
                    echo "❌ Errore nel salvataggio del file: $name<br>";
                    debugLog("Errore salvataggio: $destination", "error");
                }
            }
        }
        echo "</div>";
        
        // Procedi con la concatenazione appropriata in base alla modalità
        if ($mode === 'detect_people' && count($segments_to_process) > 0) {
            echo "<br>⏳ <strong>Finalizzazione del montaggio...</strong><br>";
            debugLog("Inizia finalizzazione montaggio con " . count($segments_to_process) . " segmenti");
            
            // Converti i segmenti rilevati in formato .ts
            $segment_ts_files = [];
            
            // Ottimizzazione: processa solo i primi 20 segmenti massimo
            $segments_to_process = array_slice($segments_to_process, 0, 20);
            
            foreach ($segments_to_process as $idx => $segment) {
                $tsFile = pathinfo($segment, PATHINFO_DIRNAME) . '/' . pathinfo($segment, PATHINFO_FILENAME) . '.ts';
                $tsResult = convertToTs($segment, $tsFile);
                if ($tsResult && file_exists($tsFile)) {
                    $segment_ts_files[] = $tsFile;
                    
                    // Traccia il file elaborato
                    if (function_exists('trackFile')) {
                        trackFile($tsFile, basename($segment), 'processing');
                    }
                    debugLog("Segmento TS creato: $tsFile");
                } else {
                    debugLog("Errore nella creazione del segmento TS: $tsFile", "error");
                }
            }
    
            if (count($segment_ts_files) > 0) {
                // Ordina i segmenti per nome file
                sort($segment_ts_files);
                debugLog("Ordinati " . count($segment_ts_files) . " segmenti TS");
                
                // Verifica se è richiesto l'adattamento della durata
                $segmentsToUse = $segments_to_process;
                
                if ($targetDuration > 0) {
                    echo "⏱️ Adattamento alla durata di " . gmdate("H:i:s", $targetDuration) . "...<br>";
                    
                    // Calcola la durata totale attuale
                    $currentDuration = calculateTotalDuration($segmentsToUse);
                    echo "ℹ️ Durata attuale dei segmenti: " . gmdate("H:i:s", $currentDuration) . "<br>";
                    debugLog("Adattamento durata: attuale=$currentDuration, target=$targetDuration");
                    
                    // Estrai le informazioni sul numero di persone nei segmenti
                    $segmentsDetailedInfo = [];
                    if (isset($detectionResult['segments_info']) && !empty($detectionResult['segments_info'])) {
                        foreach ($detectionResult['segments_info'] as $info) {
                            if (isset($info['people_count'])) {
                                $segmentsDetailedInfo[] = $info;
                            } else {
                                // Se non è specificato, aggiungiamo un valore predefinito
                                $segmentsDetailedInfo[] = ['people_count' => 1];
                            }
                        }
                    }
                    
                    // Se stiamo usando un metodo basato sulle interazioni, mostra informazioni sulle persone rilevate
                    if ($durationMethod === 'select_interactions') {
                        echo "<details>";
                        echo "<summary>📊 Informazioni sul rilevamento delle interazioni</summary>";
                        echo "<div style='max-height: 200px; overflow-y: auto; padding: 10px; background: #f5f5f5; border-radius: 5px;'>";
                        echo "<p>Il sistema selezionerà prioritariamente le scene con più persone, dove è più probabile che ci siano interazioni:</p>";
                        echo "<ul>";
                        
                        if (!empty($segmentsDetailedInfo)) {
                            foreach ($segmentsDetailedInfo as $idx => $info) {
                                $peopleCount = isset($info['people_count']) ? $info['people_count'] : '?';
                                echo "<li>Segmento " . ($idx + 1) . ": " . $peopleCount . " persone";
                                if ($peopleCount >= 3) {
                                    echo " <strong style='color: green;'>(Alta priorità)</strong>";
                                } elseif ($peopleCount == 2) {
                                    echo " <strong style='color: orange;'>(Media priorità)</strong>";
                                } else {
                                    echo " <strong style='color: gray;'>(Bassa priorità)</strong>";
                                }
                                echo "</li>";
                            }
                        } else {
                            echo "<li>Nessuna informazione dettagliata disponibile sulle interazioni</li>";
                        }
                        
                        echo "</ul>";
                        echo "</div>";
                        echo "</details><br>";
                    }
                    
                    // Adatta i segmenti alla durata desiderata
                    $tempDir = getConfig('paths.temp', 'temp') . '/duration_' . uniqid();
                    $adaptedSegments = adaptSegmentsToDuration($segmentsToUse, $targetDuration, $tempDir, $segmentsDetailedInfo);
                    
                    if (!empty($adaptedSegments)) {
                        $segmentsToUse = $adaptedSegments;
                        debugLog("Segmenti adattati: " . count($segmentsToUse));
                        
                        // Converti i segmenti adattati in formato .ts
                        $segment_ts_files = [];
                        foreach ($adaptedSegments as $segment) {
                            $tsFile = pathinfo($segment, PATHINFO_DIRNAME) . '/' . pathinfo($segment, PATHINFO_FILENAME) . '.ts';
                            $tsResult = convertToTs($segment, $tsFile);
                            if ($tsResult && file_exists($tsFile)) {
                                $segment_ts_files[] = $tsFile;
                                debugLog("Segmento adattato TS: $tsFile");
                            } else {
                                debugLog("Errore segmento adattato TS: $tsFile", "error");
                            }
                        }
                    }
                }
                
                // Concatena i segmenti rilevati
                $timestamp = date('Ymd_His');
                $outputFinal = getConfig('paths.uploads', 'uploads') . '/video_montato_' . $timestamp . '.mp4';
                
                echo "🔄 Creazione del video finale con " . count($segment_ts_files) . " segmenti...<br>";
                $start_time = microtime(true);
                $concatResult = concatenateTsFiles($segment_ts_files, $outputFinal);
                $concatenation_time = round(microtime(true) - $start_time, 1);
                
                if (!$concatResult || !file_exists($outputFinal) || filesize($outputFinal) <= 0) {
                    echo "❌ <strong>Errore nella creazione del video finale.</strong><br>";
                    debugLog("Errore concatenazione: $outputFinal", "error");
                } else {
                    debugLog("Video finale creato: $outputFinal (" . filesize($outputFinal) . " bytes)");
                }
                    // SEZIONE CORRETTA PER APPLICARE EFFETTI
                    // ===========================================================================
                    
                    // Applica effetto video se richiesto
                    if (!empty($videoEffect)) {
                        $effects = getVideoEffects();
                        if (isset($effects[$videoEffect])) {
                            echo "🎨 Applicazione effetto " . $effects[$videoEffect]['name'] . "...<br>";
                            $outputWithEffect = getConfig('paths.uploads', 'uploads') . '/video_effect_' . $timestamp . '.mp4';
                            
                            if (safeApplyVideoEffect($outputFinal, $outputWithEffect, $videoEffect, $timestamp)) {
                                // Se l'effetto è stato applicato con successo, usa il nuovo file
                                if (file_exists($outputWithEffect) && filesize($outputWithEffect) > 0) {
                                    // Verifica che il file ha dimensioni adeguate prima di eliminare l'originale
                                    if (file_exists($outputFinal)) {
                                        unlink($outputFinal); // Rimuovi il file senza effetto
                                    }
                                    $outputFinal = $outputWithEffect;
                                    echo "✅ Effetto applicato con successo<br>";
                                } else {
                                    echo "⚠️ Il file con effetto risulta danneggiato, si utilizza l'originale<br>";
                                }
                            } else {
                                echo "⚠️ Non è stato possibile applicare l'effetto video.<br>";
                            }
                        }
                    }
                 }
                    
                    // Applica audio di sottofondo se richiesto
                    if (!empty($audioCategory)) {
                        echo "🔊 Aggiunta audio di sottofondo " . ucfirst($audioCategory) . " (non interrompe il parlato)...<br>";
                        
                        $result = safeApplyBackgroundAudio($outputFinal, $audioCategory, $audioVolume, $timestamp);
                        
                        if ($result && file_exists($result)) {
                            // Se l'audio è stato applicato con successo, usa il nuovo file
                            if (file_exists($outputFinal)) {
                                unlink($outputFinal); // Rimuovi il file senza audio
                            }
                            $outputFinal = $result;
                            echo "✅ Audio aggiunto con successo (ottimizzato per non coprire il parlato)<br>";
                         } else {
                            echo "⚠️ Non è stato possibile aggiungere l'audio di sottofondo.<br>";
                        }
                    }
                    ?>
                    
    <?php if (function_exists('getPrivacyPolicyHtml')): ?>
    <div class="privacy-info">
        <?php echo getPrivacyPolicyHtml(); ?>
    </div>
    <?php endif; ?>
    
    <!-- Debug button -->
    <button id="show-debug-btn" class="show-debug">Mostra informazioni di debug</button>
    
    <!-- Debug info section -->
    <div id="debug-info" class="debug-info">
        <h3>Informazioni di Debug</h3>
        <p>Versione sistema: 1.2.0 (Ottimizzato)</p>
        <?php 
        $deps = checkDependencies();
        echo "<p>FFmpeg: " . ($deps['ffmpeg'] ? "✅ Disponibile" : "❌ Non disponibile") . "</p>";
        
        $logDir = 'logs';
        if (file_exists($logDir)) {
            $logFiles = glob("$logDir/debug_*.log");
            if (!empty($logFiles)) {
                $latestLog = end($logFiles);
                echo "<p>Ultimo log: " . basename($latestLog) . "</p>";
                
                if (file_exists($latestLog)) {
                    $logContent = file_get_contents($latestLog);
                    $logLines = array_slice(explode("\n", $logContent), -15); // Mostra ultime 15 righe
                    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 4px; max-height: 200px; overflow-y: auto; font-size: 12px;'>";
                    foreach ($logLines as $line) {
                        echo htmlspecialchars($line) . "\n";
                    }
                    echo "</pre>";
                }
            }
        }
        
        // Check for OpenCV
        echo "<p>OpenCV (per privacy volti): " . (isOpenCVAvailable() ? "✅ Disponibile" : "❌ Non disponibile") . "</p>";
        
        // Temp directory status
        $tempDir = getConfig('paths.temp', 'temp');
        $tempFiles = 0;
        $tempSize = 0;
        if (file_exists($tempDir)) {
            $tempIterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($tempIterator as $file) {
                if (!$file->isDir()) {
                    $tempFiles++;
                    $tempSize += $file->getSize();
                }
            }
        }
        echo "<p>File temporanei: $tempFiles (" . round($tempSize / (1024*1024), 2) . " MB)</p>";
        
        // Output directory status
        $outputDir = getConfig('paths.uploads', 'uploads');
        $outputFiles = 0;
        $outputSize = 0;
        if (file_exists($outputDir)) {
            $outputIterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($outputDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($outputIterator as $file) {
               if (!$file->isDir()) {
                    $outputFiles++;
                    $outputSize += $file->getSize();
                }
            }
        }
        echo "<p>File di output: $outputFiles (" . round($outputSize / (1024*1024), 2) . " MB)</p>";
        ?>
        
        <h4>Diagnostica FFmpeg</h4>
        <?php
        // Esegui un test base di FFmpeg
        $testCmd = "ffmpeg -version";
        exec($testCmd, $testOutput, $testReturnCode);
        echo "<p>Test FFmpeg: " . ($testReturnCode === 0 ? "✅ Successo" : "❌ Fallito") . "</p>";
        if ($testReturnCode === 0) {
            echo "<p>Versione FFmpeg: " . htmlspecialchars($testOutput[0]) . "</p>";
        }
        
        // Verifica i codec disponibili
        $codecCmd = "ffmpeg -codecs | grep -E 'libx264|aac'";
        exec($codecCmd, $codecOutput, $codecReturnCode);
        echo "<p>Codec richiesti: " . ($codecReturnCode === 0 && !empty($codecOutput) ? "✅ Disponibili" : "⚠️ Verifica necessaria") . "</p>";
        
        // Verifica i filtri disponibili
        $filterCmd = "ffmpeg -filters | grep -E 'volume|amix|fade'";
        exec($filterCmd, $filterOutput, $filterReturnCode);
        echo "<p>Filtri richiesti: " . ($filterReturnCode === 0 && !empty($filterOutput) ? "✅ Disponibili" : "⚠️ Verifica necessaria") . "</p>";
        ?>
        
        <h4>Test Risoluzione Problemi</h4>
        <p>Se hai problemi con l'aggiunta di audio o effetti, prova questi comandi:</p>
        <ul>
            <li>Verifica installazione FFmpeg: <code>ffmpeg -version</code></li>
            <li>Verifica supporto libx264: <code>ffmpeg -codecs | grep libx264</code></li>
            <li>Verifica supporto AAC: <code>ffmpeg -codecs | grep aac</code></li>
            <li>Per problemi audio: <code>ffmpeg -i video.mp4 -stream_loop -1 -i audio.mp3 -filter_complex "[1:a]volume=0.3[music];[0:a][music]amix=inputs=2:duration=first" -c:v copy -c:a aac -b:a 128k -shortest output.mp4</code></li>
            <li>Per problemi effetti: <code>ffmpeg -i video.mp4 -vf "colorbalance=rs=0.1:gs=0.05:bs=-0.1" -c:v libx264 -preset ultrafast -crf 28 -c:a copy output.mp4</code></li>
        </ul>
        
        <?php if (function_exists('diagnosticFacePrivacy')): ?>
        <a href="diagnostica.php" target="_blank" style="display: inline-block; background: #2196F3; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; margin-top: 10px; font-size: 14px;">Esegui diagnostica completa</a>
        <?php endif; ?>
    </div>
</body>
</html>
                    
