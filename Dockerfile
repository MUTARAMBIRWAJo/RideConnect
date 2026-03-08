FROM php:8.4-cli

WORKDIR /var/www

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        procps \
        libpq-dev \
        libicu-dev \
        libbrotli-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql intl zip pcntl \
    && pecl install redis swoole \
    && docker-php-ext-enable redis swoole \
    && rm -rf /var/lib/apt/lists/*

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

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction --no-scripts \
    && rm -f bootstrap/cache/*.php \
    && if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi \
    && mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache \
    && chmod +x /var/www/scripts/*.sh

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV OCTANE_SERVER=swoole
ENV OCTANE_WORKERS=4
ENV PORT=10000

EXPOSE 10000

CMD ["/bin/sh", "/var/www/scripts/start.sh"]
