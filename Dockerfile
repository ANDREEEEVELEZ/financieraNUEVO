# Etapa 1: build con Composer
FROM composer:2 AS build

WORKDIR /app
COPY . /app

# Instalamos dependencias en etapa build (sin ejecutar scripts aún)
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Etapa 2: imagen PHP con extensiones necesarias
FROM php:8.3-fpm

# Instalar dependencias del sistema necesarias para intl, zip, etc.
RUN apt-get update && apt-get install -y \
    git curl unzip libzip-dev libicu-dev zlib1g-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql intl zip opcache

# Copiar el código desde la etapa build
COPY --from=build /app /var/www/html

WORKDIR /var/www/html

# Dar permisos a Laravel
RUN chown -R www-data:www-data storage bootstrap/cache

# Exponer el puerto de PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
