#!/usr/bin/env sh
set -eux

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"

cd "${APP_DIR}"

APP_ENV="${APP_ENV:-production}"
APP_DEBUG="${APP_DEBUG:-false}"
PORT_VALUE="${PORT:-10000}"
DB_MIGRATE_ON_BOOT="${DB_MIGRATE_ON_BOOT:-true}"
DB_ENSURE_SEEDED_ON_BOOT="${DB_ENSURE_SEEDED_ON_BOOT:-true}"
DB_FORCE_SEED_ON_BOOT="${DB_FORCE_SEED_ON_BOOT:-false}"

echo "[startup] Starting application startup script"
echo "[startup] APP_ENV=${APP_ENV} APP_DEBUG=${APP_DEBUG} DB_MIGRATE_ON_BOOT=${DB_MIGRATE_ON_BOOT} DB_ENSURE_SEEDED_ON_BOOT=${DB_ENSURE_SEEDED_ON_BOOT}"

if [ "${APP_ENV}" = "production" ]; then
  echo "[startup] Production detected: automatic seeding is disabled"
  DB_ENSURE_SEEDED_ON_BOOT="false"
fi

/bin/sh "${SCRIPT_DIR}/bootstrap-laravel.sh"
/bin/sh "${SCRIPT_DIR}/fix-laravel-permissions.sh"

php artisan config:cache --no-interaction || true
php artisan route:cache --no-interaction || true
php artisan view:cache --no-interaction || true

mkdir -p /var/www/storage/logs /var/www/storage/logs/startup /etc/nginx/conf.d

if [ "${DB_MIGRATE_ON_BOOT}" = "true" ]; then
  echo "[startup] Waiting for database connection"
  /bin/sh "${SCRIPT_DIR}/wait-for-db.sh"
  echo "[startup] Running safe migrations"
  if php artisan migrate --force --no-interaction; then
    echo "[startup] Migrations completed successfully"
  else
    echo "[startup] ERROR: Migrations failed" >&2
    exit 1
  fi
else
  echo "[startup] DB_MIGRATE_ON_BOOT disabled; skipping migrations"
fi

if [ "${DB_ENSURE_SEEDED_ON_BOOT}" = "true" ]; then
  SEED_FORCE_FLAG=""
  if [ "${DB_FORCE_SEED_ON_BOOT}" = "true" ]; then
    SEED_FORCE_FLAG="--force"
  fi

  echo "[startup] Running optional database seeding"
  if php artisan app:seed-database --marker="${DB_SEED_MARKER:-development-default}" ${SEED_FORCE_FLAG} --no-interaction; then
    echo "[startup] Optional database seeding completed"
  else
    echo "[startup] WARNING: Optional database seeding failed" >&2
  fi
else
  echo "[startup] Automatic seeding disabled for this environment"
fi

if [ "${DASHBOARD_WARM_ON_BOOT:-true}" = "true" ]; then
  echo "[startup] Running dashboard warm cache in background"
  nohup php artisan dashboard:warm-cache --clear --days="${DASHBOARD_WARM_DAYS:-7}" --no-interaction > /var/www/storage/logs/startup/dashboard-warm.log 2>&1 &
fi

export PORT="${PORT_VALUE}"
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

echo "[startup] Starting supervisord"
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
