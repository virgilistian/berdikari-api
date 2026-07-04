FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libpq \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    git \
    bash \
    shadow

RUN apk add --no-cache --virtual .pecl-deps autoconf g++ make \
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
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .pecl-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN chmod +x /var/www/html/docker/entrypoint.sh 2>/dev/null || true

EXPOSE 8000

ENTRYPOINT ["/bin/sh", "-c", "chmod +x /var/www/html/docker/entrypoint.sh && /var/www/html/docker/entrypoint.sh"]
