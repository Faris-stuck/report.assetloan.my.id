# SECURITY

## Secret Handling

- `.env` dan env produksi tidak boleh masuk git.
- Dokumentasi memakai `[REDACTED]` untuk password/token.
- GitHub token, DB password, dan app key tidak boleh ditampilkan di output.

## Public Form

- CSRF Laravel aktif.
- CAPTCHA session aktif untuk submit publik.
- Token anti-duplikat form digunakan pada submit browser.
- Validation error kembali ke form, bukan error page mentah.

## File Upload

- Upload dibatasi jumlah, tipe MIME, ekstensi, dan ukuran.
- File disimpan di private disk.
- Download harus lewat controller dan policy.

## Headers dan Runtime

- Docker image mematikan `expose_php`.
- Apache memakai `ServerTokens Prod`, `ServerSignature Off`, dan `TraceEnable Off`.
- Security middleware tersedia untuk header aplikasi.

## Audit

- Status history mencatat perubahan status laporan.
- Audit log tersedia untuk aksi admin/master data.
