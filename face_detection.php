<?php
require_once __DIR__ . '/config.php';

/**
 * Applica la privacy sui volti (stub: copia file)
 *
 * @param string $input  Percorso video input
 * @param string $output Percorso video output
 * @return bool          True se il file output è stato creato con successo
 */
function applyFacePrivacy($input, $output) {
    // Verifica che il file di input esista
    if (!file_exists($input)) {
        return false;
    }
    // Assicura che la directory di output esista
    $dir = dirname($output);
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    // Copia il file
    return copy($input, $output);
}
?>
