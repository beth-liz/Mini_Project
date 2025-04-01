FROM php:8.1-cli

WORKDIR /app

COPY . /app

CMD php -S 0.0.0.0:10000 -t .
RUN docker-php-ext-install mysqli
