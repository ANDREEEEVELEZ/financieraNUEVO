FROM php:8.3-fpm

# Instalar dependencias del sistema y PHP
RUN apt-get update && apt-get install -y \
    git curl unzip libzip-dev libicu-dev zlib1g-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql intl zip opcache \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Opcional: copiar .env si no se inyecta por variables en Railway
# COPY .env.example .env

RUN php artisan key:generate --force || true
RUN php artisan config:cache || true

# Crear carpetas y dar permisos
RUN mkdir -p storage/framework/{sessions,views,cache} && \
    chmod -R 775 storage bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

# Servir Laravel correctamente sin nginx
CMD ["sh", "-c", "php -S 0.0.0.0:8080 -t public"]
