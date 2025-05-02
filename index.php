<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors',1);

require_once 'config.php';
require_once 'ffmpeg_script.php';
require_once 'people_detection.php';
require_once 'duration_editor.php';
require_once 'video_effects.php';
require_once 'audio_manager.php';
require_once 'face_detection.php';
require_once 'privacy_manager.php';

$job     = uniqid();
$logFile = getConfig('paths.temp')."/progress_{$job}.log";
file_put_contents($logFile, "JOB $job avviato\n");

// Helper
function logStep($m){ global $logFile; file_put_contents($logFile,"[$m]\n",FILE_APPEND); }

if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_FILES['videos'])) {
  // 1) crea dirs
  foreach([getConfig('paths.uploads'),getConfig('paths.temp')] as $d){
    if(!is_dir($d)) mkdir($d,0777,true);
  }
  set_time_limit(0);

  // 2) sposta gli upload
  $clips=[];
  foreach($_FILES['videos']['tmp_name'] as $idx=>$tmp){
    if($_FILES['videos']['error'][$idx]===0){
      $name = uniqid('vid_').'_'.basename($_FILES['videos']['name'][$idx]);
      $dst  = getConfig('paths.uploads').'/'.$name;
      move_uploaded_file($tmp,$dst);
      trackFile($dst,$name,'upload');
      logStep("upload $name");
      $clips[]=$dst;
    }
  }

  // 3) opzioni
  $opts = [
    'mode' => $_POST['mode'] ?? 'simple',
    'duration' => intval($_POST['duration'])*60,
    'duration_method'=>$_POST['duration_method'],
    'effect'=>$_POST['effect'],
    'audio'=>$_POST['audio'],
    'privacy'=>isset($_POST['privacy'])
  ];

  // 4) processa
  $out = applyTransitions($clips, getConfig('paths.uploads').'/render_'.date('Ymd_His').'.mp4');
  if($opts['effect']!=='none'){
    $tmp = getConfig('paths.temp').'/eff.mp4';
    if(applyVideoEffect($out,$tmp,$opts['effect'])){ unlink($out); $out=$tmp; }
  }
  if($opts['privacy']){
    $tmp=getConfig('paths.temp').'/priv.mp4';
    if(applyFacePrivacy($out,$tmp)){ unlink($out); $out=$tmp; }
  }
  if($opts['audio']!=='none'){
    $aud = getRandomAudioFromCategory($opts['audio']);
    $tmpA = getConfig('paths.temp').'/aud.mp3';
    $tmpV = getConfig('paths.temp').'/audvid.mp4';
    if($aud && downloadAudio($aud['url'],$tmpA) && applyBackgroundAudio($out,$tmpA,$tmpV,0.3)){
      unlink($out); $out=$tmpV;
    }
  }
  trackFile($out, basename($out),'output');
  logStep("done");

  // 5) output HTML + polling log
  $fileName = basename($out);
  echo "<div><h2>🚀 Elaborazione avviata</h2>
        <pre id='log' style='height:200px;overflow:auto;background:#eee'></pre>
        <script>
          const job='$job';
          setInterval(async()=>{
            let t=await fetch('status.php?job='+job).then(r=>r.text());
            document.getElementById('log').innerText=t;
          },500);
        </script>
        <p><a href='uploads/$fileName'>Scarica il video</a></p>
        </div>";
  exit;
}
?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>VideoAssembly</title></head><body>
<form method="post" enctype="multipart/form-data" style="max-width:600px;margin:auto">
  <h1>VideoAssembly</h1>
  Carica video: <input type="file" name="videos[]" multiple required><br>
  Modalità:
    <select name="mode"><option>simple</option><option>detect_people</option></select><br>
  Durata (min) <input type="number" name="duration" value="3"><br>
  Metodo:
    <select name="duration_method"><option>trim</option><option>speed</option></select><br>
  Effetto:
    <select name="effect"><option>none</option><option>bw</option><option>vintage</option><option>contrast</option></select><br>
  Audio:
    <select name="audio"><option>none</option><option>emozionale</option></select><br>
  <label><input type="checkbox" name="privacy" checked> Privacy volti</label><br>
  <button type="submit">Avvia Montaggio</button>
</form>
<p style="text-align:center"><a href="diagnostica.php">Diagnostica</a></p>
</body></html>
