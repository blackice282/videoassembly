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

# Inizializza sia il rilevatore HOG che il rilevatore basato su rete neurale (Yolo/SSD) se disponibile
hog = cv2.HOGDescriptor()
hog.setSVMDetector(cv2.HOGDescriptor_getDefaultPeopleDetector())

# Flag per indicare se è disponibile un rilevatore basato su deep learning
has_deep_detector = False

# Prova a caricare un rilevatore YOLO se disponibile
yolo_paths = {
    'config': 'models/yolov4.cfg',
    'weights': 'models/yolov4.weights',
    'classes': 'models/coco.names'
}

try:
    # Verifica se i file del modello esistono
    if all(os.path.exists(p) for p in yolo_paths.values()):
        # Carica il modello YOLO
        net = cv2.dnn.readNetFromDarknet(yolo_paths['config'], yolo_paths['weights'])
        
        # Carica le classi
        with open(yolo_paths['classes'], 'r') as f:
            classes = [line.strip() for line in f.readlines()]
        
        # Trova l'indice della classe "person"
        person_class_id = classes.index('person') if 'person' in classes else 0
        
        # Imposta alcuni parametri
        layer_names = net.getLayerNames()
        output_layers = [layer_names[i - 1] for i in net.getUnconnectedOutLayers()]
        
        has_deep_detector = True
        print("Rilevatore YOLO caricato con successo!")
    else:
        print("File del modello YOLO non trovati. Verrà utilizzato solo HOG.")
except Exception as e:
    print(f"Errore nel caricare YOLO: {e}")
    print("Verrà utilizzato solo HOG per il rilevamento delle persone.")

# Analizza tutti i fotogrammi
frames = sorted([f for f in os.listdir(frames_dir) if f.endswith('.jpg')])
results = []

print(f"Trovati {len(frames)} fotogrammi da analizzare")

# Funzione per rilevare persone con HOG
def detect_with_hog(frame):
    # Resize per migliorare prestazioni/precisione (opzionale)
    height, width = frame.shape[:2]
    if width > 800:
        scale = 800 / width
        frame = cv2.resize(frame, (0, 0), fx=scale, fy=scale)
    
    # Rileva persone con HOG
    boxes, weights = hog.detectMultiScale(
        frame, 
        winStride=(8, 8), 
        padding=(4, 4), 
        scale=1.05
    )
    
    # Riscala le box alle dimensioni originali se necessario
    if width > 800:
        boxes = [[int(x/scale), int(y/scale), int(w/scale), int(h/scale)] for (x, y, w, h) in boxes]
    
    # Filtra per confidenza
    people_boxes = [box for box, weight in zip(boxes, weights) if weight > confidence_threshold]
    return people_boxes

# Funzione per rilevare persone con YOLO
def detect_with_yolo(frame):
    height, width = frame.shape[:2]
    
    # Prepara l'immagine per il modello
    blob = cv2.dnn.blobFromImage(frame, 0.00392, (416, 416), (0, 0, 0), True, crop=False)
    net.setInput(blob)
    outs = net.forward(output_layers)
    
    # Informazioni rilevamento
    class_ids = []
    confidences = []
    boxes = []
    
    # Per ogni uscita
    for out in outs:
        for detection in out:
            scores = detection[5:]
            class_id = np.argmax(scores)
            confidence = scores[class_id]
            
            # Filtra solo persone (classe 0) con confidenza sufficiente
            if class_id == person_class_id and confidence > confidence_threshold:
                # Coordinate del bounding box
                center_x = int(detection[0] * width)
                center_y = int(detection[1] * height)
                w = int(detection[2] * width)
                h = int(detection[3] * height)
                
                # Coordinate del rettangolo
                x = int(center_x - w / 2)
                y = int(center_y - h / 2)
                
                boxes.append([x, y, w, h])
                confidences.append(float(confidence))
                class_ids.append(class_id)
    
    # Applica non-max suppression per eliminare box duplicate
    indexes = cv2.dnn.NMSBoxes(boxes, confidences, confidence_threshold, 0.4)
    result_boxes = []
    
    for i in range(len(boxes)):
        if i in indexes:
            result_boxes.append(boxes[i])
            
    return result_boxes

# Per ogni fotogramma, prova entrambi i metodi di rilevamento
for i, frame_name in enumerate(frames):
    # Tempo stimato del fotogramma (in secondi)
    time_sec = i / frame_rate
    
    frame_path = os.path.join(frames_dir, frame_name)
    frame = cv2.imread(frame_path)
    
    if frame is None:
        print(f"Impossibile leggere il fotogramma: {frame_path}")
        continue
    
    # Prima prova con HOG (più veloce ma meno preciso)
    people_boxes = detect_with_hog(frame)
    
    # Se HOG non trova nulla e abbiamo il rilevatore deep learning, proviamo con quello
    if len(people_boxes) == 0 and has_deep_detector:
        people_boxes = detect_with_yolo(frame)
    
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
    current_segment = {"start": results[0]["time"], "end": results[0]["time"] + (1/frame_rate), "people_count": results[0]["people_count"]}
    
    for r in results[1:]:
        # Se il timestamp è continuo con il segmento corrente, estendi il segmento
        if r["time"] <= current_segment["end"] + max_gap:
            current_segment["end"] = r["time"] + (1/frame_rate)
            # Aggiorna il conteggio persone con il valore massimo
            current_segment["people_count"] = max(current_segment["people_count"], r["people_count"])
        else:
            # Altrimenti, chiudi il segmento corrente e iniziane uno nuovo
            if (current_segment["end"] - current_segment["start"]) >= min_duration:
                segments.append(current_segment)
            current_segment = {"start": r["time"], "end": r["time"] + (1/frame_rate), "people_count": r["people_count"]}
    
    # Aggiungi l'ultimo segmento se abbastanza lungo
    if (current_segment["end"] - current_segment["start"]) >= min_duration:
        segments.append(current_segment)

# Aggiungi margini ai segmenti (prima e dopo) per catturare meglio il movimento
expanded_segments = []
for segment in segments:
    # Aggiungi un secondo prima e dopo ogni segmento, ma non andare oltre i limiti del video
    expanded_start = max(0, segment["start"] - 1)
    # Qui assumiamo che la durata del video sia disponibile; se non lo è, useremo un valore alto
    expanded_end = segment["end"] + 1  # Aggiungi 1 secondo alla fine
    
    # Verifica se il segmento si sovrappone a uno precedente
    if expanded_segments and expanded_start <= expanded_segments[-1]["end"]:
        # Unisci con il segmento precedente
        expanded_segments[-1]["end"] = max(expanded_segments[-1]["end"], expanded_end)
        expanded_segments[-1]["people_count"] = max(expanded_segments[-1]["people_count"], segment["people_count"])
    else:
        # Aggiungi come nuovo segmento
        expanded_segments.append({
            "start": expanded_start,
            "end": expanded_end,
            "people_count": segment["people_count"]
        })

# Sostituisci i segmenti originali con quelli espansi
segments = expanded_segments

# Ordina i segmenti per tempo di inizio
segments.sort(key=lambda x: x["start"])

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
?>
