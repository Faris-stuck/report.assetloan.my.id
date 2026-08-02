# DATABASE

## Engine

Produksi memakai MariaDB/MySQL. Test suite memakai SQLite in-memory melalui `phpunit.xml`.

## Tabel Utama

| Tabel                     | Fungsi                                     |
| ------------------------- | ------------------------------------------ |
| `users`                   | Akun internal dan role                     |
| `classes`                 | Kelas SMK Taruna Bangsa Bekasi per jurusan |
| `reports`                 | Laporan utama dan status                   |
| `bullying_details`        | Detail perundungan/pelanggaran             |
| `damage_details`          | Detail kerusakan fasilitas                 |
| `report_attachments`      | Metadata lampiran aman                     |
| `report_notes`            | Catatan petugas atau pelapor               |
| `report_status_histories` | Audit status laporan                       |
| `qr_codes`                | QR form untuk lokasi atau kelas            |
| `audit_logs`              | Aktivitas admin dan sistem                 |

## Status Laporan

- `menunggu_verifikasi`
- `memerlukan_informasi`
- `diverifikasi`
- `ditolak`
- `ditugaskan`
- `sedang_ditangani`
- `menunggu_konfirmasi`
- `dibuka_kembali`
- `selesai`
- `diarsipkan`

## Nomor Laporan

Format: `LPRYYYYMM####`.
Contoh: `LPR2026071234`.
Empat digit terakhir dibuat acak dan dicek unik di transaksi.

## Migration Notes

- `docker/start.sh` menjalankan `php artisan migrate --force` saat container start.
- Backup database wajib dilakukan sebelum rebuild/recreate container produksi.
- Credential DB di `.env` produksi harus tetap di luar repository.
