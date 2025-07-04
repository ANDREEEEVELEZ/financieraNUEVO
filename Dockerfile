# Etapa 1: construir dependencias con Composer
FROM composer:2 AS build

WORKDIR /app

COPY . /app

RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Etapa 2: PHP con extensiones necesarias
FROM php:8.3-fpm

# Instalación de extensiones necesarias para Laravel y Filament
RUN apt-get update && apt-get install -y \
    git curl unzip libzip-dev libicu-dev zlib1g-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql intl zip opcache

# Copiamos la aplicación desde la etapa build
COPY --from=build /app /var/www/html

# Establecemos permisos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

WORKDIR /var/www/html

CMD ["php-fpm"]
