# Spesifikasi Perombakan Empat Role LAPORIN

Tanggal: 30 Juli 2026

## Tujuan

Menyederhanakan otorisasi internal menjadi tepat empat role aktif dan memisahkan kepemilikan alur laporan tanpa menghilangkan data historis.

## Role aktif

1. `superadmin` — seluruh akses internal, termasuk dashboard global, Kesiswaan, Sarpras, detail laporan, catatan/tindakan, user, QR, audit, dan master data.
2. `kesiswaan` — seluruh alur internal laporan `violation` (perundungan/pelanggaran) dan tidak dapat menangani laporan `damage`.
3. `sarpras` — seluruh alur internal laporan `damage` (kerusakan) dan tidak dapat menangani laporan `violation`.
4. `wali_kelas` — hanya membaca laporan `violation` yang `related_class_id`-nya termasuk kelas yang diampu; tidak boleh menambah catatan, mengubah status, atau menjalankan tindakan laporan.

`reporter_type` publik (`siswa`, `guru`, `staff`) tetap dipertahankan karena itu jenis pelapor tanpa login, bukan role akun internal.

## Kepemilikan alur

### Perundungan/pelanggaran

- Laporan otomatis masuk ke Kesiswaan.
- Kesiswaan memverifikasi, memilih siswa/jenis pelanggaran, memproses poin, menolak laporan, dan menandai penanganan selesai untuk meminta konfirmasi pelapor.
- Wali Kelas tidak menjadi aktor workflow dan hanya dapat membaca laporan berdasarkan **kelas kejadian** (`related_class_id`).
- Konfirmasi selesai/buka kembali oleh pelapor melalui tracking tetap dipertahankan. Pelapor publik bukan role internal.

### Kerusakan

- Laporan otomatis masuk ke Sarpras.
- Sarpras mengatur prioritas/jadwal, mencatat pengerjaan, mengunggah bukti selesai, dan menolak laporan yang tidak valid.
- Role lain selain SuperAdmin tidak dapat menangani laporan kerusakan.

## Routing Wali Kelas

- Sumber routing tunggal adalah `reports.related_class_id`.
- `reporter_class_id`, `victim_class_id`, dan `alleged_actor_class_id` tidak memberi akses tambahan.
- Pada laporan perundungan/pelanggaran, field **Kelas kejadian/terkait** wajib agar laporan pasti masuk ke Wali Kelas yang tepat.
- Jika data lama tidak memiliki `related_class_id`, hanya SuperAdmin dan Kesiswaan yang dapat melihatnya sampai datanya dilengkapi.

## Data role lama

- Akun role `guru` dan `siswa` tidak dihapus.
- Nilai role historis tetap disimpan apa adanya agar akun tidak memperoleh privilege role aktif lain. Migrasi menyimpan status aktif lama, menonaktifkan akun, menghapus remember token, sesi, dan token reset password, serta memberi penanda waktu pengarsipan.
- Relasi siswa, tugas guru, riwayat poin, laporan, dan audit tetap tersimpan.
- Rollback hanya memulihkan status aktif akun yang benar-benar ditandai oleh migrasi; sesi dan token lama tidak dipulihkan.

## Form Lapor

- `reporter_phone` wajib di backend dan frontend.
- Label memakai penanda wajib, input memiliki `required`, `autocomplete="tel"`, `inputmode="tel"`, helper format, serta pesan validasi berbahasa Indonesia.
- Validasi menerima karakter nomor telepon yang wajar, tetapi harus mengandung 8–15 digit setelah karakter pemisah diabaikan.

## Navbar internal

- SuperAdmin: Dashboard, Kesiswaan, Sarpras, menu Admin.
- Kesiswaan: Dashboard, Kesiswaan.
- Sarpras: Dashboard, Sarpras.
- Wali Kelas: Dashboard.
- Menu publik tetap tersedia bagi pengunjung yang belum login; menu publik tidak memenuhi navbar akun internal.
- Middleware/policy tetap menjadi keamanan utama; penyembunyian menu bukan pengganti otorisasi.

## Diagram batang dashboard

Diagram menampilkan laporan masuk per bulan selama enam bulan terakhir:

- SuperAdmin: semua laporan.
- Kesiswaan: hanya `violation`.
- Sarpras: hanya `damage`.
- Wali Kelas: hanya `violation` dengan `related_class_id` kelas yang diampu.

Diagram harus responsif, memiliki label dan nilai tekstual yang dapat dibaca screen reader, serta tidak bergantung pada library eksternal baru.

## Kriteria Penerimaan

- Hanya empat role dapat dibuat, diaktifkan, atau digunakan untuk otorisasi. Nilai `guru`/`siswa` tetap boleh tersimpan hanya sebagai histori akun nonaktif.
- Akun legacy tidak hilang dan tidak dapat login.
- SuperAdmin lolos semua gate internal.
- Kesiswaan dan Sarpras saling terisolasi berdasarkan jenis laporan.
- Wali Kelas hanya dapat GET/baca laporan kelas kejadian yang diampu; semua POST perubahan ditolak.
- Nomor HP kosong/terlalu pendek/format salah ditolak; input valid tersimpan.
- Navbar setiap role hanya berisi menu role tersebut.
- Diagram dashboard memakai data yang telah difilter sesuai role.
- Full test, formatter, Composer audit, preview browser QA desktop/mobile, backup, canary, cutover, dan live read-only QA lulus sebelum rilis dinyatakan selesai.
