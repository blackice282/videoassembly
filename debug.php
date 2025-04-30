<?php
// debug.php - Utility per debugging di FFmpeg e file multimediali

/**
 * Verifica lo stato di un file video/audio
 * 
 * @param string $filePath Percorso del file da verificare
 * @return array Risultato della verifica
 */
function checkMediaFile($filePath) {
    if (!file_exists($filePath)) {
        return [
            'exists' => false,
            'message' => 'Il file non esiste'
        ];
    }
    
    $filesize = filesize($filePath);
    if ($filesize <= 0) {
        return [
            'exists' => true,
            'filesize' => 0,
            'message' => 'Il file esiste ma è vuoto'
        ];
    }
    
    // Verifica se il file è un container multimediale valido
    $cmd = "ffprobe -v error " . escapeshellarg($filePath);
    exec($cmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        return [
            'exists' => true,
            'filesize' => $filesize,
            'valid' => false,
            'message' => 'Il file esiste ma non è un file multimediale valido'
        ];
    }
    
    // Ottieni informazioni sul file
    $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height,codec_name,duration -of json " . escapeshellarg($filePath);
    $videoInfo = shell_exec($cmd);
    $videoData = json_decode($videoInfo, true);
    
    $cmd = "ffprobe -v error -select_streams a:0 -show_entries stream=codec_name,channels,sample_rate -of json " . escapeshellarg($filePath);
    $audioInfo = shell_exec($cmd);
    $audioData = json_decode($audioInfo, true);
    
    $result = [
        'exists' => true,
        'filesize' => $filesize,
        'valid' => true,
        'message' => 'File multimediale valido'
    ];
    
    // Aggiungi info video se disponibili
    if (isset($videoData['streams']) && !empty($videoData['streams'])) {
        $result['has_video'] = true;
        $result['video'] = $videoData['streams'][0];
    } else {
        $result['has_video'] = false;
    }
    
    // Aggiungi info audio se disponibili
    if (isset($audioData['streams']) && !empty($audioData['streams'])) {
        $result['has_audio'] = true;
        $result['audio'] = $audioData['streams'][0];
    } else {
        $result['has_audio'] = false;
    }
    
    return $result;
}

/**
 * Tenta di riparare un file video danneggiato
 * 
 * @param string $inputFile File di input
 * @param string $outputFile File di output riparato
 * @return bool Successo dell'operazione
 */
function repairVideoFile($inputFile, $outputFile) {
    // Tentativo di riparazione con recodifica completa
    $cmd = "ffmpeg -i " . escapeshellarg($inputFile) . 
           " -c:v libx264 -preset ultrafast -crf 23 -c:a aac " . 
           escapeshellarg($outputFile);
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode !== 0 || !file_exists($outputFile) || filesize($outputFile) <= 0) {
        // Tentativo alternativo solo con stream copy
        $cmd = "ffmpeg -i " . escapeshellarg($inputFile) . 
               " -c copy " . 
               escapeshellarg($outputFile);
        
        exec($cmd, $output, $returnCode);
    }
    
    return $returnCode === 0 && file_exists($outputFile) && filesize($outputFile) > 0;
}

/**
 * Genera log di debug FFmpeg dettagliato
 * 
 * @param string $command Comando FFmpeg da eseguire
 * @param string $logPath Percorso del file di log
 * @return array Risultato dell'esecuzione
 */
function runFFmpegWithDebug($command, $logPath) {
    // Aggiungi opzioni per logging dettagliato
    $debugCommand = str_replace("ffmpeg ", "ffmpeg -v debug ", $command);
    
    // Esegui il comando e cattura l'output
    $output = [];
    $returnCode = 0;
    exec($debugCommand . " 2>&1", $output, $returnCode);
    
    // Salva l'output in un file di log
    file_put_contents($logPath, implode("\n", $output));
    
    return [
        'command' => $debugCommand,
        'return_code' => $returnCode,
        'log_file' => $logPath,
        'success' => $returnCode === 0
    ];
}

/**
 * Crea un file video di test
 * 
 * @param string $outputPath Percorso di output
 * @param int $duration Durata in secondi
 * @return bool Successo dell'operazione
 */
function createTestVideo($outputPath, $duration = 5) {
    // Crea un video di test con un colore di sfondo e un testo
    $cmd = "ffmpeg -y -f lavfi -i color=c=blue:s=640x480:d=$duration -vf " . 
           "\"drawtext=fontfile=/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf:text='Video di Test':fontcolor=white:fontsize=36:x=(w-text_w)/2:y=(h-text_h)/2\" " . 
           "-c:v libx264 " . escapeshellarg($outputPath);
    
    exec($cmd, $output, $returnCode);
    
    return $returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
}

/**
 * Verifica il supporto per i vari codec e filtri FFmpeg
 * 
 * @return array Capacità supportate
 */
function checkFFmpegCapabilities() {
    // Verifica codec supportati
    $cmd = "ffmpeg -codecs 2>&1";
    $codecsOutput = shell_exec($cmd);
    
    // Verifica filtri supportati
    $cmd = "ffmpeg -filters 2>&1";
    $filtersOutput = shell_exec($cmd);
    
    // Analizza il supporto per codec comuni
    $codecs = [
        'libx264' => strpos($codecsOutput, 'libx264') !== false,
        'h264' => strpos($codecsOutput, 'h264') !== false,
        'aac' => strpos($codecsOutput, 'aac') !== false,
        'mp3' => strpos($codecsOutput, 'mp3') !== false
    ];
    
    // Analizza il supporto per filtri comuni
    $filters = [
        'colorbalance' => strpos($filtersOutput, 'colorbalance') !== false,
        'unsh
