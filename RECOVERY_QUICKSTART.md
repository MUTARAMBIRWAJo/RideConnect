# RideConnect Recovery Quickstart

## Quick Execution (Recommended)

Run the automated recovery script in your WSL terminal:

```bash
cd ~/projects/RideConnect
chmod +x recovery_script.sh
./recovery_script.sh
```

This will automatically:
- Remove Windows Node from PATH
- Install Node.js 22 LTS
- Clean and reinstall node_modules
- Build assets
- Verify Laravel boots
- Test Firebase commands

---

## Manual Step-by-Step (If Script Fails)

### 1. Remove Windows Node from PATH

```bash
nano ~/.bashrc
# Remove any lines with /mnt/c/Program Files/nodejs
# CTRL+O, ENTER, CTRL+X
source ~/.bashrc
```

### 2. Install Node.js 22 LTS

```bash
sudo apt update
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

### 3. Clean and Reinstall Dependencies

```bash
cd ~/projects/RideConnect
rm -rf node_modules package-lock.json public/build
npm cache clean --force
npm install
```

### 4. Build Assets

```bash
npm run build
```

### 5. Verify Laravel

```bash
composer dump-autoload
php artisan optimize:clear
php artisan list
```

### 6. Test Firebase Commands

```bash
php artisan firebase:bootstrap --force
php artisan firebase:validate
php artisan firebase:reconcile --dry-run
php artisan rideconnect:production-check
```

---

## Verification

After recovery, verify:

```bash
which node  # Should NOT be /mnt/c/...
which npm   # Should NOT be /mnt/c/...
node -v     # Should be v22.x.x
npm -v      # Should be 10.x.x
ls node_modules/vite  # Should exist
ls node_modules/esbuild  # Should exist
ls public/build  # Should exist
```

---

## Full Documentation

See `docs/RECOVERY_REPORT.md` for detailed analysis and root causes.
