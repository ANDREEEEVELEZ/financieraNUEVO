FROM php:8.3-fpm

# Instalar dependencias del sistema necesarias
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

# Establecer el directorio de trabajo
WORKDIR /var/www/html

# Copiar todos los archivos al contenedor
COPY . .

# Instalar dependencias de Laravel
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Ajustar permisos necesarios para Laravel
RUN chown -R www-data:www-data storage bootstrap/cache

# Exponer el puerto requerido por Railway
EXPOSE 8080

# Usar servidor embebido de PHP apuntando a /public
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
