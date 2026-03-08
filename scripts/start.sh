#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"

cd "${APP_DIR}"

PORT_VALUE="${PORT:-10000}"
ENABLE_OCTANE_VALUE="${ENABLE_OCTANE:-auto}"

/bin/sh "${SCRIPT_DIR}/bootstrap-laravel.sh"

php artisan config:cache --no-interaction || true
php artisan route:cache --no-interaction || true
php artisan view:cache --no-interaction || true
php artisan migrate --force --no-interaction || true

if [ "${ENABLE_OCTANE_VALUE}" = "true" ]; then
	exec php artisan octane:start --server=swoole --host=0.0.0.0 --port="${PORT_VALUE}"
fi

if [ "${ENABLE_OCTANE_VALUE}" = "auto" ] && php -m | grep -qi '^swoole$'; then
	exec php artisan octane:start --server=swoole --host=0.0.0.0 --port="${PORT_VALUE}"
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT_VALUE}"
