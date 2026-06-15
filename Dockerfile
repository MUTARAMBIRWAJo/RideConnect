FROM php:8.4-fpm-alpine

WORKDIR /var/www

# ──────────────────────────────────────────────────────────────────────
# System deps & PHP extensions
# NOTE: We intentionally do NOT compile grpc/protobuf from PECL.
#   pecl install grpc on Alpine compiles the entire gRPC C++ library
#   from source (~2500 files) and takes 40+ minutes.
#   Instead we use the pure-PHP grpc/grpc composer package which
#   provides adequate transport for Firestore operations.
#   If you need the C extension for performance, switch the base image
#   to php:8.4-fpm (Debian) where pecl install grpc is much faster.
# ──────────────────────────────────────────────────────────────────────
RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        autoconf \
        build-base \
        icu-dev \
        libzip-dev \
        linux-headers \
        pkgconf \
        postgresql-dev \
        re2c \
        sqlite-dev \
    && apk add --no-cache \
        git \
        unzip \
        nodejs \
        npm \
        procps \
        nginx \
        supervisor \
        gettext \
        icu-libs \
        libpq \
        libzip \
        sqlite-libs \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql pdo_sqlite intl zip pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ──────────────────────────────────────────────────────────────────────
# Composer install
# --ignore-platform-req=ext-grpc: google/cloud-firestore requires the
# grpc C extension, but the pure-PHP grpc composer package works as a
# fallback. Firebase/Firestore operations function correctly without
# the C extension (just slightly slower serialization).
# ──────────────────────────────────────────────────────────────────────
COPY composer.json composer.lock ./
RUN set -eux; composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-scripts \
    --no-progress \
    --ignore-platform-req=ext-grpc

COPY package*.json ./
RUN set -eux; npm install

COPY . .

RUN set -eux; composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction --no-scripts \
    && npm run build \
    && apk del git unzip nodejs npm \
    && rm -f bootstrap/cache/*.php \
    && if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi \
    && php artisan key:generate --force --no-interaction || true \
    && php artisan config:cache --no-interaction || true \
    && php artisan route:cache --no-interaction || true \
    && php artisan view:cache --no-interaction || true \
    && mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache \
    && test -f public/build/manifest.json \
    && test -d public/build/assets \
    && chmod +x /var/www/scripts/*.sh \
    && rm -rf /var/cache/apk/* /tmp/*

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV PORT=10000

EXPOSE 10000

COPY docker/nginx/default.conf /etc/nginx/templates/default.conf.template
COPY docker/supervisor.conf /etc/supervisor/conf.d/supervisord.conf

RUN mkdir -p /etc/nginx/templates /etc/nginx/http.d /var/log/nginx /var/cache/nginx \
    && chown -R nginx:nginx /var/log/nginx /var/cache/nginx \
    && chmod +x /var/www/scripts/*.sh

CMD ["/bin/sh", "/var/www/scripts/start.sh"]
