// index.php
<?php
// Carica configurazione generale
require_once __DIR__ . '/config.php';

// Include transitions.php solo se presente
$transitionsFile = __DIR__ . '/transitions.php';
if (file_exists($transitionsFile)) {
    require_once $transitionsFile;
}

// Definizione unica di cleanupTempFiles
if (!function_exists('cleanupTempFiles')) {
    /**
     * Rimuove file temporanei
     *
     * @param string[] $files
     * @param bool $keepOriginals
     */
    function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
        foreach ($files as $f) {
            if (file_exists($f) && (!$keepOriginals || strpos($f, 'uploads/') === false)) {
                @unlink($f);
            }
        }
    }
}

// Includi lo script FFmpeg (usa stessa funzione guardata)
require_once __DIR__ . '/ffmpeg_script.php';

// ... altre parti del codice di index.php ...
?>


// ffmpeg_script.php
<?php
// Assicuriamoci di non ridefinire cleanupTempFiles
if (!function_exists('cleanupTempFiles')) {
    /**
     * Rimuove file temporanei specifici per FFmpeg
     *
     * @param string[] $files
     * @param bool $keepOriginals
     */
    function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
        foreach ($files as $f) {
            if (file_exists($f) && (!$keepOriginals || strpos($f, 'uploads/') === false)) {
                @unlink($f);
            }
        }
    }
}

// Funzioni specifiche per FFmpeg
function runFfmpegCommand(string $cmd): void {
    // implementazione del comando
    exec($cmd, $output, $returnCode);
    // gestione output e errori...
}

// ... resto di ffmpeg_script.php ...
?>
