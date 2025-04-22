<?php
// debug_utility.php - Funzioni di supporto per debugging e logging

/**
 * Scrive un messaggio di log
 * 
 * @param string $message Il messaggio da loggare
 * @param string $level Livello del log (info, error, warning, debug)
 * @param string $context Contesto del messaggio
 * @return bool Successo dell'operazione
 */
function debugLog($message, $level = "info", $context = "") {
    if (!getConfig('system.debug', false)) {
        return false;
    }
    
    $logDir = 'logs';
    $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
    
    // Crea la directory dei log se non esiste
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    // Formatta il messaggio
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = $context ? "[$context] " : "";
    $formattedMessage = "[$timestamp] [$level] $contextStr$message\n";
    
    // Scrivi il messaggio nel file di log
    return file_put_contents($logFile, $formattedMessage, FILE_APPEND) !== false;
}

/**
 * Monitora l'esecuzione di un comando shell
 * 
 * @param string $cmd Il comando da eseguire
 * @param string $logPrefix Prefisso per i log
 * @return array Risultato dell'esecuzione
 */
function monitorCommand($cmd, $logPrefix = "") {
    debugLog("Esecuzione comando: $cmd", "debug", $logPrefix);
    
    $startTime = microtime(true);
    exec($cmd . " 2>&1", $output, $returnCode);
    $executionTime = round(microtime(true) - $startTime, 2);
    
    $outputSummary = count($output) > 10 
                   ? implode("\n", array_slice($output, 0, 3)) . "\n...\n" . implode("\n", array_slice($output, -3)) 
                   : implode("\n", $output);
    
    if ($returnCode !== 0) {
        debugLog("Errore nell'esecuzione del comando. Codice: $returnCode, Tempo: {$executionTime}s", "error", $logPrefix);
        debugLog("Output: $outputSummary", "error", $logPrefix);
    } else {
        debugLog("Comando eseguito con successo. Tempo: {$executionTime}s", "info", $logPrefix);
        debugLog("Output: $outputSummary", "debug", $logPrefix);
    }
    
    return [
        'success' => $returnCode === 0,
        'returnCode' => $returnCode,
        'output' => $output,
        'executionTime' => $executionTime
    ];
}

/**
 * Analizza un file video e ottiene informazioni dettagliate
 * 
 * @param string $videoPath Percorso del file video
 * @return array Informazioni sul video
 */
function analyzeVideo($videoPath) {
    if (!file_exists($videoPath)) {
        debugLog("File non trovato: $videoPath", "error", "analyzer");
        return [
            'success' => false,
            'message' => 'File non trovato'
        ];
    }
    
    // Verifica la dimensione del file
    $filesize = filesize($videoPath);
    if ($filesize <= 0) {
        debugLog("File vuoto: $videoPath", "error", "analyzer");
        return [
            'success' => false,
            'message' => 'File vuoto',
            'filesize' => 0
        ];
    }
    
    // Ottieni informazioni sul formato
    $formatCmd = "ffprobe -v error -show_format -of json " . escapeshellarg($videoPath);
    $formatResult = monitorCommand($formatCmd, "analyzer-format");
    
    if (!$formatResult['success']) {
        return [
            'success' => false,
            'message' => 'Errore nell\'analisi del formato',
            'filesize' => $filesize
        ];
    }
    
    $formatOutput = implode("\n", $formatResult['output']);
    $formatInfo = json_decode($formatOutput, true);
    
    // Ottieni informazioni sui stream
    $streamsCmd = "ffprobe -v error -show_streams -of json " . escapeshellarg($videoPath);
    $streamsResult = monitorCommand($streamsCmd, "analyzer-streams");
    
    if (!$streamsResult['success']) {
        return [
            'success' => false,
            'message' => 'Errore nell\'analisi degli stream',
            'filesize' => $filesize,
            'format' => $formatInfo
        ];
    }
    
    $streamsOutput = implode("\n", $streamsResult['output']);
    $streamsInfo = json_decode($streamsOutput, true);
    
    // Prepara il risultato finale
    $videoInfo = [
        'success' => true,
        'filesize' => $filesize,
        'format' => isset($formatInfo['format']) ? $formatInfo['format'] : [],
        'streams' => isset($streamsInfo['streams']) ? $streamsInfo['streams'] : []
    ];
    
    // Estrai informazioni utili
    $videoInfo['duration'] = isset($formatInfo['format']['duration']) 
                          ? floatval($formatInfo['format']['duration']) 
                          : 0;
    
    $videoInfo['filename'] = isset($formatInfo['format']['filename']) 
                          ? basename($formatInfo['format']['filename']) 
                          : basename($videoPath);
    
    // Trova il primo stream video e audio
    $videoInfo['video_stream'] = null;
    $videoInfo['audio_stream'] = null;
    
    if (isset($streamsInfo['streams'])) {
        foreach ($streamsInfo['streams'] as $stream) {
            if (isset($stream['codec_type'])) {
                if ($stream['codec_type'] === 'video' && $videoInfo['video_stream'] === null) {
                    $videoInfo['video_stream'] = $stream;
                } else if ($stream['codec_type'] === 'audio' && $videoInfo['audio_stream'] === null) {
                    $videoInfo['audio_stream'] = $stream;
                }
            }
        }
    }
    
    // Estrai risoluzione, framerate, ecc.
    if ($videoInfo['video_stream'] !== null) {
        $videoInfo['width'] = intval($videoInfo['video_stream']['width'] ?? 0);
        $videoInfo['height'] = intval($videoInfo['video_stream']['height'] ?? 0);
        
        // Calcola framerate
        if (isset($videoInfo['video_stream']['r_frame_rate'])) {
            $fpsRaw = $videoInfo['video_stream']['r_frame_rate'];
            list($numerator, $denominator) = explode('/', $fpsRaw);
            $videoInfo['fps'] = $denominator ? round($numerator / $denominator, 2) : 0;
        } else {
            $videoInfo['fps'] = 0;
        }
    }
    
    debugLog("Analisi completata: {$videoInfo['filename']}, " . 
             "Durata: {$videoInfo['duration']}s, " . 
             "Risoluzione: {$videoInfo['width']}x{$videoInfo['height']}", 
             "info", "analyzer");
    
    return $videoInfo;
}

/**
 * Verifica se un file video è valido
 * 
 * @param string $videoPath Percorso del file video
 * @return bool Se il video è valido
 */
function isValidVideo($videoPath) {
    if (!file_exists($videoPath)) {
        return false;
    }
    
    if (filesize($videoPath) <= 0) {
        return false;
    }
    
    // Verifica se il file è un container multimediale valido
    $cmd = "ffprobe -v error " . escapeshellarg($videoPath);
    exec($cmd, $output, $returnCode);
    
    return $returnCode === 0;
}

/**
 * Genera un percorso di file univoco con timestamp
 * 
 * @param string $prefix Prefisso del nome file
 * @param string $extension Estensione del file
 * @param string $directory Directory di destinazione
 * @return string Percorso del file
 */
function generateUniqueFilePath($prefix, $extension, $directory = null) {
    $timestamp = date('Ymd_His');
    $uniqueId = uniqid();
    
    if ($directory === null) {
        $directory = getConfig('paths.uploads', 'uploads');
    }
    
    // Assicurati che la directory esista
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }
    
    // Rimuovi i punti iniziali dall'estensione se presenti
    $extension = ltrim($extension, '.');
    
    return "$directory/{$prefix}_{$timestamp}_{$uniqueId}.$extension";
}
