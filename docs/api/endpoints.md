---
domain: api
purpose: endpoint-specifications
version: 1.0
updated: 2024-01-15
owner: platform-team
status: stable
related:
  - ../auth/authentication.md
  - ../deployment/deployment-pipeline.md
---

# API

Aplikasi saat ini memakai server-rendered rute Laravel, bukan JSON API publik.

## Rute Publik

| Metode | Rute                                  | Nama                    | Fungsi                         |
| ------ | ------------------------------------- | ----------------------- | ------------------------------ |
| GET    | `/`                                   | `public.report`         | Form laporan publik            |
| GET    | `/lapor/{qr?}`                        | `public.report.qr`      | Form publik dari QR            |
| POST   | `/lapor`                              | `public.report.store`   | Submit laporan                 |
| GET    | `/lapor-sukses/{report:public_token}` | `public.report.success` | Halaman sukses setelah submit  |
| GET    | `/lacak`                              | `track.form`            | Form pelacakan                 |
| POST   | `/lacak`                              | `track.search`          | Cari laporan dengan kode akses |
| POST   | `/lacak/{report}/info`                | `track.info`            | Kirim info tambahan            |
| POST   | `/lacak/{report}/confirm`             | `track.confirm`         | Konfirmasi selesai             |

## Rute Terautentikasi

| Prefix              | Role        | Fungsi                       |
| ------------------- | ----------- | ---------------------------- |
| `/dashboard`        | auth active | Dashboard role-spesifik      |
| `/reports/{report}` | policy      | Detail laporan               |
| `/admin/*`          | superadmin  | User, QR, master data, audit |
| `/kesiswaan/*`      | kesiswaan   | Proses laporan perundungan   |
| `/sarpras/*`        | sarpras     | Proses laporan kerusakan     |

## Pola Respon

- Sukses formulir: terarahkan dengan flash session.
- Error validasi: kembali dengan `errors` bag.
- Pelacakan publik: render hasil jika kode akses benar.
- Lampiran: unduhan streaming lewat policy.
