# Struktur PROJECT dan Database Peminjaman

Dokumen ini dibuat sebagai ringkasan teknis yang bisa diberikan ke ChatGPT lain agar cepat memahami isi folder `PROJECT` dan database `peminjaman` untuk membantu membuat slide presentasi.

Catatan:

- Dokumen ini fokus pada file dan tabel yang berpengaruh langsung ke fitur bisnis.
- Asset vendor, icon, font, dan file bawaan library tidak dijelaskan satu per satu.
- Data audit di bawah mengacu pada isi database yang aktif saat pengecekan terakhir pada 30 Maret 2026.

## 1. Ringkasan Project

Nama project:

Sistem Informasi Peminjaman Barang Berbasis Web

Tujuan utama:

- Mengelola pengajuan peminjaman barang.
- Memproses approval oleh manager atau admin.
- Memantau status peminjaman aktif dan jatuh tempo.
- Mengelola pengembalian barang dan kerusakan.
- Mendukung perpanjangan peminjaman.
- Mengirim notifikasi email dan reminder jatuh tempo.

Role utama dalam sistem:

- `admin`
- `manager`
- `user`
- `pic_barang`

## 2. Struktur Folder Root PROJECT

Folder dan file penting di root:

```text
PROJECT/
|-- admin/
|-- api/
|-- assets/
|-- config/
|-- manager/
|-- phpmailer/
|-- pic-barang/
|-- user/
|-- peminjaman.sql
|-- index.html
|-- register.html
|-- forgot-password.html
|-- README.md
```

Fungsi utama masing-masing:

- `admin/`: halaman frontend untuk admin
- `manager/`: halaman frontend untuk manager atau approver
- `user/`: halaman frontend untuk borrower atau requester
- `pic-barang/`: halaman frontend untuk PIC barang
- `api/`: backend PHP yang menangani proses bisnis dan koneksi database
- `config/`: konfigurasi inti, terutama koneksi database dan email
- `assets/`: JavaScript, CSS, image, vendor, dan script frontend pendukung
- `phpmailer/`: library pengiriman email
- `peminjaman.sql`: struktur database utama
- `index.html`: halaman login

## 3. Struktur Frontend per Role

### 3.1 Admin

File halaman admin:

```text
admin/dashboard.html
admin/pengaturan.html
admin/user/buat-user.html
admin/user/buat-user.php
admin/user/buat-user-wrapper.php
admin/barang/data-barang.php
admin/barang/detail-barang.html
admin/peminjaman/admin-approval.html
admin/peminjaman/data-peminjaman.html
admin/peminjaman/detail-peminjaman.html
admin/peminjaman/menunggu-persetujuan.html
admin/peminjaman/sedang-dipinjam.html
admin/peminjam/data-peminjam.html
admin/peminjam/riwayat-peminjaman.html
admin/pengembalian/barang-rusak.html
admin/pengembalian/pengembalian-barang.html
admin/laporan/laporan-peminjaman.html
admin/laporan/laporan-pengembalian.html
admin/laporan/laporan-stok.html
```

Fungsi utama admin:

- Melihat dashboard global
- Mengelola user
- Mengelola barang
- Melakukan approval atau reject peminjaman
- Melihat data peminjam
- Memproses pengembalian
- Melihat laporan

### 3.2 Manager

File halaman manager:

```text
manager/dashboard.html
manager/persetujuan/menunggu-approval.html
manager/persetujuan/disetujui.html
manager/persetujuan/ditolak.html
manager/laporan/laporan-peminjaman.html
manager/laporan/laporan-stok.html
```

Fungsi utama manager:

- Melihat dashboard approval
- Menyetujui atau menolak peminjaman
- Melihat laporan stok dan laporan peminjaman

### 3.3 User

File halaman user:

```text
user/dashboard.html
user/profil.html
user/riwayat.html
user/peminjaman/ajukan-peminjaman.html
user/peminjaman/detail.html
user/peminjaman/status-peminjaman.html
user/pengembalian/ajukan-pengembalian.html
```

Fungsi utama user:

