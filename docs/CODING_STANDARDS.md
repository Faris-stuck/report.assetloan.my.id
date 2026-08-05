# STANDAR KODING

## PHP dan Laravel

- Ikuti konvensi Laravel 12.
- Controller boleh mengorkestrasi request, tetapi logika izin taruh di middleware atau policy.
- Gunakan relasi Eloquent untuk query domain.
- Migration harus aman untuk data produksi dan idempotent bila mengecek kolom/tabel.

## Blade dan UI

- Label harus terkait input dengan `for` dan `id`.
- Jangan pakai placeholder sebagai pengganti label.
- Copy UI bahasa Indonesia, singkat, dan spesifik.
- Hindari efek visual ramai. Prioritaskan keterbacaan dan kontras.

## JavaScript

- Alpine.js hanya untuk state UI ringan.
- Jangan simpan rahasia di browser.
- Jangan buat request baru tanpa CSRF dan validasi server.

## Pemformatan

- Gunakan `prettier.config.js` untuk JS/TS/JSON/Markdown/CSS.
- Gunakan `eslint.config.js` untuk konfigurasi JS/TS dan resource frontend.
