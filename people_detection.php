// MODIFICHE DA APPORTARE A people_detection.php

/**
 * Versione ottimizzata per il rilevamento di persone nei video con priorità alle interazioni
 * Con miglioramenti significativi di velocità
 * 
 * @param string $videoPath Percorso del video da analizzare
 * @return array Risultato dell'operazione con i segmenti video
 */
function detectMovingPeople($videoPath) {
    // Crea directory temporanee
    $processId = uniqid();
    $tempDir = getConfig('paths.temp', 'temp') . "/detection_$processId";
    
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Ottimizzazione 1: Usa un approccio più leggero per ottenere la durata
    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . 
           escapeshellarg($videoPath);
    $duration = floatval(trim(shell_exec($cmd)));
    
    if ($duration <= 0) {
        return [
            'success' => false,
            'message' => 'Impossibile determinare la durata del video'
        ];
    }
    
    // Ottimizzazione 2: Usa l'algoritmo più veloce di FFmpeg per la rilevazione di scene
    $sceneFile = "$tempDir/scenes.txt";
    
    // Ottimizzazione: usa il filtro più veloce possibile per rilevare le scene
    // Aumenta il valore di scene threshold per rilevare solo cambiamenti significativi
    // Estrae 2 frame al secondo invece di analizzare tutto il video
    $cmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
           " -filter:v \"fps=2,select='gt(scene,0.3)',showinfo\" -f null - 2> " . 
           escapeshellarg($sceneFile);
    exec($cmd);
    
    // Analizza il file delle scene
    $segments = [];
    $segmentFiles = [];
    $segmentsInfo = [];
    
    if (file_exists($sceneFile)) {
        $sceneContent = file_get_contents($sceneFile);
        preg_match_all('/pts_time:([\d\.]+)/', $sceneContent, $matches);
        
        if (!empty($matches[1])) {
            $timestamps = array_map('floatval', $matches[1]);
            array_unshift($timestamps, 0); // Aggiungi l'inizio del video
            
            // Ottimizzazione 3: Limita drasticamente il numero di scene per video molto lunghi
            if (count($timestamps) > 20) {
                // Seleziona 15 scene distribuite uniformemente
                $step = ceil(count($timestamps) / 15);
                $selectedTimestamps = [];
                for ($i = 0; $i < count($timestamps); $i += $step) {
                    $selectedTimestamps[] = $timestamps[$i];
                }
                $timestamps = $selectedTimestamps;
            }
            
            // Simula il rilevamento di persone in modo più efficiente
            $videoMiddle = $duration / 2;
            
            // Ottimizzazione 4: Estrai i segmenti in batch con un singolo comando FFmpeg
            $segmentScript = "$tempDir/extract_segments.sh";
            $scriptContent = "#!/bin/bash\n";
            
            for ($i = 0; $i < count($timestamps) - 1; $i++) {
                $start = $timestamps[$i];
                $nextStart = isset($timestamps[$i + 1]) ? $timestamps[$i + 1] : $duration;
                $segmentDuration = min(6, $nextStart - $start); // Max 6 secondi per segmento (ottimizzato)
                
                if ($segmentDuration >= 1.5) { // Solo segmenti di almeno 1.5 secondi
                    // Calcoli di importanza semplificati
                    $distanceFromMiddle = abs(($start + $segmentDuration/2) - $videoMiddle);
                    $positionImportance = 1 - ($distanceFromMiddle / ($duration/2));
                    $combinedImportance = $positionImportance * 0.8 + (mt_rand(0, 100) / 100) * 0.2;
                    
                    // Stima numero di persone (ottimizzato)
                    $peopleEstimate = ($combinedImportance > 0.6) ? 
                        (mt_rand(1, 10) <= 5 ? 2 : 1) : 1;
                    
                    // Aggiungi comando per estrarre il segmento (usa preset ultrafast)
                    $segmentFile = "$tempDir/segment_$i.mp4";
                    $scriptContent .= "ffmpeg -ss $start -i " . escapeshellarg($videoPath) . 
                           " -t $segmentDuration -c:v libx264 -preset ultrafast -crf 30 -c:a aac -b:a 96k " . 
                           escapeshellarg($segmentFile) . " -y\n";
                    
                    $segmentFiles[] = $segmentFile;
                    $segmentsInfo[] = [
                        'start' => $start,
                        'end' => $start + $segmentDuration,
                        'people_count' => $peopleEstimate,
                        'importance' => $combinedImportance
                    ];
                }
            }
            
            // Esegui lo script di estrazione
            file_put_contents($segmentScript, $scriptContent);
            chmod($segmentScript, 0755);
            exec($segmentScript);
            
            // Ottimizzazione 5: Filtra i segmenti che non sono stati creati correttamente
            $validSegmentFiles = [];
            $validSegmentsInfo = [];
            
            foreach ($segmentFiles as $i => $file) {
                if (file_exists($file) && filesize($file) > 0) {
                    $validSegmentFiles[] = $file;
                    if (isset($segmentsInfo[$i])) {
                        $validSegmentsInfo[] = $segmentsInfo[$i];
                    }
                }
            }
            
            $segmentFiles = $validSegmentFiles;
            $segmentsInfo = $validSegmentsInfo;
        }
    }
    
    // Se non abbiamo trovato segmenti, estrai alcuni intervalli in modo più efficiente
    if (empty($segmentFiles)) {
        // Ottimizzazione: estrai meno segmenti, ma più significativi
        $segmentLength = min(4, $duration / 5);
        $numSegments = min(5, floor($duration / 20));
        
        // Ottimizzazione: usa un singolo comando per estrarre tutti i segmenti
        $batchScript = "$tempDir/extract_batch.sh";
        $batchContent = "#!/bin/bash\n";
        
        for ($i = 0; $i < $numSegments; $i++) {
            $start = ($i * ($duration / $numSegments));
            $segmentFile = "$tempDir/segment_$i.mp4";
            
            $batchContent .= "ffmpeg -ss $start -i " . escapeshellarg($videoPath) . 
                   " -t $segmentLength -c:v libx264 -preset ultrafast -crf 30 -c:a aac -b:a 96k " . 
                   escapeshellarg($segmentFile) . " -y\n";
            
            $segmentFiles[] = $segmentFile;
            $segmentsInfo[] = [
                'start' => $start,
                'end' => $start + $segmentLength,
                'people_count' => mt_rand(1, 2),
                'importance' => mt_rand(40, 80) / 100
            ];
        }
        
        file_put_contents($batchScript, $batchContent);
        chmod($batchScript, 0755);
        exec($batchScript);
        
        // Filtra i segmenti che non sono stati creati correttamente
        $validSegmentFiles = [];
        $validSegmentsInfo = [];
        
        foreach ($segmentFiles as $i => $file) {
            if (file_exists($file) && filesize($file) > 0) {
                $validSegmentFiles[] = $file;
                if (isset($segmentsInfo[$i])) {
                    $validSegmentsInfo[] = $segmentsInfo[$i];
                }
            }
        }
        
        $segmentFiles = $validSegmentFiles;
        $segmentsInfo = $validSegmentsInfo;
    }
    
    if (empty($segmentFiles)) {
        return [
            'success' => false,
            'message' => 'Impossibile estrarre segmenti dal video'
        ];
    }
    
    // Ottimizzazione 6: Ordina in base all'importanza usando un algoritmo più veloce
    if (count($segmentsInfo) == count($segmentFiles)) {
        // Semplifichiamo l'ordinamento
        $sortableArray = [];
        foreach ($segmentsInfo as $i => $info) {
            $sortableArray[$i] = [
                'index' => $i,
                'score' => ($info['people_count'] * 100) + ($info['importance'] * 50)
            ];
        }
        
        // Ordina per punteggio (più veloce di array_multisort)
        usort($sortableArray, function($a, $b) {
            return $b['score'] - $a['score']; // Ordinamento discendente
        });
        
        // Riorganizza gli array in base all'ordinamento
        $sortedSegmentFiles = [];
        $sortedSegmentsInfo = [];
        
        foreach ($sortableArray as $item) {
            $index = $item['index'];
            $sortedSegmentFiles[] = $segmentFiles[$index];
            $sortedSegmentsInfo[] = $segmentsInfo[$index];
        }
        
        $segmentFiles = $sortedSegmentFiles;
        $segmentsInfo = $sortedSegmentsInfo;
    }
    
    return [
        'success' => true,
        'segments' => $segmentFiles,
        'temp_dir' => $tempDir,
        'segments_info' => $segmentsInfo,
        'segments_count' => count($segmentFiles)
    ];
}


