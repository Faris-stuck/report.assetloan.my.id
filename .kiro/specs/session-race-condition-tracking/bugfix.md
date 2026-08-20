# Bugfix Requirements: Session Race Condition dalam Tracking Controller

## Introduction

Session race condition terjadi di `TrackingController` ketika pengguna mengakses tracking dengan nomor laporan dan kode akses. Antara validasi session (di `hasTrackingAccess()`) dan eksekusi operasi database (di `addInfo()` atau `confirmComplete()`), session bisa di-clear atau timeout, menyebabkan operasi gagal dengan pesan "sesi tracking sudah habis" meskipun session sebenarnya masih valid saat user mengklik tombol. Ini mengakibatkan user frustasi karena harus login ulang tanpa alasan yang jelas.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN pengguna submit form `addInfo()` dengan session yang valid saat klik submit THEN `hasTrackingAccess()` bisa return false karena session di-clear antara method dispatch dan session check execution, menyebabkan redirect dengan error "sesi tracking sudah habis"

1.2 WHEN pengguna klik button `confirmComplete()` dengan session yang masih dalam TTL (1800 detik) THEN `hasTrackingAccess()` bisa di-trigger sebelum actual database operation, session check pass, tetapi session di-forget immediately di dalam `hasTrackingAccess()` sebelum operation berjalan

1.3 WHEN session `track_verified_at` check di-execute di awal method, session bisa expired atau di-manipulasi antara check dan data operation, leaving inconsistent state

### Expected Behavior (Correct)

2.1 WHEN pengguna submit form `addInfo()` dengan session yang valid saat klik submit THEN sistem SHALL melakukan session validation dan data operation secara atomic, ensuring session tidak di-clear sampai operation complete

2.2 WHEN pengguna klik button `confirmComplete()` dengan session dalam TTL THEN sistem SHALL validate session sekali, dan jika valid, execute operation tanpa re-checking atau clearing session di tengah-tengah operation

2.3 WHEN session validation dilakukan, sistem SHALL memastikan session tetap konsisten antara validation check dan actual database write, preventing false timeout errors

### Unchanged Behavior (Regression Prevention)

3.1 WHEN pengguna belum melakukan search dengan kode akses yang valid THEN sistem SHALL CONTINUE TO reject akses dengan error yang sesuai

3.2 WHEN session benar-benar expired (melampaui TTL 1800 detik) THEN sistem SHALL CONTINUE TO clear session dan redirect ke form pencarian

3.3 WHEN pengguna mengakses tracking dengan report ID yang tidak sesuai dengan session THEN sistem SHALL CONTINUE TO reject akses

3.4 WHEN pengguna submit `addInfo()` dengan status laporan tidak dalam daftar yang diizinkan THEN sistem SHALL CONTINUE TO reject dengan error status laporan

3.5 WHEN pengguna submit `confirmComplete()` dengan status laporan bukan 'menunggu_konfirmasi' THEN sistem SHALL CONTINUE TO reject dengan error status laporan
