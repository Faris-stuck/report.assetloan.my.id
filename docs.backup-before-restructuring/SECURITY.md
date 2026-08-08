# KEAMANAN

## Penanganan Rahasia

- `.env` dan env produksi tidak boleh masuk git.
- Dokumentasi memakai `[REDACTED]` untuk password/token.
- Token GitHub, password DB, dan app key tidak boleh ditampilkan di output.

## Form Publik

- CSRF Laravel aktif.
- CAPTCHA session aktif untuk submit publik.
- Token anti-duplikat form digunakan pada submit browser.
- Error validasi kembali ke form, bukan halaman error mentah.

## Unggah File

- Unggah dibatasi jumlah, tipe MIME, ekstensi, dan ukuran.
- File disimpan di private disk.
- Unduhan harus lewat controller dan policy.

## Header dan Runtime

- Gambar Docker mematikan `expose_php`.
- Apache memakai `ServerTokens Prod`, `ServerSignature Off`, dan `TraceEnable Off`.
- Middleware keamanan tersedia untuk header aplikasi.

## Audit

- Status history mencatat perubahan status laporan.
- Audit log tersedia untuk aksi admin/master data.
