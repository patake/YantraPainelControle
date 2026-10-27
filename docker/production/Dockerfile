# Imagens de produção do painel (E9, docs/implantacao.md). Contexto: a raiz do projeto.
#
#   docker build -f docker/production/Dockerfile --target app -t yantra-painel-app:<versão> .
#   docker build -f docker/production/Dockerfile --target web -t yantra-painel-web:<versão> .
#
# A imagem app roda o PHP-FPM, a fila e o agendador (docker-compose.prod.yml). A imagem web é o
# nginx com os arquivos de public/. Diferente da imagem de desenvolvimento, o código fica dentro
# da imagem, sem as dependências de desenvolvimento, e o PHP-FPM roda como www-data.

# 1. Dependências PHP, sem as de desenvolvimento.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# 2. Telas (Vite, Tailwind e Flux). O CSS do Flux vem de vendor/.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY --from=vendor /app /app
RUN npm run build

# 3. PHP-FPM com o código.
FROM php:8.5-fpm-alpine AS app

RUN apk add --no-cache icu-libs libzip libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev postgresql-dev linux-headers \
    && docker-php-ext-install pdo_pgsql intl zip bcmath pcntl \
    && apk del .build-deps

COPY docker/php/conf.d/yantra.ini /usr/local/etc/php/conf.d/yantra.ini
COPY docker/production/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/production/entrypoint.sh /usr/local/bin/yantra-entrypoint

WORKDIR /var/www/html
COPY --from=assets --chown=www-data:www-data /app /var/www/html
RUN rm -rf node_modules tests .env \
    && php artisan package:discover --ansi \
    && chmod +x /usr/local/bin/yantra-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
ENTRYPOINT ["yantra-entrypoint"]
CMD ["php-fpm"]

# 4. nginx com os arquivos estáticos (public/, com as telas compiladas).
FROM nginx:1.29-alpine AS web
COPY docker/production/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /app/public /var/www/html/public
