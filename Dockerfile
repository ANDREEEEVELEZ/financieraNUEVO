FROM php:8.3-fpm

# 1. Instalar dependencias del sistema necesarias para Laravel
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libzip-dev \
    libicu-dev \
    zlib1g-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql intl zip opcache \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 2. Establecer directorio de trabajo
WORKDIR /var/www/html

# 3. Copiar todos los archivos del proyecto
COPY . .

# 4. Instalar dependencias PHP (Laravel)
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# 5. Generar APP_KEY y cachear configuración
RUN php artisan key:generate --force || true
RUN php artisan config:cache || true

# 6. Permitir escritura a Laravel
RUN chown -R www-data:www-data storage bootstrap/cache

# 7. Configurar php-fpm para usar el puerto 8080 (para Railway)
RUN sed -i 's/listen = .*/listen = 0.0.0.0:8080/' /usr/local/etc/php-fpm.d/www.conf

EXPOSE 8080

# 8. Iniciar php-fpm (modo servidor)
CMD ["php-fpm"]
