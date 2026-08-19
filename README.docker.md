# 🐳 assetloan.my.id — Full Docker (Self-Contained)

## Apa Bedanya dengan Yang Lama?

| Aspek | Cara Lama (Host) | Cara Baru (Docker) |
|---|---|---|
| Source code | `/var/www/assetloan.my.id/` (host) | **BAKED ke dalam image** |
| Nginx | Host (systemd) | Container `assetloan_nginx` |
| PHP-FPM | Host (systemd) | Container `assetloan_php` |
| MySQL | Host (systemd) | Container `assetloan_mysql` |
| Volume mount | Tidak ada | Hanya `mysql_data` (persist) |
| `docker ps` | Tidak muncul | ✅ Muncul semua |

## 📁 Struktur

```
assetloan.my.id/
│
├── docker-compose.yml        ← Orchestra: nginx + php + mysql
│
├── docker/
│   ├── php/
│   │   └── Dockerfile       ← PHP-FPM 8.3 Alpine + SOURCE CODE
│   │
│   └── nginx/
│       ├── Dockerfile       ← Nginx Alpine + SOURCE CODE
│       └── conf.d/
│           └── default.conf ← Nginx config
│
├── docker/mysql/init/
│   └── 01-init.sql         ← Database schema (auto-import)
│
├── .dockerignore
├── .env.docker              ← Template env
└── README.docker.md
```

## ⚡ Cara Menjalankan

```bash
# 1. Dari root project
cd /var/www/assetloan.my.id

# 2. Build image (source code di-bake ke dalam image)
docker compose -f docker-compose.yml build

# 3. Jalankan
docker compose -f docker-compose.yml up -d

# 4. Cek docker ps
docker ps
```

## 📋 Hasil `docker ps`

```
CONTAINER ID   IMAGE               COMMAND                  STATUS
a1b2c3d4e5f6   assetloan/nginx    "nginx -g 'daemon…"    Up 2 minutes
b2c3d4e5f6a1   assetloan/php      "php-fpm --nodaemon…"   Up 2 minutes
c3d4e5f6a1b2   assetloan/mysql    "docker-entrypoint…"    Up 2 minutes (healthy)
```

## 🌐 Akses

| Service | URL |
|---|---|
| **App (HTTP)** | http://localhost:8080 |
| **App (HTTPS)** | https://localhost:8443 |
| **MySQL (host)** | localhost:3307 |

> ⚠️ SSL certificate self-signed — klik "Proceed anyway" di browser.

## 🔧 Konfigurasi

### Ganti Password Database

Buka `docker-compose.yml`, bagian `mysql:` dan `php:`

```yaml
mysql:
  environment:
    MYSQL_ROOT_PASSWORD=password_root_baru    # ← ganti
    MYSQL_PASSWORD=password_app_baru          # ← ganti

php:
  environment:
    DB_PASSWORD=password_app_baru             # ← samakan
```

### Konfigurasi Ulang setelah Perubahan Code

Karena code baked ke dalam image, setiap ada perubahan code perlu rebuild:

```bash
docker compose -f docker-compose.yml build --no-cache
docker compose -f docker-compose.yml up -d
```

### Masuk ke Container

```bash
# Container PHP
docker exec -it assetloan_php sh

# Container MySQL
docker exec -it assetloan_mysql mysql -u root -p

# Container Nginx
docker exec -it assetloan_nginx sh
```

## 🔄 Workflow Development

```
1. Edit kode di /var/www/assetloan.my.id/
         │
         │  (belum langsung terefleksi — perlu rebuild)
         ▼
2. docker compose build --no-cache   ← rebuild image
         │
         ▼
3. docker compose up -d              ← deploy image baru
         │
         ▼
4. Test di http://localhost:8080
```

## 🗑️ Perintah Berguna

```bash
# Stop semua
docker compose -f docker-compose.yml down

# Stop + hapus data database
docker compose -f docker-compose.yml down -v

# Lihat logs
docker compose -f docker-compose.yml logs -f

# Lihat logs service tertentu
docker compose -f docker-compose.yml logs -f php
docker compose -f docker-compose.yml logs -f nginx
docker compose -f docker-compose.yml logs -f mysql

# Restart service tertentu
docker compose -f docker-compose.yml restart php

# Hapus image
docker rmi assetloan/nginx assetloan/php assetloan/mysql
```

## ⚠️ Catatan Penting

1. **Source code BAKED** — tidak bisa edit langsung dari host dan langsung terefleksi. Setiap perubahan butuh rebuild.
2. **SSL self-signed** — untuk production, generate cert baru atau gunakan reverse proxy (nginx-proxy-manager).
3. **Database volume** — `mysql_data` volume persist even after `down`. Hapus dengan `down -v`.
4. **Hermes AI** — sudah termasuk dalam image. Config AI tetap pakai SumoPod external.
5. **Port conflict** — jika 8080/8443/3307 sudah dipakai, edit di `docker-compose.yml`.

## 📦 Image Tags

```
assetloan/nginx:latest   — Nginx Alpine
assetloan/php:latest     — PHP-FPM 8.3 Alpine
assetloan/mysql:latest   — MySQL 8.0
```
