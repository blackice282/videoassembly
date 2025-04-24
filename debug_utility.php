<?php
require_once 'config.php';

/**
 * Scrive un messaggio nei log, se abilitato
 *
 * @param string $message Contenuto da scrivere
 * @param string $level Livello log (info, error, warning)
 * @param string $category Categoria opzionale
 */
function debugLog($message, $level = "info", $category = "app") {
    if (!ENABLE_DEBUG) return;

    if (!file_exists(LOG_DIR)) {
        mkdir(LOG_DIR, 0777, true);
    }

    $logFile = LOG_DIR . '/app_' . date('Ymd') . '.log';
    $timestamp = date('[Y-m-d H:i:s]');
    $line = "$timestamp [$level][$category] $message" . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
}
