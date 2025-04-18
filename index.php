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

// Aggiungi una funzione di debug per tracciare l'esecuzione
function debugLog($message, $level = 'info') {
    $logDir = 'logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/debug_' . date('Ymd') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp][$level] $message\n";
    
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    
    // Anche mostrare in pagina se richiesto
    if (getConfig('system.debug', false) || $level === 'error') {
        echo "<small style='color: " . ($level === 'error' ? 'red' : '#666') . ";'>Debug: $message</small><br>";
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
    // Utilizza copy invece di ricodifica per una conversione più veloce
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
                    trackFile($tsFile, basename($segment), 'processing');
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
                
                    // ASSICURATI CHE QUESTA SEZIONE CHE APPLICA EFFETTI SIA ESEGUITA CORRETTAMENTE
                    // ===========================================================================
                    
                    // Applica effetto video se richiesto
                    if (!empty($videoEffect)) {
                        $effects = getVideoEffects();
                        if (isset($effects[$videoEffect])) {
                            echo "🎨 Applicazione effetto " . $effects[$videoEffect]['name'] . "...<br>";
                            $outputWithEffect = getConfig('paths.uploads', 'uploads') . '/video_effect_' . $timestamp . '.mp4';
                            
                            // Verifica esistenza e dimensione del file di input
                            if (!file_exists($outputFinal) || filesize($outputFinal) <= 0) {
                                echo "⚠️ File di input per l'effetto non valido o vuoto.<br>";
                                debugLog("File input effetto non valido: $outputFinal", "error");
                                // Salta l'applicazione dell'effetto
                            } else {
                                // Debug dei comandi
                                debugLog("Applicazione effetto su file " . filesize($outputFinal) . " bytes");
                                
                                if (applyVideoEffect($outputFinal, $outputWithEffect, $videoEffect)) {
                                    // Se l'effetto è stato applicato con successo, usa il nuovo file
                                    if (file_exists($outputWithEffect) && filesize($outputWithEffect) > 0) {
                                        // Verifica che il file ha dimensioni adeguate prima di eliminare l'originale
                                        unlink($outputFinal); // Rimuovi il file senza effetto
                                        $outputFinal = $outputWithEffect;
                                        echo "✅ Effetto applicato con successo<br>";
                                        debugLog("Effetto applicato: $outputWithEffect (" . filesize($outputWithEffect) . " bytes)");
                                    } else {
                                        echo "⚠️ Il file con effetto risulta danneggiato, si utilizza l'originale<br>";
                                        debugLog("File effetto danneggiato: $outputWithEffect", "error");
                                    }
                                } else {
                                    echo "⚠️ Non è stato possibile applicare l'effetto video.<br>";
                                    debugLog("Fallimento applicazione effetto", "error");
                                }
                            }
                        }
                    }
                    
                    // Applica audio di sottofondo se richiesto
                    if (!empty($audioCategory)) {
                        echo "🔊 Aggiunta audio di sottofondo " . ucfirst($audioCategory) . " (non interrompe il parlato)...<br>";
                        $audio = getRandomAudioFromCategory($audioCategory);
                        
                        if ($audio) {
                            // Scarica l'audio se necessario
                            $audioDir = getConfig('paths.temp', 'temp') . '/audio';
                            if (!file_exists($audioDir)) {
                                mkdir($audioDir, 0777, true);
                            }
                            
                            $audioFile = $audioDir . '/' . basename($audio['url']);
                            if (!file_exists($audioFile)) {
                                // Debug download
                                debugLog("Tentativo download audio da " . $audio['url']);
                                
                                $downloadSuccess = downloadAudio($audio['url'], $audioFile);
                                if (!$downloadSuccess) {
                                    echo "⚠️ Impossibile scaricare l'audio.<br>";
                                    debugLog("Errore download audio: " . $audio['url'], "error");
                                    // Usa un audio locale se disponibile
                                    $localAudio = 'assets/audio/default_background.mp3';
                                    if (file_exists($localAudio)) {
                                        $audioFile = $localAudio;
                                        echo "✅ Utilizzo audio di backup locale<br>";
                                        debugLog("Uso audio di backup: $localAudio");
                                    } else {
                                        $audioFile = null;
                                        debugLog("Audio backup non disponibile", "error");
                                    }
                                } else {
                                    debugLog("Audio scaricato: $audioFile (" . filesize($audioFile) . " bytes)");
                                }
                            }
                            
                            // Applica l'audio al video se disponibile
                            if ($audioFile && file_exists($audioFile)) {
                                // Debug file audio
                                debugLog("Audio file: " . $audioFile . " (" . filesize($audioFile) . " bytes)");
                                
                                // Verifica file video
                                if (!file_exists($outputFinal) || filesize($outputFinal) <= 0) {
                                    echo "⚠️ File video non valido per aggiungere audio.<br>";
                                    debugLog("File video non valido per audio: $outputFinal", "error");
                                } else {
                                    debugLog("Video file: " . $outputFinal . " (" . filesize($outputFinal) . " bytes)");
                                    
                                    // METODO ALTERNATIVO: Usa un comando FFmpeg diretto per applicare l'audio
                                    $outputWithAudio = getConfig('paths.uploads', 'uploads') . '/video_audio_' . $timestamp . '.mp4';
                                    $audioCmd = "ffmpeg -i " . escapeshellarg($outputFinal) . 
                                                " -stream_loop -1 -i " . escapeshellarg($audioFile) . 
                                                " -filter_complex \"[1:a]volume=" . $audioVolume . "[music];[0:a][music]amix=inputs=2:duration=first\" " .
                                                " -c:v copy -c:a aac -b:a 128k -shortest " . 
                                                escapeshellarg($outputWithAudio) . " -y";
                                    
                                    debugLog("Esecuzione comando audio: $audioCmd");
                                    exec($audioCmd, $audioOutput, $audioReturnCode);
                                    
                                    if ($audioReturnCode === 0 && file_exists($outputWithAudio) && filesize($outputWithAudio) > 0) {
                                        // Se l'audio è stato applicato con successo, usa il nuovo file
                                        unlink($outputFinal); // Rimuovi il file senza audio
                                        $outputFinal = $outputWithAudio;
                                        echo "✅ Audio aggiunto: " . $audio['name'] . " (ottimizzato per non coprire il parlato)<br>";
                                        debugLog("Audio aggiunto con successo: $outputWithAudio (" . filesize($outputWithAudio) . " bytes)");
                                    } else {
                                        echo "⚠️ Non è stato possibile aggiungere l'audio con il comando diretto, provo metodo alternativo.<br>";
                                        debugLog("Comando audio fallito, codice: $audioReturnCode", "error");
                                        
                                        // Prova con la funzione originale come fallback
                                        if (applyBackgroundAudio($outputFinal, $audioFile, $outputWithAudio, $audioVolume)) {
                                            // Verifica che il file esista e abbia dimensioni
                                            if (file_exists($outputWithAudio) && filesize($outputWithAudio) > 0) {
                                                // Se l'audio è stato applicato con successo, usa il nuovo file
                                                unlink($outputFinal); // Rimuovi il file senza audio
                                                $outputFinal = $outputWithAudio;
                                                echo "✅ Audio aggiunto: " . $audio['name'] . " (metodo alternativo)<br>";
                                                debugLog("Audio aggiunto con metodo alternativo: $outputWithAudio");
                                            } else {
                                                echo "⚠️ File con audio danneggiato, si utilizza l'originale<br>";
                                                debugLog("File audio danneggiato: $outputWithAudio", "error");
                                            }
                                        } else {
                                            echo "⚠️ Non è stato possibile aggiungere l'audio.<br>";
                                            debugLog("Fallimento aggiunta audio", "error");
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Aggiungi privacy dei volti se richiesta
                    if ($applyFacePrivacy) {
                        // Verifica se OpenCV è disponibile
                        $opencvAvailable = isOpenCVAvailable();
                        
                        if ($opencvAvailable) {
                            echo "😊 Applicazione emoji sorridenti sui volti per privacy...<br>";
                            $outputWithFacePrivacy = getConfig('paths.uploads', 'uploads') . '/video_privacy_' . $timestamp . '.mp4';
                            
                            if (file_exists($outputFinal) && filesize($outputFinal) > 0) {
                                debugLog("Applying face privacy to " . $outputFinal . " (" . filesize($outputFinal) . " bytes)");
                                
                                if (applyFacePrivacy($outputFinal, $outputWithFacePrivacy, $excludeYellowVests)) {
                                    if (file_exists($outputWithFacePrivacy) && filesize($outputWithFacePrivacy) > 0) {
                                        unlink($outputFinal); // Rimuovi il file senza privacy
                                        $outputFinal = $outputWithFacePrivacy;
                                        echo "✅ Privacy dei volti applicata";
                                        if ($excludeYellowVests) {
                                            echo " (escluse persone con pettorine gialle)";
                                        }
                                        echo "<br>";
                                        debugLog("Privacy volti applicata: $outputWithFacePrivacy (" . filesize($outputWithFacePrivacy) . " bytes)");
                                    } else {
                                        echo "⚠️ File con privacy volti danneggiato, si utilizza l'originale<br>";
                                        debugLog("File privacy danneggiato: $outputWithFacePrivacy", "error");
                                    }
                                } else {
                                    echo "⚠️ Non è stato possibile applicare la privacy dei volti.<br>";
                                    debugLog("Fallimento applicazione privacy volti", "error");
                                }
                            } else {
                                echo "⚠️ File video non valido per applicare privacy volti.<br>";
                                debugLog("File video non valido per privacy: $outputFinal", "error");
                            }
                        } else {
                            echo "⚠️ OpenCV non disponibile per la privacy dei volti.<br>";
                            echo "Per utilizzare questa funzione, installa OpenCV per Python:<br>";
                            echo "<code>pip install opencv-python</code><br>";
                            debugLog("OpenCV non disponibile", "error");
                        }
                    }
                    
                    // FINE SEZIONE DA VERIFICARE
                    // ===========================================================================
                    
                    // Traccia il file finale
                    trackFile($outputFinal, 'video_finale.mp4', 'output');
                    
                    // Genera una miniatura per il video finale (ottimizzato)
                    $thumbnailPath = getConfig('paths.uploads', 'uploads') . '/thumbnail_' . $timestamp . '.jpg';
                    $thumbnailCmd = "ffmpeg -ss 00:00:03 -i " . escapeshellarg($outputFinal) . " -vframes 1 -q:v 2 " . escapeshellarg($thumbnailPath);
                    shell_exec($thumbnailCmd);
                    
                    echo "<br>🎉 <strong>Montaggio completato in $concatenation_time secondi!</strong><br><br>";
                    
                    echo "<div style='display echo "<div style='display: flex; align-items: center; gap: 20px;'>";
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
                }
            } else {
                echo "<br>⚠️ <strong>Nessun segmento valido</strong> da unire nel video finale.";
                debugLog("Nessun segmento valido da unire", "error");
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
            $concatResult = concatenateTsFiles($uploaded_ts_files, $outputFinal);
            
            if (!$concatResult || !file_exists($outputFinal) || filesize($outputFinal) <= 0) {
                echo "❌ <strong>Errore nella creazione del video finale.</strong><br>";
                debugLog("Errore concatenazione modalità semplice: $outputFinal", "error");
            } else {
                debugLog("Video finale creato (modalità semplice): $outputFinal (" . filesize($outputFinal) . " bytes)");
                
                // Applica effetto video se richiesto
                if (!empty($videoEffect)) {
                    $effects = getVideoEffects();
                    if (isset($effects[$videoEffect])) {
                        echo "🎨 Applicazione effetto " . $effects[$videoEffect]['name'] . "...<br>";
                        $outputWithEffect = getConfig('paths.uploads', 'uploads') . '/video_effect_' . $timestamp . '.mp4';
                        
                        debugLog("Tentativo applicazione effetto: $videoEffect");
                        if (applyVideoEffect($outputFinal, $outputWithEffect, $videoEffect)) {
                            // Se l'effetto è stato applicato con successo, usa il nuovo file
                            if (file_exists($outputWithEffect) && filesize($outputWithEffect) > 0) {
                                unlink($outputFinal); // Rimuovi il file senza effetto
                                $outputFinal = $outputWithEffect;
                                echo "✅ Effetto applicato con successo<br>";
                                debugLog("Effetto applicato con successo: $outputWithEffect");
                            } else {
                                echo "⚠️ Il file con effetto risulta danneggiato, si utilizza l'originale<br>";
                                debugLog("File effetto danneggiato: $outputWithEffect", "error");
                            }
                        } else {
                            debugLog("Fallimento applicazione effetto", "error");
                        }
                    }
                }
                
                // Applica audio di sottofondo se richiesto
                if (!empty($audioCategory)) {
                    echo "🔊 Aggiunta audio di sottofondo " . ucfirst($audioCategory) . " (non interrompe il parlato)...<br>";
                    $audio = getRandomAudioFromCategory($audioCategory);
                    
                    if ($audio) {
                        // Scarica l'audio se necessario
                        $audioDir = getConfig('paths.temp', 'temp') . '/audio';
                        if (!file_exists($audioDir)) {
                            mkdir($audioDir, 0777, true);
                        }
                        
                        $audioFile = $audioDir . '/' . basename($audio['url']);
                        if (!file_exists($audioFile)) {
                            debugLog("Tentativo download audio: " . $audio['url']);
                            $downloadSuccess = downloadAudio($audio['url'], $audioFile);
                            if (!$downloadSuccess) {
                                echo "⚠️ Impossibile scaricare l'audio.<br>";
                                debugLog("Errore download audio: " . $audio['url'], "error");
                                // Usa un audio locale se disponibile
                                $localAudio = 'assets/audio/default_background.mp3';
                                if (file_exists($localAudio)) {
                                    $audioFile = $localAudio;
                                    echo "✅ Utilizzo audio di backup locale<br>";
                                    debugLog("Uso audio di backup: $localAudio");
                                } else {
                                    $audioFile = null;
                                    debugLog("Audio backup non disponibile", "error");
                                }
                            } else {
                                debugLog("Audio scaricato con successo: $audioFile");
                            }
                        }
                        
                        // Applica l'audio al video se disponibile
                        if ($audioFile && file_exists($audioFile)) {
                            // METODO ALTERNATIVO: Usa un comando FFmpeg diretto
                            $outputWithAudio = getConfig('paths.uploads', 'uploads') . '/video_audio_' . $timestamp . '.mp4';
                            $audioCmd = "ffmpeg -i " . escapeshellarg($outputFinal) . 
                                        " -stream_loop -1 -i " . escapeshellarg($audioFile) . 
                                        " -filter_complex \"[1:a]volume=" . $audioVolume . "[music];[0:a][music]amix=inputs=2:duration=first\" " .
                                        " -c:v copy -c:a aac -b:a 128k -shortest " . 
                                        escapeshellarg($outputWithAudio) . " -y";
                            
                            debugLog("Esecuzione comando audio: $audioCmd");
                            exec($audioCmd, $audioOutput, $audioReturnCode);
                            
                            if ($audioReturnCode === 0 && file_exists($outputWithAudio) && filesize($outputWithAudio) > 0) {
                                // Se l'audio è stato applicato con successo, usa il nuovo file
                                unlink($outputFinal); // Rimuovi il file senza audio
                                $outputFinal = $outputWithAudio;
                                echo "✅ Audio aggiunto: " . $audio['name'] . " (ottimizzato per non coprire il parlato)<br>";
                                debugLog("Audio aggiunto con successo: $outputWithAudio");
                            } else {
                                echo "⚠️ Non è stato possibile aggiungere l'audio con il comando diretto, provo metodo alternativo.<br>";
                                debugLog("Fallimento comando audio, provo metodo alternativo", "error");
                                
                                // Prova con la funzione originale come fallback
                                if (applyBackgroundAudio($outputFinal, $audioFile, $outputWithAudio, $audioVolume)) {
                                    // Verifica che il file esista e abbia dimensioni
                                    if (file_exists($outputWithAudio) && filesize($outputWithAudio) > 0) {
                                        // Se l'audio è stato applicato con successo, usa il nuovo file
                                        unlink($outputFinal); // Rimuovi il file senza audio
                                        $outputFinal = $outputWithAudio;
                                        echo "✅ Audio aggiunto: " . $audio['name'] . " (metodo alternativo)<br>";
                                        debugLog("Audio aggiunto con metodo alternativo: $outputWithAudio");
                                    } else {
                                        echo "⚠️ File con audio danneggiato, si utilizza l'originale<br>";
                                        debugLog("File audio danneggiato: $outputWithAudio", "error");
                                    }
                                } else {
                                    echo "⚠️ Non è stato possibile aggiungere l'audio.<br>";
                                    debugLog("Fallimento aggiunta audio", "error");
                                }
                            }
                        }
                    }
                }
                
                // Aggiungi privacy dei volti se richiesta
                if ($applyFacePrivacy) {
                    // Verifica se OpenCV è disponibile
                    $opencvAvailable = isOpenCVAvailable();
                    
                    if ($opencvAvailable) {
                        echo "😊 Applicazione emoji sorridenti sui volti per privacy...<br>";
                        $outputWithFacePrivacy = getConfig('paths.uploads', 'uploads') . '/video_privacy_' . $timestamp . '.mp4';
                        
                        if (file_exists($outputFinal) && filesize($outputFinal) > 0) {
                            debugLog("Applicazione privacy volti: $outputFinal");
                            
                            if (applyFacePrivacy($outputFinal, $outputWithFacePrivacy, $excludeYellowVests)) {
                                if (file_exists($outputWithFacePrivacy) && filesize($outputWithFacePrivacy) > 0) {
                                    unlink($outputFinal); // Rimuovi il file senza privacy
                                    $outputFinal = $outputWithFacePrivacy;
                                    echo "✅ Privacy dei volti applicata";
                                    if ($excludeYellowVests) {
                                        echo " (escluse persone con pettorine gialle)";
                                    }
                                    echo "<br>";
                                    debugLog("Privacy volti applicata con successo: $outputWithFacePrivacy");
                                } else {
                                    echo "⚠️ File con privacy volti danneggiato, si utilizza l'originale<br>";
                                    debugLog("File privacy danneggiato: $outputWithFacePrivacy", "error");
                                }
                            } else {
                                echo "⚠️ Non è stato possibile applicare la privacy dei volti.<br>";
                                debugLog("Fallimento applicazione privacy volti", "error");
                            }
                        } else {
                            echo "⚠️ File video non valido per applicare privacy volti.<br>";
                            debugLog("File video non valido per privacy: $outputFinal", "error");
                        }
                    } else {
                        echo "⚠️ OpenCV non disponibile per la privacy dei volti.<br>";
                        echo "Per utilizzare questa funzione, installa OpenCV per Python:<br>";
                        echo "<code>pip install opencv-python</code><br>";
                        debugLog("OpenCV non disponibile", "error");
                    }
                }

                // Mostra il link al video finale
                echo "<br>🎉 <strong>Montaggio completato!</strong>";
                
                echo "<div style='display: flex; align-items: center; gap: 20px; margin-top: 15px;'>";
                
                // Genera una miniatura per il video finale
                $thumbnailPath = getConfig('paths.uploads', 'uploads') . '/thumbnail_' . $timestamp . '.jpg';
                $thumbnailCmd = "ffmpeg -ss 00:00:03 -i " . escapeshellarg($outputFinal) . " -vframes 1 -q:v 2 " . escapeshellarg($thumbnailPath);
                shell_exec($thumbnailCmd);
                
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
            }
            
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
        label input[type="radio"], 
        label input[type="checkbox"] {
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
        .debug-info {
            margin-top: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 0.85em;
            color: #666;
            display: none;
        }
        .show-debug {
            margin-top: 20px;
            font-size: 0.85em;
            color: #666;
            background: none;
            border: none;
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
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
            if (durationSelect) {
                toggleDurationOptions();
                
                // Aggiungi event listener per cambi futuri
                durationSelect.addEventListener('change', toggleDurationOptions);
            }
            
            // Visualizza il valore dello slider del volume
            const volumeSlider = document.querySelector('input[name="audio_volume"]');
            const volumeValue = document.getElementById('volume-value');
            
            if (volumeSlider && volumeValue) {
                // Mostra il valore iniziale
                volumeValue.textContent = volumeSlider.value;
                
                // Aggiorna quando cambia
                volumeSlider.addEventListener('input', function() {
                    volumeValue.textContent = this.value;
                });
            }
            
            // Toggle debug info
            const debugBtn = document.getElementById('show-debug-btn');
            const debugInfo = document.getElementById('debug-info');
            
            if (debugBtn && debugInfo) {
                debugBtn.addEventListener('click', function() {
                    if (debugInfo.style.display === 'none' || !debugInfo.style.display) {
                        debugInfo.style.display = 'block';
                        debugBtn.textContent = 'Nascondi informazioni di debug';
                    } else {
                        debugInfo.style.display = 'none';
                        debugBtn.textContent = 'Mostra informazioni di debug';
                    }
                });
            }
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
                        Rilevamento persone (estrae scene con persone in movimento)
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
                            Interazioni tra persone (priorità a scene con più persone)
                        </label>
                        <label>
                            <input type="radio" name="duration_method" value="select"> 
                            Selezione migliori (scene più importanti)
                        </label>
                        <label>
                            <input type="radio" name="duration_method" value="trim"> 
                            Taglio proporzionale (tutte le scene, ridotte)
                        </label>
                        <label>
                            <input type="radio" name="duration_method" value="speed"> 
                            Modifica velocità (accelera il video)
                        </label>
                    </div>
                </div>
                
                <div class="option-group">
                    <h3>Audio di sottofondo:</h3>
                    <select name="audio_category">
                        <option value="">Nessun audio</option>
                        <option value="emozionale">Emozionale</option>
                        <option value="bambini">Bambini</option>
                        <option value="azione">Azione</option>
                        <option value="relax">Relax</option>
                        <option value="divertimento">Divertimento</option>
                        <option value="vacanze">Vacanze</option>
                    </select>
                    
                    <div style="margin-top: 10px;">
                        <label for="audio_volume">Volume: <span id="volume-value">0.3</span></label>
                        <input type="range" name="audio_volume" min="0.1" max="0.7" step="0.1" value="0.3" class="range-slider">
                    </div>
                </div>
                
                <div class="option-group">
                    <h3>Effetto video:</h3>
                    <select name="video_effect">
                        <option value="">Nessun effetto</option>
                        <option value="vintage">Vintage/Retrò</option>
                        <option value="bianco_nero">Bianco e Nero</option>
                        <option value="caldo">Toni Caldi</option>
                        <option value="freddo">Toni Freddi</option>
                        <option value="dream">Effetto Sogno</option>
                        <option value="cinema">Cinema</option>
                        <option value="hdr">Effetto HDR</option>
                        <option value="brillante">Colori Brillanti</option>
                        <option value="instagram">Instagram Style</option>
                    </select>
                </div>
                
                <div class="option-group">
                    <h3>Privacy dei volti:</h3>
                    <label>
                        <input type="checkbox" name="apply_face_privacy" value="1"> 
                        Applica emoji sorridenti sui volti
                    </label>
                    <label>
                        <input type="checkbox" name="exclude_yellow_vests" value="1" checked> 
                        Escludi persone con pettorine gialle
                    </label>
                </div>
            </div>
            
            <button type="submit">Carica e Monta</button>
        </form>
    </div>
    
    <div class="instructions">
        <h3>📋 Istruzioni</h3>
        <ol>
            <li><strong>Carica i tuoi video</strong> - Seleziona uno o più file video dal tuo dispositivo</li>
            <li><strong>Scegli la modalità</strong> - Concatenazione semplice o rilevamento di scene con persone</li>
            <li><strong>Imposta la durata</strong> - Scegli quanto dovrà durare il video finale (opzionale)</li>
            <li><strong>Personalizza l'audio</strong> - Aggiungi un sottofondo musicale che non interrompe il parlato</li>
            <li><strong>Scegli un effetto</strong> - Applica filtri visivi per migliorare l'aspetto</li>
            <li><strong>Privacy dei volti</strong> - Aggiungi emoji sorridenti sui volti per proteggere la privacy</li>
            <li><strong>Avvia il montaggio</strong> - Clicca su "Carica e Monta" e attendi il completamento</li>
            <li><strong>Scarica il risultato</strong> - Una volta completato, scarica il video finale</li>
        </ol>
        <p><em>Nota: Il rilevamento di persone darà priorità alle scene con più persone insieme, ideale per momenti di interazione.</em></p>
    </div>
    
    <div class="privacy-info">
        <?php echo getPrivacyPolicyHtml(); ?>
    </div>
    
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
                    $logLines = array_slice(explode("\n", $logContent), -15); // Mo
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
        
        <a href="diagnostica.php" target="_blank" style="display: inline-block; background: #2196F3; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; margin-top: 10px; font-size: 14px;">Esegui diagnostica completa</a>
    </div>
</body>
</html>
