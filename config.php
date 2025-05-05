<?php
// config.php

// Percorsi delle directory
$CONFIG = [
    // Directory per i file
    'paths' => [
        'uploads' => 'uploads',
        'temp'    => 'temp',
        'output'  => 'output'
    ],
    
    // Configurazione FFmpeg
    'ffmpeg' => [
        'video_codec'  => 'libx264',
        'audio_codec'  => 'aac',
        'video_quality'=> '23', // CRF (Constant Rate Factor) - valori più bassi = qualità superiore
        'resolution'   => '720x1280', // Verticale 9:16
    ],
    
    // Configurazione per il rilevamento delle persone
    'detection' => [
        'min_duration' => 1,    // Durata minima in secondi di un segmento con persone
        'max_gap'      => 2,    // Durata massima in secondi tra segmenti da unire
        'frame_rate'   => 1,    // Fotogrammi al secondo da analizzare
        'confidence'   => 0.5,  // Soglia di confidenza per il rilevamento (0-1)
    ],
    
    // Configurazione per le transizioni
    'transitions' => [
        'enabled'  => true,   // Abilita le transizioni tra segmenti
        'type'     => 'fade', // Tipo di transizione (fade, dissolve, wipe)
        'duration' => 0.5,    // Durata della transizione in secondi
    ],
    
    // Configurazione per l'assistenza AI
    'ai' => [
        'enabled'           => true,
        'prompt_placeholder'=> 'Descrivi qui l\'intervento AI desiderato (es. ritaglio, stabilizzazione, color grading)...'
    ],
    
    // Altre impostazioni di sistema
    'system' => [
        'cleanup_temp'    => true,   // Elimina i file temporanei dopo l'elaborazione
        'keep_original'   => true,   // Mantieni i file originali
        'max_upload_size' => 500,    // Dimensione massima di upload in MB
        'base_url'        => 'https://your-app-name.onrender.com', // URL base dell'app
        'debug'           => false,  // Modalità debug
    ]
];

/**
 * Restituisce un valore di configurazione per chiave puntata (es. 'ffmpeg.resolution').
 */
function getConfig($key, $default = null) {
    global $CONFIG;
    $keys = explode('.', $key);
    $value = $CONFIG;
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    return $value;
}

/**
 * Imposta/modifica un valore di configurazione per chiave puntata.
 */
function setConfig($key, $value) {
    global $CONFIG;
    $keys = explode('.', $key);
    $lastKey = array_pop($keys);
    $current = &$CONFIG;
    foreach ($keys as $k) {
        if (!isset($current[$k]) || !is_array($current[$k])) {
            $current[$k] = [];
        }
        $current = &$current[$k];
    }
    $current[$lastKey] = $value;
}
?>
