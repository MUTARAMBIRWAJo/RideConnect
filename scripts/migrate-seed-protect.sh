#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"

cd "${APP_DIR}"

echo "[migrate-seed-protect] Running additive migrations, seeding, and table lock protection..."

php artisan db:migrate-seed-protect \
  --seed-marker="${DB_SEED_MARKER:-rideconnect-production}" \
  "$@"

echo "[migrate-seed-protect] Complete. Locked tables:"
php artisan db:protect-tables --list