- Mengajukan peminjaman
- Melihat status pinjaman
- Mengajukan pengembalian
- Melihat riwayat
- Mengelola profil

### 3.4 PIC Barang

File halaman PIC barang:

```text
pic-barang/dashboard.html
pic-barang/profil.html
pic-barang/update-barang/update-barang.html
pic-barang/update-barang/detail-barang.php
pic-barang/pengembalian/pengembalian-barang.html
```

Fungsi utama PIC barang:

- Mengelola update data barang
- Membantu proses pengembalian
- Melihat dashboard operasional barang

## 4. Struktur Backend API

Subfolder API utama:

```text
api/
|-- admin/
|-- approver/
|-- auth/
|-- barang/
|-- cron/
|-- email/
|-- extend/
|-- peminjaman/
|-- pengembalian/
|-- pic_barang/
|-- user/
|-- vendor/
|-- koneksi.php
|-- session-helper.php
```

### 4.1 Auth

File penting:

- `api/auth/login.php`
- `api/auth/logout.php`
- `api/auth/register.php`
- `api/auth/forgot-password.php`
- `api/auth/verify-session.php`

Fungsi:

- Login dan pembuatan session
- Logout
- Register
- Verifikasi session user yang sedang aktif

### 4.2 User

File penting:

- `api/user/request-peminjaman.php`
- `api/user/get-my-requests.php`
- `api/user/dashboard-stats.php`
- `api/user/dashboard-chart-data.php`
- `api/user/profile.php`
- `api/user/change_password.php`

Fungsi:

- Pengajuan peminjaman dari borrower
- Dashboard user
- Pengambilan riwayat milik user
- Update profil user

### 4.3 Approver

File penting:

- `api/approver/approve-items.php`
- `api/approver/approve.php`
- `api/approver/reject.php`
- `api/approver/list-by-status.php`
- `api/approver/dashboard-stats.php`
- `api/approver/dashboard-chart-data.php`

Fungsi:

- Approval peminjaman oleh manager
- Approval parsial per item atau per unit
- Pengambilan data approval berdasarkan status
- Statistik dashboard manager

### 4.4 Admin

File penting:

- `api/admin/approve.php`
- `api/admin/reject.php`
- `api/admin/process-return.php`
- `api/admin/dashboard-stats.php`
- `api/admin/roles.php`
- `api/admin/get-detail.php`
- `api/admin/get-statuses.php`

Fungsi:

- Approval dan rejection oleh admin
- Proses pengembalian
- Dashboard admin
- Manajemen role sistem

### 4.5 Peminjaman

File penting:

- `api/peminjaman/get_all.php`
- `api/peminjaman/get_by_status.php`
- `api/peminjaman/get_detail.php`
- `api/peminjaman/get_detail_units.php`
- `api/peminjaman/get_extend_units.php`
- `api/peminjaman/update_status.php`
- `api/peminjaman/return.php`

Fungsi:

- Mengambil data transaksi peminjaman
- Mengambil detail item atau unit
- Mengubah status
- Mendukung pengembalian

### 4.6 Pengembalian

File penting:

- `api/pengembalian/list.php`
- `api/pengembalian/detail.php`
- `api/pengembalian/inspect.php`
- `api/pengembalian/damaged.php`

Fungsi:

- Menampilkan data pengembalian
- Inspeksi barang yang dikembalikan
- Mencatat barang rusak dan biaya ganti rugi

### 4.7 Extend

File penting:

- `api/extend/request.php`
- `api/extend/list.php`
- `api/extend/approve.php`
- `api/extend/reject.php`
- `api/extend/status.php`

Fungsi:

- Pengajuan perpanjangan
- Approval atau reject extend
- Monitoring status extend

### 4.8 Email

File penting:

- `api/email/email-functions.php`
- `api/email/send-pinjam-request.php`
- `api/email/send-approved.php`
- `api/email/send-rejected.php`
- `api/email/send-return-request.php`
- `api/email/send-return-confirmed.php`
- `api/email/send-extend-request.php`
- `api/email/send-extend-approved.php`
- `api/email/send-extend-rejected.php`
- `api/email/send-background.php`
- `api/email/send-queue-worker.php`

