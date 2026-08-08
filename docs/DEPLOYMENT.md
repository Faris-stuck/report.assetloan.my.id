# PENEMPATAN

## Host Produksi

Aplikasi berjalan sebagai container Docker `app` di network `cf-network`, memakai container DB `laporin-db` dan volume `laporin-prod-storage` untuk storage aplikasi.

## Backup Wajib

Sebelum buat ulang container:

1. Dump database produksi.
2. Arsipkan storage produksi.
3. Arsipkan source saat ini.

Backup tidak boleh dipush ke GitHub.

## Redis Produksi

Aplikasi wajib memakai Redis untuk session dan cache. Redis berjalan sebagai container internal-only di `cf-network` tanpa publish port ke host.

Konfigurasi env produksi yang relevan:

```dotenv
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_CONNECTION=default
QUEUE_CONNECTION=database
REDIS_CLIENT=phpredis
REDIS_HOST=laporin-redis
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
```

Gunakan `REDIS_PASSWORD` dari secure env file atau secret manager. Jangan commit password Redis ke repository.

Contoh container Redis:

```bash
docker run -d \
  --name laporin-redis \
  --restart unless-stopped \
  --network cf-network \
  -v laporin-redis-data:/data \
  --env-file <secure-redis-env-file> \
  redis:7-alpine \
  sh -c 'exec redis-server --appendonly yes --requirepass "$REDIS_PASSWORD" --maxmemory 256mb --maxmemory-policy allkeys-lru'
```

## Bangun dan Penempatan

```bash
npm run build
docker build -t laporin-app:<timestamp> .
```

Container penempatan harus memakai env produksi dari container lama atau secret manager, lalu mount volume:

```bash
docker run -d \
  --name app \
  --restart unless-stopped \
  --network cf-network \
  --env-file <secure-env-file> \
  -v laporin-prod-storage:/var/www/html/storage/app \
  laporin-app:<timestamp>
```

Entrypoint `docker/start.sh` menjalankan `php artisan migrate --force`, lalu cache Laravel.

## Pemeriksaan Kesehatan

```bash
docker exec app php artisan migrate:status --no-interaction
docker exec app php artisan tinker --execute="DB::select('select 1')"
curl -I https://report.assetloan.my.id/
```

## Pemulihan

1. Hentikan container baru.
2. Jalankan lagi image lama dengan env dan volume yang sama.
3. Jika migrasi merusak data, pulihkan DB dari backup terakhir.
