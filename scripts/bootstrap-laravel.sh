#!/usr/bin/env sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
APP_DIR="${APP_DIR:-$(CDPATH= cd -- "${SCRIPT_DIR}/.." && pwd)}"

cd "${APP_DIR}"

mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache

# Best-effort permissions (won't fail when running as non-root)
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

if [ -f bootstrap/cache/config.php ] && ! grep -Fq "${APP_DIR}/" bootstrap/cache/config.php; then
  echo "[bootstrap] Detected stale Laravel cache for a different app path; clearing bootstrap/cache" >&2
  rm -f bootstrap/cache/*.php
fi

APP_KEY_FROM_ENV="${APP_KEY:-}"
if [ -n "${APP_KEY_FROM_ENV}" ]; then
  exit 0
fi

if [ ! -f .env ]; then
  echo "[bootstrap] APP_KEY is unset and .env is missing; skipping key generation" >&2
  exit 0
fi

if ! grep -q '^APP_KEY=' .env; then
  echo "[bootstrap] APP_KEY entry is missing in .env; skipping key generation" >&2
  exit 0
fi

APP_KEY_FROM_FILE="$(sed -n 's/^APP_KEY=//p' .env | head -n 1)"
if [ -z "${APP_KEY_FROM_FILE}" ]; then
  if [ "${AUTO_GENERATE_APP_KEY:-true}" = "true" ]; then
    echo "[bootstrap] APP_KEY missing in .env; generating application key" >&2
    php artisan key:generate --force --no-interaction || true
  else
    echo "[bootstrap] APP_KEY missing and AUTO_GENERATE_APP_KEY=false" >&2
  fi
fi
