#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

echo "============================================================"
echo " LAPORIN CONTAINER STARTUP"
echo "============================================================"

# Production Laravel runs inside Docker and must reach the primary
# MariaDB container over the Docker network. 127.0.0.1:13306 is the
# developer's SSH tunnel and only exists on the developer workstation.
# Normalize the known local-tunnel configuration when it is accidentally
# copied into the production container, before Laravel caches config.
if [[ "${APP_ENV:-}" == "production" ]] && [[ "${DB_HOST:-}" == "127.0.0.1" || "${DB_HOST:-}" == "localhost" ]] && [[ "${DB_PORT:-}" == "13306" ]]; then
    echo "[WARN] Production DB points to the local SSH tunnel 127.0.0.1:13306."
    echo "[FIX] Switching production database connection to laporin-db:3306."
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
