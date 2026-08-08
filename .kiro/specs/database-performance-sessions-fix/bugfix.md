# Bugfix Requirements: Database Performance & Sessions Management

## Introduction

Aplikasi LAPORIN saat ini mengalami dua masalah kritis yang saling terkait:
1. **Critical Blocking Issue**: Session handler gagal karena tabel `sessions` tidak ada di database, menyebabkan error dan kegagalan login workflow
2. **Performance Degradation**: Response time lambat (151-651ms) karena query langsung ke database tanpa caching layer

Bugfix ini mengatasi kedua masalah dengan:
- Membuat tabel sessions yang missing
- Mengimplementasikan aggressive caching layer dengan Redis untuk mengurangi beban database
- Mengonfigurasi session handler untuk bekerja dengan Redis

Hasil akhir: Aplikasi stabil dengan session management yang berfungsi + response time <50ms untuk cached queries.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN aplikasi pertama kali dijalankan atau session handler dipicu THEN sistem crash dengan error "QueryException: Base table or view not found: 1146 Table 'laporin.sessions' doesn't exist"

1.2 WHEN user melakukan login atau session diakses THEN sistem gagal mengonversi session data karena DatabaseSessionHandler tidak menemukan tabel sessions

1.3 WHEN query dilakukan ke database untuk user profiles, reports, atau master data THEN sistem menunggu 151-651ms untuk response karena query langsung ke database tanpa caching layer

1.4 WHEN multiple users mengakses report list atau master data simultaneously THEN database mengalami load tinggi dan response time meningkat eksponensial

1.5 WHEN report atau master data berubah (create/update/delete) THEN user melihat stale data sampai query cache natural expired karena no explicit cache invalidation strategy

### Expected Behavior (Correct)

2.1 WHEN aplikasi dijalankan dan session handler dipicu THEN sistem akan menemukan tabel sessions dan berhasil menyimpan/retrieve session data tanpa error

2.2 WHEN user melakukan login THEN sistem akan mengonversi session data dan menyimpannya di Redis session storage

2.3 WHEN query dilakukan ke user profiles, reports, atau master data THEN sistem akan:
   - Check Redis cache terlebih dahulu untuk data yang diminta
   - Jika cache hit, return cached data <50ms
   - Jika cache miss, query database, cache hasil, dan return dalam <100ms
   - Database hanya di-query ketika cache miss

2.4 WHEN multiple users mengakses report list atau master data simultaneously THEN database load berkurang karena redis cache menangani mayoritas read requests

2.5 WHEN report atau master data diubah (create/update/delete) THEN sistem akan:
   - Invalidate related cache entries immediately
   - Next query akan fetch fresh data dari database dan cache hasilnya
   - User akan selalu melihat data terbaru

### Unchanged Behavior (Regression Prevention)

3.1 WHEN user login dengan valid credentials THEN sistem SHALL CONTINUE TO authenticate user dan set session cookie dengan same session security settings (HTTP_ONLY, SAME_SITE, SECURE flags)

3.2 WHEN user logout THEN sistem SHALL CONTINUE TO destroy session completely dan clear session data dari Redis

3.3 WHEN session lifetime (120 minutes) expires THEN sistem SHALL CONTINUE TO automatically invalidate session dan require re-authentication

3.4 WHEN unauthorized user tries to access protected route THEN sistem SHALL CONTINUE TO return 403/401 error dengan no performance change

3.5 WHEN database connection fails THEN sistem SHALL CONTINUE TO gracefully handle error dan not break application (fallback behavior)

3.6 WHEN create/read/delete operations happen on reports THEN sistem SHALL CONTINUE TO maintain data integrity dan foreign key constraints

3.7 WHEN audit logging happens THEN sistem SHALL CONTINUE TO record all user actions di AuditLog dengan same timestamp accuracy

3.8 WHEN file attachment upload/download happens THEN sistem SHALL CONTINUE TO work with same reliability dan not be affected by caching layer
