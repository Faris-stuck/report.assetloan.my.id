# Presentasi Project Peminjaman Barang

Dokumen ini berisi isi slide, arah narasi presenter, data audit, dan rekomendasi visual untuk presentasi PROJECT peminjaman barang.

Gunakan dokumen ini sebagai bahan membuat PowerPoint, Google Slides, atau Canva.

## Profil Presentasi

- Bahasa: Indonesia formal-semi formal
- Durasi ideal: 10-15 menit
- Jumlah slide: 18
- Fokus utama: alur sistem dan hasil implementasi
- Fokus pendukung: bukti teknis dari coding dan database

## Data Audit Per 30 Maret 2026

### Ringkasan Database Live

- roles: 4
- users: 5
- barang: 10
- peminjaman: 10
- detail_peminjaman: 18
- peminjaman_units: 51
- pengembalian: 2
- extend_peminjaman: 2

### Distribusi Status Peminjaman

- Overdue: 3
- Due In 2 Days: 2
- Returned: 2
- Due In 1 Day: 1
- Partial Approved: 1
- Rejected: 1

### Poin Teknis Utama yang Layak Dijual

- Sistem memakai role-based access untuk `admin`, `manager`, `user`, dan `pic_barang`.
- Approval berjalan sampai level unit/item, bukan hanya per transaksi.
- Status aktif seperti `Due In`, `Due Today`, dan `Overdue` dihitung dinamis.
- Pengembalian mendukung barang baik, barang rusak, dan return parsial.
- Extend peminjaman didukung oleh tabel dan API terpisah.
- Email notifikasi dan reminder sudah terintegrasi.

---

## Slide 1 - Judul Project

**Judul slide**

Sistem Informasi Peminjaman Barang Berbasis Web

**Isi slide**

- Nama project
- Nama penyusun
- Instansi, kelas, atau divisi
- Tanggal presentasi
- Subtitle: "Sistem terintegrasi untuk pengajuan, approval, monitoring, dan pengembalian barang"

**Narasi presenter**

Pada presentasi ini saya akan menjelaskan project sistem informasi peminjaman barang berbasis web yang saya kembangkan untuk mengelola proses peminjaman secara end-to-end, mulai dari login user, pengajuan pinjam, approval, monitoring jatuh tempo, pengembalian, sampai notifikasi email.

**Visual yang disarankan**

- Gunakan screenshot `index.html` atau logo perusahaan.
- Pakai layout pembuka yang bersih dan profesional.

---

## Slide 2 - Latar Belakang Masalah

**Isi slide**

- Proses peminjaman barang sering masih dicatat manual.
- Data approval dan pengembalian mudah terlewat.
- Monitoring stok dan status jatuh tempo sulit dilakukan.
- Dibutuhkan sistem yang terpusat, cepat, dan akurat.

**Narasi presenter**

Latar belakang dari project ini adalah masih adanya potensi kesalahan jika peminjaman barang dikelola secara manual. Masalah utamanya adalah sulit memantau stok barang, status peminjaman, approval, serta pengembalian yang sudah jatuh tempo. Karena itu saya membangun sistem yang lebih terintegrasi agar prosesnya lebih cepat dan data lebih akurat.

**Visual yang disarankan**

- Diagram sederhana "Manual -> Masalah -> Solusi Sistem".

---

## Slide 3 - Tujuan Project

**Isi slide**

- Mencatat transaksi peminjaman secara terpusat.
- Mempermudah proses approval dan monitoring.
- Menjaga akurasi stok barang.
- Mendukung pengembalian, extend, dan notifikasi.
- Menyediakan dashboard dan laporan berbasis data.

**Narasi presenter**

Tujuan project ini bukan hanya menyimpan data peminjaman, tetapi juga membangun alur kerja yang lengkap. Sistem harus bisa dipakai untuk mengelola approval, memantau stok barang, menangani pengembalian, serta memberikan informasi yang mudah dibaca melalui dashboard dan laporan.

**Visual yang disarankan**

- Ikon target atau empat kotak fitur utama.

---

## Slide 4 - Aktor dan Hak Akses

**Isi slide**

- `Admin`: kelola user, data barang, approval, laporan, dan pengembalian.
- `Manager`: approval peminjaman dan monitoring laporan.
- `User`: ajukan peminjaman, lihat status, ajukan pengembalian, dan extend.
- `PIC Barang`: update data barang dan bantu proses pengembalian.
- Data live saat audit menunjukkan ada `4 role` dan `5 user aktif`.

**Narasi presenter**

Sistem ini dibagi berdasarkan role agar setiap pengguna hanya melihat fitur yang sesuai tanggung jawabnya. Dengan pemisahan ini, alur kerja menjadi lebih aman, lebih jelas, dan lebih mudah dipahami oleh pengguna.

