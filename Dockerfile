# Usa l'immagine di PHP 8.0 come base
FROM php:8.0-cli

# Installa FFmpeg e le dipendenze
RUN apt-get update && \
    apt-get install -y ffmpeg

# Copia il codice del progetto nell'immagine Docker
WORKDIR /var/www/html
COPY . .

# Comando di avvio (per avviare il server PHP incorporato su Render)
CMD ["php", "-S", "0.0.0.0:10000"]
