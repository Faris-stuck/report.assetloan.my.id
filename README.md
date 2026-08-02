# report.assetloan.my.id - LAPORIN

LAPORIN adalah aplikasi pelaporan untuk SMK Taruna Bangsa Bekasi. Aplikasi ini menerima laporan perundungan, pelanggaran siswa, dan kerusakan fasilitas, lalu mengarahkan tindak lanjut ke role sekolah yang tepat.

## Fitur Utama

- Form laporan publik tanpa login dengan CAPTCHA, nomor laporan, dan kode akses.
- Tracking laporan publik memakai nomor laporan dan kode akses.
- Dashboard role-based untuk `superadmin`, `kesiswaan`, `sarpras`, dan `wali_kelas`.
- Manajemen master data, QR Code, lampiran aman, audit log, dan histori status.
- SEO publik untuk halaman panduan lapor dan FAQ.

## Quick Start Lokal

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Jika host PHP tidak punya `pdo_sqlite`, jalankan test melalui Docker:

```bash
npm run test:docker
```

## Commands

| Command                | Fungsi                                |
| ---------------------- | ------------------------------------- |
| `php artisan test`     | Run test Laravel                      |
| `npm run test:docker`  | Run test memakai image Docker LAPORIN |
| `npm run build`        | Build asset Vite produksi             |
| `npm run lint`         | Lint JS/TS/config frontend            |
| `npm run format:check` | Cek format Prettier                   |

## Struktur Penting

```text
app/Http/Controllers/      Controller Laravel
app/Http/Middleware/       Role, active-user, dan security header middleware
app/Models/                Model domain LAPORIN
database/migrations/       Skema database
database/seeders/          Seed role, kelas, master data, dan demo user
resources/views/           Blade UI publik dan dashboard
public/css/laporin.css     Design tokens dan style utama
docs/                      Dokumentasi produk, arsitektur, database, dan deploy
```

## Keamanan Secret

- Nilai `.env` produksi tidak boleh dipush.
- Dokumen dan contoh environment memakai `[REDACTED]` untuk password/token.
- Backup produksi disimpan di server, bukan di repository.

## Dokumentasi

Mulai dari `docs/PRODUCT.md`, lalu lanjut ke `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, dan `docs/DEPLOYMENT.md`.
