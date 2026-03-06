#!/usr/bin/env sh
set -eu

cd /var/www

mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache

# Best-effort permissions (won't fail when running as non-root)
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

APP_KEY_FROM_ENV="${APP_KEY:-}"
APP_KEY_FROM_FILE=""
if [ -f .env ]; then
  APP_KEY_FROM_FILE="$(sed -n 's/^APP_KEY=//p' .env | head -n 1)"
fi

if [ -z "${APP_KEY_FROM_ENV}" ] && [ -z "${APP_KEY_FROM_FILE}" ]; then
  if [ "${AUTO_GENERATE_APP_KEY:-true}" = "true" ]; then
    echo "[bootstrap] APP_KEY missing; generating application key" >&2
    php artisan key:generate --force --no-interaction || true
  else
    echo "[bootstrap] APP_KEY missing and AUTO_GENERATE_APP_KEY=false" >&2
  fi
fi
