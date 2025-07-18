// index.php
<?php
// ... altre parti del codice ...

// Assicuriamoci di non ridefinire la funzione
if (!function_exists('cleanupTempFiles')) {
    /**
     * Rimuove file temporanei in generale
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

// Inclusione dello script FFmpeg (usa la stessa funzione guardata)
require_once __DIR__ . '/ffmpeg_script.php';

// ... resto di index.php ...
?>

// ffmpeg_script.php
<?php
// ... eventuali require/include aggiuntivi ...

// Definizione guardata: se già esiste, non verrà ridefinita
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

// Esempio di utilizzo:
// cleanupTempFiles($filesDaEliminare);

// ... resto di ffmpeg_script.php ...
?>
