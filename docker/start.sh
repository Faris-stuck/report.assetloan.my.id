#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

echo "============================================================"
echo " LAPORIN CONTAINER STARTUP"
echo "============================================================"

# 127.0.0.1:13306 is the developer workstation's SSH tunnel.
# A production Laravel container cannot reach that tunnel because
# 127.0.0.1 inside the container refers to the container itself.
# If this exact tunnel configuration is accidentally supplied to a
# Docker deployment, switch it to the internal MariaDB service before
# Laravel builds its configuration cache.
if [[ -f /.dockerenv ]] && [[ "${DB_PORT:-}" == "13306" ]] && [[ "${DB_HOST:-}" == "127.0.0.1" || "${DB_HOST:-}" == "localhost" ]]; then
    echo "[WARN] Docker container received developer DB target 127.0.0.1:13306."
    echo "[FIX] Using internal production database laporin-db:3306."
    export DB_HOST="laporin-db"
    export DB_PORT="3306"
fi

mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "[INFO] Database target: ${DB_HOST:-127.0.0.1}:${DB_PORT:-3306}/${DB_DATABASE:-laravel}"
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
