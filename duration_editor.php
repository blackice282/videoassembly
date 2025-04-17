<?php
// duration_editor.php - Gestisce la durata del video finale

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
    
    // Ottieni la durata di ciascun segmento
    $segments = [];
    $totalDuration = 0;
    
    foreach ($segmentFiles as $index => $file) {
        $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($file);
        $duration = floatval(trim(shell_exec($cmd)));
        
        if ($duration > 0) {
            // Inizializza con informazioni base
            $segmentData = [
                'file' => $file,
                'duration' => $duration,
                'importance' => 1.0, // Valore predefinito di importanza
                'people_count' => 1  // Valore predefinito di persone
            ];
            
            // Aggiungi informazioni aggiuntive se disponibili
            if (!empty($segmentsInfo) && isset($segmentsInfo[$index])) {
                if (isset($segmentsInfo[$index]['people_count'])) {
                    $segmentData['people_count'] = $segmentsInfo[$index]['people_count'];
                }
                
                // Calcola l'importanza in base al numero di persone (più persone = più importante)
                $segmentData['importance'] = min(2.0, sqrt($segmentData['people_count']));
            }
            
            $segments[] = $segmentData;
            $totalDuration += $duration;
        }
    }
    
    // Se non ci sono segmenti validi, restituisci un array vuoto
    if (empty($segments)) {
        return [];
    }
    
    // Se la durata totale è già uguale o minore alla durata target, restituisci i segmenti originali
    if ($totalDuration <= $targetDuration) {
        return $segmentFiles;
    }
    
    // Determina come adattare i segmenti
    $adjustmentMethod = getConfig('duration_editor.method', 'select_interactions');
    
    switch ($adjustmentMethod) {
        case 'trim':
            // Taglia uniformemente tutti i segmenti
            return trimSegmentsProportionally($segments, $targetDuration, $outputDirectory);
            
        case 'select':
            // Seleziona i segmenti più importanti (default)
            return selectBestSegments($segments, $targetDuration, $outputDirectory);
            
        case 'speed':
            // Accelera uniformemente tutti i segmenti
            return adjustSegmentsSpeed($segments, $targetDuration, $outputDirectory);
            
        case 'select_interactions':
            // Seleziona i segmenti con più interazioni tra persone
            return selectSegmentsWithInteractions($segments, $targetDuration, $outputDirectory);
            
        default:
            // Metodo predefinito: select_interactions
            return selectSegmentsWithInteractions($segments, $targetDuration, $outputDirectory);
    }
}

/**
 * Taglia proporzionalmente i segmenti per raggiungere la durata desiderata
 */
function trimSegmentsProportionally($segments, $targetDuration, $outputDirectory) {
    // Calcola la durata totale corrente
    $totalDuration = array_sum(array_column($segments, 'duration'));
    
    // Calcola il fattore di scala
    $scaleFactor = $targetDuration / $totalDuration;
    
    // Crea i segmenti tagliati
    $outputFiles = [];
    
    foreach ($segments as $segment) {
        $newDuration = $segment['duration'] * $scaleFactor;
        $outputFile = "$outputDirectory/trimmed_" . basename($segment['file']);
        
        // Taglia il segmento alla nuova durata
        $cmd = "ffmpeg -i " . escapeshellarg($segment['file']) . 
               " -t $newDuration -c copy " . 
               escapeshellarg($outputFile);
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($outputFile)) {
            $outputFiles[] = $outputFile;
        }
    }
    
    return $outputFiles;
}

/**
 * Seleziona i segmenti con più interazioni tra persone
 * Dà priorità ai segmenti con più persone e poi ordina per durata
 */
