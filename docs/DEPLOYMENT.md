# DEPLOYMENT

## Production Host

Aplikasi berjalan sebagai container Docker `app` di network `cf-network`, memakai DB container `laporin-db` dan volume `laporin-prod-storage` untuk storage aplikasi.

## Backup Wajib

Sebelum recreate container:

1. Dump database produksi.
2. Arsipkan storage produksi.
3. Arsipkan source saat ini.

Backup tidak boleh dipush ke GitHub.

## Build dan Deploy

```bash
npm run build
docker build -t laporin-app:<timestamp> .
```

Deploy container harus memakai env produksi dari container lama atau secret manager, lalu mount volume:

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

## Health Check

```bash
docker exec app php artisan migrate:status --no-interaction
docker exec app php artisan tinker --execute="DB::select('select 1')"
curl -I https://report.assetloan.my.id/
```

## Rollback

1. Stop container baru.
2. Jalankan lagi image lama dengan env dan volume yang sama.
3. Jika migration merusak data, restore DB dari backup terakhir.
