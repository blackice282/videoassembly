<?php
// face_detection.php
require_once __DIR__ . '/config.php';

/**
 * Applica la privacy dei volti (stub: per ora copia semplicemente il file)
 *
 * @param string $input   Percorso del video di input
 * @param string $output  Percorso del video di output
 * @return bool           True se l'output esiste e non è vuoto
 */
function applyFacePrivacy($input, $output) {
    // TODO: integrare OpenCV o microservice per sovrapporre emoji sui volti
    $ok = copy($input, $output);
    return $ok && file_exists($output) && filesize($output) > 0;
}