Fungsi:

- Menyediakan fungsi email terpusat
- Mengirim email notifikasi untuk semua proses bisnis
- Menjalankan queue worker untuk pengiriman asynchronous

### 4.9 Cron

File penting:

- `api/cron/send-reminder-h7.php`
- `api/cron/update-due-status.php`
- `api/cron/process-email-queue.php`

Fungsi:

- Reminder jatuh tempo H-7 sampai H-0
- Update status due atau overdue
- Memproses email yang masuk antrean

## 5. File Kunci yang Menjelaskan Alur Sistem

Jika ChatGPT lain perlu memahami logika bisnis utama, fokus ke file berikut:

- `config/database.php`
  - Koneksi database utama ke DB `peminjaman`
- `api/koneksi.php`
  - Bridge koneksi database dan helper status due atau overdue
- `api/auth/login.php`
  - Login dan session berdasarkan role
- `api/user/request-peminjaman.php`
  - Pengajuan peminjaman, insert ke tabel peminjaman, update stok, kirim email
- `api/approver/approve-items.php`
  - Approval parsial per unit dan update status `Borrowed`, `Rejected`, `Partial Approved`
- `api/pengembalian/inspect.php`
  - Inspeksi pengembalian, update stok, status return, barang rusak, biaya ganti rugi
- `api/extend/request.php`
  - Pengajuan perpanjangan tanggal kembali
- `api/email/email-functions.php`
  - Utility email dan email queue
- `api/cron/send-reminder-h7.php`
  - Reminder otomatis menjelang jatuh tempo

## 6. Struktur Database `peminjaman`

Database utama bernama:

`peminjaman`

### 6.1 Tabel `users`

Fungsi:

- Menyimpan data user sistem
- Menyimpan role, email, password, dan data identitas dasar

Kolom penting:

- `id`
- `nama`
- `nrp`
- `email`
- `password`
- `role`

### 6.2 Tabel `roles`

Fungsi:

- Menyimpan daftar role yang bisa digunakan sistem

Kolom penting:

- `id`
- `role_name`
- `deskripsi`
- `is_protected`
- `badge_color`

### 6.3 Tabel `barang`

Fungsi:

- Menyimpan master data barang
- Menyimpan stok total, stok tersedia, safety stock, dan stok rusak

Kolom penting:

- `id`
- `kode_barang`
- `nama_barang`
- `kategori`
- `lokasi`
- `stok_total`
- `stok_tersedia`
- `safety_stock`
- `stok_rusak`

### 6.4 Tabel `peminjaman`

Fungsi:

- Header transaksi peminjaman

Kolom penting:

- `id`
- `kode_peminjaman`
- `user_id`
- `nama_peminjam`
- `nrp`
- `lokasi_umum`
- `tanggal_pinjam`
- `rencana_kembali`
- `tanggal_disetujui`
- `tanggal_kembali`
- `status`
- `rejection_reason`
- `last_reminder_date`

### 6.5 Tabel `detail_peminjaman`

Fungsi:

- Menyimpan item barang yang termasuk dalam satu transaksi peminjaman

Kolom penting:

- `id`
- `peminjaman_id`
- `barang_id`
- `lokasi`
- `jumlah`
- `expected_return`
- `kondisi_pinjam`
- `approval_status`
- `approved_by`
- `approval_time`

### 6.6 Tabel `peminjaman_units`

Fungsi:

- Menyimpan unit barang secara lebih detail untuk approval dan return per unit

Kolom penting:

- `id`
- `peminjaman_id`
- `detail_peminjaman_id`
- `barang_id`
- `unit_number`
- `unit_display`
- `return_status`
- `expected_return`
- `kondisi_kembali`
- `tanggal_kembali`
- `approval_status`
- `approved_by`
- `approval_time`

### 6.7 Tabel `pengembalian`

Fungsi:

- Header transaksi pengembalian

Kolom penting:

- `id`
- `kode_pengembalian`
- `peminjaman_id`
- `user_id`
- `status`
- `catatan_user`
- `catatan_petugas`
- `checked_by_role`
- `checked_by_user_id`
- `has_rusak`
- `total_ganti_rugi`

