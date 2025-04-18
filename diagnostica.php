<?php
// diagnostica.php - Strumento diagnostico per VideoAssembly

require_once 'config.php';
require_once 'audio_manager.php';
require_once 'video_effects.php';
require_once 'face_detection.php';

/**
 * Verifica il funzionamento dei comandi FFmpeg e registra output dettagliato
 */
function testFFmpegCommand($command, $logPath = null) {
    // Se non specificato, crea un percorso di log
    if ($logPath === null) {
        $logDir = 'logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logPath = $logDir . '/ffmpeg_debug_' . date('Ymd_His') . '.log';
    }
    
    // Aggiungi livello di debug al comando
    $debugCommand = str_replace('ffmpeg ', 'ffmpeg -v debug ', $command);
    
    // Esegui il comando e cattura l'output
    $output = [];
    $returnCode = 0;
    exec($debugCommand . " 2>&1", $output, $returnCode);
    
    // Salva l'output in un file di log
    file_put_contents($logPath, implode("\n", $output));
    
    return [
        'command' => $debugCommand,
        'return_code' => $returnCode,
        'log_path' => $logPath,
        'success' => $returnCode === 0,
        'output_summary' => implode("\n", array_slice($output, -10)) // Ultimi 10 messaggi
    ];
}

/**
 * Testa la funzione di applicazione effetti video
 */
function testVideoEffect($testVideoPath, $effectName) {
    // Verifica l'esistenza del file di input
    if (!file_exists($testVideoPath)) {
        return ['success' => false, 'message' => 'File video di input non trovato'];
    }
    
    // Ottieni la funzione degli effetti
    $effects = getVideoEffects();
    if (!isset($effects[$effectName])) {
        return ['success' => false, 'message' => 'Effetto non trovato'];
    }
    
    // Crea directory temporanea per test
    $testDir = 'temp/effect_test_' . uniqid();
    if (!file_exists($testDir)) {
        mkdir($testDir, 0777, true);
    }
    
    // Prepara i percorsi
    $outputPath = "$testDir/test_effect.mp4";
    $logPath = "$testDir/effect_log.txt";
    
    // Crea manualmente il comando FFmpeg
    $effect = $effects[$effectName];
    $filter = $effect['filter'];
    
    $cmd = "ffmpeg -i " . escapeshellarg($testVideoPath) . 
           " -vf \"$filter\" -c:v libx264 -preset ultrafast -crf 28 -c:a copy " . 
           escapeshellarg($outputPath);
    
    // Esegui il test
    $result = testFFmpegCommand($cmd, $logPath);
    
    // Aggiungi informazioni aggiuntive
    $result['effect_name'] = $effectName;
    $result['effect_filter'] = $filter;
    $result['output_file'] = $outputPath;
    
    if ($result['success'] && file_exists($outputPath) && filesize($outputPath) > 0) {
        $result['output_size'] = filesize($outputPath);
        $result['message'] = 'Effetto applicato con successo';
    } else {
        $result['message'] = 'Errore nell\'applicazione dell\'effetto';
    }
    
    return $result;
}

/**
 * Testa la funzione di applicazione audio di sottofondo
 */
function testBackgroundAudio($testVideoPath, $category, $volume = 0.3) {
    // Verifica l'esistenza del file di input
    if (!file_exists($testVideoPath)) {
        return ['success' => false, 'message' => 'File video di input non trovato'];
    }
    
    // Ottieni un audio casuale dalla categoria
    $audio = getRandomAudioFromCategory($category);
    if (!$audio) {
        return ['success' => false, 'message' => 'Nessun audio trovato nella categoria specificata'];
    }
    
    // Crea directory temporanea per test
    $testDir = 'temp/audio_test_' . uniqid();
    if (!file_exists($testDir)) {
        mkdir($testDir, 0777, true);
    }
    
    // Prepara i percorsi
    $audioPath = "$testDir/test_audio.mp3";
    $outputPath = "$testDir/test_with_audio.mp4";
    $logPath = "$testDir/audio_log.txt";
    
    // Scarica l'audio
    $downloadSuccess = downloadAudio($audio['url'], $audioPath);
    if (!$downloadSuccess) {
        return ['success' => false, 'message' => 'Impossibile scaricare l\'audio'];
    }
    
    // Ottieni la durata del video
    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . 
           escapeshellarg($testVideoPath);
    $videoDuration = floatval(trim(shell_exec($cmd)));
    
    // Crea il comando FFmpeg semplificato
    $cmd = "ffmpeg -i " . escapeshellarg($testVideoPath) . 
           " -stream_loop -1 -i " . escapeshellarg($audioPath) . 
           " -filter_complex \"[1:a]volume=" . $volume . "[music];[0:a][music]amix=inputs=2:duration=first\" " .
           " -c:v copy -c:a aac -b:a 128k -shortest " . 
           escapeshellarg($outputPath);
    
    // Esegui il test
    $result = testFFmpegCommand($cmd, $logPath);
    
    // Aggiungi informazioni aggiuntive
    $result['audio_name'] = $audio['name'];
    $result['audio_url'] = $audio['url'];
    $result['volume'] = $volume;
    $result['output_file'] = $outputPath;
    
    if ($result['success'] && file_exists($outputPath) && filesize($outputPath) > 0) {
        $result['output_size'] = filesize($outputPath);
        $result['message'] = 'Audio aggiunto con successo';
    } else {
        $result['message'] = 'Errore nell\'aggiunta dell\'audio';
    }
    
    return $result;
}

