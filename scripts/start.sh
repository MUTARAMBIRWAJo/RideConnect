#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"

cd "${APP_DIR}"

PORT_VALUE="${PORT:-10000}"

/bin/sh "${SCRIPT_DIR}/bootstrap-laravel.sh"

php artisan config:cache --no-interaction || true
php artisan route:cache --no-interaction || true
php artisan view:cache --no-interaction || true
php artisan migrate --force --no-interaction || true

exec php artisan octane:start --server=swoole --host=0.0.0.0 --port="${PORT_VALUE}"
