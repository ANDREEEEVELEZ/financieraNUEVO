# Etapa 1: Construcción de dependencias con Composer
FROM composer:2 as build

WORKDIR /app

COPY . /app

# Instalamos dependencias PHP del sistema necesarias para ext-intl, zip, etc.
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    unzip \
    zlib1g-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install intl zip

# Instalamos dependencias PHP del proyecto
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Etapa 2: Servidor PHP-FPM con extensiones necesarias
FROM php:8.3-fpm

# Instalamos las mismas extensiones que en build
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    unzip \
    zlib1g-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql intl zip opcache

WORKDIR /var/www/html

COPY --from=build /app /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Puerto expuesto por PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
