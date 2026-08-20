---
domain: development
purpose: coding-standards
version: 1.0
updated: 2024-01-15
owner: development-team
status: stable
---

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

## UI/UX Standards

Lihat dokumentasi berikut untuk pattern konsisten:
- **Panduan Halaman Baru**: `docs/FUTURE_PAGES_IMPLEMENTATION_GUIDE.md` - template search/filter, table vs card, checklist
- **Modal Workflow**: Semua edit/action HARUS menggunakan modal, bukan inline
- **Search/Filter**: Semua halaman dengan 20+ items WAJIB punya search/filter server-side
- **Accessibility**: Focus trap dalam modal, keyboard navigation (Tab/Shift+Tab/Escape)

## Pemformatan

- Gunakan `prettier.config.js` untuk JS/TS/JSON/Markdown/CSS.
- Gunakan `eslint.config.js` untuk konfigurasi JS/TS dan resource frontend.
