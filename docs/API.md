# API

Aplikasi saat ini memakai server-rendered routes Laravel, bukan JSON API publik.

## Public Routes

| Method | Route                                 | Name                    | Fungsi                         |
| ------ | ------------------------------------- | ----------------------- | ------------------------------ |
| GET    | `/`                                   | `public.report`         | Form laporan publik            |
| GET    | `/lapor/{qr?}`                        | `public.report.qr`      | Form publik dari QR            |
| POST   | `/lapor`                              | `public.report.store`   | Submit laporan                 |
| GET    | `/lapor-sukses/{report:public_token}` | `public.report.success` | Halaman sukses setelah submit  |
| GET    | `/lacak`                              | `track.form`            | Form tracking                  |
| POST   | `/lacak`                              | `track.search`          | Cari laporan dengan kode akses |
| POST   | `/lacak/{report}/info`                | `track.info`            | Kirim info tambahan            |
| POST   | `/lacak/{report}/confirm`             | `track.confirm`         | Konfirmasi selesai             |

## Authenticated Routes

| Prefix              | Role        | Fungsi                       |
| ------------------- | ----------- | ---------------------------- |
| `/dashboard`        | auth active | Dashboard role-scoped        |
| `/reports/{report}` | policy      | Detail laporan               |
| `/admin/*`          | superadmin  | User, QR, master data, audit |
| `/kesiswaan/*`      | kesiswaan   | Proses laporan perundungan   |
| `/sarpras/*`        | sarpras     | Proses laporan kerusakan     |

## Response Pattern

- Form success: redirect dengan flash session.
- Validation error: redirect back dengan `errors` bag.
- Public tracking: render result jika kode akses benar.
- Attachment: streamed download lewat policy.
