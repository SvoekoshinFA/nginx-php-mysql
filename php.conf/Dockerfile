FROM php:7.4-fpm
RUN apt-get update -y && apt-get install -y curl && apt-get clean -y
RUN docker-php-ext-install mysqli