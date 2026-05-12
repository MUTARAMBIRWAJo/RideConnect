#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"

cd "${APP_DIR}"

PORT_VALUE="${PORT:-10000}"

/bin/sh "${SCRIPT_DIR}/bootstrap-laravel.sh"
/bin/sh "${SCRIPT_DIR}/fix-laravel-permissions.sh"

php artisan config:cache --no-interaction || true
php artisan route:cache --no-interaction || true
php artisan view:cache --no-interaction || true

export PORT="${PORT_VALUE}"
mkdir -p /var/www/storage/logs /var/www/storage/logs/startup /etc/nginx/conf.d
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

BOOT_LOG_DIR="/var/www/storage/logs/startup"
SEED_FORCE_FLAG=""
if [ "${DB_FORCE_SEED_ON_BOOT:-false}" = "true" ]; then
	SEED_FORCE_FLAG="--force"
fi

if [ "${DB_MIGRATE_ON_BOOT:-true}" = "true" ]; then
	nohup php artisan migrate --force --no-interaction >> "${BOOT_LOG_DIR}/db-migrate.log" 2>&1 &
fi

if [ "${DB_ENSURE_SEEDED_ON_BOOT:-true}" = "true" ]; then
	nohup php artisan app:seed-database --marker="${DB_SEED_MARKER:-production-default}" ${SEED_FORCE_FLAG} --no-interaction >> "${BOOT_LOG_DIR}/db-seed.log" 2>&1 &
fi

if [ "${DASHBOARD_WARM_ON_BOOT:-true}" = "true" ]; then
	nohup php artisan dashboard:warm-cache --clear --days="${DASHBOARD_WARM_DAYS:-7}" --no-interaction >> "${BOOT_LOG_DIR}/dashboard-warm.log" 2>&1 &
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
