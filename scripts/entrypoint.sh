#!/usr/bin/env sh
set -eux

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"

cd "${APP_DIR}"

echo "[entrypoint] Starting entrypoint"
rm -f bootstrap/cache/*.php

/bin/sh "${SCRIPT_DIR}/bootstrap-laravel.sh"

if [ "${WAIT_FOR_DB:-false}" = "true" ]; then
  echo "[entrypoint] Waiting for database connection"
  /bin/sh "${SCRIPT_DIR}/wait-for-db.sh"
fi

if [ "${RUN_CONFIG_CACHE:-true}" = "true" ]; then
  echo "[entrypoint] Caching config, routes, and views"
  php artisan config:clear --no-interaction || true
  php artisan route:clear --no-interaction || true
  php artisan view:clear --no-interaction || true
  php artisan config:cache --no-interaction || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "[entrypoint] Running safe migrations"
  /bin/sh "${SCRIPT_DIR}/wait-for-db.sh"
  php artisan migrate --force --no-interaction
fi

PORT_VALUE="${PORT:-10000}"
echo "[entrypoint] Starting Laravel HTTP server on 0.0.0.0:${PORT_VALUE}" >&2
exec php artisan serve --host=0.0.0.0 --port="${PORT_VALUE}" --no-reload
