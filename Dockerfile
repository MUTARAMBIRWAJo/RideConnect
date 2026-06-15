################################################################################
# Stage 1: Builder — compile PHP extensions and install dependencies
################################################################################
FROM php:8.4-fpm-alpine AS builder

WORKDIR /var/www

# ──────────────────────────────────────────────────────────────────────────────
# Build-time dependencies
# NOTE: We intentionally do NOT compile grpc/protobuf from PECL.
#   pecl install grpc on Alpine compiles the entire gRPC C++ library from
#   source (~2500 .cc files) and takes 40+ minutes.
#   Firestore is not active in production (GCP database not initialized).
#   Runtime guards in FirebaseHealthService detect the missing extension and
#   degrade gracefully. When Firestore becomes critical, switch to
#   php:8.4-fpm (Debian) where pecl install grpc takes ~10 min.
# ──────────────────────────────────────────────────────────────────────────────
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
        icu-libs \
        libpq \
        libzip \
        sqlite-libs \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pcntl \
        pdo \
        pdo_pgsql \
        pdo_sqlite \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

# ──────────────────────────────────────────────────────────────────────────────
# Composer dependencies
# --ignore-platform-req=ext-grpc: google/cloud-firestore requires the grpc
# C extension, but Firestore is not active. Runtime guards handle this.
# ──────────────────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN set -eux; composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-scripts \
    --no-progress \
    --ignore-platform-req=ext-grpc \
    && rm -rf /root/.composer/cache

# ──────────────────────────────────────────────────────────────────────────────
# Frontend build
# ──────────────────────────────────────────────────────────────────────────────
RUN apk add --no-cache nodejs npm

COPY package*.json ./
RUN set -eux; npm ci --no-audit --no-fund

COPY . .

RUN set -eux; \
    composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction --no-scripts \
    && npm run build \
    && rm -rf node_modules /root/.npm /tmp/*


################################################################################
# Stage 2: Runtime — lean production image
################################################################################
FROM php:8.4-fpm-alpine

WORKDIR /var/www

# Runtime-only system packages (no build toolchain)
RUN set -eux; \
    apk add --no-cache \
        gettext \
        icu-libs \
        libpq \
        libzip \
        nginx \
        procps \
        sqlite \
        sqlite-libs \
        supervisor

# Copy compiled PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Copy application from builder (vendor + built assets)
COPY --from=builder /var/www /var/www

# Laravel post-build caching
RUN set -eux; \
    rm -f bootstrap/cache/*.php \
    && if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi \
    && php artisan key:generate --force --no-interaction || true \
    && php artisan config:cache --no-interaction || true \
    && php artisan route:cache --no-interaction || true \
    && php artisan view:cache --no-interaction || true \
    && mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache \
    && test -f public/build/manifest.json \
    && test -d public/build/assets \
    && chmod +x /var/www/scripts/*.sh

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
