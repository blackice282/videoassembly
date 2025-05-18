<?php
// index.php - Frontend form for video assembly
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Montaggio Video Automatico</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .hidden { display: none; }
        .debug-log { background: #f9f9f9; border: 1px solid #ccc; padding: 10px; height: 200px; overflow-y: scroll; white-space: pre-wrap; font-family: monospace; }
        #message { margin-bottom: 10px; font-weight: bold; }
        #message a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Montaggio Video Verticale 9:16</h1>
        </header>
        <main>
            <form id="montaggioForm" action="upload.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="video">Seleziona video (mp4):</label>
                    <input type="file" id="video" name="videos[]" accept="video/mp4" multiple required>
                </div>
                <div class="form-group">
                    <label for="duration">Durata desiderata (minuti):</label>
                    <input type="number" id="duration" name="duration" min="1" max="60" value="1" required>
                </div>
                <div class="form-group">
                    <label for="instructions">Istruzioni AI:</label>
                    <textarea id="instructions" name="instructions" rows="4" placeholder="Descrivi l'intervento desiderato"></textarea>
                </div>
                <button type="submit" class="btn">Carica e Monta</button>
            </form>
            <section id="response" class="hidden">
                <h2>Risultato</h2>
                <div id="message">Elaborazione in corso...</div>
                <pre id="debugLog" class="debug-log"></pre>
            </section>
        </main>
    </div>
    <script>
    document.getElementById('montaggioForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        const responseEl = document.getElementById('response');
        const messageEl = document.getElementById('message');
        const debugEl = document.getElementById('debugLog');
        
        // show response section
        responseEl.classList.remove('hidden');
        // reset message and debug
        messageEl.textContent = 'Elaborazione in corso...';
        debugEl.textContent = '';

        fetch(form.action, { method: 'POST', body: data }).then(res => {
            if (!res.body) throw new Error('Streaming non supportato');
            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            messageEl.textContent = '';

            function read() {
                reader.read().then(({done, value}) => {
                    if (done) {
                        // if no download link found yet
                        if (!messageEl.textContent) {
                            messageEl.textContent = 'Elaborazione completata, verificare log per dettagli.';
                        }
                        return;
                    }
                    const chunk = decoder.decode(value, { stream: true });
                    debugEl.textContent += chunk;
                    debugEl.scrollTop = debugEl.scrollHeight;

                    // cerca URL finale
                    const match = chunk.match(/URL finale: (.+)/);
                    if (match) {
                        const url = match[1].trim();
                        messageEl.innerHTML = `<a href="${url}" target="_blank">Scarica video montato</a>`;
                    }

                    read();
                }).catch(err => {
                    messageEl.textContent = 'Errore durante il debug.';
                    console.error(err);
                });
            }
            read();
        }).catch(err => {
            messageEl.textContent = 'Errore di rete o server.';
            console.error(err);
        });
    });
    </script>
</body>
</html>
