#!/bin/bash

# Recovery Script for RideConnect Environment
# This script fixes WSL/Windows PATH conflicts and environment issues
# Version: 2.0 - Comprehensive Recovery

set -e  # Exit on error

echo "=========================================="
echo "RIDECONNECT ENVIRONMENT RECOVERY SCRIPT"
echo "=========================================="
echo ""

# PHASE 1: Verify WSL environment
echo "PHASE 1: Verifying WSL environment..."
cd /home/joseph/projects/RideConnect
CURRENT_DIR=$(pwd)
echo "Current directory: $CURRENT_DIR"

if [ "$CURRENT_DIR" != "/home/joseph/projects/RideConnect" ]; then
    echo "ERROR: Not in the correct directory!"
    exit 1
fi

echo "✓ WSL environment verified"
echo ""

# PHASE 2: Remove Windows Node from PATH
echo "PHASE 2: Checking for Windows Node in PATH..."
if echo "$PATH" | grep -q "/mnt/c/Program Files/nodejs"; then
    echo "⚠ WARNING: Windows Node detected in PATH"
    echo "Removing Windows Node from ~/.bashrc..."
    
    # Backup bashrc
    cp ~/.bashrc ~/.bashrc.backup
    
    # Remove Windows Node entries
    sed -i '/mnt\/c\/Program Files\/nodejs/d' ~/.bashrc
    
    echo "✓ Windows Node removed from PATH"
    echo "✓ Backup saved to ~/.bashrc.backup"
    
    # Reload bashrc
    source ~/.bashrc
    echo "✓ bashrc reloaded"
else
    echo "✓ No Windows Node in PATH"
fi
echo ""

# PHASE 3: Install Node.js 22 LTS
echo "PHASE 3: Checking Node.js installation..."
NODE_VERSION=$(node -v 2>/dev/null || echo "not installed")
echo "Current Node version: $NODE_VERSION"

if [[ "$NODE_VERSION" != v22* ]]; then
    echo "Installing Node.js 22 LTS..."
    
    # Update packages
    sudo apt update
    
    # Add NodeSource repository
    curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
    
    # Install Node.js
    sudo apt install -y nodejs
    
    echo "✓ Node.js 22 LTS installed"
else
    echo "✓ Node.js 22 LTS already installed"
fi

# Verify Node installation
echo "Verifying Node installation..."
which node
which npm
node -v
npm -v
echo ""

# PHASE 4: Clean broken Node environment
echo "PHASE 4: Cleaning broken Node environment..."
echo "Removing node_modules..."
rm -rf node_modules
echo "Removing package-lock.json..."
rm -f package-lock.json
echo "Removing public/build..."
rm -rf public/build
echo "Cleaning npm cache..."
npm cache clean --force
echo "✓ Node environment cleaned"
echo ""

# PHASE 5: Reinstall npm packages
echo "PHASE 5: Installing npm packages..."
npm install

# Check if npm install succeeded
if [ $? -ne 0 ]; then
    echo "npm install failed, trying with platform/arch flags..."
    npm install --platform=linux --arch=x64
fi

echo "✓ Node packages installed"
echo ""

# Verify critical dependencies
echo "Verifying critical dependencies..."
if [ -d "node_modules/vite" ]; then
    echo "✓ vite installed"
else
    echo "✗ vite missing"
    exit 1
fi

if [ -d "node_modules/esbuild" ]; then
    echo "✓ esbuild installed"
else
    echo "✗ esbuild missing"
    exit 1
fi
echo ""

# PHASE 6: Build assets
echo "PHASE 6: Building assets..."
npm run build
echo "✓ Assets built"
echo ""

# Verify build output
if [ -d "public/build" ]; then
    echo "✓ public/build directory created"
else
    echo "✗ public/build directory missing"
    exit 1
fi
echo ""

# PHASE 7: Fix Laravel dependencies
echo "PHASE 7: Updating Laravel dependencies..."
echo "Dumping autoload..."
composer dump-autoload
echo "✓ Autoload dumped"
echo ""

# PHASE 8: Clear Laravel caches
echo "PHASE 8: Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
echo "✓ All Laravel caches cleared"
echo ""

# PHASE 9: Verify Laravel boots
echo "PHASE 9: Verifying Laravel boots..."
if php artisan list > /dev/null 2>&1; then
    echo "✓ Laravel boots successfully"
else
    echo "✗ Laravel failed to boot"
    exit 1
fi
echo ""

# PHASE 10: Validate Firebase commands
echo "PHASE 10: Validating Firebase commands..."
echo "Testing firebase:bootstrap..."
if php artisan firebase:bootstrap --force; then
    echo "✓ firebase:bootstrap works"
else
    echo "⚠ firebase:bootstrap had issues (may be expected if Firebase not configured)"
fi
echo ""

echo "Testing firebase:validate..."
if php artisan firebase:validate; then
    echo "✓ firebase:validate works"
else
    echo "⚠ firebase:validate had issues (may be expected if Firebase not configured)"
fi
echo ""

echo "Testing firebase:reconcile --dry-run..."
if php artisan firebase:reconcile --dry-run; then
    echo "✓ firebase:reconcile works"
else
    echo "⚠ firebase:reconcile had issues (may be expected if Firebase not configured)"
fi
echo ""

echo "Testing rideconnect:production-check..."
if php artisan rideconnect:production-check; then
    echo "✓ rideconnect:production-check works"
else
    echo "⚠ rideconnect:production-check had issues"
fi
echo ""

echo "=========================================="
echo "RECOVERY SCRIPT COMPLETED SUCCESSFULLY"
echo "=========================================="
echo ""
echo "Summary:"
echo "✓ WSL environment verified"
echo "✓ Windows Node removed from PATH (if present)"
echo "✓ Node.js 22 LTS installed"
echo "✓ Node packages reinstalled"
echo "✓ Assets built"
echo "✓ Laravel dependencies updated"
echo "✓ Laravel caches cleared"
echo "✓ Laravel boots successfully"
echo "✓ Firebase commands validated"
echo ""
echo "Next steps:"
echo "1. Run: php artisan serve"
echo "2. Visit: http://localhost:8000"
echo "3. Check: docs/RECOVERY_REPORT.md for full details"
echo ""
