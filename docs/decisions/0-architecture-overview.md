---
domain: decisions
purpose: adr
version: 1.0
updated: 2024-01-15
decision_date: 2024-01-15
status: accepted
---

# ARCHITECTURE

## Ringkasan Tumpukan

- Laravel 12 sebagai monolith backend dan server-rendered UI.
- Blade untuk view, Bootstrap 5 untuk komponen dasar, Alpine.js untuk wizard form ringan.
- Vite membangun asset frontend.
- MariaDB/MySQL untuk produksi, SQLite in-memory untuk pengujian otomatis.
- Gambar Docker PHP 8.3 Apache mengekspos port 8080 di network `cf-network`.

## Modul

| Modul         | File utama                            | Tanggung jawab                                         |
| ------------- | ------------------------------------- | ------------------------------------------------------ |
| Public report | `PublicReportController`              | Form publik, submit laporan, nomor laporan, kode akses |
| Tracking      | `TrackingController`                  | Pencarian laporan publik dan feedback pelapor          |
| Dashboard     | `DashboardController`                 | Statistik dan daftar laporan berbasis role               |
| Kesiswaan     | `KesiswaanController`                 | Proses laporan perundungan/pelanggaran                 |
| Sarpras       | `SarprasController`                   | Proses laporan kerusakan fasilitas                     |
| Admin         | `AdminController`, `QRCodeController` | User, master data, QR, audit                           |
| Attachment    | `AttachmentController`, policy        | Download lampiran sesuai izin                          |

## Alur Permintaan Publik

1. `GET /` membuat token submit session dan CAPTCHA.
2. `POST /lapor` validasi payload, CAPTCHA, dan token form bila ada.
3. Transaksi DB membuat `reports`, detail domain, lampiran, histori status, dan email log. Nomor laporan dibuat sebagai `LAP-XXXXXX-XXXXXX`.
4. Arahkan ke halaman sukses dengan nomor laporan dan kode akses.

## Alur Penempatan

1. Bangun asset dengan `npm run build`.
2. Bangun image Docker baru.
3. Mulai container baru dengan env produksi yang sama.
4. Entrypoint menjalankan `php artisan migrate --force`.
5. Cache config, rute, dan view.
6. Pemeriksaan kesehatan HTTP dan query DB artisan.

## Batasan

- Jangan masukkan logika bisnis role ke Blade jika bisa ditaruh di policy, middleware, atau controller.
- Jangan mengekspos lampiran melalui `public/storage`; unduhan harus lewat rute dan policy.
- Jangan pakai rahasia hardcoded di source atau docs.
