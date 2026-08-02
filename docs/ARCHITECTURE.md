# ARCHITECTURE

## Ringkasan Stack

- Laravel 12 sebagai monolith backend dan server-rendered UI.
- Blade untuk view, Bootstrap 5 untuk komponen dasar, Alpine.js untuk wizard form ringan.
- Vite membangun asset frontend.
- MariaDB/MySQL untuk produksi, SQLite in-memory untuk automated tests.
- Docker image PHP 8.3 Apache expose port 8080 di network `cf-network`.

## Modul

| Modul         | File utama                            | Tanggung jawab                                         |
| ------------- | ------------------------------------- | ------------------------------------------------------ |
| Public report | `PublicReportController`              | Form publik, submit laporan, nomor laporan, kode akses |
| Tracking      | `TrackingController`                  | Pencarian laporan publik dan feedback pelapor          |
| Dashboard     | `DashboardController`                 | Statistik dan daftar laporan role-scoped               |
| Kesiswaan     | `KesiswaanController`                 | Proses laporan perundungan/pelanggaran                 |
| Sarpras       | `SarprasController`                   | Proses laporan kerusakan fasilitas                     |
| Admin         | `AdminController`, `QRCodeController` | User, master data, QR, audit                           |
| Attachment    | `AttachmentController`, policy        | Download lampiran sesuai izin                          |

## Request Flow Publik

1. `GET /` membuat token submit session dan CAPTCHA.
2. `POST /lapor` validasi payload, CAPTCHA, dan token form bila ada.
3. Transaksi DB membuat `reports`, detail domain, lampiran, histori status, dan email log.
4. Redirect ke halaman sukses dengan nomor laporan dan kode akses.

## Deployment Flow

1. Build asset dengan `npm run build`.
2. Build Docker image baru.
3. Start container baru dengan env produksi yang sama.
4. Entrypoint menjalankan `php artisan migrate --force`.
5. Cache config, route, dan view.
6. Health check HTTP dan artisan DB query.

## Boundaries

- Jangan masukkan business logic role ke Blade jika bisa ditaruh di policy, middleware, atau controller.
- Jangan expose lampiran melalui `public/storage`; download harus lewat route dan policy.
- Jangan pakai secret hardcoded di source atau docs.
