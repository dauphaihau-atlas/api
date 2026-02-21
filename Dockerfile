FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash git curl unzip zip \
    libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev \
    icu-dev oniguruma-dev postgresql-dev linux-headers

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo pdo_pgsql pgsql bcmath intl mbstring zip gd pcntl sockets opcache

RUN apk add --no-cache --virtual .pecl-deps autoconf g++ make \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .pecl-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

RUN composer install --optimize-autoloader

RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data
EXPOSE 9000