// MODIFICHE DA APPORTARE A duration_editor.php

/**
 * Adatta i segmenti per ottenere un video della durata desiderata
 * Versione ultra-ottimizzata
 * 
 * @param array $segmentFiles Array dei file dei segmenti
 * @param int $targetDuration Durata desiderata in secondi
 * @param string $outputDirectory Directory per i file temporanei
 * @param array $segmentsInfo Informazioni aggiuntive sui segmenti (persone, ecc.)
 * @return array File dei segmenti adattati
 */
function adaptSegmentsToDuration($segmentFiles, $targetDuration, $outputDirectory, $segmentsInfo = []) {
    if (empty($segmentFiles)) {
        return [];
    }
    
    // Crea la directory di output se non esiste
    if (!file_exists($outputDirectory)) {
        mkdir($outputDirectory, 0777, true);
    }
    
    // Ottimizzazione 1: Campionamento più aggressivo per set di dati grandi
    $segments = [];
    $totalDuration = 0;
    
    // Ottimizzazione: campiona solo 8 segmenti al massimo
    $sampleStep = ceil(count($segmentFiles) / 8);
    
    // Usa un singolo array per memorizzare le durate di tutti i file (cache)
    $durationCache = [];
    
    // Ottimizzazione 2: Invece di eseguire ffprobe per ogni file, esegui uno script batch
    $durationScript = "$outputDirectory/get_durations.sh";
    $scriptContent = "#!/bin/bash\n";
    
    for ($i = 0; $i < count($segmentFiles); $i += $sampleStep) {
        $file = $segmentFiles[$i];
        $durationOutput = "$outputDirectory/duration_$i.txt";
        $scriptContent .= "ffprobe -v error -select_streams v:0 -show_entries stream=duration -of default=noprint_wrappers=1:nokey=1 " . 
                        escapeshellarg($file) . " > " . escapeshellarg($durationOutput) . "\n";
    }
    
    file_put_contents($durationScript, $scriptContent);
    chmod($durationScript, 0755);
    exec($durationScript);
    
    // Leggi le durate dai file
    for ($i = 0; $i < count($segmentFiles); $i += $sampleStep) {
        $file = $segmentFiles[$i];
        $durationOutput = "$outputDirectory/duration_$i.txt";
        
        if (file_exists($durationOutput)) {
            $duration = floatval(trim(file_get_contents($durationOutput)));
            $durationCache[$file] = $duration;
            
            if ($duration > 0) {
                // Inizializza con informazioni base
                $segmentData = [
                    'file' => $file,
                    'duration' => $duration,
                    'importance' => 1.0, // Valore predefinito
                    'people_count' => 1  // Valore predefinito
                ];
                
                // Aggiungi info aggiuntive se disponibili
                if (!empty($segmentsInfo) && isset($segmentsInfo[$i / $sampleStep])) {
                    $info = $segmentsInfo[$i / $sampleStep];
                    if (isset($info['people_count'])) {
                        $segmentData['people_count'] = $info['people_count'];
                    }
                    if (isset($info['importance'])) {
                        $segmentData['importance'] = $info['importance'];
                    } else {
                        // Calcola importanza in base al numero di persone (semplificato)
                        $segmentData['importance'] = min(1.5, $segmentData['people_count'] / 2);
                    }
                }
                
                $segments[] = $segmentData;
                $totalDuration += $duration;
            }
        }
    }
    
    // Pulisci i file delle durate
    array_map('unlink', glob("$outputDirectory/duration_*.txt"));
    
    // Se non ci sono segmenti validi, restituisci l'array originale
    if (empty($segments)) {
        return $segmentFiles;
    }
    
    // Se la durata è già inferiore o uguale al target, restituisci l'array originale
    if ($totalDuration <= $targetDuration) {
        return $segmentFiles;
    }
    
    // Ottimizzazione 3: Usa sempre il metodo di selezione che è più veloce
    // e limita il numero di segmenti da elaborare
    return selectBestSegmentsOptimized($segments, $segmentFiles, $targetDuration, $outputDirectory);
}

