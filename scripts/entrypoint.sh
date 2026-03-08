#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"

cd "${APP_DIR}"

/bin/sh "${SCRIPT_DIR}/bootstrap-laravel.sh"

# Optional DB readiness gate for commands that require a live database.
if [ "${WAIT_FOR_DB:-false}" = "true" ]; then
  /bin/sh "${SCRIPT_DIR}/wait-for-db.sh"
fi

# Production-safe cache warmup. If a cache command fails, clear stale cache and continue.
if [ "${RUN_CONFIG_CACHE:-true}" = "true" ]; then
  php artisan config:cache --no-interaction || php artisan config:clear --no-interaction || true
  php artisan route:cache --no-interaction || php artisan route:clear --no-interaction || true
  php artisan view:cache --no-interaction || php artisan view:clear --no-interaction || true
fi

# Migrations are opt-in to avoid blocking HTTP startup on student/free-tier environments.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  /bin/sh "${SCRIPT_DIR}/wait-for-db.sh"
  php artisan migrate --force --no-interaction
fi

PORT_VALUE="${PORT:-10000}"
echo "[entrypoint] Starting Laravel HTTP server on 0.0.0.0:${PORT_VALUE}" >&2
exec php artisan serve --host=0.0.0.0 --port="${PORT_VALUE}" --no-reload
