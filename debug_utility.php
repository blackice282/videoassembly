<?php
require_once 'config.php';

/**
 * Scrive un messaggio nei log di debug
 *
 * @param string $message Messaggio da loggare
 * @param string $level Livello (info, warning, error)
 * @param string $category Categoria opzionale (es. 'processor')
 */
function debugLog($message, $level = "info", $category = "general") {
    if (!ENABLE_DEBUG) return;

    if (!file_exists(LOG_DIR)) {
        mkdir(LOG_DIR, 0777, true);
    }

    $logFile = LOG_DIR . '/app_' . date('Ymd') . '.log';
    $timestamp = date('[Y-m-d H:i:s]');
    $logMessage = "$timestamp [$level] [$category] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
