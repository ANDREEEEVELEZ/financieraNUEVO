FROM php:8.3-fpm

# Instalar dependencias necesarias
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

WORKDIR /var/www/html

COPY . .

RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

RUN chown -R www-data:www-data storage bootstrap/cache

RUN php artisan config:cache || true

# Cambiamos el puerto del FPM para que escuche en 8080 directamente
RUN sed -i 's/listen = .*/listen = 0.0.0.0:8080/' /usr/local/etc/php-fpm.d/www.conf

EXPOSE 8080

CMD ["php-fpm"]
