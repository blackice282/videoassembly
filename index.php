// File: index.php
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

// Include script opzionali solo se presenti
$scripts = ['people_detection.php', 'transitions.php', 'duration_editor.php'];
foreach ($scripts as $script) {
    $path = __DIR__ . '/' . $script;
    if (file_exists($path)) {
        require_once $path;
    }
}

// Carica lo script FFmpeg per le funzioni specializzate
require_once __DIR__ . '/ffmpeg_script.php';

function createUploadsDir(): void {
    $uploads = getConfig('paths.uploads', 'uploads');
    $temp    = getConfig('paths.temp', 'temp');
    if (!file_exists($uploads)) mkdir($uploads, 0777, true);
    if (!file_exists($temp))    mkdir($temp,    0777, true);
}

if (!function_exists('cleanupTempFiles')) {
    /**
     * Rimuove file temporanei
     *
     * @param string[] $files
     * @param bool $keepOriginals
     */
    function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
        foreach ($files as $file) {
            if (file_exists($file) && (!$keepOriginals || strpos($file, 'uploads/') === false)) {
                @unlink($file);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    createUploadsDir();
    set_time_limit(300);

    // ... logica di caricamento, conversione, montaggio ...

    // Pulizia file temporanei
    cleanupTempFiles($uploaded_ts_files, getConfig('system.keep_original', true));
    cleanupTempFiles($segments_to_process, getConfig('system.keep_original', true));
}
?>
