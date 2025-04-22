<?php
// improved_face_privacy.php - Versione migliorata per applicare emoji sui volti

/**
 * Applica emoji sorridenti ai volti nel video, escludendo persone con pettorine gialle
 * 
 * @param string $videoPath Percorso del video di input
 * @param string $outputPath Percorso del video di output
 * @param bool $excludeYellowVests Se escludere le persone con pettorine gialle
 * @return bool Successo dell'operazione
 */
function applyFacePrivacy($videoPath, $outputPath, $excludeYellowVests = true) {
    // Verifica preliminare
    if (!file_exists($videoPath) || filesize($videoPath) <= 0) {
        if (function_exists('debugLog')) {
            debugLog("File video di input non valido: $videoPath", "error", "face-privacy");
        }
        return false;
    }
    
    // Verifica se Python e OpenCV sono disponibili
    if (!isOpenCVAvailable()) {
        if (function_exists('debugLog')) {
            debugLog("OpenCV non disponibile per la privacy dei volti", "error", "face-privacy");
        }
        return false;
    }
    
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
           escapeshellarg($videoPath) . " 2>&1";
    $probeOutput = shell_exec($cmd);
    
    if (function_exists('debugLog')) {
        debugLog("Output ffprobe: $probeOutput", "debug", "face-privacy");
    }
    
    $probeLines = explode("\n", trim($probeOutput));
    
    if (count($probeLines) < 2) {
        if (function_exists('debugLog')) {
            debugLog("Impossibile ottenere informazioni sul video", "error", "face-privacy");
        }
        return false;
    }
    
    // Calcola framerate
    $fpsRaw = $probeLines[0];
    list($numerator, $denominator) = explode('/', $fpsRaw);
    $fps = $numerator / $denominator;
    $duration = floatval($probeLines[1]);
    
    // Ottimizzazione: estrai un frame ogni secondo per video lunghi, 
    // o più frames per video corti (per una migliore qualità)
    $extractFrameRate = ($duration > 60) ? 1 : 2;
    $extractCmd = "ffmpeg -i " . escapeshellarg($videoPath) . 
                 " -vf \"fps=$extractFrameRate\" " . 
                 escapeshellarg("$framesDir/frame_%04d.jpg") . " 2>&1";
    
    if (function_exists('debugLog')) {
        debugLog("Comando estrazione frame: $extractCmd", "debug", "face-privacy");
    }
    
    exec($extractCmd, $extractOutput, $extractCode);
    
    if (function_exists('debugLog')) {
        debugLog("Output estrazione frame: " . implode("\n", array_slice($extractOutput, 0, 5)) . "...", "debug", "face-privacy");
    }
    
    if ($extractCode !== 0) {
        if (function_exists('debugLog')) {
            debugLog("Errore nell'estrazione dei frame: codice $extractCode", "error", "face-privacy");
        }
        return false;
    }
    
    // Verifica se sono stati estratti dei frame
    $extractedFrames = glob("$framesDir/frame_*.jpg");
    if (empty($extractedFrames)) {
        if (function_exists('debugLog')) {
            debugLog("Nessun frame estratto dal video", "error", "face-privacy");
        }
        return false;
    }
    
    if (function_exists('debugLog')) {
        debugLog("Estratti " . count($extractedFrames) . " frame dal video", "info", "face-privacy");
    }
    
    // Crea script Python per rilevamento volti ed esclusione pettorine gialle
    $pythonScript = <<<'EOT'
import sys
import os
import cv2
import numpy as np
import json

# Debug
print(f"Script Python avviato con argomenti: {sys.argv}")
print(f"Versione OpenCV: {cv2.__version__}")

# Directory con i frame
frames_dir = sys.argv[1]
processed_frames_dir = sys.argv[2]
exclude_yellow_vests = sys.argv[3].lower() == 'true'

print(f"Directory frames: {frames_dir}")
print(f"Directory output: {processed_frames_dir}")
print(f"Escludere pettorine gialle: {exclude_yellow_vests}")

# Verifica se le directory esistono
print(f"Directory frames esiste: {os.path.exists(frames_dir)}")
print(f"Directory output esiste: {os.path.exists(processed_frames_dir)}")

# Carica i classificatori di OpenCV
haar_cascade_path = cv2.data.haarcascades + 'haarcascade_frontalface_default.xml'
print(f"Percorso Haar cascade: {haar_cascade_path}")
print(f"File cascade esiste: {os.path.exists(haar_cascade_path)}")

face_cascade = cv2.CascadeClassifier(haar_cascade_path)
print(f"Classificatore caricato correttamente: {not face_cascade.empty()}")

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
print(f"Emoji sorriso creata: {smile_emoji.shape}")

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
print(f"Trovati {len(frames)} frame da elaborare")

results = []

for idx, frame_name in enumerate(frames):
    print(f"Elaborazione frame {idx+1}/{len(frames)}: {frame_name}")
    
    frame_path = os.path.join(frames_dir, frame_name)
    output_path = os.path.join(processed_frames_dir, frame_name)
    
    # Leggi il frame
    frame = cv2.imread(frame_path)
    if frame is None:
        print(f"Impossibile leggere il frame: {frame_path}")
        continue
    
    print(f"Frame shape: {frame.shape}")
    
    # Converti in scala di grigi per il rilevamento volti
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    
    # Rileva i volti - migliora il rilevamento con diversi fattori di scala
    faces = face_cascade.detectMultiScale(gray, 1.3, 5, minSize=(30, 30))
    
    # Se non vengono rilevati volti, prova una seconda volta con parametri diversi
    if len(faces) == 0:
        faces = face_cascade.detectMultiScale(gray, 1.1, 3, minSize=(20, 20))
    
    print(f"Rilevati {len(faces)} volti nel frame {frame_name}")
    
    # Per ogni volto rilevato
    for (x, y, w, h) in faces:
        # Controlla se la persona indossa una pettorina gialla
        is_yellow_vest = False
        if exclude_yellow_vests:
            try:
                is_yellow_vest = is_on_yellow_vest(frame, (x, y, w, h))
                print(f"Volto con pettorina gialla: {is_yellow_vest}")
            except Exception as e:
                print(f"Errore nella verifica della pettorina: {e}")
                is_yellow_vest = False
                
        if exclude_yellow_vests and is_yellow_vest:
            # Skip se indossa una pettorina gialla
            print("Saltato volto con pettorina gialla")
            continue
        
        try:
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
            
            print(f"Applicata emoji al volto ({x}, {y}, {w}, {h})")
        except Exception as e:
            print(f"Errore nell'applicazione dell'emoji: {e}")
    
    # Salva il frame elaborato
    try:
        cv2.imwrite(output_path, frame)
        print(f"Salvato frame elaborato: {output_path}")
    except Exception as e:
        print(f"Errore nel salvare il frame elaborato: {e}")
    
    # Aggiungi informazioni sul frame
    results.append({
        'frame': frame_name,
        'faces_detected': len(faces)
    })

# Salva i risultati per riferimento
with open(os.path.join(processed_frames_dir, 'results.json'), 'w') as f:
    json.dump(results, f)

print("Elaborazione completata")
EOT;
    
    $pythonFile = "$tempDir/face_detection.py";
    file_put_contents($pythonFile, $pythonScript);
    
    // Verifica se Python è disponibile
    exec("python3 --version 2>&1", $pythonVersionOutput, $pythonVersionCode);
    $pythonCmd = ($pythonVersionCode === 0) ? "python3" : "python";
    
    if (function_exists('debugLog')) {
        debugLog("Comando Python: $pythonCmd", "debug", "face-privacy");
    }
    
    // Esegui lo script Python
    $detectCmd = "$pythonCmd $pythonFile " . 
                escapeshellarg($framesDir) . " " . 
                escapeshellarg($processedFramesDir) . " " .
                ($excludeYellowVests ? "true" : "false") . " 2>&1";
    
    if (function_exists('debugLog')) {
        debugLog("Comando rilevamento volti: $detectCmd", "debug", "face-privacy");
    }
    
    exec($detectCmd, $detectOutput, $detectCode);
    
    if (function_exists('debugLog')) {
        debugLog("Output rilevamento volti: " . implode("\n", array_slice($detectOutput, 0, 10)) . "...", "debug", "face-privacy");
    }
    
    if ($detectCode !== 0) {
        if (function_exists('debugLog')) {
            debugLog("Errore nell'esecuzione dello script di rilevamento volti: codice $detectCode", "error", "face-privacy");
        }
        
        // Fallback: copia il video originale
        if (copy($videoPath, $outputPath)) {
            if (function_exists('debugLog')) {
                debugLog("Fallback: copiato video originale", "warning", "face-privacy");
            }
            return true;
        }
        return false;
    }
    
    // Verifica se ci sono frame elaborati
    $processedFrames = glob("$processedFramesDir/frame_*.jpg");
    if (empty($processedFrames)) {
        if (function_exists('debugLog')) {
            debugLog("Nessun frame elaborato trovato", "error", "face-privacy");
        }
        
        // Fallback: copia il video originale
        if (copy($videoPath, $outputPath)) {
            if (function_exists('debugLog')) {
                debugLog("Fallback: copiato video originale perché nessun frame è stato elaborato", "warning", "face-privacy");
            }
            return true;
        }
        return false;
    }
    
    if (function_exists('debugLog')) {
        debugLog("Trovati " . count($processedFrames) . " frame elaborati", "info", "face-privacy");
    }
    
    // Ricostruisci il video dai frame elaborati
    $rebuildCmd = "ffmpeg -y -framerate $fps -i " . 
                 escapeshellarg("$processedFramesDir/frame_%04d.jpg") . 
                 " -i " . escapeshellarg($videoPath) . 
                 " -map 0:v -map 1:a -c:v libx264 -preset ultrafast -crf 23 " . 
                 " -c:a copy " . escapeshellarg($outputPath) . " 2>&1";
    
    if (function_exists('debugLog')) {
        debugLog("Comando ricostruzione video: $rebuildCmd", "debug", "face-privacy");
    }
    
    exec($rebuildCmd, $rebuildOutput, $rebuildCode);
    
    if (function_exists('debugLog')) {
        debugLog("Output ricostruzione video: " . implode("\n", array_slice($rebuildOutput, 0, 10)) . "...", "debug", "face-privacy");
    }
    
    // Verifica se il video è stato creato correttamente
    if ($rebuildCode !== 0 || !file_exists($outputPath) || filesize($outputPath) <= 0) {
        if (function_exists('debugLog')) {
            debugLog("Errore nella ricostruzione del video: codice $rebuildCode", "error", "face-privacy");
        }
        
        // Tenta un approccio alternativo con ffmpeg
        $alternativeCmd = "ffmpeg -y -pattern_type glob -i " . 
                         escapeshellarg("$processedFramesDir/frame_*.jpg") . 
                         " -c:v libx264 -pix_fmt yuv420p -r $fps " . 
                         " -i " . escapeshellarg($videoPath) . 
                         " -map 0:v -map 1:a -c:a copy " . 
                         escapeshellarg($outputPath) . " 2>&1";
        
        if (function_exists('debugLog')) {
            debugLog("Tentativo alternativo ricostruzione video: $alternativeCmd", "debug", "face-privacy");
        }
        
        exec($alternativeCmd, $altOutput, $altCode);
        
        if (function_exists('debugLog')) {
            debugLog("Output tentativo alternativo: " . implode("\n", array_slice($altOutput, 0, 10)) . "...", "debug", "face-privacy");
        }
        
        if ($altCode !== 0 || !file_exists($outputPath) || filesize($outputPath) <= 0) {
            if (function_exists('debugLog')) {
                debugLog("Fallito anche il tentativo alternativo: codice $altCode", "error", "face-privacy");
            }
            
            // Ultimo tentativo: copia il video originale
            if (copy($videoPath, $outputPath)) {
                if (function_exists('debugLog')) {
                    debugLog("Fallback finale: copiato video originale", "warning", "face-privacy");
                }
                return true;
            }
            return false;
        }
    }
    
    if (function_exists('debugLog')) {
        debugLog("Video ricostruito con successo: $outputPath (" . filesize($outputPath) . " bytes)", "info", "face-privacy");
    }
    
    // Pulizia
    cleanupPrivacyTempFiles($tempDir);
    
    return true;
}

