# Etapa 1: Build para instalar dependencias de PHP
FROM composer:2 AS build

WORKDIR /app

COPY . /app

# Instala solo las dependencias del proyecto (NO uses apt-get aquí)
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Etapa 2: Imagen final con PHP + extensiones necesarias
FROM php:8.3-fpm

# Instalar dependencias necesarias para ext-intl, ext-zip, etc.
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libzip-dev \
    libicu-dev \
    zlib1g-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql intl zip opcache

WORKDIR /var/www/html

COPY --from=build /app /var/www/html

# Establecer permisos para Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Puerto por defecto para PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
