<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';   // per detectMovingPeople()
require_once 'duration_editor.php';    // per adaptSegmentsToDuration()

function createUploadsDir(): void {
    $u = getConfig('paths.uploads','uploads');
    $t = getConfig('paths.temp','temp');
    if (!file_exists($u)) mkdir($u,0777,true);
    if (!file_exists($t)) mkdir($t,0777,true);
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    createUploadsDir();
    set_time_limit(0);

    // --- Parametri da form ---
    $mode       = $_POST['mode'] ?? 'simple';
    $targetDur  = (!empty($_POST['duration']) && is_numeric($_POST['duration']))
                  ? intval($_POST['duration'])*60
                  : 0;
    $audioPath  = !empty($_POST['audio'])
                  ? realpath(__DIR__.'/musica/'.basename($_POST['audio']))
                  : null;
    $tickerText = trim($_POST['ticker_text'] ?? '');

    // --- Upload e generazione .ts ---
    echo "<div style='padding:10px;background:#eef;'><strong>🔄 Elaborazione...</strong><br>";
    $uploaded = [];
    $tsFiles  = [];
    $segments = [];

    foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
        if ($_FILES['files']['error'][$i]===UPLOAD_ERR_OK) {
            $name = basename($_FILES['files']['name'][$i]);
            $dest = getConfig('paths.uploads','uploads')."/$name";
            if (move_uploaded_file($tmp,$dest)) {
                echo "✅ Caricato: $name<br>";
                $uploaded[] = $dest;
                // se detect_people, raccolgo segmenti
                if ($mode==='detect_people') {
                    $r = detectMovingPeople($dest);
                    if ($r['success']) {
                        $segments = array_merge($segments,$r['segments']);
                    }
                } else {
                    // video o immagine
                    $ext = strtolower(pathinfo($name,PATHINFO_EXTENSION));
                    $ts  = pathinfo($name,PATHINFO_FILENAME).'.ts';
                    $out = getConfig('paths.uploads','uploads')."/$ts";
                    if (in_array($ext,['jpg','jpeg','png','gif'])) {
                        convertImageToTs($dest,$out);
                    } else {
                        convertToTs($dest,$out);
                    }
                    $tsFiles[] = $out;
                }
            }
        }
    }
    echo "</div>";

    // --- Se detect_people, produco .ts da segmenti ---
    if ($mode==='detect_people' && count($segments)>0) {
        if ($targetDur>0) {
            $segments = adaptSegmentsToDuration($segments,$targetDur);
        }
        foreach ($segments as $idx=>$seg) {
            $tsOut = getConfig('paths.uploads','uploads')."/seg{$idx}.ts";
            convertToTs($seg,$tsOut);
            $tsFiles[] = $tsOut;
        }
    }

    // --- Concatenazione finale ---
    if (count($tsFiles)>1) {
        $outfile = getConfig('paths.uploads','uploads')
                 .'/video_'.date('Ymd_His').'.mp4';
        concatenateTsFiles($tsFiles,$outfile,$audioPath,$tickerText);

        $fn  = basename($outfile);
        $dir = getConfig('paths.uploads','uploads');
        echo "<br><strong>✅ Montaggio finito:</strong> "
           ."<a href=\"{$dir}/{$fn}\" download>Scarica il video</a>";

        // alert audio + visivo
        echo '<audio src="/musica/finish.mp3" autoplay></audio>';
        echo '<script>alert("🎉 Montaggio completato!");</script>';

        cleanupTempFiles($tsFiles);
    } else {
        echo "<br>⚠️ Carica almeno due file (video o immagini).";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>🎬 VideoAssembly</title>
  <style>
    body { font-family:Arial,sans-serif; background:#f4f4f4; padding:20px; max-width:800px; margin:auto; }
    h1 { text-align:center; color:#333; }
    .upload-container { background:#fff; padding:20px; border-radius:5px; border:2px dashed #ccc; }
    .options { background:#f8f9fa; padding:15px; margin-top:15px; border-radius:5px; }
    .option-group { margin-bottom:15px; }
    button { background:#4CAF50; color:white; padding:12px 20px; border:none; border-radius:4px; cursor:pointer; font-size:16px; }
    button:hover { background:#45a049; }
  </style>
  <script>
    function previewAudio(file){
      const a=document.getElementById('audioPreview');
      if(file){ a.src='musica/'+encodeURIComponent(file); a.style.display='block'; a.load(); }
      else a.style.display='none';
    }
  </script>
</head>
<body>
  <h1>🎬 VideoAssembly</h1>
  <div class="upload-container">
    <form method="POST" enctype="multipart/form-data">
      <div class="option-group">
        <label>📂 Carica file (video o immagini):</label>
        <input type="file" name="files[]" multiple accept="video/*,image/*" required>
      </div>
      <div class="options">
        <div class="option-group">
          <label>⚙️ Modalità:</label><br>
          <label><input type="radio" name="mode" value="simple" checked> Montaggio semplice</label>
          <label><input type="radio" name="mode" value="detect_people"> Rilevamento persone</label>
        </div>
        <div class="option-group">
          <label>⏱️ Durata massima (min):</label>
          <select name="duration">
            <option value="0">Originale</option>
            <option value="1">1</option>
            <option value="3">3</option>
            <option value="5">5</option>
            <option value="10">10</option>
            <option value="15">15</option>
          </select>
        </div>
        <div class="option-group">
          <label>🎵 Musica di sottofondo:</label>
          <select name="audio" onchange="previewAudio(this.value)">
            <option value="">-- Nessuna --</option>
            <?php
              $m=__DIR__.'/musica';
              if(is_dir($m)){
                foreach(scandir($m) as $f){
                  if(preg_match('/\.(mp3|wav)$/i',$f)){
                    echo "<option value=\"$f\">$f</option>";
                  }
                }
              }
            ?>
          </select>
          <audio id="audioPreview" controls style="display:none;margin-top:10px;"></audio>
        </div>
        <div class="option-group">
          <label>📝 Testo ticker (opzionale):</label>
          <input type="text" name="ticker_text" placeholder="Messaggio che scorre">
        </div>
      </div>
      <button type="submit">🚀 Carica e Monta</button>
    </form>
  </div>
</body>
</html>
