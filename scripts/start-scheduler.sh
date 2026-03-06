#!/usr/bin/env sh
set -eu

if [ "${RUN_SCHEDULER:-true}" != "true" ]; then
  echo "[scheduler] RUN_SCHEDULER is not true; idling" >&2
  exec tail -f /dev/null
fi

/bin/sh /var/www/scripts/bootstrap-laravel.sh
/bin/sh /var/www/scripts/wait-for-db.sh

echo "[scheduler] Starting scheduler" >&2
exec php /var/www/artisan schedule:work
