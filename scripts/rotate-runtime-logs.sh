#!/usr/bin/env sh
set -eu

# Rotate and truncate active Laravel/supervisor logs while keeping timestamped backups.
ROOT_DIR="${1:-/var/www}"
LOG_DIR="${ROOT_DIR}/storage/logs"
ARCHIVE_DIR="${LOG_DIR}/archive"
TS="$(date -u +%Y%m%dT%H%M%SZ)"

mkdir -p "${ARCHIVE_DIR}"

for f in laravel.log web.log web-error.log scheduler.log scheduler-error.log worker.log worker-error.log; do
  src="${LOG_DIR}/${f}"
  if [ -f "${src}" ]; then
    cp "${src}" "${ARCHIVE_DIR}/${f}.${TS}.bak"
    : > "${src}"
    echo "rotated: ${src} -> ${ARCHIVE_DIR}/${f}.${TS}.bak"
  fi
done

echo "done: logs rotated at ${TS}"