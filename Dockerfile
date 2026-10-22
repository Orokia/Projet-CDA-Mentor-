
FROM php:8.4-fpm-alpine


RUN apk add --no-cache \
    git \
    unzip \
    zip \
    icu-dev \
    libzip-dev \
    libxslt-dev \
    pkgconf \
    $PHPIZE_DEPS

RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    zip \
    xsl \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --prefer-dist --no-interaction

RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

EXPOSE 9000

CMD ["php-fpm"]
