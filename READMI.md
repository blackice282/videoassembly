# VideoAssembly - Sistema di Montaggio Video Automatico

Questo sistema permette di creare automaticamente video montati a partire da clip esistenti, con funzionalità avanzate come:

- Rilevamento automatico delle scene con persone
- Applicazione di emoji sui volti per la privacy
- Aggiunta di effetti video
- Inserimento di audio di sottofondo
- Adattamento della durata del video finale

## Requisiti

- PHP 7.2+
- FFmpeg e FFprobe installati
- Python 3 con OpenCV (opzionale, per la privacy dei volti)

## Installazione

1. Clona o scarica il repository nella cartella del tuo server web
2. Assicurati che le directory seguenti siano scrivibili:
   - `uploads/`
   - `temp/`
   - `logs/`
3. Verifica che FFmpeg sia installato e accessibile

### Installazione di FFmpeg

**Su Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install ffmpeg
```

**Su CentOS/RHEL:**
```bash
sudo yum install epel-release
sudo yum install ffmpeg ffmpeg-devel
```

**Su macOS:**
```bash
brew install ffmpeg
```

### Installazione di OpenCV (per la privacy dei volti)

**Su Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install python3-pip
pip3 install opencv-python
```

**Su macOS:**
```bash
pip3 install opencv-python
```

## Struttura del progetto

- `index.php` - Pagina principale dell'applicazione
- `config.php` - Configurazione del sistema
- `people_detection.php` - Rilevamento scene con persone
- `face_detection.php` - Applicazione emoji sui volti
- `video_effects.php` - Applicazione effetti video
- `audio_manager.php` - Gestione dell'audio di sottofondo
- `transitions.php` - Effetti di transizione tra clip
- `duration_editor.php` - Adattamento della durata del video
- `privacy_manager.php` - Gestione della privacy dei contenuti
- `debug_utility.php` - Utilità di debugging

## Utilizzo

1. Apri l'applicazione nel browser
2. Carica uno o più file video
3. Seleziona le opzioni di elaborazione:
   - Modalità di montaggio (semplice o rilevamento persone)
   - Durata desiderata
   - Privacy dei volti
   - Effetti video
   - Audio di sottofondo
4. Clicca "Carica e Monta"
5. Attendi il completamento dell'elaborazione
6. Scarica il video finale

## Risoluzione problemi

Se riscontri problemi:

1. Verifica che tutte le dipendenze siano installate correttamente
2. Controlla i log in `logs/app_*.log`
3. Assicurati che le directory abbiano i permessi di scrittura corretti

## File di configurazione

È possibile personalizzare il comportamento del sistema modificando il file `config.php`. Le opzioni principali sono:

- Percorsi delle directory
- Codec video/audio da utilizzare
- Parametri di rilevamento delle persone
- Impostazioni delle transizioni

## Supporto

Per assistenza o segnalazione di bug, contatta il supporto tecnico.

## Licenza

Questo software è distribuito sotto licenza GNU GPL v3.
