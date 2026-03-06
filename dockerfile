FROM php:8.4-fpm-alpine AS vendor

WORKDIR /var/www

# Build-time deps for extensions and Composer
RUN set -eux; \
    for attempt in 1 2 3; do \
        apk add --no-cache libpq icu-libs libzip && break; \
        if [ "$attempt" -eq 3 ]; then exit 1; fi; \
        sleep 3; \
    done; \
    for attempt in 1 2 3; do \
        apk add --no-cache --virtual .build-deps \
            $PHPIZE_DEPS \
            postgresql-dev \
            icu-dev \
            libzip-dev \
            git \
            unzip \
        && break; \
        if [ "$attempt" -eq 3 ]; then exit 1; fi; \
        sleep 3; \
    done; \
        docker-php-ext-configure intl; \
    docker-php-ext-install -j$(nproc) pdo pdo_pgsql intl zip; \
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


FROM php:8.4-fpm-alpine AS runtime

# Disable IPv6
RUN echo "net.ipv6.conf.all.disable_ipv6 = 1" >> /etc/sysctl.conf && \
    echo "net.ipv6.conf.default.disable_ipv6 = 1" >> /etc/sysctl.conf

WORKDIR /var/www

# Copy compiled PHP extensions and required shared libs from build stage
COPY --from=vendor /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=vendor /usr/local/etc/php/conf.d/docker-php-ext-intl.ini /usr/local/etc/php/conf.d/
COPY --from=vendor /usr/local/etc/php/conf.d/docker-php-ext-pdo_pgsql.ini /usr/local/etc/php/conf.d/
COPY --from=vendor /usr/local/etc/php/conf.d/docker-php-ext-zip.ini /usr/local/etc/php/conf.d/
COPY --from=vendor /usr/lib/libpq.so* /usr/lib/
COPY --from=vendor /usr/lib/libzip.so* /usr/lib/
COPY --from=vendor /usr/lib/libicu*.so* /usr/lib/
COPY --from=vendor /usr/lib/libstdc++.so* /usr/lib/
COPY --from=vendor /usr/lib/libbz2.so* /usr/lib/
COPY --from=vendor /usr/share/icu/ /usr/share/icu/

RUN addgroup -g 1000 www-data || true && \
    adduser -G www-data -g www-data -s /bin/sh -D www-data || true

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/vendor ./vendor

RUN mkdir -p storage/framework/views \
    storage/framework/cache \
    storage/framework/sessions \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

USER www-data

CMD ["php-fpm"]