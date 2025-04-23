<?php
// duration_editor.php - Versione ottimizzata
require_once 'config.php';

/**
 * Adatta i segmenti per ottenere un video della durata desiderata
 * 
 * @param array $segmentFiles Array dei file dei segmenti
 * @param int $targetDuration Durata desiderata in secondi
 * @param string $outputDirectory Directory per i file temporanei
 * @param array $segmentsInfo Informazioni aggiuntive sui segmenti (persone, ecc.)
 * @return array File dei segmenti adattati
 */
function adaptSegmentsToDuration($segmentFiles, $targetDuration, $outputDirectory, $segmentsInfo = []) {
    if (empty($segmentFiles)) {
        return [];
    }
    
    // Crea la directory di output se non esiste
    if (!file_exists($outputDirectory)) {
        mkdir($outputDirectory, 0777, true);
    }
    
    // Ottieni la durata di ciascun segmento (ottimizzato)
    $segments = [];
    $totalDuration = 0;
    
    // Ottimizzazione: se abbiamo meno di 10 segmenti, elaborali tutti; altrimenti campiona
    $processAll = count($segmentFiles) <= 10;
    $sampleStep = $processAll ? 1 : ceil(count($segmentFiles) / 10);
    
    for ($i = 0; $i < count($segmentFiles); $i += $sampleStep) {
        $file = $segmentFiles[$i];
        
        // Ottimizzazione: usiamo un comando più leggero per ottenere la durata
        $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($file);
        $duration = floatval(trim(shell_exec($cmd)));
        
        if ($duration > 0) {
            // Inizializza con informazioni base
            $segmentData = [
                'file' => $file,
                'duration' => $duration,
                'importance' => 1.0, // Valore predefinito
                'people_count' => 1  // Valore predefinito
            ];
            
            // Aggiungi info aggiuntive se disponibili
            if (!empty($segmentsInfo) && isset($segmentsInfo[$i])) {
                if (isset($segmentsInfo[$i]['people_count'])) {
                    $segmentData['people_count'] = $segmentsInfo[$i]['people_count'];
                }
                if (isset($segmentsInfo[$i]['importance'])) {
                    $segmentData['importance'] = $segmentsInfo[$i]['importance'];
                } else {
                    // Calcola importanza in base al numero di persone
                    $segmentData['importance'] = min(2.0, sqrt($segmentData['people_count']));
                }
            }
            
            $segments[] = $segmentData;
            $totalDuration += $duration;
        }
    }
    
    // Se non ci sono segmenti validi, restituisci l'array originale
    if (empty($segments)) {
        return $segmentFiles;
    }
    
    // Se la durata è già inferiore o uguale al target, restituisci l'array originale
    if ($totalDuration <= $targetDuration) {
        return $segmentFiles;
    }
    
    // Metodo di adattamento (simplificato)
    $adjustmentMethod = getConfig('duration_editor.method', 'select_interactions');
    
    // Ottimizzazione: usa sempre il metodo di selezione che è più veloce
    return selectBestSegmentsOptimized($segments, $segmentFiles, $targetDuration, $outputDirectory, $adjustmentMethod);
}

/**
 * Versione ottimizzata per selezionare i migliori segmenti
 */
function selectBestSegmentsOptimized($segments, $originalFiles, $targetDuration, $outputDirectory, $method = 'select_interactions') {
    // Ordinamento più efficiente in base al metodo
    if ($method === 'select_interactions') {
        // Ordina prima per numero di persone, poi per importanza
        usort($segments, function($a, $b) {
            if ($a['people_count'] != $b['people_count']) {
                return $b['people_count'] - $a['people_count']; // Discendente
            }
            return $b['importance'] - $a['importance']; // Discendente
        });
    } else {
        // Ordina semplicemente per importanza
        usort($segments, function($a, $b) {
            return $b['importance'] - $a['importance']; // Discendente
        });
    }
    
    // Seleziona i segmenti fino a raggiungere la durata desiderata
    $selectedFiles = [];
    $currentDuration = 0;
    
    foreach ($segments as $segment) {
        if ($currentDuration + $segment['duration'] <= $targetDuration) {
            $selectedFiles[] = $segment['file'];
            $currentDuration += $segment['duration'];
        } else {
            // Se abbiamo già almeno 3 segmenti, non tagliamo l'ultimo (per velocità)
            if (count($selectedFiles) < 3) {
                // Se abbiamo meno di 3 segmenti, possiamo considerare il taglio dell'ultimo segmento
                $remainingDuration = $targetDuration - $currentDuration;
                
                // Solo se resta almeno il 40% della durata, altrimenti diventa troppo breve
                if ($remainingDuration >= ($segment['duration'] * 0.4)) {
                    $trimmedFile = "$outputDirectory/trimmed_" . basename($segment['file']);
                    
                    // Ottimizzazione: usa un preset più veloce per la codifica
                    $cmd = "ffmpeg -i " . escapeshellarg($segment['file']) . 
                           " -t $remainingDuration -c:v libx264 -preset ultrafast -crf 28 -c:a aac " . 
                           escapeshellarg($trimmedFile);
                    
                    exec($cmd, $output, $returnCode);
                    
                    if ($returnCode === 0 && file_exists($trimmedFile)) {
                        $selectedFiles[] = $trimmedFile;
                        $currentDuration += $remainingDuration;
                    }
                }
            }
            
            // Abbiamo raggiunto la durata desiderata
            break;
        }
    }
    
    // Se non abbiamo selezionato nulla (improbabile), restituisci file originali
    if (empty($selectedFiles)) {
        return array_slice($originalFiles, 0, 3); // Restituisci al massimo 3 file
    }
    
    return $selectedFiles;
}

/**
 * Calcola la durata totale di un insieme di file video
 * Versione ottimizzata che stima la durata per set più grandi
 */
function calculateTotalDuration($videoFiles) {
    $totalDuration = 0;
    
    // Se ci sono meno di 5 file, calcola la durata esatta
    // Altrimenti, campiona e stima
    $filesToProcess = count($videoFiles) <= 5 ? $videoFiles : 
                    array_intersect_key($videoFiles, array_flip(array_rand($videoFiles, min(5, count($videoFiles)))));
    
    foreach ($filesToProcess as $file) {
        $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($file);
        $duration = floatval(trim(shell_exec($cmd)));
        
        if ($duration > 0) {
            $totalDuration += $duration;
        }
    }
    
    // Se abbiamo campionato, stimiamo la durata totale
    if (count($filesToProcess) < count($videoFiles)) {
        $averageDuration = $totalDuration / count($filesToProcess);
        $totalDuration = $averageDuration * count($videoFiles);
    }
    
    return $totalDuration;
}
?>
