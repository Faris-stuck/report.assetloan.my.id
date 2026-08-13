# Original User Request

## Initial Request — 2026-08-13T01:56:24Z

Optimasi performa tinggi pada aplikasi LAPORIN (Laravel/MySQL/Redis) untuk menjamin kinerja yang sangat cepat, efisien, dan stabil ketika menerima trafik pengguna yang tinggi (high-traffic production environment).

Working directory: c:\Users\azmia\Downloads\report.assetloan.my.id
Integrity mode: development

## Requirements

### R1. Eliminasikan Masalah N+1 Query pada Seluruh Query List & Relasi Model
- Pastikan seluruh pengambilan data berulang (seperti pada daftar laporan di Kesiswaan, Sarpras, Admin, dan Dashboard) menggunakan Eager Loading (`with(...)`) untuk meminimalkan jumlah ekspedisi query ke database.

### R2. Optimasi & Caching Agregat Statistik (Dashboard & Reporting)
- Gabungkan query agregat (`COUNT(*)`) individual yang berjalan berulang pada controller/dashboard menjadi query terkelompok (`GROUP BY`) atau bungkus dalam mekanisme caching (`CacheHelper::remember`) dengan durasi TTL yang sesuai.

### R3. Keamanan Data & Isolasi Pengujian (AGENTS.md Policy Compliance)
- Seluruh tindakan optimasi wajib mematuhi aturan AGENTS.md. Dilarang keras melakukan `migrate:fresh`, `db:wipe`, atau perintah destruktif lainnya pada database produksi.

## Acceptance Criteria

### Performance & Stability Verification
- [ ] Pengujian otomatis `php artisan test` berjalan 100% PASS tanpa adanya error/regresi.
- [ ] Pengambilan data relasi utama tidak menimbulkan N+1 query.
- [ ] Kecepatan pemrosesan halaman utama & dashboard meningkat signifikan saat menangani data dalam jumlah besar.
- [ ] Logika otorisasi peran, validasi laporan, dan keamanan data tetap terjaga utuh.
