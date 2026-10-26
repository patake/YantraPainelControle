# Imagem PHP do administrativo: PHP-FPM 8.5 + extensões do Laravel + Composer.
# O OPcache já vem embutido no PHP 8.5 (não instalar com docker-php-ext-install).
FROM php:8.5-fpm-alpine

RUN apk add --no-cache icu-libs libzip libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev postgresql-dev linux-headers \
    && docker-php-ext-install pdo_pgsql intl zip bcmath pcntl \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY conf.d/yantra.ini /usr/local/etc/php/conf.d/yantra.ini
COPY fpm-dev.conf /usr/local/etc/php-fpm.d/zz-yantra-dev.conf

WORKDIR /var/www/html

# -R permite o pool como root (fpm-dev.conf). Só para desenvolvimento.
CMD ["php-fpm", "-R"]
