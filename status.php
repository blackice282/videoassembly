<?php
require_once 'config.php';
require_once 'ffmpeg_script.php';

// Controlla se è stato specificato un file da elaborare
$inputFile = isset($_GET['file']) ? $_GET['file'] : null;

if ($inputFile) {
    // Messaggio di inizio elaborazione (contenitore grigio chiaro)
    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>🔄 Elaborazione video...</strong><br>";
    
    // Esegue il processo di montaggio video
    $result = process_video($inputFile);
    
    if (!$result['success']) {
        // Chiude il contenitore grigio e mostra errore in contenitore rosso
        echo "</div>";
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; color: #721c24;'>";
        echo "<strong>⚠️ Errore:</strong><br>" . nl2br(htmlspecialchars($result['message']));
        echo "</div>";
    } else {
        // Chiude il contenitore grigio e mostra il risultato finale
        echo "</div>";
        $url = $result['video_url'];
        $thumbnailUrl = $result['thumbnail_url'];
        
        // Messaggio di montaggio completato
        echo "<br>🎉 <strong>Montaggio completato!</strong><br><br>";
        echo "<div style='display: flex; align-items: center; gap: 20px;'>";
        if (!empty($thumbnailUrl)) {
            echo "<img src='$thumbnailUrl' style='max-width: 200px; max-height: 120px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);'>";
        }
        echo "<a href='$url' download style='display: inline-block; background: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; font-weight: bold;'>";
        echo "  <svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' style='vertical-align: text-bottom; margin-right: 5px;' viewBox='0 0 16 16'>";
        echo "    <path d='M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z'/>";
        echo "    <path d='M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z'/>";
        echo "  </svg>";
        echo "  Scarica il video</a>";
        echo "</div>";
        
        // Informazioni su risoluzione e durata del video elaborato
        $baseUrl = rtrim(getConfig('system.base_url', ''), '/');
        $outputPath = '';
        if (!empty($baseUrl) && strpos($url, $baseUrl) === 0) {
            // Estrae il percorso locale del file a partire dall'URL
            $outputPath = substr($url, strlen($baseUrl));
            if (substr($outputPath, 0, 1) === '/') {
                $outputPath = substr($outputPath, 1);
            }
        } else {
            // Se base_url non è impostato o l'URL è relativo
            $outputPath = $url;
        }
        if (!empty($outputPath) && file_exists($outputPath)) {
            // Esegue ffprobe per ottenere width, height e durata
            $cmdInfo = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height,duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($outputPath);
            $info = shell_exec($cmdInfo);
            if (!empty($info)) {
                $lines = explode("\n", trim($info));
                if (count($lines) >= 3) {
                    $w = intval($lines[0]);
                    $h = intval($lines[1]);
                    $dur = floatval($lines[2]);
                    echo "<div style='margin-top: 15px; font-size: 14px; color: #666;'>";
                    echo "ℹ️ <strong>Informazioni video:</strong> {$w}x{$h} | Durata: " . gmdate("H:i:s", $dur);
                    echo "</div>";
                }
            }
        }
    }
} else {
    // Nessun file specificato: messaggio di avviso
    echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; color: #856404;'>";
    echo "<strong>⚠️ Nessun file specificato.</strong>";
    echo "</div>";
}
?>
