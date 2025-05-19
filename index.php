<?php
// Inclusione dipendenze
require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';
require_once 'transitions.php';
require_once 'duration_editor.php';

// Creazione directory
function createUploadsDir() {
    if (!file_exists(getConfig('paths.uploads','uploads'))) mkdir(getConfig('paths.uploads','uploads'),0777,true);
    if (!file_exists(getConfig('paths.temp','temp'))) mkdir(getConfig('paths.temp','temp'),0777,true);
}

// Utility di conversione
function convertToTs($input,$output) {
    shell_exec("ffmpeg -i "$input" -c copy -bsf:v h264_mp4toannexb -f mpegts "$output"");
}

// Integrazione audio di sottofondo
$musicaDir = __DIR__ . '/musica';
$filesAudio = glob($musicaDir.'/*.mp3');
$emozionali=[]; $divertenti=[];
foreach($filesAudio as $f){
    $b = basename($f);
    if(stripos($b,'emozionale')===0) $emozionali[]=$b;
    elseif(stripos($b,'divertente')===0) $divertenti[]=$b;
}
$backgroundAudio = null;
if($_SERVER['REQUEST_METHOD']=='POST' && !empty($_POST['background_file'])){
    $bg = basename($_POST['background_file']);
    $path = "$musicaDir/$bg";
    if(file_exists($path)) $backgroundAudio = $path;
}

// Funzione di concatenazione con mix audio
function concatenateTsFiles($tsFiles, $outputFile) {
    global $backgroundAudio;
    $tsList = implode('|',$tsFiles);
    if($backgroundAudio){
        $cmd = "ffmpeg -i \"concat:$tsList\" -stream_loop -1 -i ".escapeshellarg($backgroundAudio).
               " -filter_complex \"[0:a][1:a]amix=inputs=2:duration=first:dropout_transition=3[aout]\" -map 0:v -map [aout] -c:v libx264 -c:a aac -shortest ".escapeshellarg($outputFile);
    } else {
        $cmd = "ffmpeg -i \"concat:$tsList\" -c copy -bsf:a aac_adtstoasc ".escapeshellarg($outputFile);
    }
    shell_exec($cmd);
}

// Pulizia file temporanei
function cleanupTempFiles($files, $keepOriginals=false) {
    foreach($files as $file){
        if(file_exists($file) && (!$keepOriginals || strpos($file,'uploads/')===false)){
            unlink($file);
        }
    }
}

// Handling POST
if($_SERVER['REQUEST_METHOD']=='POST'){
    createUploadsDir();
    set_time_limit(300);
    $mode = $_POST['mode'] ?? 'simple';
    $targetDuration = is_numeric($_POST['duration']??'') ? intval($_POST['duration'])*60 : 0;
    $durationMethod = $_POST['duration_method'] ?? 'trim';
    setConfig('duration_editor.method',$durationMethod);

    if(isset($_FILES['files'])){
        $uploaded_ts=[];
        foreach($_FILES['files']['tmp_name'] as $i => $tmp){
            $name = basename($_FILES['files']['name'][$i]);
            $dest = getConfig('paths.uploads','uploads')."/$name";
            move_uploaded_file($tmp,$dest);
            $ts = pathinfo($dest,PATHINFO_DIRNAME)."/".pathinfo($dest,PATHINFO_FILENAME).".ts";
            convertToTs($dest,$ts);
            $uploaded_ts[]=$ts;
        }
        $outputVideo = getConfig('paths.uploads','uploads').'/final_'.date('Ymd_His').'.mp4';
        concatenateTsFiles($uploaded_ts,$outputVideo);
        cleanupTempFiles($uploaded_ts,getConfig('system.keep_original',true));
        echo "<h2>Video generato con successo</h2><a href='$outputVideo' download>Scarica qui</a>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>VideoAssembly</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<h1>🎬 VideoAssembly</h1>
<div class="upload-container">
<form method="POST" enctype="multipart/form-data">
  <h3>1. Carica video</h3>
  <input type="file" name="files[]" multiple accept="video/*">
  <div class="options">
    <div class="option-group">
      <h4>Modalità:</h4>
      <label><input type="radio" name="mode" value="simple" checked> Semplice</label>
      <label><input type="radio" name="mode" value="detect_people"> Rilevamento persone</label>
    </div>
    <div class="option-group">
      <h4>Durata (minuti):</h4>
      <input type="number" name="duration" min="1" placeholder="Es. 2">
      <div id="durationMethodOptions" style="display:none;">
        <h5>Metodo:</h5>
        <label><input type="radio" name="duration_method" value="trim" checked> Taglio</label>
        <label><input type="radio" name="duration_method" value="speed"> Velocità</label>
      </div>
    </div>
    <div class="option-group">
      <h4>Audio di sottofondo:</h4>
      <select id="background_file" name="background_file">
        <optgroup label="Emozionale">
          <?php foreach($emozionali as $a): ?>
            <option value="<?=htmlspecialchars($a)?>"><?=htmlspecialchars($a)?></option>
          <?php endforeach; ?>
        </optgroup>
        <optgroup label="Divertente">
          <?php foreach($divertenti as $a): ?>
            <option value="<?=htmlspecialchars($a)?>"><?=htmlspecialchars($a)?></option>
          <?php endforeach; ?>
        </optgroup>
      </select>
    </div>
    <div class="option-group">
      <h4>Anteprima audio:</h4>
      <audio controls id="audioPreview">
        <source id="audioSource" src="" type="audio/mpeg">
        Il browser non supporta l'elemento audio.
      </audio>
    </div>
  </div>
  <button type="submit">Monta video</button>
</form>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var sel=document.getElementById('background_file'),
      src=document.getElementById('audioSource'),
      aud=document.getElementById('audioPreview');
  if(!sel) return;
  sel.addEventListener('change',function(){
    src.src='musica/'+this.value;
    aud.load();
  });
  sel.dispatchEvent(new Event('change'));
});
</script>
</body>
</html>