**Visual yang disarankan**

- Tabel role vs fitur.
- Screenshot dashboard `admin/dashboard.html`, `manager/dashboard.html`, `user/dashboard.html`, dan `pic-barang/dashboard.html`.

---

## Slide 5 - Arsitektur Sistem

**Isi slide**

- Frontend menggunakan halaman HTML dan JavaScript per role.
- Backend menggunakan API PHP yang modular.
- Database menggunakan MySQL `peminjaman`.
- Email memakai PHPMailer dengan queue worker dan reminder otomatis.
- Koneksi database dipusatkan di `config/database.php` dan `api/koneksi.php`.

**Narasi presenter**

Secara arsitektur, sistem ini terdiri dari frontend untuk tampilan per role, backend API PHP untuk proses bisnis, dan database MySQL sebagai pusat data. Selain itu, sistem juga memiliki integrasi email untuk notifikasi dan reminder sehingga lebih siap dipakai pada skenario nyata.

**Visual yang disarankan**

- Diagram 3 layer: Frontend -> API PHP -> Database.
- Tambahkan panah ke modul email.

---

## Slide 6 - Desain Database

**Isi slide**

- Tabel inti: `users`, `roles`, `barang`, `peminjaman`, `detail_peminjaman`, `peminjaman_units`, `pengembalian`, `detail_pengembalian`, `extend_peminjaman`.
- Relasi utama:
  - `peminjaman -> users`
  - `detail_peminjaman -> peminjaman + barang`
  - `pengembalian -> peminjaman + users`
  - `extend_peminjaman -> peminjaman + users`
- Struktur ini mendukung transaksi sampai level item/unit.

**Narasi presenter**

Database dirancang untuk mendukung proses peminjaman secara detail. Tidak hanya menyimpan header transaksi, tetapi juga detail barang, unit yang disetujui, data pengembalian, dan data extend. Struktur ini membuat sistem lebih fleksibel dan akurat.

**Visual yang disarankan**

- ERD sederhana.
- Highlight tabel `peminjaman`, `detail_peminjaman`, dan `peminjaman_units`.

---

## Slide 7 - Login dan Role-Based Access

**Isi slide**

- User login menggunakan email dan password.
- Session dibentuk sesuai role.
- Setiap role diarahkan ke dashboard dan fitur masing-masing.
- Sistem dipisah per role, bukan satu tampilan untuk semua.

**Narasi presenter**

Setelah login, sistem akan menentukan role pengguna dan mengarahkan ke dashboard yang sesuai. Dengan pendekatan ini, pengalaman pengguna menjadi lebih relevan dan keamanan akses juga lebih terjaga karena fitur dibatasi sesuai peran.

**Visual yang disarankan**

- Screenshot halaman login `index.html`.
- Tambahkan panah ke empat dashboard role.

---

## Slide 8 - Alur Pengajuan Peminjaman

**Isi slide**

- User memilih barang yang ingin dipinjam.
- User menentukan tanggal pinjam, tanggal kembali, dan lokasi penggunaan.
- Data disimpan ke tabel `peminjaman` dan `detail_peminjaman`.
- `stok_tersedia` langsung berkurang jika request valid.
- Email request dikirim ke pihak terkait.

**Narasi presenter**

Pada tahap ini user mengisi form peminjaman barang. Jika data valid dan stok tersedia, sistem akan menyimpan transaksi ke database, mengurangi stok yang tersedia, lalu mengirim notifikasi email agar proses approval bisa segera dilakukan.

**Visual yang disarankan**

- Flowchart 5 langkah.
- Screenshot dari `user/peminjaman/ajukan-peminjaman.html`.

---

## Slide 9 - Alur Approval Peminjaman

**Isi slide**

- Manager atau Admin memproses approval.
- Approval dilakukan per item atau per unit.
- Status hasil approval bisa `Borrowed`, `Rejected`, atau `Partial Approved`.
- Jika ada item yang ditolak, stok dikembalikan otomatis.
- Sistem juga mengirim email approval atau rejection.

**Narasi presenter**

Salah satu keunggulan sistem ini adalah approval tidak hanya dilakukan per transaksi, tetapi bisa sampai ke level unit. Jadi jika dalam satu transaksi ada beberapa item, sebagian bisa disetujui dan sebagian bisa ditolak. Hal ini membuat proses lebih fleksibel dan realistis.

**Visual yang disarankan**

- Screenshot `manager/persetujuan/menunggu-approval.html` atau `admin/peminjaman/admin-approval.html`.
- Diagram decision flow untuk approved, rejected, partial approved.

---