### 6.8 Tabel `detail_pengembalian`

Fungsi:

- Menyimpan detail item yang dikembalikan dan kondisinya

Kolom penting:

- `id`
- `pengembalian_id`
- `barang_id`
- `jumlah_kembali`
- `kondisi_kembali`
- `jumlah_rusak`
- `sisa_dikembalikan`
- `biaya_ganti_rugi`

### 6.9 Tabel `extend_peminjaman`

Fungsi:

- Header transaksi perpanjangan peminjaman

Kolom penting:

- `id`
- `peminjaman_id`
- `user_id`
- `tanggal_kembali_sekarang`
- `tanggal_perpanjang`
- `alasan`
- `status`
- `approved_by`
- `approved_at`

### 6.10 Tabel `extend_peminjaman_items`

Fungsi:

- Menyimpan detail unit yang diperpanjang

Kolom penting:

- `id`
- `extend_peminjaman_id`
- `detail_peminjaman_id`
- `unit_number`
- `tanggal_perpanjang`

## 7. Relasi Database yang Paling Penting

Relasi inti yang perlu dipahami:

- `peminjaman.user_id -> users.id`
- `detail_peminjaman.peminjaman_id -> peminjaman.id`
- `detail_peminjaman.barang_id -> barang.id`
- `peminjaman_units.peminjaman_id -> peminjaman.id`
- `peminjaman_units.detail_peminjaman_id -> detail_peminjaman.id`
- `peminjaman_units.barang_id -> barang.id`
- `pengembalian.peminjaman_id -> peminjaman.id`
- `pengembalian.user_id -> users.id`
- `detail_pengembalian.pengembalian_id -> pengembalian.id`
- `detail_pengembalian.barang_id -> barang.id`
- `extend_peminjaman.peminjaman_id -> peminjaman.id`
- `extend_peminjaman.user_id -> users.id`
- `extend_peminjaman_items.extend_peminjaman_id -> extend_peminjaman.id`
- `extend_peminjaman_items.detail_peminjaman_id -> detail_peminjaman.id`

## 8. Alur Sistem dari Sudut Pandang File dan Database

### 8.1 Login

- Frontend login: `index.html`
- Backend login: `api/auth/login.php`
- Tabel terkait: `users`

Output proses:

- Session aktif
- User diarahkan ke role yang sesuai

### 8.2 Pengajuan Peminjaman

- Frontend: `user/peminjaman/ajukan-peminjaman.html`
- Backend utama: `api/user/request-peminjaman.php`
- Tabel terkait: `peminjaman`, `detail_peminjaman`, `barang`

Output proses:

- Transaksi peminjaman baru terbentuk
- Stok tersedia berkurang
- Email request dikirim

### 8.3 Approval

- Frontend manager: `manager/persetujuan/menunggu-approval.html`
- Frontend admin: `admin/peminjaman/admin-approval.html`
- Backend utama: `api/approver/approve-items.php`
- Tabel terkait: `peminjaman`, `detail_peminjaman`, `peminjaman_units`, `barang`

Output proses:

- Status menjadi `Borrowed`, `Rejected`, atau `Partial Approved`
- Stok unit yang ditolak dikembalikan

### 8.4 Monitoring Due dan Overdue

- Frontend dashboard: file dashboard masing-masing role
- Backend utama: `api/koneksi.php`, `api/*/dashboard-stats.php`, `api/*/dashboard-chart-data.php`
- Tabel terkait: `peminjaman`, `peminjaman_units`

Output proses:

- Status aktif seperti `Due In`, `Due Today`, `Overdue` dihitung dan ditampilkan

### 8.5 Pengembalian

- Frontend: `user/pengembalian/ajukan-pengembalian.html`
- Backend utama: `api/pengembalian/inspect.php`
- Tabel terkait: `pengembalian`, `detail_pengembalian`, `peminjaman`, `peminjaman_units`, `barang`

Output proses:

- Barang baik menambah stok tersedia
- Barang rusak menambah stok rusak
- Status return diperbarui