/**
 * Pulizia dei file temporanei
 * 
 * @param string $tempDir Directory temporanea da pulire
 */
function cleanupPrivacyTempFiles($tempDir) {
    if (!file_exists($tempDir)) {
        return;
    }
    
    // Usa un approccio più sicuro per la pulizia
    try {
        $framesDir = "$tempDir/frames";
        $processedFramesDir = "$tempDir/processed_frames";
        
        // Rimuovi i file nei sottodirectory
        if (file_exists($framesDir)) {
            $files = glob("$framesDir/*");
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($framesDir);
        }
        
        if (file_exists($processedFramesDir)) {
            $files = glob("$processedFramesDir/*");
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($processedFramesDir);
        }
        
        // Rimuovi i file nella directory principale
        $files = glob("$tempDir/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        rmdir($tempDir);
        
        if (function_exists('debugLog')) {
            debugLog("Directory temporanea pulita: $tempDir", "info", "face-privacy");
        }
    } catch (Exception $e) {
        if (function_exists('debugLog')) {
            debugLog("Errore durante la pulizia dei file temporanei: " . $e->getMessage(), "error", "face-privacy");
        }
    }
}

/**
 * Verifica la presenza di OpenCV in Python
 * 
 * @return bool Se OpenCV è disponibile
 */
function isOpenCVAvailable() {
    // Verifica se Python è disponibile
    exec("python3 --version 2>&1", $pythonVersionOutput, $pythonVersionCode);
    if ($pythonVersionCode !== 0) {
        exec("python --version 2>&1", $pythonVersionOutput, $pythonVersionCode);
        if ($pythonVersionCode !== 0) {
            if (function_exists('debugLog')) {
                debugLog("Python non è disponibile", "error", "face-privacy");
            }
            return false;
        }
    }
    
    // Determina il comando Python
    $pythonCmd = ($pythonVersionCode === 0 && strpos(implode("\n", $pythonVersionOutput), "Python 3") !== false) ? "python3" : "python";
    
    // Verifica OpenCV
    $checkCmd = "$pythonCmd -c 'import cv2; print(\"OpenCV è disponibile - versione\", cv2.__version__)' 2>&1";
    exec($checkCmd, $opencvOutput, $opencvReturnCode);
    
    $isAvailable = ($opencvReturnCode === 0);
    
    if (function_exists('debugLog')) {
        debugLog("Verifica OpenCV: " . ($isAvailable ? "disponibile" : "non disponibile"), "info", "face-privacy");
        if ($isAvailable) {
            debugLog("Output verifica OpenCV: " . implode("\n", $opencvOutput), "debug", "face-privacy");
        }
    }
    
    return $isAvailable;
}
