#!/usr/bin/env sh
set -eu
cd /var/www/html
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
exec php artisan queue:work database --sleep=3 --tries=3 --timeout=120 --max-time=3600
