FROM php:8.2-cli
RUN apt-get update && apt-get install -y ffmpeg git libpng-dev libjpeg-dev libfreetype6-dev
RUN docker-php-ext-install mysqli gd
COPY . /usr/src/app
WORKDIR /usr/src/app
COPY php.ini /usr/local/etc/php/
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/usr/src/app"]