function selectSegmentsWithInteractions($segments, $targetDuration, $outputDirectory) {
    // Ordina i segmenti per:
    // 1. Numero di persone (più persone = più priorità, indica più interazione)
    // 2. Importanza
    // 3. Durata (preferendo segmenti più corti per massimizzare la varietà)
    usort($segments, function($a, $b) {
        // Prima confronta per numero di persone (discendente)
        if ($a['people_count'] != $b['people_count']) {
            return $b['people_count'] - $a['people_count'];
        }
        
        // Poi per importanza (discendente)
        if ($a['importance'] != $b['importance']) {
            return $b['importance'] - $a['importance'];
        }
        
        // Infine per durata (ascendente - preferisce segmenti più brevi)
        return $a['duration'] - $b['duration'];
    });
    
    // Seleziona i segmenti fino a raggiungere la durata desiderata
    $selectedFiles = [];
    $currentDuration = 0;
    
    // Prima passa: includi tutti i segmenti con 3 o più persone se possibile
    foreach ($segments as $key => $segment) {
        if ($segment['people_count'] >= 3) {
            if ($currentDuration + $segment['duration'] <= $targetDuration) {
                $selectedFiles[] = $segment['file'];
                $currentDuration += $segment['duration'];
                unset($segments[$key]); // Rimuovi questo segmento dalla lista
            }
        }
    }
    
    // Reindizza l'array dopo rimozione elementi
    $segments = array_values($segments);
    
    // Seconda passa: includi segmenti con 2 persone
    foreach ($segments as $key => $segment) {
        if ($segment['people_count'] == 2) {
            if ($currentDuration + $segment['duration'] <= $targetDuration) {
                $selectedFiles[] = $segment['file'];
                $currentDuration += $segment['duration'];
                unset($segments[$key]); // Rimuovi questo segmento dalla lista
            }
        }
    }
    
    // Reindizza l'array dopo rimozione elementi
    $segments = array_values($segments);
    
    // Terza passa: includi i segmenti rimanenti in ordine di importanza
    foreach ($segments as $segment) {
        if ($currentDuration + $segment['duration'] <= $targetDuration) {
            $selectedFiles[] = $segment['file'];
            $currentDuration += $segment['duration'];
        } else {
            // Se aggiungere l'intero segmento supererebbe la durata target,
            // possiamo decidere se tagliarlo o saltarlo
            $remainingDuration = $targetDuration - $currentDuration;
            
            // Se resta almeno il 30% della durata del segmento, lo includiamo tagliato
            if ($remainingDuration >= ($segment['duration'] * 0.3)) {
                $trimmedFile = "$outputDirectory/trimmed_" . basename($segment['file']);
                
                // Taglia il segmento per adattarlo al tempo rimanente
                $cmd = "ffmpeg -i " . escapeshellarg($segment['file']) . 
                       " -t $remainingDuration -c copy " . 
                       escapeshellarg($trimmedFile);
                
                exec($cmd, $output, $returnCode);
                
                if ($returnCode === 0 && file_exists($trimmedFile)) {
                    $selectedFiles[] = $trimmedFile;
                    $currentDuration += $remainingDuration;
                }
            }
            
            // Abbiamo raggiunto la durata desiderata, possiamo interrompere
            break;
        }
    }
    
    return $selectedFiles;
}

/**
 * Seleziona i segmenti migliori per raggiungere la durata desiderata
 */
