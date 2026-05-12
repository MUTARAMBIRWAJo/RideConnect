# ML Service - Migration & Initialization Guide

## Overview

This guide covers database initialization, weight audit logging, and running migrations inside Docker containers.

---

## 1. Database Initialization

### One-Shot Initialization

The ML service automatically creates required tables on startup. For manual initialization:

```bash
# From ml-service directory
docker-compose --profile init run --rm init-db
```

This runs `app/scripts/init_db.py` which calls `app.services.weights_db.init_db()` to create:
- `ml_weights` table (stores matching algorithm weights)
- `ml_weights_audit` table (stores all weight changes)

### Schema

**ml_weights table:**
```sql
CREATE TABLE ml_weights (
  id SERIAL PRIMARY KEY,
  key VARCHAR(128) UNIQUE NOT NULL,
  value FLOAT NOT NULL
);
```

**ml_weights_audit table:**
```sql
CREATE TABLE ml_weights_audit (
  id SERIAL PRIMARY KEY,
  actor VARCHAR(128) NOT NULL,
  payload TEXT NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

### Default Weights

```python
_weights = {
    "distance": 0.35,      # Driver proximity importance
    "rating": 0.2,         # Driver rating importance
    "acceptance": 0.15,    # Historical acceptance rate
    "cancellation": 0.1,   # Historical cancellation rate (inverted)
    "behavior": 0.1,       # Driver behavior score
    "direction": 0.1,      # Route direction alignment
}
```

---

## 2. Weight Audit Logs

### Viewing Audit Logs

```bash
curl -X GET "http://localhost:8000/api/admin/weights/audit" \
  -H "X-Admin-Token: $(cat ../../.env | grep APP_KEY | cut -d= -f2)" \
  -H "Content-Type: application/json" | python3 -m json.tool
```

**Query Parameters:**
- `limit` (default: 50, max: 200) - Results per page
- `offset` (default: 0) - Pagination offset

**Response:**
```json
{
  "items": [
    {
      "id": 1,
      "actor": "admin",
      "payload": {
        "distance": 0.40,
        "rating": 0.25
      },
      "created_at": "2026-05-11T10:30:45.123456+00:00"
    }
  ],
  "total": 42,
  "limit": 50,
  "offset": 0
}
```

### Updating Weights

```bash
curl -X POST "http://localhost:8000/api/admin/weights" \
  -H "X-Admin-Token: YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "distance": 0.40,
    "rating": 0.25,
    "acceptance": 0.15
  }'
```

This automatically records the change in the audit log.

### Retrieving Current Weights

```bash
curl -X GET "http://localhost:8000/api/admin/weights" \
  -H "X-Admin-Token: YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" | python3 -m json.tool
```

---

## 3. Running Migrations & Tests in Docker

### Option A: During Service Startup

The `init-db` profile runs automatically when you first start the service:

```bash
cd ml-service

# Start all services including database initialization
docker-compose --profile init up --build
```

This executes:
1. Redis starts first
2. Init-db runs and creates tables
3. ML service starts (model loads, checks tables exist)
4. Nginx starts

### Option B: Manual Migration Run

```bash
# Run migrations only (don't start ml-service)
docker-compose --profile init run --rm init-db
```

### Option C: Running Tests in Docker

```bash
# Run pytest inside Docker container
docker-compose run --rm ml-service pytest tests/ -v
```

**To run specific test file:**
```bash
docker-compose run --rm ml-service pytest tests/test_api.py -v
```

**With coverage:**
```bash
docker-compose run --rm ml-service pytest tests/ --cov=app --cov-report=html
```

---

## 4. Docker Compose Profiles

The docker-compose.yml uses profiles to control service startup:

```yaml
# Default profile (always runs)
services:
  redis:
  ml-service:
  nginx:

# Init profile (run manually)
services:
  init-db:
    profiles:
      - init
```

**Usage:**
```bash
# Run without init service
docker-compose up

# Run with init service
docker-compose --profile init up

# Run only init service
docker-compose --profile init run --rm init-db
```

---

## 5. Migration Helper Script

### app/scripts/init_db.py

```python
"""One-shot database initialization helper."""

from __future__ import annotations

import sys

from app.core.logging import get_logger
from app.services.weights_db import init_db

logger = get_logger(__name__)


def main() -> int:
    """Create the ML service tables if they do not exist yet."""
    try:
        init_db()
        logger.info("ML service tables initialized")
        return 0
    except Exception as exc:
        logger.exception("Failed to initialize ML service tables: %s", exc)
        return 1


if __name__ == "__main__":
    sys.exit(main())
```

### Running the Script Standalone

```bash
# Inside Docker container
docker-compose run --rm ml-service python -m app.scripts.init_db

# Or with poetry/pipenv if installed locally
python -m app.scripts.init_db
```

---

## 6. Environment Configuration

### Required Variables in .env

```env
# Database
DATABASE_URL=postgresql://...        # Auto-built from DB_* vars
DB_HOST=aws-1-us-east-1.pooler.supabase.com
DB_PORT=5432
DB_USERNAME=postgres.tpahuvmhlfluztuhznfj
DB_PASSWORD=...
DB_DATABASE=postgres
DB_SSLMODE=require

# Admin
APP_KEY=base64:...                   # Used as SECRET_KEY for admin endpoints

# Redis
REDIS_URL=redis://redis:6379/0
```

### For ML Service Only

```env
# Model
MODEL_PATH=/app/models/trained/rideconnect_v2_best.keras