## Slide 10 - Monitoring Status dan Jatuh Tempo

**Isi slide**

- Sistem menghitung status aktif seperti `Due In`, `Due Today`, dan `Overdue`.
- Status tidak hanya diambil mentah dari database.
- Sistem membaca tanggal pengembalian dan unit yang masih aktif.
- Dashboard selalu menyesuaikan kondisi aktual.

**Narasi presenter**

Monitoring status pada project ini menjadi nilai tambah karena status aktif tidak bersifat statis. Sistem menghitungnya secara dinamis dari tanggal kembali dan unit yang masih aktif, sehingga informasi di dashboard lebih akurat dan tidak mudah miss.

**Visual yang disarankan**

- Timeline jatuh tempo dari H-7 sampai overdue.
- Screenshot card atau chart di dashboard yang menunjukkan status aktif.

---

## Slide 11 - Pengembalian Barang

**Isi slide**

- User dapat mengajukan pengembalian barang.
- Admin atau PIC melakukan inspeksi kondisi barang.
- Barang baik menambah `stok_tersedia`.
- Barang rusak tercatat pada stok rusak dan biaya ganti rugi.
- Status akhir dapat menjadi `Returned`, `Partially Returned`, atau `Return in Process`.

**Narasi presenter**

Pada proses pengembalian, sistem tidak hanya mencatat bahwa barang sudah kembali, tetapi juga memeriksa kondisi barang. Jika ada kerusakan, sistem bisa mencatat stok rusak dan biaya ganti rugi. Ini membuat kontrol inventaris menjadi lebih lengkap.

**Visual yang disarankan**

- Screenshot `user/pengembalian/ajukan-pengembalian.html` dan `pic-barang/pengembalian/pengembalian-barang.html`.

---

## Slide 12 - Extend atau Perpanjangan

**Isi slide**

- User dapat mengajukan perpanjangan tanggal kembali.
- Sistem memvalidasi bahwa pinjaman masih aktif.
- Extend disimpan pada tabel khusus.
- Extend juga dapat berlaku sampai level unit.
- Approver dapat menerima atau menolak extend.

**Narasi presenter**

Fitur extend menunjukkan bahwa sistem ini sudah lebih matang daripada CRUD biasa. Jika user masih membutuhkan barang, ia dapat mengajukan perpanjangan dan sistem akan memprosesnya sesuai aturan yang berlaku.

**Visual yang disarankan**

- Flowchart kecil: request extend -> approval -> update expected return.

---

## Slide 13 - Dashboard dan Laporan

**Isi slide**

- Setiap role memiliki dashboard sendiri.
- Dashboard membaca data dari API dan database, bukan hardcoded.
- Sistem menampilkan card, chart, dan laporan.
- Contoh informasi: peminjaman aktif, returned, overdue, dan loan vs return ratio.
- Logika `Borrowed` telah disesuaikan agar mencakup `Partial Approved` dan status `Due`.

**Narasi presenter**

Dashboard menjadi pusat monitoring sistem. Dengan adanya dashboard per role, pengguna dapat langsung melihat informasi yang paling relevan untuk tugasnya. Laporan juga memudahkan pengambilan keputusan karena data yang ditampilkan berasal langsung dari database.

**Visual yang disarankan**

- Screenshot `admin/dashboard.html` dan `manager/laporan/laporan-peminjaman.html`.

---

## Slide 14 - Integrasi Email dan Reminder

**Isi slide**

- Email dikirim untuk request, approval, reject, return, dan extend.
- Sistem memiliki queue worker agar pengiriman email tidak memperlambat request.
- Terdapat reminder otomatis H-7 sampai H-0 untuk pinjaman aktif.
- Fitur ini mendukung komunikasi dan kedisiplinan pengembalian.

**Narasi presenter**

Project ini juga mendukung notifikasi email agar proses bisnis tidak hanya berhenti di pencatatan data. Dengan reminder otomatis, user dapat diingatkan sebelum jatuh tempo, sementara queue worker membantu menjaga performa aplikasi.

**Visual yang disarankan**

- Diagram API -> email queue -> SMTP -> user/admin.

---

## Slide 15 - Hasil Implementasi Nyata

**Isi slide**

- Data audit per 30 Maret 2026:
  - roles: 4
  - users: 5
  - barang: 10
  - peminjaman: 10
  - detail_peminjaman: 18
  - peminjaman_units: 51
  - pengembalian: 2
  - extend_peminjaman: 2
- Distribusi status:
  - Overdue: 3
  - Due In 2 Days: 2
  - Returned: 2
  - Due In 1 Day: 1
  - Partial Approved: 1
  - Rejected: 1

**Narasi presenter**

