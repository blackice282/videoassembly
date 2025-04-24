<?php
// transitions.php - Gestisce le transizioni tra i segmenti video

require_once 'config.php';

/**
 * Applica una transizione tra segmenti video
 * 
 * @param array $segmentFiles Array dei file dei segmenti
 * @param string $outputDirectory Directory per i file temporanei
 * @param string $transitionType Tipo di transizione (fade, dissolve, wipe)
 * @return array File dei segmenti con transizioni
 */
function applyTransitions($segmentFiles, $outputDirectory, $transitionType = 'fade') {
    if (count($segmentFiles) <= 1) {
        return $segmentFiles; // Nessuna transizione necessaria
    }
    
    // Crea la directory di output se non esiste
    if (!file_exists($outputDirectory)) {
        mkdir($outputDirectory, 0777, true);
    }
    
    $outputFiles = [];
    $transitionDuration = getConfig('transitions.duration', 0.5); // In secondi
    
    // Genera un filtro di transizione in base al tipo
    switch ($transitionType) {
        case 'fade':
            $transitionFilter = "fade=t=in:st=0:d=$transitionDuration,fade=t=out:st=duration-$transitionDuration:d=$transitionDuration";
            break;
        case 'dissolve':
            // Nota: la dissoluzione richiede un approccio diverso con xfade e viene gestita separatamente
            $transitionFilter = "fade=t=in:st=0:d=$transitionDuration,fade=t=out:st=duration-$transitionDuration:d=$transitionDuration";
            break;
        case 'wipe':
            // Le transizioni wipe richiederebbero un approccio più complesso
            $transitionFilter = "fade=t=in:st=0:d=$transitionDuration,fade=t=out:st=duration-$transitionDuration:d=$transitionDuration";
            break;
        default:
            $transitionFilter = "fade=t=in:st=0:d=$transitionDuration,fade=t=out:st=duration-$transitionDuration:d=$transitionDuration";
    }
    
    // Per ogni segmento, applica il filtro
    foreach ($segmentFiles as $index => $inputFile) {
        $outputFile = "$outputDirectory/trans_" . basename($inputFile);
        
        // Applica la transizione
        $cmd = "ffmpeg -i " . escapeshellarg($inputFile) . " -vf \"$transitionFilter\" -c:a copy " . escapeshellarg($outputFile);
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($outputFile)) {
            $outputFiles[] = $outputFile;
        } else {
            // Se l'applicazione della transizione fallisce, usa il file originale
            $outputFiles[] = $inputFile;
        }
    }
    
    // Se è stato selezionato il tipo di transizione "dissolve", crea un video con dissolvenze tra segmenti
    if ($transitionType === 'dissolve' && count($outputFiles) > 1) {
        $finalOutput = "$outputDirectory/final_with_transitions.mp4";
        $concatFilter = "concat=n=" . count($outputFiles) . ":v=1:a=1";
        
        // Crea un file di input per la concatenazione
        $concatFile = "$outputDirectory/concat_list.txt";
        $concatContent = "";
        foreach ($outputFiles as $file) {
            $concatContent .= "file '" . str_replace("'", "\\'", $file) . "'\n";
        }
        file_put_contents($concatFile, $concatContent);
        
        // Esegui la concatenazione con dissolvenze
        $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($concatFile) . " -filter_complex \"$concatFilter\" " . escapeshellarg($finalOutput);
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($finalOutput)) {
            return [$finalOutput];
        }
    }
    
    return $outputFiles;
}

/**
 * Crea la concatenazione di segmenti con transizioni avanzate
 * 
 * @param array $segmentFiles Array dei file dei segmenti
 * @param string $outputFile File di output finale
 * @return bool Successo dell'operazione
 */
