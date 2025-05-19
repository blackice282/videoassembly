FROM php:8.2-cli
RUN apt-get update && apt-get install -y ffmpeg libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-install mysqli gd \
    && rm -rf /var/lib/apt/lists/*
COPY . /usr/src/app
WORKDIR /usr/src/app
COPY php.ini /usr/local/etc/php/
RUN mkdir -p uploads temp output
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/usr/src/app"]
