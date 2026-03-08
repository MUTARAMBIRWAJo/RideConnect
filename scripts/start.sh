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
php artisan migrate --force --no-interaction || true

export PORT="${PORT_VALUE}"
mkdir -p /var/www/storage/logs /etc/nginx/conf.d
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
