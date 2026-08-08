# DESIGN

## Design Read

Aplikasi ini dibaca sebagai layanan sekolah yang trust-first dan accessibility-first. Bahasa visualnya bersih, hijau institusional, motion rendah, dan density sedang agar nyaman dipakai di HP.

## Dials

| Dial             | Nilai | Alasan                                       |
| ---------------- | ----- | -------------------------------------------- |
| Design variance  | 3     | Layanan sekolah perlu familiar dan stabil    |
| Motion intensity | 2     | Hindari distraksi pada form laporan sensitif |
| Visual density   | 5     | Dashboard butuh ringkas, form butuh lega     |

## Token Warna

| Token               | Hex       | Fungsi                        |
| ------------------- | --------- | ----------------------------- |
| `laporin.green`     | `#00A651` | Aksi utama dan status positif |
| `laporin.green-700` | `#04783E` | Hover, link, dan emphasis     |
| `laporin.green-900` | `#064225` | Teks aksen gelap              |
| `laporin.gold`      | `#F6C23E` | Highlight ringan              |
| `laporin.ink`       | `#10281B` | Teks utama                    |
| `laporin.muted`     | `#647067` | Helper text                   |
| `laporin.line`      | `#DFEEE5` | Border lembut                 |
| `laporin.danger`    | `#DC3545` | Error dan validasi            |

## Shape dan Spacing

- Radius utama: `1.1rem` untuk card, `0.85rem` untuk input dan button.
- Gunakan `gap-3` atau setara untuk field form.
- Hindari efek ramai: tidak ada glow neon, marquee, cursor custom, atau animasi loop.

## Komponen

- Form: label di atas, helper text di bawah, error dekat field.
- Button utama: hijau solid, teks putih, satu label aksi per intent.
- Table dashboard: heading jelas, status pill konsisten, aksi kanan.
- Card: hanya untuk grouping nyata, bukan dekorasi kosong.

## Accessibility Checklist

- Semua input punya label terkait `for` dan `id`.
- Skip link tersedia di layout utama.
- CTA tidak wrap di desktop.
- Reduced motion mematikan transisi dan animasi.
- Kontras button dan form mengikuti WCAG AA.
