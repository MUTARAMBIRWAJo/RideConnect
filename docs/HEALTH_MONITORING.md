# RideConnect Production Health Monitoring

Production health endpoints for Render, uptime monitors, and on-call diagnostics.

## Endpoint inventory

| Endpoint | Purpose | Auth | Cost |
|----------|---------|------|------|
| `GET /health/live` | Liveness — process is running | None | Minimal |
| `GET /health/ready` | Readiness — core dependencies OK | None | Low |
| `GET /health/full` | Full subsystem diagnostics | None | Medium |
| `GET /health` | Alias → `/health/live` | None | Minimal |
| `GET /up` | Laravel built-in health route | None | Minimal |
| `GET /api/v1/health/finance` | Finance module deep check | None | Medium |
| `GET /api/v1/health/settlement` | Settlement module check | None | Medium |
| `GET /api/v1/health/warehouse` | Warehouse / ETL check | None | Medium |
| `GET /api/v1/ml/health` | ML proxy (Sanctum required) | Bearer | Medium |

**Render configuration:** `healthCheckPath: /health/live` (see `render.yaml`).

---

## Standard endpoints

### Liveness — `GET /health/live`

Use for load balancer / Render health checks. Does **not** call PostgreSQL, Firebase, or ML.

```json
{
  "status": "alive",
  "timestamp": "2026-06-13T15:30:00+00:00"
}
```

HTTP **200** when PHP process responds.

### Readiness — `GET /health/ready`

Use before routing production traffic after deploy.

```json
{
  "status": "ready",
  "database": true,
  "firebase": true,
  "ml_service": true,
  "queue": true,
  "timestamp": "2026-06-13T15:30:00+00:00"
}
```

| HTTP | Meaning |
|------|---------|
| **200** | Required checks (`database`, `queue`) passed |
| **503** | Required dependency failed — do not route traffic |

Optional checks (`firebase`, `ml_service`) are reported but do not fail readiness by default.

### Full diagnostics — `GET /health/full`

```json
{
  "status": "healthy",
  "environment": "production",
  "version": "1.0.0",
  "checks": {
    "database": {
      "ok": true,
      "status": "ok",
      "message": "PostgreSQL connection healthy",
      "latency_ms": 12,
      "details": {
        "driver": "pgsql",
        "database": "postgres",
        "migration_status": {
          "pending_count": 0,
          "up_to_date": true
        }
      }
    },
    "firebase": { "...": "..." },
    "ml_service": { "...": "..." },
    "queue": { "...": "..." },
    "storage": { "...": "..." },
    "application": { "...": "..." }
  },
  "summary": {
    "status": "healthy",
    "http_status": 200,
    "ok_count": 6,
    "total": 6,
    "failed": []
  },
  "timestamp": "2026-06-13T15:30:00+00:00"
}
```

| Summary status | HTTP | Meaning |
|----------------|------|---------|
| `healthy` | 200 | All checks passed |
| `degraded` | 200 | Optional subsystem failed |
| `unhealthy` | 503 | Required subsystem failed |

---

## Subsystem coverage

| Subsystem | Checks |
|-----------|--------|
| **Database** | PDO connection, `SELECT 1`, migration pending count |
| **Firebase** | Credentials file, Admin SDK init, optional Firestore/RTDB probe |
| **ML Service** | `GET /health`, response time, optional `/rank-drivers` probe |
| **Queue** | Driver config, `jobs` table, pending/failed counts |
| **Storage** | Writable `storage/*` and `bootstrap/cache` |
| **Application** | Laravel boot, config/route cache status (full only) |

---

## Configuration

`config/health.php` and environment variables:

```env
HEALTH_DB_TIMEOUT_MS=3000
HEALTH_FIREBASE_TIMEOUT_MS=3000
HEALTH_ML_TIMEOUT_MS=5000
HEALTH_QUEUE_TIMEOUT_MS=2000
ML_SERVICE_URL=https://ml-service-j72g.onrender.com
FIREBASE_ENABLED=true
FIREBASE_CREDENTIALS=/path/to/credentials.json
FIREBASE_DATABASE_URL=https://your-project.firebaseio.com
```

---

## Troubleshooting

### `/health/ready` returns 503, `database: false`

1. Verify Supabase credentials in Render env vars (`DB_HOST`, `DB_PASSWORD`, `DB_SSLMODE=require`).
2. Confirm pooler allows connections from Render IP.
3. Run `php artisan migrate:audit --pending` (migration safety system).

### `firebase: false`

1. Ensure `FIREBASE_CREDENTIALS` points to a readable JSON key file on Render.
2. Set `FIREBASE_PROJECT_ID` and `FIREBASE_DATABASE_URL`.
3. If Firebase is intentionally disabled: `FIREBASE_ENABLED=false` (reports `true` as skipped).

### `ml_service: false`

1. Confirm ML service is awake: `curl https://ml-service-j72g.onrender.com/health`
2. Set `ML_SERVICE_URL` or `TFLITE_ENDPOINT` to the correct Render URL.
3. Cold starts on free tier can exceed timeout — increase `HEALTH_ML_TIMEOUT_MS`.

### `/api/v1/health/finance` returns 503

This is a **module-specific** check (ledger accounts, event outbox). It does not affect `/health/live`.

1. Run pending migrations: `php artisan migrate --force`
2. Seed ledger accounts if empty: `php artisan db:seed --class=LedgerAccountSeeder`

### High `failed_jobs` in `/health/full`

1. Inspect `failed_jobs` table.
2. Restart queue worker on Render or scale to dedicated worker service.
3. Re-run: `php artisan queue:retry all` (staging only).

---

## Operations commands

```bash
# Quick liveness (Render)
curl -s https://rideconnect-emp0.onrender.com/health/live

# Readiness after deploy
curl -s https://rideconnect-emp0.onrender.com/health/ready

# Full diagnostics
curl -s https://rideconnect-emp0.onrender.com/health/full | jq .

# Module checks
curl -s https://rideconnect-emp0.onrender.com/api/v1/health/finance
```

---

## Architecture

```
PlatformHealthController
        │
        ▼
HealthCheckService  ──► DatabaseHealthCheck
                     ──► FirebaseHealthCheck
                     ──► MlServiceHealthCheck
                     ──► QueueHealthCheck
                     ──► StorageHealthCheck
                     ──► ApplicationHealthCheck
```

All checks use timeout wrappers and return structured `{ ok, status, message, latency_ms, details }` payloads without throwing.

---

## Testing

```bash
php artisan test --filter=PlatformHealthEndpointTest
```

Tests cover liveness, readiness failure modes, ML unreachable, queue failure, and full diagnostics shape.