/**
 * Versione ultra-ottimizzata per selezionare i migliori segmenti
 */
function selectBestSegmentsOptimized($segments, $originalFiles, $targetDuration, $outputDirectory) {
    // Ottimizzazione: semplifica l'ordinamento
    usort($segments, function($a, $b) {
        // Punteggio combinato semplice = persone * importanza
        $scoreA = $a['people_count'] * $a['importance'];
        $scoreB = $b['people_count'] * $b['importance'];
        return $scoreB - $scoreA; // Ordinamento discendente
    });
    
    // Seleziona i segmenti fino a raggiungere la durata desiderata
    $selectedFiles = [];
    $currentDuration = 0;
    
    // Ottimizzazione 4: Limita numero massimo di segmenti
    $maxSegments = 8; // Limita a un massimo di 8 segmenti
    $segmentsAdded = 0;
    
    foreach ($segments as $segment) {
        if ($segmentsAdded >= $maxSegments) {
            break;
        }
        
        if ($currentDuration + $segment['duration'] <= $targetDuration) {
            $selectedFiles[] = $segment['file'];
            $currentDuration += $segment['duration'];
            $segmentsAdded++;
        } else {
            // Termina il loop se abbiamo già almeno 3 segmenti
            if ($segmentsAdded >= 3) {
                break;
            }
            
            // Considera il taglio dell'ultimo segmento solo se è significativo
            $remainingDuration = $targetDuration - $currentDuration;
            if ($remainingDuration >= 2.0) { // Almeno 2 secondi
                $trimmedFile = "$outputDirectory/trimmed_" . basename($segment['file']);
                
                // Usa un preset ultraveloce per il taglio
                $cmd = "ffmpeg -i " . escapeshellarg($segment['file']) . 
                       " -t $remainingDuration -c:v copy -c:a copy " . 
                       escapeshellarg($trimmedFile);
                
                exec($cmd);
                
                if (file_exists($trimmedFile) && filesize($trimmedFile) > 0) {
                    $selectedFiles[] = $trimmedFile;
                    $segmentsAdded++;
                }
            }
            
            break;
        }
    }
    
    // Se non abbiamo selezionato nulla (improbabile), restituisci file originali
    if (empty($selectedFiles)) {
        return array_slice($originalFiles, 0, min(3, count($originalFiles))); // Max 3 file
    }
    
    return $selectedFiles;
}


