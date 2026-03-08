FROM php:8.4-cli-alpine

WORKDIR /var/www

RUN set -eux; \
    apk add --no-cache \
        bash \
        git \
        unzip \
        libpq \
        icu-libs \
        libzip; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        icu-dev \
        libzip-dev; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql intl zip; \
    apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-scripts \
    --no-progress

COPY . .

# Rebuild authoritative autoload after app source is present.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction --no-scripts

RUN set -eux; \
    rm -f bootstrap/cache/*.php; \
    mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache; \
    chmod +x /var/www/scripts/*.sh; \
    chmod -R 775 storage bootstrap/cache; \
    if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi; \
    php artisan key:generate --force --no-interaction || true; \
    addgroup -S app || true; \
    adduser -S -G app app || true; \
    chown -R app:app /var/www

USER app

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV PORT=10000

EXPOSE 10000

CMD ["/bin/sh", "/var/www/scripts/entrypoint.sh"]
