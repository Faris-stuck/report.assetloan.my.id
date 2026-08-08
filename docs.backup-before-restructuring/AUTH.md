# AUTH

## Role Internal

| Role         | Akses                                                            |
| ------------ | ---------------------------------------------------------------- |
| `superadmin` | Semua dashboard, user, QR, master data, audit, dan report detail |
| `kesiswaan`  | Laporan perundungan/pelanggaran dan tindak lanjut kesiswaan      |
| `sarpras`    | Laporan kerusakan fasilitas dan tindak lanjut sarpras            |
| `wali_kelas` | Baca laporan pelanggaran/perundungan untuk kelas ampuan          |

## Public User

Pelapor publik tidak login. Setelah submit, pelapor menerima:

- Nomor laporan.
- Kode akses 6 digit.

Keduanya diperlukan untuk tracking.

## Middleware dan Policy

- `auth` memastikan user internal login.
- `active` menolak akun nonaktif.
- `role:*` membatasi prefix admin/kesiswaan/sarpras.
- `ReportPolicy` dan `ReportAttachmentPolicy` membatasi detail dan lampiran.

## Legacy Role

Role lama seperti `guru` dan `siswa` diarsipkan oleh migration role reduction, tidak dihapus. Akun legacy dibuat nonaktif agar tidak mendapat akses internal tak sengaja.
