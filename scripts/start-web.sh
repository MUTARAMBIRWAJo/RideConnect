#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"

/bin/sh "${SCRIPT_DIR}/bootstrap-laravel.sh"

PORT_VALUE="${PORT:-10000}"
echo "[web] Starting Laravel server on 0.0.0.0:${PORT_VALUE}" >&2
exec php "${APP_DIR}/artisan" serve --host=0.0.0.0 --port="${PORT_VALUE}" --no-reload
