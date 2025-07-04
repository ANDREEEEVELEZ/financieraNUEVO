# Etapa base: PHP con extensiones necesarias
FROM php:8.3-fpm

# Instalar dependencias del sistema y PHP
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

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Copiar proyecto Laravel
COPY . .

# Instalar dependencias Laravel
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Configurar permisos para Laravel
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN cp .env.example .env

# Generar clave de aplicación (no falla si ya existe)
RUN php artisan key:generate --force || true

RUN php artisan shield:generate --all
# Limpiar caché de configuración


# Puerto que expondrá Railway
EXPOSE 8080

# Iniciar Laravel usando el servidor embebido en la carpeta 'public'
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