/**
 * Verifica la presenza di OpenCV in Python e diagnostica problemi
 */
function diagnosticFacePrivacy() {
    $result = ['success' => false];
    
    // Controlla la versione di Python
    exec("python3 --version 2>&1", $pythonVersionOutput, $pythonVersionCode);
    $pythonCmd = ($pythonVersionCode === 0) ? "python3" : "python";
    
    $result['python_available'] = ($pythonVersionCode === 0);
    $result['python_version'] = implode("\n", $pythonVersionOutput);
    $result['python_command'] = $pythonCmd;
    
    // Controlla se OpenCV è installato
    $checkCmd = "$pythonCmd -c 'import cv2; print(\"OpenCV version:\", cv2.__version__)' 2>&1";
    exec($checkCmd, $output, $returnCode);
    
    $result['opencv_available'] = ($returnCode === 0);
    $result['opencv_output'] = implode("\n", $output);
    
    // Controlla i percorsi di harrcascade
    if ($result['opencv_available']) {
        $cascadeCmd = "$pythonCmd -c 'import cv2; import os; print(os.path.exists(cv2.data.haarcascades + \"haarcascade_frontalface_default.xml\"))' 2>&1";
        exec($cascadeCmd, $cascadeOutput, $cascadeCode);
        
        $result['cascade_available'] = (trim(implode("\n", $cascadeOutput)) === "True");
        $result['cascade_output'] = implode("\n", $cascadeOutput);
    }
    
    // Controlla se possiamo creare un semplice script di test
    $testScript = "temp/face_test.py";
    $scriptContent = <<<EOT
import cv2
import sys

# Test basic OpenCV functionality
print("OpenCV version:", cv2.__version__)
print("Haar cascade path:", cv2.data.haarcascades + "haarcascade_frontalface_default.xml")

# Try to load the face classifier
face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
print("Classifier loaded:", face_cascade.empty() == False)

# Create a simple test image
test_img = cv2.imread(cv2.samples.findFile('lena.jpg')) if len(sys.argv) < 2 else cv2.imread(sys.argv[1])
if test_img is None:
    # Create a blank image if no test image is available
    test_img = cv2.rectangle(cv2.cvtColor(cv2.imread(cv2.data.haarcascades + "haarcascade_frontalface_default.xml"), cv2.COLOR_BGR2GRAY), (10, 10), (100, 100), (255), -1)
    print("Using generated test image")
else:
    print("Using sample image")

# Convert to grayscale
gray = cv2.cvtColor(test_img, cv2.COLOR_BGR2GRAY) if len(test_img.shape) > 2 else test_img

# Run detection
faces = face_cascade.detectMultiScale(gray, 1.3, 5)
print("Faces detected:", len(faces))

# Success
print("Test completed successfully")
EOT;

    file_put_contents($testScript, $scriptContent);
    
    // Esegui lo script di test
    $testCmd = "$pythonCmd $testScript 2>&1";
    exec($testCmd, $testOutput, $testCode);
    
    $result['test_success'] = ($testCode === 0);
    $result['test_output'] = implode("\n", $testOutput);
    
    // Risultato complessivo
    $result['success'] = $result['opencv_available'] && $result['test_success'];
    $result['message'] = $result['success'] ? 
                         "OpenCV è disponibile e funzionante" : 
                         "Problemi con l'installazione di OpenCV";
    
    return $result;
}

