<?php
// config.php

// Percorsi delle directory
$CONFIG = [
    // Directory per i file
    'paths' => [
        'uploads' => 'uploads',
        'temp' => 'temp',
        'output' => 'output'
    ],
    
    // Configurazione FFmpeg
    'ffmpeg' => [
        'video_codec' => 'libx264',
        'audio_codec' => 'aac',
        'video_quality' => '23', // CRF (Constant Rate Factor) - Valori più bassi = qualità superiore
        'resolution' => '1280x720', // Risoluzione del video di output
    ],
    
    // Configurazione per il rilevamento delle persone
    'detection' => [
        'min_duration' => 1, // Durata minima in secondi di un segmento con persone
        'max_gap' => 2,      // Durata massima in secondi tra segmenti da unire
        'frame_rate' => 1,   // Fotogrammi al secondo da analizzare
        'confidence' => 0.5, // Soglia di confidenza per il rilevamento (0-1)
    ],
    
    // Configurazione per le transizioni
    'transitions' => [
        'enabled' => true,    // Abilita le transizioni tra segmenti
        'type' => 'fade',     // Tipo di transizione (fade, dissolve, wipe)
        'duration' => 0.5,    // Durata della transizione in secondi
    ],
    
    // Altre impostazioni
    'system' => [
        'cleanup_temp' => true,      // Elimina i file temporanei dopo l'elaborazione
        'keep_original' => true,     // Mantieni i file originali
        'max_upload_size' => 200,    // Dimensione massima di upload in MB
        'base_url' => 'https://your-app-name.onrender.com', // URL base dell'applicazione
        'debug' => false,            // Modalità debug
    ]
];

// Funzione per ottenere una configurazione
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

// Funzione per impostare una configurazione
function setConfig($key, $value) {
    global $CONFIG;
    
    $keys = explode('.', $key);
    $lastKey = array_pop($keys);
    $current = &$CONFIG;
    
    foreach ($keys as $k) {
        if (!isset($current[$k])) {
            $current[$k] = [];
        }
        $current = &$current[$k];
    }
    
    $current[$lastKey] = $value;
}
?>