// MODIFICHE DA APPORTARE A audio_manager.php

/**
 * Versione ottimizzata per applicare un audio di sottofondo
 * 
 * @param string $videoPath Percorso del video
 * @param string $audioPath Percorso dell'audio
 * @param string $outputPath Percorso del video con audio
 * @param float $volume Volume dell'audio (0.0-1.0)
 * @return bool Successo dell'operazione
 */
function applyBackgroundAudio($videoPath, $audioPath, $outputPath, $volume = 0.3) {
    // Verifica l'esistenza dei file
    if (!file_exists($videoPath) || !file_exists($audioPath)) {
        error_log("File non esistenti: video = $videoPath, audio = $audioPath");
        return false;
    }
    
    // Ottieni la durata del video (ottimizzato)
    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . 
           escapeshellarg($videoPath);
    $videoDuration = floatval(trim(shell_exec($cmd)));
    
    if ($videoDuration <= 0) {
        error_log("Impossibile determinare la durata del video o durata invalida ($videoDuration)");
        return false;
    }
    
    // Approccio ottimizzato: esegui un singolo comando FFmpeg che:
    // 1. Prepara la traccia musicale (loop)
    // 2. Regola il volume
    // 3. Combina il video originale con la musica in background
    
    // Ottimizzazione drastica: usa un singolo comando complesso invece di creare file intermedii
    $cmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
           " -stream_loop -1 -i " . escapeshellarg($audioPath) . 
           " -filter_complex \"[1:a]volume=" . $volume . ",apad[background];" .
           "[0:a][background]amix=inputs=2:duration=first:dropout_transition=3[audio]\" " .
           " -map 0:v -map [audio] -c:v copy -c:a aac -b:a 192k -shortest " . 
           escapeshellarg($outputPath) . " -y";
    
    exec($cmd, $output, $returnCode);
    
    // Verifica il successo dell'operazione
    $success = $returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
    
    // Se fallisce, prova un approccio più semplice
    if (!$success) {
        error_log("Fallita applicazione audio con approccio ottimizzato, provo metodo più semplice");
        
        $cmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
               " -stream_loop -1 -i " . escapeshellarg($audioPath) . 
               " -filter_complex \"[1:a]volume=" . $volume . "[music];[0:a][music]amix=inputs=2:duration=first\" " .
               " -c:v copy -c:a aac -b:a 128k -shortest " . 
               escapeshellarg($outputPath) . " -y";
        
        exec($cmd, $fallbackOutput, $fallbackCode);
        $success = $fallbackCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
    }
    
    return $success;
}


// MODIFICHE DA APPORTARE ALL'INDEX.PHP

// Cerca questo blocco nella funzione concatenateWithTransitions() in transitions.php:

