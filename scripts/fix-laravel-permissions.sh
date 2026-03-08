#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

cd "${APP_DIR}"

# Ensure all required runtime directories exist before permission updates.
mkdir -p \
  storage/framework/cache \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

# Apply ownership when running as root; skip safely for non-root containers.
if [ "$(id -u)" -eq 0 ]; then
  chown -R "${WEB_USER}:${WEB_GROUP}" storage bootstrap/cache 2>/dev/null || true
fi

# Keep write permissions broad enough for the web process and group.
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# Remove stale compiled artifacts and clear all Laravel caches.
rm -f bootstrap/cache/*.php 2>/dev/null || true
rm -f storage/framework/views/*.php 2>/dev/null || true

php artisan config:clear --no-interaction || true
php artisan route:clear --no-interaction || true
php artisan view:clear --no-interaction || true
php artisan cache:clear --no-interaction || true
