# ---------- Stage 1: Build Vendor Dependencies ----------
FROM php:8.4-fpm-alpine AS vendor

WORKDIR /var/www

# Install runtime libraries and build dependencies
RUN set -eux; \
    apk add --no-cache \
        libpq \
        icu-libs \
        libzip; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        git \
        unzip; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        intl \
        zip; \
    apk del .build-deps

# Install Composer
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


# ---------- Stage 2: Runtime ----------
FROM php:8.4-fpm-alpine AS runtime

WORKDIR /var/www

# Disable IPv6 (optional but safe)
RUN echo "net.ipv6.conf.all.disable_ipv6 = 1" >> /etc/sysctl.conf && \
    echo "net.ipv6.conf.default.disable_ipv6 = 1" >> /etc/sysctl.conf

# Install runtime packages
RUN apk add --no-cache \
        libpq \
        icu-libs \
        libzip \
        bash \
        supervisor

# Copy PHP extensions from vendor stage
COPY --from=vendor /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=vendor /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Create Laravel user
RUN addgroup -g 1000 www-data || true && \
    adduser -G www-data -g www-data -s /bin/sh -D www-data || true

# Copy application
COPY --chown=www-data:www-data . .

# Copy vendor dependencies
COPY --from=vendor --chown=www-data:www-data /var/www/vendor ./vendor

# Create required Laravel directories
RUN mkdir -p \
    storage/framework/views \
    storage/framework/cache \
    storage/framework/sessions \
    storage/logs \
    bootstrap/cache \
    && chmod +x /var/www/scripts/*.sh \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

# Supervisor configuration
COPY docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf

USER www-data

EXPOSE 8000

CMD ["/usr/bin/supervisord","-c","/etc/supervisor/conf.d/supervisor.conf"]