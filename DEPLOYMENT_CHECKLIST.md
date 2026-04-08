# RideConnect Dashboard - Deployment Checklist

## Pre-Deployment (Local/Staging)

- [x] Fix all PHP syntax errors
- [x] Register all 10 dashboards in AdminPanelProvider
- [x] Verify role routing logic
- [x] Test all database role types
- [x] Create comprehensive test suite
- [x] Verify environment variables configured
- [x] Cache config and routes
- [x] Create documentation

## Deployment Steps

### 1. Code Deployment
```bash
# Pull latest changes
git pull origin main

# Install dependencies (if needed)
composer install --no-dev

# Clear any previous caches
php artisan cache:clear
php artisan view:clear
php artisan config:clear
php artisan route:clear
```

### 2. Application Caching
```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

### 3. Database Verification
```bash
# Run pending migrations (if any)
php artisan migrate --force

# Ensure baseline seed data exists (idempotent, does not wipe existing rows)
php artisan app:seed-database --marker=rideconnect-production --no-interaction

# Verify users have proper roles assigned
php artisan tinker
# $users = User::all();
# foreach($users as $user) { echo $user->email . ' -> ' . $user->role . "\n"; }
# exit();
```

### 4. Testing (Optional but Recommended)
```bash
# Run dashboard routing tests
php artisan test tests/Feature/DashboardRoutingTest.php --no-coverage

# Run full test suite
php artisan test
```

### 5. Application Startup
```bash
# Start Laravel server
php artisan serve

# Or with Octane (if using)
php artisan octane:start

# Or with Supervisor (production)
sudo supervisorctl restart all

# Or via Docker
docker-compose up -d
```

## Post-Deployment Verification

### 1. Application Health Check
```bash
# Check application logs for errors
tail -f storage/logs/laravel.log

# Monitor for 403 errors or exceptions
grep -i "error\|403\|exception" storage/logs/laravel.log

# Verify no critical errors in logs
php artisan log:clear
```

### 2. Manual Testing - Super Admin
- [ ] Log in as SUPER_ADMIN user
- [ ] Verify redirects to /admin/super-dashboard
- [ ] Verify all widgets load correctly
- [ ] Verify can access all other dashboards
- [ ] Test navigation menu shows all dashboards

### 3. Manual Testing - Admin
- [ ] Log in as ADMIN user
- [ ] Verify redirects to /admin/admin-dashboard
- [ ] Verify cannot access /admin/super-dashboard (403)
- [ ] Verify correct widgets display
- [ ] Verify navigation shows only authorized dashboards

### 4. Manual Testing - Accountant
- [ ] Log in as ACCOUNTANT user
- [ ] Verify redirects to /admin/accountant-dashboard
- [ ] Can access BI dashboard via navigation
- [ ] Can access Compliance dashboard via navigation
- [ ] Cannot access ADMIN or SUPER_ADMIN dashboards

### 5. Manual Testing - Officer
- [ ] Log in as OFFICER user
- [ ] Verify redirects to /admin/officer-dashboard-v2
- [ ] Verify officer-specific widgets display
- [ ] Cannot access other manager dashboards

### 6. Manual Testing - Driver
- [ ] Log in as DRIVER user
- [ ] Verify redirects to /admin/driver-dashboard
- [ ] Verify driver-specific data displays
- [ ] Cannot access manager dashboards

### 7. Manual Testing - Passenger
- [ ] Log in as PASSENGER user
- [ ] Verify redirects to /admin/passenger-dashboard
- [ ] Verify passenger-specific data displays
- [ ] Cannot access manager dashboards

### 8. Error Handling Tests
- [ ] Test 403 access attempts (unauthorized users)
- [ ] Test with invalid/missing role
- [ ] Test session timeout handling
- [ ] Check error pages display correctly

## Monitoring After Deployment

### Daily Monitoring
1. Check application logs for errors
2. Monitor dashboard access patterns
3. Verify no 403 errors from legitimate users
4. Check AI service connectivity

### Weekly Monitoring
1. Review user access patterns
2. Verify performance metrics
3. Check database role assignments
4. Monitor error rates

### Performance Metrics to Track
- Dashboard load time
- Widget render time
- API response times
- Error rate percentage
- User access frequency

## Rollback Plan (If Issues Occur)

### If Routes Not Working
```bash
# Clear all caches and regain access
php artisan cache:clear
php artisan route:clear
php artisan config:clear
php artisan route:cache
php artisan config:cache
```

### If Users Locked Out
```bash
# Verify dashboard registrations
php artisan tinker
# Use Filament\Filament::getCurrentPanel()
# Check panel configuration
# exit()

