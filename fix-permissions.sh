#!/bin/bash

echo "🔧 Fixing Laravel permissions and cache issues..."

# Fix ownership and permissions
echo "📁 Setting correct permissions..."
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Fix file permissions specifically
find storage -type f -exec chmod 664 {} \;
find storage -type d -exec chmod 775 {} \;
find bootstrap/cache -type f -exec chmod 664 {} \;
find bootstrap/cache -type d -exec chmod 775 {} \;

# Clear all caches
echo "🧹 Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Rebuild essential caches
echo "🔄 Rebuilding caches..."
php artisan config:cache
php artisan route:cache

# Test view compilation
echo "🧪 Testing view compilation..."
php artisan tinker --execute="echo 'Testing views...'; try { view('welcome'); echo 'Views working correctly'; } catch (Exception \$e) { echo 'Error: ' . \$e->getMessage(); }"

echo "✅ Permission and cache fixes completed!"