# Etapa de construcción
FROM composer:2 AS build

WORKDIR /app

COPY . /app

# Instalamos las dependencias de PHP sin ejecutar scripts
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Etapa de producción
FROM php:8.3-fpm

# Instalar extensiones requeridas (intl, zip, pdo_mysql)
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql intl zip opcache

# Instalar Node.js (si necesitas compilar assets con Vite)
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g npm

# Copiar código desde etapa build
COPY --from=build /app /var/www/html

# Establecer el directorio de trabajo
WORKDIR /var/www/html

# Permisos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Exponer el puerto
EXPOSE 9000

CMD ["php-fpm"]
