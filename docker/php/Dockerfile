FROM php:8.3-fpm-alpine

# Dependencias del sistema
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-dev \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS

# Extensiones PHP requeridas
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        zip \
        bcmath \
        pcntl \
        gd \
        intl \
        opcache

# Extensión Redis
RUN pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar código de la aplicación
COPY . .

# Instalar dependencias PHP (sin dev en producción)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Permisos de storage y bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
