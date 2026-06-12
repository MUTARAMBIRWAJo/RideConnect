## Database Safety Checklist

- [ ] I reviewed every migration in this PR for production data safety.
- [ ] This PR uses additive migrations only, or owner approval is linked below.
- [ ] No protected table is removed, recreated, emptied, or destructively altered.
- [ ] Seeders are idempotent and use first-or-create, update-or-create, or upsert patterns.
- [ ] Render deployment uses only `php artisan migrate --force`.
- [ ] Daily Supabase backups are enabled before deployment.

Owner approval for destructive schema work:

Link or write `N/A`:
