# CODING_STANDARDS

## PHP dan Laravel

- Ikuti konvensi Laravel 12.
- Controller boleh mengorkestrasi request, tetapi logic izin taruh di middleware atau policy.
- Gunakan Eloquent relationship untuk query domain.
- Migration harus aman untuk data produksi dan idempotent bila mengecek kolom/tabel.

## Blade dan UI

- Label harus terkait input dengan `for` dan `id`.
- Jangan pakai placeholder sebagai pengganti label.
- Copy UI bahasa Indonesia, singkat, dan spesifik.
- Hindari efek visual ramai. Prioritaskan keterbacaan dan kontras.

## JavaScript

- Alpine.js hanya untuk state UI ringan.
- Jangan simpan secret di browser.
- Jangan buat request baru tanpa CSRF dan validasi server.

## Formatting

- Gunakan `prettier.config.js` untuk JS/TS/JSON/Markdown/CSS.
- Gunakan `eslint.config.js` untuk JS/TS config dan resource frontend.
