---
domain: testing
purpose: testing-framework
version: 1.0
updated: 2024-01-15
owner: qa-team
status: stable
---

# PENGUJIAN

## Rangkaian Pengujian

Pengujian Laravel berada di `tests/Feature` dan `tests/Unit`.

## Perintah

```bash
php artisan test
```

Jika host PHP tidak punya driver SQLite:

```bash
npm run test:docker
```

## Cakupan Penting

- Form publik, validasi, CAPTCHA, nomor laporan, dan halaman sukses.
- Pelacakan nomor laporan dan kode akses.
- Otorisasi role untuk empat role internal.
- Dashboard berbasis role dan grafik bulanan.
- Kelas per jurusan dan urutan natural.
- SEO publik, robots, sitemap, dan llms.txt.
- Markup aksesibilitas dasar.

## Tolok Ukur Terakhir

Pada audit ini, suite pengujian berbasis Docker lulus dengan `48 passed` dan `1303 assertions`.
