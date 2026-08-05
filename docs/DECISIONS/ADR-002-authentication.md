# ADR-002: Empat Role Internal dengan Kode Pelacakan Publik

## Status

Diterima

## Tanggal

2026-08-02

## Konteks

Aplikasi memiliki dua mode identitas: pelapor publik tanpa login, dan pengguna internal sekolah dengan akses berbasis role. Nama role legacy ada, tetapi tidak boleh mempertahankan akses aktif.

## Keputusan

Gunakan empat role internal: `superadmin`, `kesiswaan`, `sarpras`, dan `wali_kelas`. Pelapor publik menggunakan nomor laporan dan kode akses untuk pelacakan daripada akun.

## Alternatif yang Dipertimbangkan

### Akun Pelapor Publik

- Kelebihan: Pelapor berulang bisa melihat riwayat.
- Kekurangan: Gesekan lebih tinggi, kewajiban privasi lebih banyak, dan kompleksitas pemulihan akun.
- Ditolak karena laporan harus mudah diajukan dengan cepat.

### Pertahankan Role `guru` dan `siswa` Lama Aktif

- Kelebihan: Pekerjaan migrasi lebih sedikit.
- Kekurangan: Permukaan serang lebih besar dan izin tidak jelas.
- Ditolak karena akses perlu eksplisit dan minimal.

## Konsekuensi

- `wali_kelas` bersifat baca-saja dan dibatasi pada kelas wali.
- `kesiswaan` dan `sarpras` hanya beroperasi pada domain laporan mereka.
- Akun legacy `guru` dan `siswa` diarsipkan tanpa menghapus data historis.
- Pelacakan publik bergantung pada perlindungan kode akses 6 digit.
