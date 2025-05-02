<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors',1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ffmpeg_script.php';
require_once __DIR__ . '/people_detection.php';
require_once __DIR__ . '/duration_editor.php';
require_once __DIR__ . '/video_effects.php';
require_once __DIR__ . '/audio_manager.php';
require_once __DIR__ . '/face_detection.php';
require_once __DIR__ . '/privacy_manager.php';

// Inizio job
$job     = uniqid();
$logFile = getConfig('paths.temp') . "/progress_{$job}.log";
file_put_contents($logFile, "[START {$job}] " . date('c') . "\n");
function logStep($m){ global $logFile; file_put_contents($logFile, "[".date('H:i:s')."] $m\n", FILE_APPEND); }

if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_FILES['videos'])) {
    // prepara cartelle
    foreach([getConfig('paths.uploads'),getConfig('paths.temp')] as $d){
        if(!is_dir($d)) mkdir($d,0777,true);
    }
    set_time_limit(0);

    // upload multipli
    $clips=[];
    foreach($_FILES['videos']['tmp_name'] as $i=>$tmp){
        if($_FILES['videos']['error'][$i]===UPLOAD_ERR_OK){
            $name='vid_'.uniqid().'_'.basename($_FILES['videos']['name'][$i]);
            $dst=getConfig('paths.uploads').'/'.$name;
            move_uploaded_file($tmp,$dst);
            trackFile($dst,$name,'upload');
            logStep("Uploaded $name");
            $clips[]=$dst;
        }
    }

    // opzioni
    $opts=[
        'mode'=>$_POST['mode']??'simple',
        'duration'=>intval($_POST['duration'])*60,
        'duration_method'=>$_POST['duration_method'],
        'effect'=>$_POST['effect'],
        'audio'=>$_POST['audio'],
        'privacy'=>isset($_POST['privacy'])
    ];

    // 1) transitions
    $outName='render_'.date('Ymd_His').'.mp4';
    $outPath=getConfig('paths.uploads').'/'.$outName;
    logStep("Applying transitions");
    applyTransitions($clips,$outPath);
    $out=$outPath;

    // 2) duration
    if($opts['duration']>0){
        logStep("Edit duration {$opts['duration_method']}");
        $out=applyDurationEdit($out,$opts['duration'],$opts['duration_method']);
    }
    // 3) effects
    if($opts['effect']!=='none'){
        logStep("Video effect {$opts['effect']}");
        $tmp=getConfig('paths.temp').'/eff_'.uniqid().'.mp4';
        applyVideoEffect($out,$tmp,$opts['effect']);
        $out=$tmp;
    }
    // 4) privacy
    if($opts['privacy']){
        logStep("Face privacy");
        $tmp=getConfig('paths.temp').'/priv_'.uniqid().'.mp4';
        applyFacePrivacy($out,$tmp);
        $out=$tmp;
    }
    // 5) audio
    if($opts['audio']!=='none'){
        logStep("Background audio {$opts['audio']}");
        $aud=getRandomAudioFromCategory($opts['audio']);
        if($aud){
            $tmpA=getConfig('paths.temp').'/aud_'.uniqid().'.mp3';
            downloadAudio($aud['url'],$tmpA);
            $tmpV=getConfig('paths.temp').'/audvid_'.uniqid().'.mp4';
            applyBackgroundAudio($out,$tmpA,$tmpV,0.3);
            $out=$tmpV;
        }
    }

    trackFile($out,basename($out),'output');
    logStep("JOB COMPLETE {$outName}");

    // risposta HTML + polling
    echo "<div style='padding:2rem;font-family:sans-serif;'>";
    echo "<h2>🚀 Elaborazione in corso…</h2>";
    echo "<pre id='log' style='background:#eee;padding:1rem;height:200px;overflow:auto;'></pre>";
    echo "<script>
      const job='{$job}';
      setInterval(async()=>{
        let t=await fetch('status.php?job='+job).then(r=>r.text());
        document.getElementById('log').innerText=t;
      },1000);
    </script>";
    echo "<p style='margin-top:1rem;'>
            Quando vedi 'JOB COMPLETE', <a href='uploads/{$outName}' 
            style='padding:0.8rem 1.2rem;background:#28a745;color:#fff;border-radius:5px;text-decoration:none;'>
            Scarica il video</a>
          </p></div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="it"><head><meta charset="UTF-8"><title>VideoAssembly</title></head><body>
<form method="post" enctype="multipart/form-data" style="max-width:600px;margin:auto;padding:2rem;background:#fff;">
  <h1>VideoAssembly</h1>
  <label>Carica video:<br><input type="file" name="videos[]" multiple required></label><br>
  <label>Modalità:<br>
    <select name="mode">
      <option value="simple">Semplice</option>
      <option value="detect_people">Rileva Persone</option>
    </select>
  </label><br>
  <label>Durata (min):<br><input type="number" name="duration" value="3" min="1"></label><br>
  <label>Metodo:<br>
    <select name="duration_method">
      <option value="trim">Taglia</option>
      <option value="speed">Accelera</option>
    </select>
  </label><br>
  <label>Effetto:<br>
    <select name="effect">
      <option value="none">Nessuno</option>
      <option value="bw">Bianco e Nero</option>
      <option value="vintage">Vintage</option>
      <option value="contrast">Contrasto</option>
    </select>
  </label><br>
  <label>Audio:<br>
    <select name="audio">
      <option value="none">Nessuno</option>
      <option value="emozionale">Emozionale</option>
    </select>
  </label><br>
  <label><input type="checkbox" name="privacy" checked> Privacy volti</label><br>
  <button type="submit">Avvia Montaggio</button>
</form>
<p style="text-align:center;margin-top:1rem;"><a href="diagnostica.php">Diagnostica</a></p>
</body></html>
