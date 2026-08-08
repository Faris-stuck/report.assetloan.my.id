---
domain: business
purpose: business-rules
version: 1.0
updated: 2024-01-15
owner: business-team
status: stable
---

# BUSINESS_RULES

## Routing Laporan

| Jenis laporan | Assigned role |
| ------------- | ------------- |
| `violation`   | `kesiswaan`   |
| `damage`      | `sarpras`     |

## Validasi Publik

- Nomor HP wajib agar sekolah bisa menghubungi pelapor.
- Email opsional, dipakai untuk notifikasi bila diisi.
- Laporan violation wajib memilih kelas terkait.
- Laporan damage wajib mengisi barang/fasilitas dan kondisi kerusakan.
- CAPTCHA harus cocok dengan jawaban di session.
- Consent wajib dicentang sebelum submit.

## Lampiran

- Maksimal 3 file per submit.
- Format: JPG, JPEG, PNG, WEBP, PDF.
- Maksimal 4 MB per file.
- File disimpan di disk private dan diunduh melalui route terproteksi.

## Tracking

- Halaman sukses hanya terbuka langsung setelah submit.
- Akses tracking membutuhkan nomor laporan dan kode akses.
- Pelapor dapat memberi info tambahan saat status meminta konfirmasi atau informasi.