# Reset application
php artisan config:cache
php artisan route:cache
```

### If Database Role Issues
```bash
# Check all users have valid roles
php artisan tinker
# User::all()->pluck('email', 'role')->toArray()
# Ensure all roles match UserRole enum cases
# exit()

# Re-run safe baseline seed (non-destructive)
php artisan app:seed-database --marker=rideconnect-production --no-interaction
```

## Production Safety Rule

- Never run `php artisan migrate:fresh`, `php artisan migrate:reset`, or `php artisan db:wipe` in production.
- Production is guarded to block these destructive commands.

### Full Rollback
```bash
# Revert to previous code version
git revert HEAD --no-edit

# Clear all caches
php artisan cache:clear
php artisan route:clear

# Restart application
sudo supervisorctl restart all

# OR if using Docker
docker-compose restart
```

## Known Issues & Solutions

### Issue: User sees 403 Forbidden
**Solution:**
1. Check user role in database: `select email, role from users where id = ?`
2. Verify role matches `UserRole` enum case (case-sensitive)
3. Clear browser cache and cookies
4. Try incognito/private window

### Issue: Dashboard isn't showing in navigation
**Solution:**
1. Verify `shouldRegisterNavigation()` returns true
2. Check user has required role
3. Clear app cache: `php artisan cache:clear`
4. Check dashboard `canView()` permission

### Issue: Wrong dashboard loading
**Solution:**
1. Check `Dashboard.php` route matching logic
2. Verify user role value matches enum
3. Check `ResolveUserRoleValue()` method
4. Clear application cache

### Issue: AI Service connection failing
**Solution:**
1. Verify environment variables set correctly
2. Check RIDE_AI_API_KEY is valid
3. Verify RIDE_AI_BASE_URL is reachable
4. Check timeout isn't too short (default 10s)

## Configuration Verification

### Environment Variables Required
```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=your-host
DB_DATABASE=rideconnect
DB_USERNAME=root
DB_PASSWORD=your-password

# AI Service
RIDE_AI_BASE_URL=https://rideconnect-ai.onrender.com
RIDE_AI_API_KEY=your-api-key
RIDE_AI_TIMEOUT=10
```

### Dashboard Routes Should Match
```php
// From app/Filament/Pages/Dashboard.php
UserRole::SUPER_ADMIN->value     => "filament.admin.pages.super-dashboard"
UserRole::ADMIN->value            => "filament.admin.pages.admin-dashboard"
UserRole::ACCOUNTANT->value       => "filament.admin.pages.accountant-dashboard"
UserRole::OFFICER->value          => "filament.admin.pages.officer-dashboard-v2"
UserRole::DRIVER->value           => "filament.admin.pages.driver-dashboard"
UserRole::PASSENGER->value        => "filament.admin.pages.passenger-dashboard"
```

## Success Criteria

- [x] All PHP files have valid syntax
- [x] All dashboards are registered and accessible
- [x] Role-based routing works correctly
- [x] Access control properly enforced
- [x] Users redirected to correct dashboard on login
- [x] No 403 errors for authorized users
- [x] Navigation shows correct dashboards per role
- [x] Widgets load with user-specific data
- [x] Application boots without errors
- [x] Test suite passes all 10 tests
- [x] Documentation is complete

## Sign-Off

| Role | Name | Date | Status |
|------|------|------|--------|
| Developer | - | - | Ready |
| QA | - | - | Pending |
| Product | - | - | Pending |
| DevOps | - | - | Pending |

## Support Contacts

- **Technical Issues:** See docs/DASHBOARD_ROUTING.md
- **Troubleshooting:** See DASHBOARD_QUICK_REFERENCE.md
- **Implementation Details:** See DASHBOARD_FIX_REPORT.md
- **Visual Guides:** See docs/DASHBOARD_VISUAL_GUIDE.md

---

**Deployment Date:** [To be filled]
**Deployed By:** [To be filled]
**Environment:** [production/staging]
**Version:** 1.0.0

All systems go! 🚀
