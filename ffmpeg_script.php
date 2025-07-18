<?php
// index.php (invariato)

// ... altre parti del codice ...

function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
    foreach ($files as $f) {
        if (file_exists($f) && (!$keepOriginals || strpos($f, 'uploads/')===false)) {
            @unlink($f);
        }
    }
}

// ... resto di index.php ...

?>

<?php
// ffmpeg_script.php (modificato con guard)

// Inizia del file /app/ffmpeg_script.php

if (!function_exists('cleanupTempFiles')) {
    /**
     * Rimuove file temporanei
     *
     * @param string[] $files
     * @param bool $keepOriginals
     */
    function cleanupTempFiles(array $files, bool $keepOriginals = false): void {
        foreach ($files as $f) {
            if (file_exists($f) && (!$keepOriginals || strpos($f, 'uploads/')===false)) {
                @unlink($f);
            }
        }
    }
}

// ... resto di ffmpeg_script.php ...

?>
