FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libpq \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    unzip \
    curl \
    git \
    bash \
    shadow

RUN apk add --no-cache --virtual .pecl-deps autoconf g++ make \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        mbstring \
        xml \
        zip \
        intl \
        opcache \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .pecl-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

COPY . .

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini

RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chmod -R 775 bootstrap/cache storage \
    && chmod +x /var/www/html/docker/entrypoint.sh

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000

ENTRYPOINT ["/bin/sh", "-c", "chmod +x /var/www/html/docker/entrypoint.sh && /var/www/html/docker/entrypoint.sh"]