Untuk menunjukkan bahwa sistem ini benar-benar berjalan, saya juga melakukan audit terhadap database aktif. Hasilnya menunjukkan bahwa data transaksi, unit peminjaman, status, dan pengembalian memang sudah tersimpan secara nyata di database, bukan sekadar simulasi tampilan.

**Visual yang disarankan**

- Gunakan tabel ringkas atau chart batang kecil.

---

## Slide 16 - Kelebihan Sistem

**Isi slide**

- Role-based access yang jelas.
- Approval per unit atau item.
- Status jatuh tempo real-time.
- Tracking pengembalian dan kerusakan barang.
- Extend peminjaman.
- Integrasi email dan dashboard laporan.

**Narasi presenter**

Kelebihan utama project ini adalah alurnya sudah menyatu dari awal sampai akhir. Tidak hanya fokus pada input data, tetapi juga memperhatikan approval, monitoring, pengembalian, kontrol stok, serta notifikasi untuk mendukung proses operasional yang nyata.

**Visual yang disarankan**

- Gunakan 6 ikon keunggulan.

---

## Slide 17 - Kendala dan Solusi

**Isi slide**

- Sinkronisasi status aktif dengan due date.
- Akurasi bucket `Borrowed` di dashboard.
- Kompleksitas approval parsial.
- Kompleksitas pengembalian parsial dan barang rusak.
- Solusi dilakukan lewat logika backend dan struktur tabel yang lebih detail.

**Narasi presenter**

Dalam pengembangan project ini ada beberapa tantangan, terutama pada sinkronisasi status dan akurasi data dashboard. Tantangan tersebut diselesaikan dengan pendekatan backend yang lebih detail, seperti perhitungan status dinamis, tracking per unit, dan pemisahan tabel transaksi yang lebih jelas.

**Visual yang disarankan**

- Tabel dua kolom: kendala dan solusi.

---

## Slide 18 - Kesimpulan dan Pengembangan Lanjutan

**Isi slide**

- Sistem berhasil mengintegrasikan proses peminjaman barang dari awal sampai akhir.
- Sistem membantu kontrol stok, approval, pengembalian, dan monitoring.
- Sistem layak dikembangkan lebih lanjut.
- Pengembangan berikutnya:
  - password hashing
  - audit log aktivitas
  - export PDF/Excel
  - notifikasi WhatsApp atau mobile

**Narasi presenter**

Kesimpulannya, project ini berhasil membangun sistem peminjaman barang yang terintegrasi dan relevan untuk kebutuhan operasional. Ke depan, sistem ini masih bisa ditingkatkan pada aspek keamanan, audit aktivitas, format laporan, dan perluasan kanal notifikasi.

**Visual yang disarankan**

- Gunakan slide penutup yang bersih dengan kalimat "Terima kasih".

---

## Bukti Teknis yang Bisa Disebut Saat Tanya Jawab

- Koneksi database terpusat: `config/database.php` dan `api/koneksi.php`
- Request peminjaman: `api/user/request-peminjaman.php`
- Approval parsial dan update stok: `api/approver/approve-items.php`
- Status due dan overdue dinamis: `api/koneksi.php`
- Pengembalian dan damage handling: `api/pengembalian/inspect.php`
- Extend peminjaman: `api/extend/request.php`
- Email queue dan reminder: `api/email/email-functions.php` dan `api/cron/send-reminder-h7.php`

## Rekomendasi Screenshot yang Perlu Disiapkan

- Login: `index.html`
- Dashboard admin: `admin/dashboard.html`
- Dashboard manager: `manager/dashboard.html`
- Dashboard user: `user/dashboard.html`
- Dashboard PIC: `pic-barang/dashboard.html`
- Form pinjam: `user/peminjaman/ajukan-peminjaman.html`
- Approval: `manager/persetujuan/menunggu-approval.html`
- Laporan: `manager/laporan/laporan-peminjaman.html`
- Pengembalian: `pic-barang/pengembalian/pengembalian-barang.html`

## Checklist Sebelum Presentasi

- Cocokkan ulang angka pada slide hasil implementasi dengan isi database terbaru.
- Pastikan istilah status konsisten: `Borrowed`, `Partial Approved`, `Returned`, `Overdue`.
- Gunakan screenshot yang jelas dan tidak terlalu penuh.
- Batasi pembahasan coding hanya pada fitur yang memberi nilai bisnis.
- Siapkan demo singkat:
  - login sebagai user
  - ajukan peminjaman
  - approval oleh manager atau admin
  - cek perubahan status di dashboard atau laporan

## Catatan Penting

Jika waktu presentasi pendek, gabungkan Slide 16, Slide 17, dan Slide 18 menjadi 2 slide penutup agar durasi tetap aman.