function concatenateWithTransitions($segmentFiles, $outputFile) {
    if (empty($segmentFiles)) {
        return false;
    }
    
    // Se le transizioni sono disabilitate o c'è un solo segmento, usa la concatenazione standard
    if (!getConfig('transitions.enabled', true) || count($segmentFiles) <= 1) {
        $tsList = [];
        foreach ($segmentFiles as $file) {
            $tsFile = pathinfo($file, PATHINFO_DIRNAME) . '/' . pathinfo($file, PATHINFO_FILENAME) . '.ts';
            $cmd = "ffmpeg -i " . escapeshellarg($file) . " -c copy -bsf:v h264_mp4toannexb -f mpegts " . escapeshellarg($tsFile);
            exec($cmd);
            if (file_exists($tsFile)) {
                $tsList[] = $tsFile;
            }
        }
        
        if (empty($tsList)) {
            return false;
        }
        
        $concatList = implode('|', $tsList);
        $cmd = "ffmpeg -i \"concat:$concatList\" -c copy -bsf:a aac_adtstoasc " . escapeshellarg($outputFile);
        exec($cmd, $output, $returnCode);
        
        // Pulizia dei file TS temporanei
        foreach ($tsList as $ts) {
            if (file_exists($ts)) {
                unlink($ts);
            }
        }
        
        return $returnCode === 0 && file_exists($outputFile);
    }
    
    // Altrimenti, usa un approccio più avanzato con filtri complex
    $transitionType = getConfig('transitions.type', 'fade');
    $transitionDuration = getConfig('transitions.duration', 0.5);
    
    // Crea una directory temporanea per i file intermedi
    $tempDir = pathinfo($outputFile, PATHINFO_DIRNAME) . '/temp_transitions_' . uniqid();
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Crea una lista di file per la concatenazione
    $concatFile = "$tempDir/concat_list.txt";
    $concatContent = "";
    foreach ($segmentFiles as $file) {
        $concatContent .= "file '" . str_replace("'", "\\'", realpath($file)) . "'\n";
    }
    file_put_contents($concatFile, $concatContent);
    
    // Determina il filtro da utilizzare in base al tipo di transizione
    $filterCommand = "";
    
    switch ($transitionType) {
        case 'fade':
            // Utilizza il filtro xfade per le dissolvenze
            $filterCommand = "-filter_complex \"";
            for ($i = 0; $i < count($segmentFiles) - 1; $i++) {
                if ($i > 0) $filterCommand .= ";";
                $filterCommand .= "[$i:v][$i+1:v]xfade=transition=fade:duration=$transitionDuration:offset=" . ($i * 5) . "[v" . ($i+1) . "]";
            }
            $filterCommand .= "\" -map \"[v" . (count($segmentFiles)-1) . "]\" -map 0:a";
            break;
            
        case 'dissolve':
            // Utilizza il filtro xfade per le dissolvenze
            $filterCommand = "-filter_complex \"";
            for ($i = 0; $i < count($segmentFiles) - 1; $i++) {
                if ($i > 0) $filterCommand .= ";";
                $filterCommand .= "[$i:v][$i+1:v]xfade=transition=dissolve:duration=$transitionDuration:offset=" . ($i * 5) . "[v" . ($i+1) . "]";
            }
            $filterCommand .= "\" -map \"[v" . (count($segmentFiles)-1) . "]\" -map 0:a";
            break;
            
        case 'wipe':
            // Utilizza il filtro xfade per le transizioni a tendina
            $filterCommand = "-filter_complex \"";
            for ($i = 0; $i < count($segmentFiles) - 1; $i++) {
                if ($i > 0) $filterCommand .= ";";
                $filterCommand .= "[$i:v][$i+1:v]xfade=transition=wiperight:duration=$transitionDuration:offset=" . ($i * 5) . "[v" . ($i+1) . "]";
            }
            $filterCommand .= "\" -map \"[v" . (count($segmentFiles)-1) . "]\" -map 0:a";
            break;
            
        default:
            // Metodo semplice di concatenazione come fallback
            $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($concatFile) . " -c copy " . escapeshellarg($outputFile);
            exec($cmd, $output, $returnCode);
            return $returnCode === 0 && file_exists($outputFile);
    }
    
    // Esegui il comando con il filtro complex
    $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($concatFile) . " $filterCommand " . escapeshellarg($outputFile);
    exec($cmd, $output, $returnCode);
    
    // Pulizia dei file temporanei
    if (getConfig('system.cleanup_temp', true)) {
        if (file_exists($concatFile)) unlink($concatFile);
        // Opzionalmente, rimuovi la directory temporanea
        if (file_exists($tempDir)) rmdir($tempDir);
    }
    
    return $returnCode === 0 && file_exists($outputFile);
}
?>
