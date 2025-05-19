<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Video Montaggio AI</title>
  <style>
    body{font-family:sans-serif;padding:20px;}
    .spinner{display:none;margin:20px auto;width:40px;height:40px;border:4px solid #ccc;border-top:4px solid #76c7c0;border-radius:50%;animation:spin 1s linear infinite;}
    @keyframes spin{to{transform:rotate(360deg);}}
    .progress{width:100%;background:#eee;height:20px;margin:20px 0;}
    .bar{height:100%;width:0;background:#76c7c0;}
  </style>
</head>
<body>
<h1>Montaggio Video AI</h1>
<form id="uploadForm">
  <input type="file" name="videos[]" multiple accept="video/*"><br><br>
  <label><input type="checkbox" name="detect_people"> Rilevamento persone</label><br><br>
  <button type="submit">Avvia Montaggio</button>
</form>
<div class="spinner" id="spinner"></div>
<div class="progress"><div class="bar" id="bar"></div></div>
<div id="result"></div>
<script>
const form = document.getElementById('uploadForm');
const spinner = document.getElementById('spinner');
const bar = document.getElementById('bar');
const result = document.getElementById('result');
form.onsubmit = async e => {
  e.preventDefault();
  spinner.style.display = 'block';
  const res = await fetch('index.php',{method:'POST',body:new FormData(form)});
  const { jobId } = await res.json();
  poll(jobId);
};
async function poll(jobId) {
  const res = await fetch('poll.php?jobId='+jobId);
  const status = await res.json();
  bar.style.width = status.progress + '%';
  if (status.status === 'done') {
    spinner.style.display = 'none';
    result.innerHTML = '<p>Video pronto: <a href="'+status.video_url+'">Scarica qui</a></p>';
  } else {
    setTimeout(()=>poll(jobId),500);
  }
}
</script>
</body>
</html>
