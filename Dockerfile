FROM php:8.2-cli

# Installa ffmpeg e dipendenze utili
RUN apt-get update && apt-get install -y \
    ffmpeg \
    unzip \
    git \
  && apt-get clean

WORKDIR /app
COPY . .

# Copreerà il php.ini con i tuoi limiti di upload personalizzati
COPY php.ini /usr/local/etc/php/

# Esponi la porta che Render gli passa, o 8000 se non definita (utile in locale)
EXPOSE ${PORT:-8000}

# Avvia il built-in server PHP sulla porta di Render
CMD ["sh", "-c", "php -d display_errors=1 -S 0.0.0.0:${PORT:-8000} -t /app"]
