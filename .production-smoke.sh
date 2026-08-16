#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' '=== LAPORIN PRODUCTION SMOKE ==='
php artisan about --only=environment 2>/dev/null | head -40 || true
printf '%s\n' '=== APP HEALTH ==='
curl -fsS -o /dev/null -w 'health_http=%{http_code}\n' http://127.0.0.1:8080/up || true
printf '%s\n' '=== WAHA DOMAIN ==='
curl -sS -o /dev/null -w 'waha_http=%{http_code}\n' https://waha.assetloan.my.id/api/sessions || true
printf '%s\n' '=== REPORT DOMAIN ==='
curl -sS -o /dev/null -w 'report_http=%{http_code}\n' https://report.assetloan.my.id/ || true
printf '%s\n' '=== CONTAINERS ==='
sudo docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' | grep -E '^(NAMES|app|laporin-worker|ubuntu-waha-1|laporin-db|laporin-redis)' || true
