# Migration Safety System

RideConnect blocks destructive database operations in production/staging unless explicitly approved.

## Commands

| Command | Purpose |
|---------|---------|
| `php artisan migrate:audit` | Scan all migrations for destructive patterns |
| `php artisan migrate:audit --pending --save-report` | Audit only pending migrations and save report |
| `php artisan migrate:simulate-deploy` | Simulate Render deploy (`migrate --force` + policy lock) |
| `php artisan db:protect-tables --policy-only` | Lock core Supabase tables |
| `php artisan db:migrate-seed-protect` | Production deploy path (additive migrate + idempotent seed + lock) |

## Approval workflow

Destructive artisan commands require:

```bash
php artisan migrate:rollback --force --approve-destructive
```

Or environment variable:

```bash
MIGRATION_APPROVE_DESTRUCTIVE=true php artisan migrate:rollback --force
```

Reports are written to `storage/migration-reports/` as JSON + Markdown.

## Permanently blocked in production

These never run, even with approval:

- `migrate:fresh`
- `db:wipe`
- `migrate:refresh`
- `migrate:reset`

## Configuration

See `config/database_protection.php`:

- `DB_TABLE_PROTECTION=true`
- `DB_GUARD_ENVIRONMENTS=production,staging`
- `DB_BLOCK_DESTRUCTIVE_PENDING_MIGRATIONS=true`
- `DB_REQUIRE_DESTRUCTIVE_APPROVAL=true`

## Render deployment

`render.yaml` uses:

```yaml
startCommand: php artisan db:migrate-seed-protect --use-marker --seed-marker=rideconnect-production && ...
```

This runs **additive migrations only** and never wipes Supabase data.
