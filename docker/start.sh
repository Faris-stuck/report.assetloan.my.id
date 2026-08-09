#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

echo "============================================================"
echo " LAPORIN CONTAINER STARTUP"
echo "============================================================"

mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "[INFO] Automatic database migration disabled."

echo "[INFO] Building configuration cache..."
php artisan config:cache

echo "[INFO] Building route cache..."
php artisan route:cache

echo "[INFO] Building view cache..."
php artisan view:cache

echo "[OK] Laravel startup preparation completed."
echo "[INFO] Starting Apache..."

exec apache2-foreground
