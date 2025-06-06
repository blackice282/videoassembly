FROM php:8.2-cli

# Installa ffmpeg e dipendenze utili
RUN apt-get update && apt-get install -y \
    ffmpeg \
    unzip \
    git \
    && apt-get clean

# Crea una cartella per i file
WORKDIR /app

# Copia tutto il contenuto della repo nella cartella di lavoro
COPY . .

# Espone la porta che Render si aspetta (Render userà 10000)
EXPOSE 10000

# Comando di avvio
CMD ["php", "-S", "0.0.0.0:10000"]

# Copia il file php.ini personalizzato
COPY php.ini /usr/local/etc/php/

# Altri comandi necessari per l'ambiente
RUN docker-php-ext-install mysqli pdo pdo_mysql