function selectBestSegments($segments, $targetDuration, $outputDirectory) {
    // Ordina i segmenti per importanza e poi per durata
    usort($segments, function($a, $b) {
        if ($a['importance'] != $b['importance']) {
            return $b['importance'] - $a['importance']; // Prima per importanza (discendente)
        }
        return $a['duration'] - $b['duration']; // Poi per durata (ascendente, preferendo segmenti più corti)
    });
    
    // Seleziona i segmenti fino a raggiungere la durata desiderata
    $selectedFiles = [];
    $currentDuration = 0;
    
    foreach ($segments as $segment) {
        if ($currentDuration + $segment['duration'] <= $targetDuration) {
            $selectedFiles[] = $segment['file'];
            $currentDuration += $segment['duration'];
        } else {
            // Se aggiungere l'intero segmento supererebbe la durata target,
            // possiamo decidere se tagliarlo o saltarlo
            $remainingDuration = $targetDuration - $currentDuration;
            
            // Se resta almeno il 30% della durata del segmento, lo includiamo tagliato
            if ($remainingDuration >= ($segment['duration'] * 0.3)) {
                $trimmedFile = "$outputDirectory/trimmed_" . basename($segment['file']);
                
                $cmd = "ffmpeg -i " . escapeshellarg($segment['file']) . 
                       " -t $remainingDuration -c copy " . 
                       escapeshellarg($trimmedFile);
                
                exec($cmd, $output, $returnCode);
                
                if ($returnCode === 0 && file_exists($trimmedFile)) {
                    $selectedFiles[] = $trimmedFile;
                    $currentDuration += $remainingDuration;
                }
            }
            
            // Abbiamo raggiunto la durata desiderata, possiamo interrompere
            break;
        }
    }
    
    return $selectedFiles;
}

/**
 * Modifica la velocità dei segmenti per raggiungere la durata desiderata
 */
function adjustSegmentsSpeed($segments, $targetDuration, $outputDirectory) {
    // Calcola la durata totale corrente
    $totalDuration = array_sum(array_column($segments, 'duration'));
    
    // Calcola il fattore di velocità necessario
    $speedFactor = $totalDuration / $targetDuration;
    
    // Limita il fattore di velocità a un range ragionevole
    $speedFactor = min(max($speedFactor, 0.5), 2.0);
    
    // Crea i segmenti con velocità modificata
    $outputFiles = [];
    
    foreach ($segments as $segment) {
        $outputFile = "$outputDirectory/speed_" . basename($segment['file']);
        
        // Modifica la velocità del segmento
        $cmd = "ffmpeg -i " . escapeshellarg($segment['file']) . 
               " -filter_complex \"[0:v]setpts=" . (1/$speedFactor) . "*PTS[v];[0:a]atempo=$speedFactor[a]\" " .
               "-map \"[v]\" -map \"[a]\" " . 
               escapeshellarg($outputFile);
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($outputFile)) {
            $outputFiles[] = $outputFile;
        } else {
            // Fallback: se la modifica della velocità fallisce, usa il file originale
            $outputFiles[] = $segment['file'];
        }
    }
    
    return $outputFiles;
}

/**
 * Calcola la durata totale di un insieme di file video
 */
function calculateTotalDuration($videoFiles) {
    $totalDuration = 0;
    
    foreach ($videoFiles as $file) {
        $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($file);
        $duration = floatval(trim(shell_exec($cmd)));
        
        if ($duration > 0) {
            $totalDuration += $duration;
        }
    }
    
    return $totalDuration;
}

/**
 * Conta il numero di persone in un frame video utilizzando un detector preaddestrato
 * Questa funzione può essere chiamata per analizzare più a fondo i segmenti
 */
function analyzeInteractions($videoFile, $outputDirectory) {
    $frameDir = "$outputDirectory/interaction_frames_" . uniqid();
    if (!file_exists($frameDir)) {
        mkdir($frameDir, 0777, true);
    }
    
    // Estrai alcuni frame dal video (1 frame ogni X secondi)
    $cmd = "ffmpeg -i " . escapeshellarg($videoFile) . " -vf fps=0.5 $frameDir/frame_%04d.jpg";
    exec($cmd);
    
    $peopleCount = 0;
    $totalFrames = 0;
    
    // Analizza i frame con OpenCV (questo richiederebbe uno script Python simile a people_detection.php)
    // Per ora, usiamo una stima basata sui valori già presenti nei segmenti
    // In una implementazione completa, chiameremmo uno script Python simile a quello in people_detection.php
    
    return [
        'avg_people' => $peopleCount > 0 ? $peopleCount / max(1, $totalFrames) : 1,
        'max_people' => $peopleCount > 0 ? $peopleCount : 1
    ];
}
?>
