# Protected Migration Review Checklist

RideConnect treats Supabase PostgreSQL as production data in every environment.
Schema work must preserve existing records and protected tables.

## Required Before Merge

- Confirm the migration is additive.
- Confirm existing columns and tables are preserved.
- Confirm seeders are idempotent.
- Confirm affected models, controllers, services, and policies match the target schema.
- Confirm Render startup uses `php artisan migrate --force`.
- Confirm daily Supabase backups are enabled for the project before deployment.
- Record manual owner approval when any schema-destructive change is proposed.

## Manual Approval Gate

If a change removes data, removes schema objects, recreates schema objects, renames tables, renames columns, or changes existing column types, stop implementation and attach an owner-approved database change request to the PR.

## Recommended Safe Pattern

```php
Schema::table('existing_table', function (Blueprint $table): void {
    if (! Schema::hasColumn('existing_table', 'new_column')) {
        $table->string('new_column')->nullable();
    }
});
```

## Deployment Requirement

Production deploys must run pending migrations only:

```bash
php artisan migrate --force
```
