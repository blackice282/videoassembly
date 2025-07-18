// index.php
<?php
// ... altre parti del codice ...

// Funzione di pulizia temporanei per uso generale
function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
    foreach ($files as $f) {
        if (file_exists($f) && (!$keepOriginals || strpos($f, 'uploads/') === false)) {
            @unlink($f);
        }
    }
}

// Esempio di chiamata in index.php
// cleanupTempFiles($arrayDiFile);

// ... resto di index.php ...
?>


// ffmpeg_script.php
<?php
// ... eventuali require/include ...

/**
 * Versione specializzata della pulizia per file FFmpeg
 * @param string[] $files
 * @param bool $keepOriginals
 */
function cleanupFfmpegTempFiles(array $files, bool $keepOriginals = false): void {
    foreach ($files as $f) {
        if (file_exists($f) && (!$keepOriginals || strpos($f, 'uploads/') === false)) {
            @unlink($f);
        }
    }
}

// Sostituisci tutte le chiamate a cleanupTempFiles() con cleanupFfmpegTempFiles()
// Esempio:
// cleanupFfmpegTempFiles($filesDaPulire);

// ... resto di ffmpeg_script.php ...
?>
