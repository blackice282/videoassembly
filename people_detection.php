<?php
// people_detection.php
require_once 'config.php';

/**
 * Rileva persone in movimento nei video utilizzando OpenCV
 * 
 * @param string $videoPath Percorso del video da analizzare
 * @return array Risultato dell'operazione con i segmenti video
 */
function detectMovingPeople($videoPath) {
    // Crea directory temporanee se non esistono
    $processId = uniqid();
    $tempDir = getConfig('paths.temp') . "/detection_$processId";
    
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // 1. Estrai informazioni sul video
    $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height,duration,r_frame_rate -of json " . escapeshellarg($videoPath);
    $videoInfoJson = shell_exec($cmd);
    
    if (!$videoInfoJson) {
        return [
            'success' => false,
            'message' => 'Impossibile leggere le informazioni del video'
        ];
    }
    
    $videoInfo = json_decode($videoInfoJson, true);
    
    // Calcola FPS dalle informazioni del frame rate (es. "24/1")
    $fpsRaw = $videoInfo['streams'][0]['r_frame_rate'];
    list($numerator, $denominator) = explode('/', $fpsRaw);
    $fps = $numerator / $denominator;
    
    $duration = floatval($videoInfo['streams'][0]['duration']);
    
    // Ottieni configurazioni di rilevamento
    $frameRate = getConfig('detection.frame_rate', 1);
    $minDuration = getConfig('detection.min_duration', 1);
    $maxGap = getConfig('detection.max_gap', 2);
    
    // 2. Estrai fotogrammi per analisi
    $framesDir = "$tempDir/frames";
    if (!file_exists($framesDir)) {
        mkdir($framesDir, 0777, true);
    }
    
    $extractFramesCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
                      " -vf fps=$frameRate $framesDir/frame_%04d.jpg";
    exec($extractFramesCmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        return [
            'success' => false,
            'message' => 'Errore nell\'estrazione dei fotogrammi'
        ];
    }
    
    // 3. Utilizza Python con OpenCV per il rilevamento
    // Salva il seguente script Python in un file temporaneo
    $confidenceThreshold = getConfig('detection.confidence', 0.5);
    
    $pythonScript = <<<EOT
import cv2
import os
import json
import sys
import numpy as np

# Directory con i fotogrammi e parametri
frames_dir = sys.argv[1]
output_file = sys.argv[2]
confidence_threshold = float(sys.argv[3])
max_gap = int(sys.argv[4])
min_duration = int(sys.argv[5])
frame_rate = float(sys.argv[6])

print(f"Analisi con parametri: conf={confidence_threshold}, max_gap={max_gap}, min_dur={min_duration}")

# Inizializza il rilevatore di persone HOG
hog = cv2.HOGDescriptor()
hog.setSVMDetector(cv2.HOGDescriptor_getDefaultPeopleDetector())

# Per video più grandi/complessi, si potrebbe usare un rilevatore basato su deep learning
# Nota: questo richiederebbe l'installazione di modelli aggiuntivi
# Esempio di implementazione alternativa commentata:
"""
# Inizializza il rilevatore SSD con MobileNet
net = cv2.dnn.readNetFromCaffe(
    'models/MobileNetSSD_deploy.prototxt',
    'models/MobileNetSSD_deploy.caffemodel'
)
"""

# Analizza tutti i fotogrammi
frames = sorted([f for f in os.listdir(frames_dir) if f.endswith('.jpg')])
results = []

print(f"Trovati {len(frames)} fotogrammi da analizzare")

for i, frame_name in enumerate(frames):
    # Tempo stimato del fotogramma (in secondi)
    time_sec = i / frame_rate
    
    frame_path = os.path.join(frames_dir, frame_name)
    frame = cv2.imread(frame_path)
    
    if frame is None:
        print(f"Impossibile leggere il fotogramma: {frame_path}")
        continue
    
    # Rileva persone con HOG
    boxes, weights = hog.detectMultiScale(
        frame, 
        winStride=(8, 8), 
        padding=(4, 4), 
        scale=1.05
    )
    
    # Filtra i risultati per confidenza
    people_boxes = [box for box, weight in zip(boxes, weights) if weight > confidence_threshold]
    
    # Se trovate persone, salva il timestamp
    if len(people_boxes) > 0:
        results.append({
            "time": time_sec,
            "people_count": len(people_boxes)
        })
        print(f"Fotogramma {i}: trovate {len(people_boxes)} persone al tempo {time_sec:.2f}s")

# Converti i timestamp in segmenti (unisci timestamp vicini)
segments = []
if results:
    current_segment = {"start": results[0]["time"], "end": results[0]["time"] + (1/frame_rate)}
    
    for r in results[1:]:
        # Se il timestamp è continuo con il segmento corrente, estendi il segmento
        if r["time"] <= current_segment["end"] + max_gap:
            current_segment["end"] = r["time"] + (1/frame_rate)
        else:
            # Altrimenti, chiudi il segmento corrente e iniziane uno nuovo
            if (current_segment["end"] - current_segment["start"]) >= min_duration:
                segments.append(current_segment)
            current_segment = {"start": r["time"], "end": r["time"] + (1/frame_rate)}
    
    # Aggiungi l'ultimo segmento se abbastanza lungo
    if (current_segment["end"] - current_segment["start"]) >= min_duration:
        segments.append(current_segment)

print(f"Generati {len(segments)} segmenti con persone")

# Salva i risultati
with open(output_file, 'w') as f:
    json.dump(segments, f)
EOT;

    $pythonFile = "$tempDir/detect_people.py";
    file_put_contents($pythonFile, $pythonScript);
    
    // Output dei segmenti rilevati
    $segmentsFile = "$tempDir/segments.json";
    
    // Esegui lo script Python con i parametri di configurazione
    $detectCmd = "python3 $pythonFile " . 
                escapeshellarg($framesDir) . " " . 
                escapeshellarg($segmentsFile) . " " .
                $confidenceThreshold . " " .
                $maxGap . " " .
                $minDuration . " " . 
                $frameRate;
    
    // Se debug è attivo, registra l'output del comando
    if (getConfig('system.debug', false)) {
        $logFile = "$tempDir/detection_log.txt";
        $detectCmd .= " 2>&1 | tee " . escapeshellarg($logFile);
    }
    
    exec($detectCmd, $pythonOutput, $pythonReturnCode);
    
    if ($pythonReturnCode !== 0) {
        return [
            'success' => false,
            'message' => 'Errore nell\'esecuzione del rilevamento: ' . implode("\n", $pythonOutput)
        ];
    }
    
    // Controlla se il file dei segmenti esiste
    if (!file_exists($segmentsFile)) {
        return [
            'success' => false,
            'message' => 'Errore nel rilevamento delle persone (file segmenti non trovato)'
        ];
    }
    
    // Leggi i segmenti rilevati
    $segments = json_decode(file_get_contents($segmentsFile), true);
    
    // Se non ci sono segmenti con persone, restituisci un avviso
    if (empty($segments)) {
        return [
            'success' => false,
            'message' => 'Nessuna persona rilevata nel video'
        ];
    }
    
    // 4. Estrai i segmenti con persone in movimento
    $segmentFiles = [];
    foreach ($segments as $index => $segment) {
        $start = $segment['start'];
        $duration = $segment['end'] - $segment['start'];
        
        // Arrotonda per evitare problemi di precisione
        $start = round($start, 2);
        $duration = round($duration, 2);
        
        // Estrai il segmento video con la qualità configurata
        $videoCodec = getConfig('ffmpeg.video_codec', 'libx264');
        $audioCodec = getConfig('ffmpeg.audio_codec', 'aac');
        $videoCRF = getConfig('ffmpeg.video_quality', '23');
        $resolution = getConfig('ffmpeg.resolution', '');
        
        $segmentFile = "$tempDir/segment_$index.mp4";
        
        $extractCmd = "ffmpeg -ss $start -i " . escapeshellarg($videoPath);
        
        // Aggiungi parametro di risoluzione se specificato
        if (!empty($resolution)) {
            $extractCmd .= " -vf scale=$resolution";
        }
        
        $extractCmd .= " -t $duration -c:v $videoCodec -crf $videoCRF -c:a $audioCodec -strict experimental " . 
                     escapeshellarg($segmentFile);
        
        exec($extractCmd, $extractOutput, $extractReturnCode);
        
        if ($extractReturnCode !== 0) {
            // Logga l'errore ma continua con gli altri segmenti
            if (getConfig('system.debug', false)) {
                file_put_contents("$tempDir/extract_error_$index.log", implode("\n", $extractOutput));
            }
            continue;
        }
        
        // Aggiungi alla lista dei segmenti
        if (file_exists($segmentFile)) {
            $segmentFiles[] = $segmentFile;
        }
    }
    
    // Se non ci sono segmenti estratti con successo
    if (empty($segmentFiles)) {
        return [
            'success' => false,
            'message' => 'Nessun segmento video con persone è stato estratto correttamente'
        ];
    }
    
    return [
        'success' => true,
        'segments' => $segmentFiles,
        'temp_dir' => $tempDir,
        'segments_info' => $segments,
        'segments_count' => count($segmentFiles)
    ];
}
