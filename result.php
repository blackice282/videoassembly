<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Video Montaggio AI</title>
  <style> body{font-family:sans-serif;} .progress{width:100%;background:#eee;} .bar{width:0;height:20px;background:#76c7c0;} </style>
</head>
<body>
  <h1>Montaggio Video AI</h1>
  <form id="upload-form" method="post" enctype="multipart/form-data">
    <input type="file" name="videos[]" multiple accept="video/*"><br><br>
    <label>Durata target: <input type="number" name="target_duration" step="0.1"></label><br>
    <label><input type="checkbox" name="detect_people"> Rilevamento persone</label><br>
    <label>CRF: <input type="number" name="crf" value="23"></label><br>
    <label>Preset: <select name="preset"><option>ultrafast</option><option>fast</option><option selected>medium</option><option>slow</option></select></label><br>
    <label><input type="checkbox" name="transitions_enabled" checked> Transizioni</label><br>
    <label>Tipo: <select name="transition_type"><option>fade</option><option>dissolve</option><option>wipe</option></select></label><br>
    <label>Durata transizione: <input type="number" name="transition_duration" value="1.0" step="0.1"></label><br><br>
    <button type="submit">Invia</button>
  </form>
  <div id="status" style="display:none;">
    <h2>Stato: <span id="st-text"></span></h2>
    <div class="progress"><div class="bar" id="bar"></div></div>
  </div>
  <div id="result" style="display:none;">
    <h2>Risultato</h2>
    <video id="video" controls width="640"></video><br>
    <a id="dl" href="" download>Scarica Video</a>
  </div>
<script>
const form=document.getElementById('upload-form');
form.onsubmit=async e=>{
  e.preventDefault(); document.getElementById('status').style.display='block';
  const data=new FormData(form);
  const res=await fetch('/',{method:'POST',body:data});
  const js=await res.json(); let job=js.jobId;
  poll(job);
};
async function poll(job){
  const res=await fetch(`poll.php?jobId=${job}`);
  const js=await res.json();
  document.getElementById('st-text').innerText=js.status;
  document.getElementById('bar').style.width=js.progress+'%';
  if(js.status==='done'){
    document.getElementById('result').style.display='block';
    document.getElementById('video').src=js.video_url;
    document.getElementById('dl').href=js.video_url;
  } else if(js.status==='error'){
    alert('Errore: '+js.error);
  } else setTimeout(()=>poll(job),1000);
}
</script>
