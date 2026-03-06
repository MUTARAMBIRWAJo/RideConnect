#!/usr/bin/env sh
set -eu

PORT_VALUE="${PORT:-8000}"
echo "[render-port-check] Starting Laravel web server on 0.0.0.0:${PORT_VALUE}" >&2
exec php /var/www/artisan serve --host=0.0.0.0 --port="${PORT_VALUE}"