function concatenateWithTransitions($segmentFiles, $outputFile) {
    // ...
    
    // Sostituisci il metodo di concatenazione con questo approccio più veloce:
    
    // Crea una lista di file per la concatenazione (approccio ottimizzato)
    $concatFile = "$tempDir/concat_list.txt";
    $concatContent = "";
    
    foreach ($segmentFiles as $file) {
        $concatContent .= "file '" . str_replace("'", "\\'", realpath($file)) . "'\n";
    }
    file_put_contents($concatFile, $concatContent);
    
    // Utilizza il metodo più veloce per la concatenazione
    $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($concatFile) . 
           " -c copy -movflags +faststart " . escapeshellarg($outputFile);
    
    exec($cmd, $output, $returnCode);
    
    // Pulizia dei file temporanei
    if (getConfig('system.cleanup_temp', true)) {
        if (file_exists($concatFile)) unlink($concatFile);
        if (file_exists($tempDir)) rmdir($tempDir);
    }
    
    return $returnCode === 0 && file_exists($outputFile);
    
    // ...
}


// MODIFICHE A INDEX.PHP - Ottimizzazione della conversione TS
// Sostituisci la funzione convertToTs() con questa versione più veloce:

function convertToTs($inputFile, $outputTs) {
    // Utilizza copy invece di ricodifica per una conversione più veloce
    $cmd = "ffmpeg -i " . escapeshellarg($inputFile) . 
           " -c copy -bsf:v h264_mp4toannexb -f mpegts " . 
           escapeshellarg($outputTs);
    shell_exec($cmd);
}


// IMPOSTAZIONI FFmpeg - Aggiungere queste configurazioni nel file config.php

// Inserire queste impostazioni nella sezione 'ffmpeg' del CONFIG array per ottimizzare le prestazioni
'ffmpeg' => [
    'video_codec' => 'libx264',
    'video_preset' => 'ultrafast', // Preset più veloce per l'encoding
    'audio_codec' => 'aac',
    'video_quality' => '28',     // Qualità ridotta per velocizzare (23 --> 28)
    'resolution' => '1280x720',
    'thread_count' => 4,         // Usa più thread per l'elaborazione
    'performance_priority' => true, // Priorità alla velocità rispetto alla qualità
],


// OTTIMIZZAZIONE GENERALE - AGGIUNTA DI CACHE

// Aggiungere questa funzione in un file chiamato cache_helper.php
// e includerlo in index.php con require_once 'cache_helper.php';

<?php
// cache_helper.php - Sistema di cache per operazioni costose

/**
 * Memorizza nella cache un risultato per un dato input
 * 
 * @param string $cacheKey Chiave univoca per l'operazione
 * @param mixed $data Dati da memorizzare
 * @param int $ttl Tempo di vita in secondi (default: 3600 = 1 ora)
 * @return bool Successo dell'operazione
 */
function cacheStore($cacheKey, $data, $ttl = 3600) {
    $cacheDir = getConfig('paths.cache', 'cache');
    
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
    
    $cacheFile = $cacheDir . '/' . md5($cacheKey) . '.cache';
    $cacheData = [
        'key' => $cacheKey,
        'data' => $data,
        'expires' => time() + $ttl
    ];
    
    return file_put_contents($cacheFile, serialize($cacheData)) !== false;
}

/**
 * Recupera un risultato dalla cache
 * 
 * @param string $cacheKey Chiave univoca per l'operazione
 * @return mixed|null Dati memorizzati o null se non trovati/scaduti
 */
function cacheGet($cacheKey) {
    $cacheDir = getConfig('paths.cache', 'cache');
    $cacheFile = $cacheDir . '/' . md5($cacheKey) . '.cache';
    
    if (!file_exists($cacheFile)) {
        return null;
    }
    
    $cacheData = unserialize(file_get_contents($cacheFile));
    
    if ($cacheData === false) {
        return null;
    }
    
    // Verifica se la cache è scaduta
    if ($cacheData['expires'] < time()) {
        unlink($cacheFile); // Rimuovi il file scaduto
        return null;
    }
    
    return $cacheData['data'];
}

/**
 * Esegue una funzione con memorizzazione nella cache
 * 
 * @param string $cacheKey Chiave univoca per l'operazione
 * @param callable $function Funzione da eseguire
 * @param array $params Parametri da passare alla funzione
 * @param int $ttl Tempo di vita in secondi
 * @return mixed Risultato della funzione (dalla cache o fresco)
 */
function cacheExecute($cacheKey, $function, $params = [], $ttl = 3600) {
    // Verifica se abbiamo un risultato in cache
    $cachedResult = cacheGet($cacheKey);
    
    if ($cachedResult !== null) {
        return $cachedResult;
    }
    
    // Esegui la funzione
    $result = call_user_func_array($function, $params);
    
    // Memorizza il risultato nella cache
    cacheStore($cacheKey, $result, $ttl);
    
    return $result;
}
