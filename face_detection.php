<?php
require_once __DIR__ . '/config.php';

/**
 * Stub privacy volti: copia semplice
 */
function applyFacePrivacy(string $input, string $output): bool {
    if (!file_exists($input)) return false;
    $dir = dirname($output);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    return copy($input, $output);
}
