# ADR-001: Memakai Laravel Monolith untuk LAPORIN

## Status

Diterima

## Tanggal

2026-08-02

## Konteks

LAPORIN membutuhkan alur pelaporan sekolah server-rendered, dashboard role, penanganan unggah file, validasi, jejak audit, dan penempatan sederhana pada VPS yang sudah ada.

## Keputusan

Gunakan Laravel 12 dengan PHP 8.3 sebagai monolith. Gunakan Blade, Bootstrap 5, Alpine.js, Vite, dan lapisan desain CSS kecil.

## Alternatif yang Dipertimbangkan

### Frontend Terpisah Next.js

- Kelebihan: Ekosistem TypeScript dan komponen yang kuat.
- Kekurangan: Menambahkan batas API, duplikasi autentikasi, dan lebih banyak bagian penempatan.
- Ditolak karena ruang lingkup saat ini lebih diuntungkan dengan monolith yang lebih sederhana.

### Frontend Statis Murni dengan API

- Kelebihan: Halaman publik cepat.
- Kekurangan: Unggah file, pelacakan, auth, dan dashboard role tetap memerlukan kompleksitas backend.
- Ditolak karena aplikasi ini sebagian besar adalah workflow dan penanganan data.

## Konsekuensi

- Satu artefak deploy menangani form publik, auth, admin, dan dashboard.
- Policy dan middleware Laravel memusatkan otorisasi.
- UI tetap mudah dimodifikasi dengan Blade dan CSS.
- Konfigurasi TypeScript ada untuk tooling frontend yang lebih ketat, bukan sebagai rewrite frontend penuh.
