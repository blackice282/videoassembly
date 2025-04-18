<?php
// people_detection.php - Versione ottimizzata con rilevamento migliorato
require_once 'config.php';

/**
 * Versione migliorata per il rilevamento di persone nei video con priorità alle interazioni
 * 
 * @param string $videoPath Percorso del video da analizzare
 * @return array Risultato dell'operazione con i segmenti video
 */
function detectMovingPeople($videoPath) {
    // Crea directory temporanee
    $processId = uniqid();
    $tempDir = getConfig('paths.temp', 'temp') . "/detection_$processId";
    
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Ottieni la durata del video
    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . 
           escapeshellarg($videoPath);
    $duration = floatval(trim(shell_exec($cmd)));
    
    if ($duration <= 0) {
        return [
            'success' => false,
            'message' => 'Impossibile determinare la durata del video'
        ];
    }
    
    // Estrai fotogrammi per l'analisi dei volti e del movimento
    $framesDir = "$tempDir/frames";
    if (!file_exists($framesDir)) {
        mkdir($framesDir, 0777, true);
    }
    
    // Estrai un fotogramma ogni secondo per l'analisi
    $extractFramesCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
                        " -vf \"fps=1,scale=640:-1\" " . 
                        "$framesDir/frame_%04d.jpg";
    exec($extractFramesCmd);
    
    // Ottieni i totali dei volti nei fotogrammi usando un semplice algoritmo Haar Cascade
    // Creiamo un piccolo script Python per questo compito
    $pythonScript = <<<'EOT'
import sys
import os
import json
import cv2

# Verifica se siamo in un ambiente con OpenCV disponibile
try:
    # Carica il cascade detector per i volti
    face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
    
    # Directory con i fotogrammi
    frames_dir = sys.argv[1]
    output_file = sys.argv[2]
    
    # Analizza tutti i fotogrammi
    frames = sorted([f for f in os.listdir(frames_dir) if f.endswith('.jpg')])
    results = []
    
    print(f"Analizzando {len(frames)} fotogrammi...")
    for i, frame_name in enumerate(frames):
        # Estrai il numero di frame dal nome file
        frame_num = int(frame_name.split('_')[1].split('.')[0])
        
        # Carica il fotogramma
        frame_path = os.path.join(frames_dir, frame_name)
        frame = cv2.imread(frame_path)
        
        if frame is None:
            print(f"Impossibile leggere il fotogramma: {frame_path}")
            continue
        
        # Converti in scala di grigi per l'analisi
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        
        # Rileva i volti
        faces = face_cascade.detectMultiScale(gray, 1.1, 4)
        
        # Se trovati volti, aggiungi l'informazione
        faces_count = len(faces)
        
        # Calcola la dimensione media dei volti per stimare la vicinanza
        avg_face_size = 0
        if faces_count > 0:
            face_sizes = [w*h for (x, y, w, h) in faces]
            avg_face_size = sum(face_sizes) / len(face_sizes)
        
        # Calcola anche la quantità di movimento nel frame
        # (Confrontando con il frame precedente se disponibile)
        movement_score = 0
        if i > 0:
            prev_frame_path = os.path.join(frames_dir, frames[i-1])
            prev_frame = cv2.imread(prev_frame_path)
            if prev_frame is not None:
                # Rileva movimento con absdiff
                prev_gray = cv2.cvtColor(prev_frame, cv2.COLOR_BGR2GRAY)
                frame_diff = cv2.absdiff(prev_gray, gray)
                movement_score = cv2.countNonZero(cv2.threshold(frame_diff, 25, 255, cv2.THRESH_BINARY)[1])
        
        # Calcola l'importanza del frame basata su volti e movimento
        importance = 0
        if faces_count > 0:
            # Volti più grandi (più vicini) hanno più importanza
            face_importance = min(1.0, avg_face_size / 10000)
            # Più volti = più importanza
            count_importance = min(1.0, faces_count / 3)
            # Combina i fattori
            importance = 0.5 + (face_importance * 0.25) + (count_importance * 0.25)
        
        # Salva i risultati
        if faces_count > 0 or movement_score > 1000:
            results.append({
                "frame": frame_num,
                "time": frame_num,  # Assumendo 1 fps dall'estrazione
                "people_count": faces_count,
                "movement": movement_score,
                "importance": importance,
                "avg_face_size": avg_face_size
            })
    
    # Crea i segmenti basati sull'analisi dei fotogrammi
    segments = []
    if results:
        # Ordina per importanza (ci interessa in particolare i fotogrammi con più persone)
        results.sort(key=lambda x: (-x["people_count"], -x["importance"], x["time"]))
        
        # Prendi i segmenti più significativi
        # Prima i segmenti con 2 o più persone
        multiple_people_results = [r for r in results if r["people_count"] >= 2]
        
        # Poi i segmenti con una persona o movimento significativo
        single_person_results = [r for r in results if r["people_count"] == 1 and r["importance"] > 0.6]
        
        # Unisci e riordina per tempo
        prioritized_results = multiple_people_results + single_person_results
        prioritized_results.sort(key=lambda x: x["time"])
        
        # Converti in segmenti (unisci fotogrammi vicini)
        if prioritized_results:
            current_segment = {
                "start": prioritized_results[0]["time"] - 0.5,  # Inizia mezzo secondo prima
                "end": prioritized_results[0]["time"] + 1.5,    # Continua 1.5 secondi dopo
                "people_count": prioritized_results[0]["people
