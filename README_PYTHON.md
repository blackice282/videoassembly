# Microservizio Python – Emoji Privacy (Render)

Questo servizio Flask elabora un video e applica automaticamente emoji smile sui volti delle persone **escludendo** chi indossa una **pettorina gialla**.

## 🚀 Funzionalità
- Rilevamento volti (OpenCV)
- Verifica presenza di giallo (pettorina) attorno ai volti
- Overlay di `faccia_felice.png` sugli altri volti

## 🔧 Requisiti
- Python 3.9+
- Flask
- OpenCV
- Numpy

## 📦 Endpoint
```
POST /process
Form-data: video (file)
Return: MP4 video modificato
```

## 🐳 Deploy su Render
Esporre la porta `5001`. Usa il Dockerfile incluso. Comando di avvio:
```bash
python app.py
```