### 8.6 Extend

- Frontend utama: status/detail pinjaman user
- Backend utama: `api/extend/request.php`
- Tabel terkait: `extend_peminjaman`, `extend_peminjaman_items`, `peminjaman_units`

Output proses:

- Tanggal kembali bisa diperpanjang bila disetujui

### 8.7 Email dan Reminder

- Backend utama: `api/email/*`, `api/cron/send-reminder-h7.php`
- Tabel terkait: `users`, `peminjaman`

Output proses:

- Email request, approval, reject, return, extend, dan reminder

## 9. Data Live Database Saat Audit

Jumlah data:

- `roles`: 4
- `users`: 5
- `barang`: 10
- `peminjaman`: 10
- `detail_peminjaman`: 18
- `peminjaman_units`: 51
- `pengembalian`: 2
- `extend_peminjaman`: 2

Distribusi status peminjaman:

- `Overdue`: 3
- `Due In 2 Days`: 2
- `Returned`: 2
- `Due In 1 Day`: 1
- `Partial Approved`: 1
- `Rejected`: 1

## 10. Poin yang Sebaiknya Ditekankan Saat Membuat Slide

- Sistem ini bukan sekadar CRUD, tetapi mendukung alur bisnis lengkap.
- Approval berjalan sampai level unit atau item.
- Status due dan overdue dihitung dinamis.
- Pengembalian mendukung barang rusak dan return parsial.
- Extend peminjaman sudah terintegrasi.
- Email dan reminder otomatis menjadi nilai tambah profesional.

## 11. Prompt Siap Pakai untuk ChatGPT Lain

Berikut konteks yang bisa diberikan ke ChatGPT lain:

```text
Saya punya project bernama "Sistem Informasi Peminjaman Barang Berbasis Web".

Struktur project:
- Frontend per role: admin, manager, user, pic_barang
- Backend: PHP modular di folder api
- Database: MySQL dengan nama database `peminjaman`

Role utama:
- admin
- manager
- user
- pic_barang

Fitur utama sistem:
- login dan role-based access
- pengajuan peminjaman barang
- approval peminjaman oleh manager/admin
- approval parsial per item/unit
- monitoring due date dan overdue
- pengembalian barang
- pencatatan barang rusak dan ganti rugi
- extend/perpanjangan peminjaman
- dashboard dan laporan
- email notification dan reminder otomatis

File backend paling penting:
- config/database.php
- api/koneksi.php
- api/auth/login.php
- api/user/request-peminjaman.php
- api/approver/approve-items.php
- api/pengembalian/inspect.php
- api/extend/request.php
- api/email/email-functions.php
- api/cron/send-reminder-h7.php

Tabel database paling penting:
- users
- roles
- barang
- peminjaman
- detail_peminjaman
- peminjaman_units
- pengembalian
- detail_pengembalian
- extend_peminjaman
- extend_peminjaman_items

Relasi penting:
- peminjaman.user_id -> users.id
- detail_peminjaman.peminjaman_id -> peminjaman.id
- detail_peminjaman.barang_id -> barang.id
- pengembalian.peminjaman_id -> peminjaman.id
- extend_peminjaman.peminjaman_id -> peminjaman.id

Data live saat audit:
- roles: 4
- users: 5
- barang: 10
- peminjaman: 10
- detail_peminjaman: 18
- peminjaman_units: 51
- pengembalian: 2
- extend_peminjaman: 2

Distribusi status:
- Overdue: 3
- Due In 2 Days: 2
- Returned: 2
- Due In 1 Day: 1
- Partial Approved: 1
- Rejected: 1

Tolong bantu saya membuat slide presentasi yang kuat, profesional, dan mudah dipahami audiens non-teknis, tetapi tetap menunjukkan nilai teknis sistem ini.
```

## 12. File Pendamping yang Sudah Ada

Dokumen presentasi yang sudah dibuat sebelumnya:

- `PRESENTASI_PROJECT_PEMINJAMAN.md`

Dokumen ini berbeda karena lebih fokus pada struktur proyek dan struktur database untuk kebutuhan handover konteks ke AI lain.
