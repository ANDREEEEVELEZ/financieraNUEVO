FROM php:8.3-fpm

# Instalar extensiones necesarias
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

# Copiar código fuente
COPY . .

# Instalar dependencias PHP
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Dar permisos correctos
RUN chown -R www-data:www-data storage bootstrap/cache

# Expone el puerto 8080 porque Railway lo exige
EXPOSE 8080

# Servir Laravel desde la carpeta public/
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
