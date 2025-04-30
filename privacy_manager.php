<?php
// privacy_manager.php - Gestisce la privacy e la pulizia automatica dei file

/**
 * Pulisce i file temporanei e di upload in base alla politica di privacy
 * 
 * @param string $directory Directory da pulire
 * @param int $maxAgeHours Età massima dei file in ore
 * @param bool $keepOriginals Se mantenere i file originali
 * @return array Risultati dell'operazione
 */
function cleanupFiles($directory, $maxAgeHours = 24, $keepOriginals = false) {
    if (!file_exists($directory)) {
        return [
            'success' => false,
            'message' => 'Directory non trovata',
            'removed' => 0
        ];
    }
    
    $now = time();
    $maxAgeSeconds = $maxAgeHours * 3600;
    $removedCount = 0;
    $logMessages = [];
    
    // Ottieni tutti i file nella directory
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $fileinfo) {
        // Salta le directory
        if ($fileinfo->isDir()) {
            continue;
        }
        
        $filePath = $fileinfo->getRealPath();
        $fileName = $fileinfo->getFilename();
        
        // Salta i file originali se richiesto
        if ($keepOriginals && strpos($filePath, 'uploads/') !== false) {
            continue;
        }
        
        // Controlla l'età del file
        $fileAge = $now - $fileinfo->getMTime();
        if ($fileAge > $maxAgeSeconds) {
            if (unlink($filePath)) {
                $removedCount++;
                $logMessages[] = "Rimosso file: $filePath (vecchio di " . round($fileAge/3600, 1) . " ore)";
            }
        }
    }
    
    // Registra l'operazione di pulizia
    $logFile = $directory . '/privacy_cleanup.log';
    $logContent = date('Y-m-d H:i:s') . " - Rimossi $removedCount file\n";
    $logContent .= implode("\n", $logMessages) . "\n";
    file_put_contents($logFile, $logContent, FILE_APPEND);
    
    return [
        'success' => true,
        'message' => "Rimossi $removedCount file più vecchi di $maxAgeHours ore",
        'removed' => $removedCount,
        'log' => $logMessages
    ];
}

/**
 * Pianifica la pulizia automatica dei file
 * Da eseguire tramite un sistema di cron o simile
 */
function scheduleCleanup() {
    // Tempo massimo di conservazione per ogni tipo di file
    $cleanupConfig = [
        'temp' => [
            'max_age' => 3,       // 3 ore per i file temporanei
            'keep_originals' => false
        ],
        'uploads' => [
            'max_age' => 48,      // 48 ore per i file caricati
            'keep_originals' => false
        ]
    ];
    
    $results = [];
    
    foreach ($cleanupConfig as $dir => $config) {
        $results[$dir] = cleanupFiles($dir, $config['max_age'], $config['keep_originals']);
    }
    
    return $results;
}

/**
 * Genera un ID sessione univoco per raggruppare i file di un utente
 * 
 * @return string ID sessione
 */
function generateSessionId() {
    if (!isset($_SESSION['privacy_session_id'])) {
        $_SESSION['privacy_session_id'] = uniqid('session_', true);
    }
    return $_SESSION['privacy_session_id'];
}

/**
 * Traccia un file caricato nel registro di privacy
 * 
 * @param string $filePath Percorso del file
 * @param string $originalName Nome originale del file
 * @param string $processingType Tipo di elaborazione (upload, processing, output)
 */
function trackFile($filePath, $originalName, $processingType = 'upload') {
    $sessionId = generateSessionId();
    $privacyLog = getConfig('paths.privacy_log', 'privacy_log.json');
    
    // Crea il file di log se non esiste
    if (!file_exists($privacyLog)) {
        file_put_contents($privacyLog, json_encode([]));
    }
    
    // Leggi il log esistente
    $log = json_decode(file_get_contents($privacyLog), true);
    if (!is_array($log)) {
        $log = [];
    }
    
    // Aggiungi l'entry del file
    $log[] = [
        'session_id' => $sessionId,
        'file_path' => $filePath,
        'original_name' => $originalName,
        'processing_type' => $processingType,
        'size' => filesize($filePath),
        'timestamp' => time(),
        'scheduled_deletion' => time() + (getConfig('privacy.retention_hours', 48) * 3600)
    ];
    
    // Salva il log aggiornato
    file_put_contents($privacyLog, json_encode($log));
}

/**
 * Genera un report di privacy per l'utente
 * 
 * @return array Report di privacy
 */
function generatePrivacyReport() {
    $sessionId = generateSessionId();
    $privacyLog = getConfig('paths.privacy_log', 'privacy_log.json');
    
    if (!file_exists($privacyLog)) {
        return [
            'files_tracked' => 0,
            'message' => 'Nessun file tracciato',
            'details' => []
        ];
    }
    
    // Leggi il log
    $log = json_decode(file_get_contents($privacyLog), true);
    if (!is_array($log)) {
        return [
            'files_tracked' => 0,
            'message' => 'Nessun file tracciato',
            'details' => []
        ];
    }
    
    // Filtra per session ID
    $sessionFiles = array_filter($log, function($entry) use ($sessionId) {
        return $entry['session_id'] === $sessionId;
    });
    
    // Prepara il report
    $report = [
        'files_tracked' => count($sessionFiles),
        'message' => count($sessionFiles) > 0 ? 'File tracciati per questa sessione' : 'Nessun file tracciato per questa sessione',
        'retention_policy' => getConfig('privacy.retention_hours', 48) . ' ore',
        'details' => $sessionFiles
    ];
    
    return $report;
}

/**
 * Imposta la policy di privacy nel config
 * 
 * @param int $retentionHours Ore di conservazione dei file
 * @param bool $trackFiles Se tracciare i file
 */
function setPrivacyPolicy($retentionHours = 48, $trackFiles = true) {
    setConfig('privacy.retention_hours', $retentionHours);
    setConfig('privacy.track_files', $trackFiles);
}

/**
 * Genera l'HTML per mostrare la politica sulla privacy
 * 
 * @return string HTML della politica sulla privacy
 */
function getPrivacyPolicyHtml() {
    $retentionHours = getConfig('privacy.retention_hours', 48);
    $retentionDays = ceil($retentionHours / 24);
    
    $html = <<<EOT
<div class="privacy-policy">
    <h3>Informativa sulla Privacy</h3>
    <p>I file caricati su questo sistema vengono utilizzati solo per l'elaborazione richiesta e vengono automaticamente eliminati dopo <strong>$retentionDays giorni</strong>.</p>
    <ul>
        <li>I file temporanei vengono eliminati entro 3 ore dalla creazione</li>
        <li>I file originali e quelli elaborati vengono eliminati dopo $retentionHours ore</li>
        <li>Nessun dato viene condiviso con terze parti</li>
        <li>Il sistema non memorizza informazioni personali oltre ai file caricati</li>
    </ul>
    <p>Caricando file in questo sistema, accetti questa politica di conservazione temporanea.</p>
</div>
EOT;
    
    return $html;
}
?>
