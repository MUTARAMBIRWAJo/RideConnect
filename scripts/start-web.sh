#!/usr/bin/env sh
set -eu

/bin/sh /var/www/scripts/bootstrap-laravel.sh

PORT_VALUE="${PORT:-8000}"
echo "[web] Starting Laravel server on 0.0.0.0:${PORT_VALUE}" >&2
exec php /var/www/artisan serve --host=0.0.0.0 --port="${PORT_VALUE}"
