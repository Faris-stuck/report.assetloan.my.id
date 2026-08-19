# Database Folder

Folder ini dipakai untuk semua hal Hermes yang berhubungan dengan database.

Isi utamanya:
- `integrated-memory-helper.php` untuk backend memory berbasis tabel `ai_memory_*`
- `maintenance/` untuk utilitas inspeksi atau migrasi database
- `tests/` untuk verifikasi koneksi, schema, dan CRUD memory database

Kalau ingin mengubah backend memory database:
- mulai dari `integrated-memory-helper.php`
- cek utilitas pendukung di `maintenance/`
