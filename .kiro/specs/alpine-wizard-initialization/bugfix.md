# Bugfix Requirements Document

## Introduction

Aplikasi LAPORIN menggunakan Alpine.js untuk form wizard pada halaman publik pembuatan laporan (report-form.blade.php) dan interaksi dropdown di navbar. Namun, Alpine.js tidak diinisialisasi dengan benar karena dimuat secara asynchronous dengan atribut `defer` tanpa memastikan DOM siap. Hal ini menyebabkan tombol navigasi wizard, validasi step, dan kondisional field tidak berfungsi, membuat pengguna tidak dapat mengirim laporan.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN pengguna mengklik tombol "Lanjut" pada wizard form THEN tombol tidak responsif dan form tidak berpindah ke langkah berikutnya

1.2 WHEN pengguna memilih reporter type (siswa/guru/staf) THEN field kondisional (kelas, mapel, unit staf) tidak disable/enable sesuai pilihan

1.3 WHEN pengguna mengklik tombol dropdown "Panel Admin" di navbar THEN dropdown menu tidak terbuka atau tertutup dengan tidak konsisten

1.4 WHEN pengguna mengklik attachment file input pada langkah 4 wizard THEN validasi file attachment tidak berjalan dan custom error message tidak ditampilkan

### Expected Behavior (Correct)

2.1 WHEN pengguna mengklik tombol "Lanjut" pada wizard form THEN sistem SHALL menjalankan `validateCurrentStep()` dan berpindah ke langkah berikutnya jika validasi lolos

2.2 WHEN pengguna memilih reporter type (siswa/guru/staf) THEN sistem SHALL menjalankan `syncConditionalFields()` dan field kondisional akan disable/enable sesuai pilihan yang dipilih

2.3 WHEN pengguna mengklik tombol dropdown "Panel Admin" di navbar THEN sistem SHALL membuka/menutup dropdown menu dengan konsisten dan responsif

2.4 WHEN pengguna mengklik attachment file input pada langkah 4 wizard THEN sistem SHALL menjalankan `validateAttachments()` dan menampilkan error message jika file tidak valid

### Unchanged Behavior (Regression Prevention)

3.1 WHEN halaman sedang loading THEN sistem SHALL CONTINUE TO menampilkan form dengan graceful tanpa error message

3.2 WHEN CSS Bootstrap dan Tailwind sudah dimuat THEN sistem SHALL CONTINUE TO menampilkan styling yang konsisten dan responsive

3.3 WHEN pengguna tidak menggunakan JavaScript THEN sistem SHALL CONTINUE TO menampilkan form dengan fallback HTML5 validation

3.4 WHEN pengguna mengakses halaman di browser yang tidak support Alpine.js THEN sistem SHALL CONTINUE TO menampilkan form namun interaksi kondisional tidak bekerja
