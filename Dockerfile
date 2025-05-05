FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    ffmpeg \
    unzip \
    git \
    && apt-get clean

WORKDIR /app
COPY . .

EXPOSE 10000
CMD ["php", "-S", "0.0.0.0:10000"]

COPY php.ini /usr/local/etc/php/

RUN docker-php-ext-install mysqli pdo pdo_mysql
