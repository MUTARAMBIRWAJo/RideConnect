#!/usr/bin/env sh
set -eu

if [ "${RUN_WORKER:-true}" != "true" ]; then
  echo "[worker] RUN_WORKER is not true; idling" >&2
  exec tail -f /dev/null
fi

if [ "${QUEUE_CONNECTION:-sync}" = "sync" ]; then
  echo "[worker] QUEUE_CONNECTION=sync; queue worker disabled by design" >&2
  exec tail -f /dev/null
fi

/bin/sh /var/www/scripts/bootstrap-laravel.sh
/bin/sh /var/www/scripts/wait-for-db.sh

QUEUE_NAME="${QUEUE_WORKER_QUEUE:-default}"
TRIES="${QUEUE_WORKER_TRIES:-3}"
TIMEOUT="${QUEUE_WORKER_TIMEOUT:-90}"
SLEEP_SEC="${QUEUE_WORKER_SLEEP:-3}"

echo "[worker] Starting queue worker (connection=${QUEUE_CONNECTION}, queue=${QUEUE_NAME})" >&2
exec php /var/www/artisan queue:work "${QUEUE_CONNECTION}" --queue="${QUEUE_NAME}" --sleep="${SLEEP_SEC}" --tries="${TRIES}" --timeout="${TIMEOUT}"