# Logging
LOG_LEVEL=INFO
DEBUG=false

# Supabase (optional)
SUPABASE_URL=...
SUPABASE_KEY=...
SUPABASE_JWT_SECRET=...
```

---

## 7. Troubleshooting

### Tables Already Exist

If tables already exist, `init_db()` safely skips creation (SQLAlchemy respects `create_all()` idempotency):

```python
Base.metadata.create_all(bind=engine)  # Safe to run multiple times
```

### Connection Refused

```
Error: Failed to connect to database
```

**Fix:**
1. Verify DATABASE_URL is correct
2. Ensure Supabase database is accessible
3. Check SSL certificates (DB_SSLMODE=require)
4. Verify firewall allows outbound connections

### Admin Token Invalid

```
HTTP 403: invalid admin token
```

**Fix:**
```bash
# Use APP_KEY from .env file
curl -H "X-Admin-Token: $(grep APP_KEY ../../.env | cut -d= -f2 | tr -d 'base64:' | base64 -d)" ...
```

Or extract just the key part:
```bash
grep APP_KEY ../../.env | cut -d= -f2
```

---

## 8. Complete Startup Example

```bash
#!/bin/bash
# Complete startup with migrations and tests

cd /home/joseph/projects/RideConnectBackend/ml-service

# 1. Clean up old containers
docker-compose down

# 2. Build images (fresh)
docker-compose build --no-cache

# 3. Start services
docker-compose --profile init up -d

# 4. Wait for startup
sleep 10

# 5. Check health
curl http://localhost:8000/health | python3 -m json.tool

# 6. Run tests
docker-compose run --rm ml-service pytest tests/ -v

# 7. View audit logs
curl -X GET "http://localhost:8000/api/admin/weights/audit" \
  -H "X-Admin-Token: YOUR_APP_KEY" \
  -H "Content-Type: application/json" | python3 -m json.tool

# 8. Update weights
curl -X POST "http://localhost:8000/api/admin/weights" \
  -H "X-Admin-Token: YOUR_APP_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "distance": 0.40,
    "rating": 0.25,
    "acceptance": 0.15,
    "cancellation": 0.10,
    "behavior": 0.10,
    "direction": 0.10
  }' | python3 -m json.tool

# 9. View updated audit logs
curl -X GET "http://localhost:8000/api/admin/weights/audit?limit=5" \
  -H "X-Admin-Token: YOUR_APP_KEY" | python3 -m json.tool
```

---

## 9. Docker Compose Service Reference

### init-db Service

```yaml
init-db:
  build:
    context: .
    dockerfile: Dockerfile
  container_name: rideconnect-ml-init-db
  env_file:
    - ../.env
  command: ["python", "-m", "app.scripts.init_db"]
  depends_on:
    - redis
  profiles:
    - init
  volumes:
    - ./models:/app/models:ro
    - ./app:/app/app:ro
  networks:
    - rideconnect-network
  restart: "no"
```

**Key Points:**
- `profiles: [init]` - Only runs with `--profile init`
- `restart: "no"` - Exits after completion
- `depends_on: [redis]` - Redis must be available first
- `env_file: ../.env` - Reads parent .env file

---

## 10. Monitoring Migrations

### Check Logs

```bash
# All service logs
docker-compose logs

# Just init-db logs
docker-compose logs init-db

# ML service logs
docker-compose logs -f ml-service

# Real-time follow
docker-compose logs -f ml-service | grep "initialize\|startup\|weights"
```

### Verify Tables Created

```bash
# Connect to database
psql "postgresql://postgres.tpahuvmhlfluztuhznfj:PASSWORD@HOST:5432/postgres?sslmode=require"

# List tables
\dt ml_*;

# Check ml_weights
SELECT * FROM ml_weights;

# Check audit logs
SELECT id, actor, created_at FROM ml_weights_audit ORDER BY created_at DESC LIMIT 10;
```

---

## 11. CI/CD Integration

### GitHub Actions Example

```yaml
name: ML Service CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_DB: test_rideconnect
          POSTGRES_PASSWORD: test
    steps:
      - uses: actions/checkout@v3
      - uses: docker/setup-buildx-action@v2
      - name: Run migrations
        run: docker-compose --profile init run --rm init-db
      - name: Run tests
        run: docker-compose run --rm ml-service pytest tests/ -v --cov=app
```

---

## Summary

| Task | Command |
|------|---------|
| Initialize DB | `docker-compose --profile init run --rm init-db` |
| View weights | `curl -H "X-Admin-Token: KEY" http://localhost:8000/api/admin/weights` |
| View audit logs | `curl -H "X-Admin-Token: KEY" http://localhost:8000/api/admin/weights/audit` |
| Update weights | `curl -X POST -H "X-Admin-Token: KEY" -d '{...}' http://localhost:8000/api/admin/weights` |
| Run tests | `docker-compose run --rm ml-service pytest tests/ -v` |
| Run migrations only | `docker-compose --profile init run --rm init-db` |
| Start with migrations | `docker-compose --profile init up --build` |
| Check tables | `psql ... -c "\dt ml_*"` |

---

For detailed architecture information, see [ML_SERVICE_ARCHITECTURE.md](ML_SERVICE_ARCHITECTURE.md)  
For implementation details, see [ML_SERVICE_IMPLEMENTATION_REPORT.md](ML_SERVICE_IMPLEMENTATION_REPORT.md)  
For quick start, see [ML_SERVICE_QUICKSTART.md](ML_SERVICE_QUICKSTART.md)
