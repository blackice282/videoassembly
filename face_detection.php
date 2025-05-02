<?php
require_once __DIR__ . '/config.php';

/**
 * Applica la privacy sui volti (stub: copia semplice)
 *
 * @param string $input  Percorso video input
 * @param string $output Percorso video output
 * @return bool          True se successo
 */
function applyFacePrivacy($input, $output) {
    $ok = copy($input, $output);
    return $ok && file_exists($output) && filesize($output) > 0;
}
?>
