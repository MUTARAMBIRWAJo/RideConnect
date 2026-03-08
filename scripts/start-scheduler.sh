#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"

if [ "${RUN_SCHEDULER:-true}" != "true" ]; then
  echo "[scheduler] RUN_SCHEDULER is not true; idling" >&2
  exec tail -f /dev/null
fi

/bin/sh "${SCRIPT_DIR}/bootstrap-laravel.sh"
/bin/sh "${SCRIPT_DIR}/wait-for-db.sh"

echo "[scheduler] Starting scheduler" >&2
exec php "${APP_DIR}/artisan" schedule:work