/**
 * Genera un report diagnostico completo su tutti gli effetti
 */
function generateEffectsReport() {
    $report = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ffmpeg' => [],
        'video_effects' => [],
        'audio' => [],
        'face_privacy' => []
    ];
    
    // Verifica FFmpeg
    exec("ffmpeg -version 2>&1", $ffmpegOutput, $ffmpegCode);
    $report['ffmpeg']['available'] = ($ffmpegCode === 0);
    $report['ffmpeg']['version'] = implode("\n", array_slice($ffmpegOutput, 0, 1));
    
    // Crea un video di test se necessario
    $testVideo = 'temp/test_video.mp4';
    if (!file_exists($testVideo)) {
        $testCmd = "ffmpeg -f lavfi -i testsrc=duration=5:size=640x480:rate=30 -c:v libx264 -crf 25 $testVideo";
        exec($testCmd);
    }
    
    // Testa un effetto video
    if (file_exists($testVideo)) {
        $report['video_effects'] = testVideoEffect($testVideo, 'vintage');
    }
    
    // Testa l'audio di sottofondo
    if (file_exists($testVideo)) {
        $report['audio'] = testBackgroundAudio($testVideo, 'relax');
    }
    
    // Testa il rilevamento volti
    $report['face_privacy'] = diagnosticFacePrivacy();
    
    return $report;
}

/**
 * Genera HTML con i risultati della diagnostica
 */
