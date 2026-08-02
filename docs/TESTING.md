# TESTING

## Test Suite

Test Laravel berada di `tests/Feature` dan `tests/Unit`.

## Command

```bash
php artisan test
```

Jika host PHP tidak punya SQLite driver:

```bash
npm run test:docker
```

## Coverage Penting

- Form publik, validasi, CAPTCHA, nomor laporan, dan success page.
- Tracking nomor laporan dan kode akses.
- Role authorization untuk empat role internal.
- Dashboard role-scoped dan chart bulanan.
- Kelas per jurusan dan sort natural.
- SEO publik, robots, sitemap, dan llms.txt.
- Markup accessibility dasar.

## Baseline Terakhir

Pada audit ini, Docker-backed test suite menghasilkan `48 passed` dengan `1303 assertions`.
