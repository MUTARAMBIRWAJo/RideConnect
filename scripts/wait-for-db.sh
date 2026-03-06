#!/usr/bin/env sh
set -eu

DB_CONNECTION="${DB_CONNECTION:-pgsql}"
DB_HOST="${DB_HOST:-}"
DB_PORT="${DB_PORT:-}"
DB_DATABASE="${DB_DATABASE:-}"
DB_USERNAME="${DB_USERNAME:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_SSLMODE="${DB_SSLMODE:-prefer}"
MAX_TRIES="${DB_WAIT_MAX_TRIES:-60}"
SLEEP_SEC="${DB_WAIT_SLEEP_SEC:-2}"

if [ -z "$DB_HOST" ] || [ -z "$DB_PORT" ]; then
  echo "[db-wait] DB_HOST/DB_PORT not set; skipping readiness check" >&2
  exit 0
fi

tries=0
while [ "$tries" -lt "$MAX_TRIES" ]; do
  tries=$((tries + 1))

  if [ "$DB_CONNECTION" = "pgsql" ]; then
    php -r '
      $host=getenv("DB_HOST");
      $port=getenv("DB_PORT");
      $db=getenv("DB_DATABASE");
      $user=getenv("DB_USERNAME");
      $pass=getenv("DB_PASSWORD");
      $ssl=getenv("DB_SSLMODE") ?: "prefer";
      $dsn="pgsql:host={$host};port={$port};dbname={$db};sslmode={$ssl}";
      try { new PDO($dsn,$user,$pass,[PDO::ATTR_TIMEOUT=>3]); exit(0); }
      catch (Throwable $e) { exit(1); }
    ' && { echo "[db-wait] Database is reachable" >&2; exit 0; }
  else
    php -r '
      $host=getenv("DB_HOST");
      $port=getenv("DB_PORT");
      $db=getenv("DB_DATABASE");
      $user=getenv("DB_USERNAME");
      $pass=getenv("DB_PASSWORD");
      $dsn="mysql:host={$host};port={$port};dbname={$db}";
      try { new PDO($dsn,$user,$pass,[PDO::ATTR_TIMEOUT=>3]); exit(0); }
      catch (Throwable $e) { exit(1); }
    ' && { echo "[db-wait] Database is reachable" >&2; exit 0; }
  fi

  echo "[db-wait] Waiting for DB ${DB_HOST}:${DB_PORT} (attempt ${tries}/${MAX_TRIES})" >&2
  sleep "$SLEEP_SEC"
done

echo "[db-wait] Database not reachable after ${MAX_TRIES} attempts" >&2
exit 1