function generateDiagnosticHTML($report) {
    $html = '<div style="font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; line-height: 1.6;">';
    $html .= '<h2>Diagnostica Effetti Video</h2>';
    $html .= '<p><strong>Timestamp:</strong> ' . $report['timestamp'] . '</p>';
    
    // FFmpeg
    $html .= '<div style="margin: 20px 0; padding: 15px; border-radius: 5px; background: ' . 
             ($report['ffmpeg']['available'] ? '#e8f5e9' : '#ffebee') . ';">';
    $html .= '<h3>FFmpeg</h3>';
    $html .= '<p><strong>Disponibile:</strong> ' . ($report['ffmpeg']['available'] ? '✅ Sì' : '❌ No') . '</p>';
    if ($report['ffmpeg']['available']) {
        $html .= '<p><strong>Versione:</strong> ' . htmlspecialchars($report['ffmpeg']['version']) . '</p>';
    }
    $html .= '</div>';
    
    // Effetti Video
    $html .= '<div style="margin: 20px 0; padding: 15px; border-radius: 5px; background: ' . 
             (isset($report['video_effects']['success']) && $report['video_effects']['success'] ? '#e8f5e9' : '#ffebee') . ';">';
    $html .= '<h3>Effetti Video</h3>';
    if (isset($report['video_effects']['success'])) {
        $html .= '<p><strong>Test eseguito:</strong> ' . ($report['video_effects']['success'] ? '✅ Successo' : '❌ Fallito') . '</p>';
        $html .= '<p><strong>Effetto testato:</strong> ' . htmlspecialchars($report['video_effects']['effect_name']) . '</p>';
        $html .= '<p><strong>Filtro:</strong> <code>' . htmlspecialchars($report['video_effects']['effect_filter']) . '</code></p>';
        $html .= '<p><strong>Risultato:</strong> ' . htmlspecialchars($report['video_effects']['message']) . '</p>';
    } else {
        $html .= '<p>Test non eseguito.</p>';
    }
    $html .= '</div>';
    
    // Audio
    $html .= '<div style="margin: 20px 0; padding: 15px; border-radius: 5px; background: ' . 
             (isset($report['audio']['success']) && $report['audio']['success'] ? '#e8f5e9' : '#ffebee') . ';">';
    $html .= '<h3>Audio di Sottofondo</h3>';
    if (isset($report['audio']['success'])) {
        $html .= '<p><strong>Test eseguito:</strong> ' . ($report['audio']['success'] ? '✅ Successo' : '❌ Fallito') . '</p>';
        if (isset($report['audio']['audio_name'])) {
            $html .= '<p><strong>Audio testato:</strong> ' . htmlspecialchars($report['audio']['audio_name']) . '</p>';
        }
        $html .= '<p><strong>Risultato:</strong> ' . htmlspecialchars($report['audio']['message']) . '</p>';
    } else {
        $html .= '<p>Test non eseguito.</p>';
    }
    $html .= '</div>';
    
    // Privacy Volti
    $html .= '<div style="margin: 20px 0; padding: 15px; border-radius: 5px; background: ' . 
             ($report['face_privacy']['success'] ? '#e8f5e9' : '#ffebee') . ';">';
    $html .= '<h3>Privacy dei Volti</h3>';
    $html .= '<p><strong>Test eseguito:</strong> ' . ($report['face_privacy']['success'] ? '✅ Successo' : '❌ Fallito') . '</p>';
    $html .= '<p><strong>Python disponibile:</strong> ' . ($report['face_privacy']['python_available'] ? '✅ Sì' : '❌ No') . '</p>';
    $html .= '<p><strong>OpenCV disponibile:</strong> ' . ($report['face_privacy']['opencv_available'] ? '✅ Sì' : '❌ No') . '</p>';
    if (isset($report['face_privacy']['cascade_available'])) {
        $html .= '<p><strong>Haar Cascade disponibile:</strong> ' . ($report['face_privacy']['cascade_available'] ? '✅ Sì' : '❌ No') . '</p>';
    }
    $html .= '<p><strong>Risultato:</strong> ' . htmlspecialchars($report['face_privacy']['message']) . '</p>';
    $html .= '</div>';
    
    // Soluzioni consigliate
    $html .= '<div style="margin: 20px 0; padding: 15px; border-radius: 5px; background: #f5f5f5;">';
    $html .= '<h3>Soluzioni Consigliate</h3>';
    
    if (!$report['ffmpeg']['available']) {
        $html .= '<div style="margin-bottom: 15px;">';
        $html .= '<h4>FFmpeg non disponibile</h4>';
        $html .= '<p>Installa FFmpeg con i seguenti comandi:</p>';
        $html .= '<pre style="background: #eee; padding: 10px; border-radius: 4px;">sudo apt update<br>sudo apt install ffmpeg</pre>';
        $html .= '</div>';
    }
    
    if (isset($report['video_effects']['success']) && !$report['video_effects']['success']) {
        $html .= '<div style="margin-bottom: 15px;">';
        $html .= '<h4>Problemi con gli effetti video</h4>';
        $html .= '<p>Verifica che FFmpeg supporti i filtri richiesti:</p>';
        $html .= '<pre style="background: #eee; padding: 10px; border-radius: 4px;">ffmpeg -filters | grep -E "colorbalance|curves|eq"</pre>';
        $html .= '</div>';
    }
    
    if (isset($report['audio']['success']) && !$report['audio']['success']) {
        $html .= '<div style="margin-bottom: 15px;">';
        $html .= '<h4>Problemi con l\'audio di sottofondo</h4>';
        $html .= '<p>Prova il seguente comando alternativo:</p>';
        $html .= '<pre style="background: #eee; padding: 10px; border-radius: 4px;">ffmpeg -i input.mp4 -i audio.mp3 -filter_complex "[1:a]volume=0.3[a1];[0:a][a1]amix=inputs=2:duration=first" -c:v copy output.mp4</pre>';
        $html .= '</div>';
    }
    
    if (!$report['face_privacy']['success']) {
        $html .= '<div style="margin-bottom: 15px;">';
        $html .= '<h4>Problemi con la privacy dei volti</h4>';
        $html .= '<p>Installa OpenCV per Python:</p>';
        $html .= '<pre style="background: #eee; padding: 10px; border-radius: 4px;">pip install opencv-python</pre>';
        $html .= '<p>o</p>';
        $html .= '<pre style="background: #eee; padding: 10px; border-radius: 4px;">pip3 install opencv-python</pre>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    // Pulsante per tornare alla pagina principale
    $html .= '<a href="index.php" style="display: inline-block; background: #4CAF50; color: white; padding: 10px 15px; border-radius: 4px; text-decoration: none; margin-top: 20px;">Torna alla pagina principale</a>';
    
    $html .= '</div>';
    return $html;
}

// Esegui diagnostica
$report = generateEffectsReport();

// Visualizza report HTML
echo "<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Diagnostica VideoAssembly</title>
</head>
<body>
    " . generateDiagnosticHTML($report) . "
</body>
</html>";
