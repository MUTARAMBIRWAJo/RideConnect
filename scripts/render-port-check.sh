#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"

PORT_VALUE="${PORT:-10000}"
echo "[render-port-check] Starting temporary Laravel server on :${PORT_VALUE}" >&2
exec php "${APP_DIR}/artisan" serve --host=0.0.0.0 --port="${PORT_VALUE}"
