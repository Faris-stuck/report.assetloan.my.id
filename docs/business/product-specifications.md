---
domain: business
purpose: product-specifications
version: 1.0
updated: 2024-01-15
owner: business-team
status: stable
---

# PRODUCT

## Ringkasan

LAPORIN adalah kanal resmi untuk menerima dan memproses laporan warga sekolah. Fokus produk adalah membuat pelapor nyaman, mengurangi salah alur, dan memastikan petugas melihat laporan yang sesuai role.

## Pengguna

| Pengguna       | Kebutuhan                                                        |
| -------------- | ---------------------------------------------------------------- |
| Pelapor publik | Mengirim laporan tanpa login dan menyimpan kode tracking         |
| Superadmin     | Mengelola user, master data, QR Code, dan audit log              |
| Kesiswaan      | Memverifikasi dan menangani laporan perundungan atau pelanggaran |
| Sarpras        | Memverifikasi dan menangani laporan kerusakan fasilitas          |
| Wali kelas     | Membaca laporan terkait kelas ampuan tanpa mengubah laporan      |

## Alur Utama

1. Pelapor membuka form publik atau QR lokasi/kelas.
2. Pelapor mengisi identitas, jenis laporan, detail kejadian, bukti, consent, dan CAPTCHA.
3. Sistem membuat nomor laporan `LAP-XXXXXX-XXXXXX` dan kode akses 6 digit.
4. Laporan diarahkan ke `kesiswaan` atau `sarpras`.
5. Petugas memproses, menolak, meminta informasi tambahan, atau menyelesaikan laporan.
6. Pelapor melacak status memakai nomor laporan dan kode akses.

## Prinsip UX

- Form bertahap, satu keputusan besar per langkah.
- Copy pendek, tidak mengintimidasi pelapor.
- Error field muncul dekat input, bukan halaman error mentah.
- Dashboard role menampilkan menu relevan saja.
- Wali kelas jelas sebagai mode baca saja.
