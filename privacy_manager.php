<?php
require_once 'config.php';

/**
 * Registra un'azione nel log privacy
 */
function trackFile($path, $name, $action) {
    if (!getConfig('privacy.track_files')) return;
    $logfile = getConfig('paths.privacy_log');
    $entries = [];
    if (file_exists($logfile)) {
        $entries = json_decode(file_get_contents($logfile), true) ?: [];
    }
    $entries[] = [
        'time'   => date('c'),
        'action' => $action,
        'file'   => $name,
        'path'   => $path
    ];
    file_put_contents($logfile, json_encode($entries, JSON_PRETTY_PRINT));
}

/**
 * Pulisce log oltre la retention
 */
function cleanupPrivacyLog() {
    $logfile = getConfig('paths.privacy_log');
    if (!file_exists($logfile)) return;
    $entries = json_decode(file_get_contents($logfile), true);
    $threshold = strtotime('-'.getConfig('privacy.retention_hours').' hours');
    $keep = array_filter($entries, function($e) use($threshold){
        return strtotime($e['time']) > $threshold;
    });
    file_put_contents($logfile, json_encode(array_values($keep), JSON_PRETTY_PRINT));
}
