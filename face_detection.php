<?php
// face_detection.php - Gestisce il rilevamento dei volti e l'applicazione di emoji

/**
 * Applica emoji sorridenti ai volti nel video, escludendo persone con pettorine gialle
 * 
 * @param string $videoPath Percorso del video di input
 * @param string $outputPath Percorso del video di output
 * @param bool $excludeYellowVests Se escludere le persone con pettorine gialle
 * @return bool Successo dell'operazione
 */
function applyFacePrivacy($videoPath, $outputPath, $excludeYellowVests = true) {
    // Crea directory temporanea
    $tempDir = dirname($outputPath) . "/face_detection_" . uniqid();
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    // Estrai frames per l'analisi
    $framesDir = "$tempDir/frames";
    $processedFramesDir = "$tempDir/processed_frames";
    
    if (!file_exists($framesDir)) {
        mkdir($framesDir, 0777, true);
    }
    
    if (!file_exists($processedFramesDir)) {
        mkdir($processedFramesDir, 0777, true);
    }
    
    // Ottieni framerate e durata del video
    $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=r_frame_rate,duration -of default=noprint_wrappers=1:nokey=1 " . 
           escapeshellarg($videoPath);
    $probeOutput = shell_exec($cmd);
    $probeLines = explode("\n", trim($probeOutput));
    
    if (count($probeLines) < 2) {
        error_log("Impossibile ottenere informazioni sul video");
        return false;
    }
    
    // Calcola framerate
    $fpsRaw = $probeLines[0];
    list($numerator, $denominator) = explode('/', $fpsRaw);
    $fps = $numerator / $denominator;
    $duration = floatval($probeLines[1]);
    
    // Estrai 1 frame ogni secondo per velocizzare
    $extractFrameRate = 1;
    $extractCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
                 " -vf \"fps=$extractFrameRate\" " . 
                 "$framesDir/frame_%04d.jpg";
    exec($extractCmd, $extractOutput, $extractCode);
    
    if ($extractCode !== 0) {
        error_log("Errore nell'estrazione dei frame");
        return false;
    }
    
    // Crea script Python per rilevamento volti ed esclusione pettorine gialle
    $pythonScript = <<<'EOT'
import sys
import os
import cv2
import numpy as np
import json

# Directory con i frame
frames_dir = sys.argv[1]
processed_frames_dir = sys.argv[2]
exclude_yellow_vests = sys.argv[3].lower() == 'true'

# Carica i classificatori di OpenCV
face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')

# Soglie per il colore giallo delle pettorine
lower_yellow = np.array([20, 100, 100])  # HSV
upper_yellow = np.array([40, 255, 255])  # HSV

# Carica l'emoji del sorriso (risorsa inclusa in OpenCV o percorso locale)
# Se non disponibile, crea un'emoji semplice usando OpenCV
def create_smile_emoji(size=50):
    # Crea un'immagine circolare gialla con un sorriso
    emoji = np.zeros((size, size, 3), dtype=np.uint8)
    emoji[:] = (255, 255, 255)  # Sfondo bianco
    
    # Disegna faccia gialla
    cv2.circle(emoji, (size//2, size//2), size//2, (0, 255, 255), -1)
    
    # Disegna occhi
    eye_size = size // 10
    left_eye_center = (size//3, size//3)
    right_eye_center = (2*size//3, size//3)
    cv2.circle(emoji, left_eye_center, eye_size, (0, 0, 0), -1)
    cv2.circle(emoji, right_eye_center, eye_size, (0, 0, 0), -1)
    
    # Disegna sorriso
    smile_start = (size//4, 2*size//3)
    smile_end = (3*size//4, 2*size//3)
    cv2.ellipse(emoji, (size//2, size//2), (size//4, size//4), 
                0, 0, 180, (0, 0, 0), eye_size//2)
    
    return emoji

# Crea l'emoji
smile_emoji = create_smile_emoji()

# Funzione per rilevare se un volto è su una pettorina gialla
def is_on_yellow_vest(frame, face_rect):
    x, y, w, h = face_rect
    
    # Espandi leggermente il rettangolo verso il basso per includere il torso
    torso_y = min(y + h + h//2, frame.shape[0])
    torso_h = max(h, (torso_y - (y + h)))
    
    # Regione di interesse per il torso
    roi = frame[y+h:torso_y, max(0, x-w//4):min(frame.shape[1], x+w+w//4)]
    
    if roi.size == 0:  # Controlla se la ROI è vuota
        return False
        
    # Converti in HSV per rilevare il giallo
    hsv_roi = cv2.cvtColor(roi, cv2.COLOR_BGR2HSV)
    
    # Crea una maschera per il colore giallo
    yellow_mask = cv2.inRange(hsv_roi, lower_yellow, upper_yellow)
    
    # Calcola la percentuale di pixel gialli
    yellow_percentage = np.count_nonzero(yellow_mask) / roi.size * 100
    
    # Se più del 30% dei pixel sono gialli, consideriamo che la persona indossa una pettorina
    return yellow_percentage > 30

# Elabora tutti i frame
frames = sorted([f for f in os.listdir(frames_dir) if f.endswith('.jpg')])
results = []

for frame_name in frames:
    frame_path = os.path.join(frames_dir, frame_name)
    output_path = os.path.join(processed_frames_dir, frame_name)
    
    # Leggi il frame
    frame = cv2.imread(frame_path)
    if frame is None:
        print(f"Impossibile leggere il frame: {frame_path}")
        continue
    
    # Converti in scala di grigi per il rilevamento volti
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    
    # Rileva i volti
    faces = face_cascade.detectMultiScale(gray, 1.3, 5)
    
    # Per ogni volto rilevato
    for (x, y, w, h) in faces:
        # Controlla se la persona indossa una pettorina gialla
        if exclude_yellow_vests and is_on_yellow_vest(frame, (x, y, w, h)):
            # Skip se indossa una pettorina gialla
            continue
        
        # Ridimensiona l'emoji per adattarla alle dimensioni del volto
        resized_emoji = cv2.resize(smile_emoji, (w, h))
        
        # Regione del frame dove inserire l'emoji
        roi = frame[y:y+h, x:x+w]
        
        # Crea una maschera per l'emoji
        emoji_gray = cv2.cvtColor(resized_emoji, cv2.COLOR_BGR2GRAY)
        _, mask = cv2.threshold(emoji_gray, 10, 255, cv2.THRESH_BINARY)
        mask_inv = cv2.bitwise_not(mask)
        
        # Regione nera nell'emoji dove mettere il volto
        emoji_bg = cv2.bitwise_and(resized_emoji, resized_emoji, mask=mask)
        
        # Regione del volto da conservare
        face_fg = cv2.bitwise_and(roi, roi, mask=mask_inv)
        
        # Combina l'emoji con il volto
        dst = cv2.add(face_fg, emoji_bg)
        frame[y:y+h, x:x+w] = dst
    
    # Salva il frame elaborato
    cv2.imwrite(output_path, frame)
    
    # Aggiungi informazioni sul frame
    results.append({
        'frame': frame_name,
        'faces_detected': len(faces)
    })

# Salva i risultati per riferimento
with open(os.path.join(processed_frames_dir, 'results.json'), 'w') as f:
    json.dump(results, f)
EOT;
    
    $pythonFile = "$tempDir/face_detection.py";
    file_put_contents($pythonFile, $pythonScript);
    
    // Verifica se Python è disponibile
    exec("python3 --version 2>&1", $pythonVersionOutput, $pythonVersionCode);
    $pythonCmd = ($pythonVersionCode === 0) ? "python3" : "python";
    
    // Esegui lo script Python
    $detectCmd = "$pythonCmd $pythonFile " . 
                escapeshellarg($framesDir) . " " . 
                escapeshellarg($processedFramesDir) . " " .
                ($excludeYellowVests ? "true" : "false");
    
    exec($detectCmd, $detectOutput, $detectCode);
    
    if ($detectCode !== 0) {
        error_log("Errore nell'esecuzione dello script di rilevamento volti");
        
        // Fallback: copia il video originale
        if (copy($videoPath, $outputPath)) {
            return true;
        }
        return false;
    }
    
    // Ricostruisci il video dai frame elaborati
    $rebuildCmd = "ffmpeg -framerate $fps -i " . 
                 escapeshellarg("$processedFramesDir/frame_%04d.jpg") . 
                 " -i " . escapeshellarg($videoPath) . 
                 " -map 0:v -map 1:a -c:v libx264 -crf 23 -preset fast " . 
                 " -c:a copy " . escapeshellarg($outputPath);
    
    exec($rebuildCmd, $rebuildOutput, $rebuildCode);
    
    // Pulizia
    if (file_exists($tempDir)) {
        array_map('unlink', glob("$framesDir/*"));
        array_map('unlink', glob("$processedFramesDir/*"));
        rmdir($framesDir);
        rmdir($processedFramesDir);
        rmdir($tempDir);
    }
    
    return $rebuildCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0;
}

/**
 * Verifica la presenza di OpenCV in Python
 * 
 * @return bool Se OpenCV è disponibile
 */
function isOpenCVAvailable() {
    $checkCmd = "python3 -c 'import cv2; print(\"OpenCV is available\")' 2>/dev/null";
    exec($checkCmd, $output, $returnCode);
    
    return $returnCode === 0;
}
?>
