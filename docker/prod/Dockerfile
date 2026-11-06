# Image de production : php-fpm + application déjà construite par la CI
# (dépendances sans dev, assets compilés). Le contexte de build est le contenu
# extrait de l'artefact "app-package", pas le dépôt.
# Nginx reste un conteneur séparé (voir docker/nginx).
FROM php:8.4-fpm-alpine

# Correctifs de sécurité Alpine publiés depuis la construction de l'image de base
# (sinon Trivy bloque la CI sur des failles CRITICAL/HIGH déjà corrigées).
# Bibliothèques d'exécution conservées, outils de compilation supprimés après usage
RUN apk upgrade --no-cache \
    && apk add --no-cache icu-libs libzip \
    && apk add --no-cache --virtual .build-deps icu-dev libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql intl zip opcache \
    && apk del .build-deps

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' \
        'opcache.enable=1' \
        'opcache.memory_consumption=256' \
        'opcache.max_accelerated_files=20000' \
        'opcache.validate_timestamps=0' \
        > "$PHP_INI_DIR/conf.d/zz-opcache.ini"

# DATABASE_URL, APP_SECRET, MAILER_DSN, etc. sont fournis au lancement du conteneur
ENV APP_ENV=prod \
    APP_DEBUG=0

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
