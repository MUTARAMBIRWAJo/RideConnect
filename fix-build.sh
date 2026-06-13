#!/bin/bash

# RideConnect Build Fix Script
# Run this script from WSL terminal to fix npm build issues

set -e

echo "=========================================="
echo "RideConnect Build Fix Script"
echo "=========================================="
echo ""

# Check if running in WSL
if [[ "$OSTYPE" == "linux-gnu" ]]; then
    echo "✓ Running in WSL Linux environment"
else
    echo "✗ ERROR: Not running in WSL Linux environment"
    echo "Please run this script from WSL terminal"
    exit 1
fi

# Check if in project directory
if [[ ! -f "composer.json" ]]; then
    echo "✗ ERROR: Not in RideConnect project directory"
    echo "Please run from: ~/projects/RideConnect"
    exit 1
fi

echo "✓ In RideConnect project directory"
echo ""

# Clean npm
echo "Cleaning npm cache..."
npm cache clean --force

echo "Removing node_modules..."
rm -rf node_modules

echo "Removing package-lock.json..."
rm -f package-lock.json

echo "✓ npm cleanup complete"
echo ""

# Reinstall dependencies
echo "Installing dependencies..."
npm install

echo "✓ Dependencies installed"
echo ""

# Build assets
echo "Building assets..."
npm run build

echo "✓ Build complete"
echo ""

# Clear Laravel caches
echo "Clearing Laravel caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan filament:clear-cached-components

echo "✓ Laravel caches cleared"
echo ""

echo "=========================================="
echo "Build Fix Complete"
echo "=========================================="
echo ""
echo "You can now run:"
echo "  php artisan serve"
echo "  php artisan firebase:validate"
echo ""
