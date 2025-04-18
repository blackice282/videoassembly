<?php
// cache_helper.php - Sistema di cache per operazioni costose

/**
 * Memorizza nella cache un risultato per un dato input
 * 
 * @param string $cacheKey Chiave univoca per l'operazione
 * @param mixed $data Dati da memorizzare
 * @param int $ttl Tempo di vita in secondi (default: 3600 = 1 ora)
 * @return bool Successo dell'operazione
 */
function cacheStore($cacheKey, $data, $ttl = 3600) {
    $cacheDir = getConfig('paths.cache', 'cache');
    
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
    
    $cacheFile = $cacheDir . '/' . md5($cacheKey) . '.cache';
    $cacheData = [
        'key' => $cacheKey,
        'data' => $data,
        'expires' => time() + $ttl
    ];
    
    return file_put_contents($cacheFile, serialize($cacheData)) !== false;
}

/**
 * Recupera un risultato dalla cache
 * 
 * @param string $cacheKey Chiave univoca per l'operazione
 * @return mixed|null Dati memorizzati o null se non trovati/scaduti
 */
function cacheGet($cacheKey) {
    $cacheDir = getConfig('paths.cache', 'cache');
    $cacheFile = $cacheDir . '/' . md5($cacheKey) . '.cache';
    
    if (!file_exists($cacheFile)) {
        return null;
    }
    
    $cacheData = unserialize(file_get_contents($cacheFile));
    
    if ($cacheData === false) {
        return null;
    }
    
    // Verifica se la cache è scaduta
    if ($cacheData['expires'] < time()) {
        unlink($cacheFile); // Rimuovi il file scaduto
        return null;
    }
    
    return $cacheData['data'];
}

/**
 * Esegue una funzione con memorizzazione nella cache
 * 
 * @param string $cacheKey Chiave univoca per l'operazione
 * @param callable $function Funzione da eseguire
 * @param array $params Parametri da passare alla funzione
 * @param int $ttl Tempo di vita in secondi
 * @return mixed Risultato della funzione (dalla cache o fresco)
 */
function cacheExecute($cacheKey, $function, $params = [], $ttl = 3600) {
    // Verifica se abbiamo un risultato in cache
    $cachedResult = cacheGet($cacheKey);
    
    if ($cachedResult !== null) {
        return $cachedResult;
    }
    
    // Esegui la funzione
    $result = call_user_func_array($function, $params);
    
    // Memorizza il risultato nella cache
    cacheStore($cacheKey, $result, $ttl);
    
    return $result;
}
