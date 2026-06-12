<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## RideConnect Ops Notes

### Supabase/Postgres Test Configuration

This project is Postgres-first. Testing is configured to use Postgres-compatible settings instead of SQLite in-memory defaults.

1. Copy `.env.testing.example` to `.env.testing`.
2. Point `TEST_DB_*` values to a dedicated test database (or isolated schema).
3. Never run tests against production Supabase data.

Run targeted dashboard tests:

```bash
php artisan test --filter=SuperDashboardRenderTest
php artisan test --filter=DashboardRoutingTest
```

### Dashboard Cache Warmup

After deployment (or during container boot), warm dashboard caches to reduce first-load latency:

```bash
php artisan dashboard:warm-cache --clear --days=7
```

Render startup script supports:

- `DASHBOARD_WARM_ON_BOOT=true|false`
- `DASHBOARD_WARM_DAYS=7`

### Persistent Database Bootstrapping

To keep production tables and baseline data consistent across deployments and restarts:

- Startup runs `php artisan db:migrate-seed-protect` (migrate + idempotent seed + lock all tables).
- `schema_table_locks` records every protected table; DROP/TRUNCATE is blocked at the Laravel layer (and via Postgres event trigger when available).
- Production blocks destructive commands (`migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`).

Local one-shot setup:

```bash
php artisan db:migrate-seed-protect
php artisan db:protect-tables --list
```

Force a full idempotent reseed:

```bash
php artisan db:migrate-seed-protect --force-seed
```

Environment flags:

- `DB_MIGRATE_ON_BOOT=true|false`
- `DB_ENSURE_SEEDED_ON_BOOT=true|false`
- `DB_SEED_MARKER=rideconnect-production`
- `DB_FORCE_SEED_ON_BOOT=true|false` (force re-run even when marker exists)
- `DB_SEED_SKIP_RESET=true` (recommended for data retention)

## ML Model Setup

Place the TFLite model file at:

```text
storage/ml/Matching_Modal_tflite_learn_1013157_3.tflite
```

The docker-compose `ml-service` mounts this path read-only into the container. For local development without Docker, point `TFLITE_ENDPOINT` to a running instance:

```bash
python -m uvicorn main:app --app-dir ml --port 8001 --reload
```

Feature vector order in `ml/main.py` `build_feature_vector()` MUST match training:

```text
[distance_km_norm, rating_norm, total_rides_norm, acceptance_rate,
 (1 - cancellation_rate), transport_type_norm]
```

If your Edge Impulse / Colab training notebook used a different column order, update `build_feature_vector()` to match before deploying.
