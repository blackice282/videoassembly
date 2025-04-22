<?php
// compatibility.php - Funzioni di compatibilità per integrare le nuove funzionalità

/**
 * Assicura che le funzioni necessarie siano definite
 * Carica questo file prima di tutto il resto
 */

// Se debugLog non è definito, crea una semplice funzione di logging
if (!function_exists('debugLog')) {
    function debugLog($message, $level = "info", $context = "") {
        // Implementazione di base per compatibilità
        if (getConfig('system.debug', false)) {
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
            file_put_contents($logFile, $formattedMessage, FILE_APPEND);
        }
    }
}

// Funzione per verificare se un file è un video valido
if (!function_exists('isValidVideo')) {
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
}

// Funzione per verificare il supporto FFmpeg
if (!function_exists('ffmpegAvailable')) {
    function ffmpegAvailable() {
        exec("ffmpeg -version 2>&1", $output, $returnCode);
        return $returnCode === 0;
    }
}

// Funzione per generare un ID univoco per i file
if (!function_exists('generateUniqueFilePath')) {
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
}

/**
 * Verifica le dipendenze necessarie per tutte le funzionalità
 * 
 * @return array Stato delle dipendenze
 */
function checkAllDependencies() {
    $deps = [
        'ffmpeg' => ffmpegAvailable(),
        'ffprobe' => false,
        'python' => false,
        'opencv' => false
    ];
    
    // Verifica FFprobe
    exec("ffprobe -version 2>&1", $output, $returnCode);
    $deps['ffprobe'] = ($returnCode === 0);
    
    // Verifica Python
    exec("python3 --version 2>&1", $pythonOutput, $pythonReturnCode);
    if ($pythonReturnCode !== 0) {
        exec("python --version 2>&1", $pythonOutput, $pythonReturnCode);
    }
    $deps['python'] = ($pythonReturnCode === 0);
    
    // Verifica OpenCV se Python è disponibile
    if ($deps['python']) {
        $pythonCmd = ($pythonReturnCode === 0 && strpos(implode("\n", $pythonOutput), "Python 3") !== false) ? "python3" : "python";
        $checkCmd = "$pythonCmd -c 'import cv2; print(\"OpenCV disponibile\")' 2>/dev/null";
        exec($checkCmd, $opencvOutput, $opencvReturnCode);
        $deps['opencv'] = ($opencvReturnCode === 0);
    }
    
    return $deps;
}
