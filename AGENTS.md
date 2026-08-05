# Instruksi Agen

## Lingkup

- Repositori ini menjalankan `report.assetloan.my.id`, aplikasi pelaporan LAPORIN untuk SMK Taruna Bangsa Bekasi.
- Jaga kredensial agar tidak masuk git. Gunakan `.env.example` dengan placeholder `[REDACTED]` saja.
- Utamakan copy UI bahasa Indonesia. Gunakan kata singkat, tenang, dan mudah ditindaklanjuti.

## Tumpukan

| Layer    | Pilihan                                                 |
| -------- | ------------------------------------------------------- |
| Backend  | Laravel 12 pada PHP 8.3                                 |
| Frontend | Blade, Bootstrap 5, Alpine.js, Vite, token Tailwind     |
| Database | MariaDB/MySQL di produksi, SQLite untuk pengujian otomatis |
| Runtime  | Docker image `laporin-app:*` di network `cf-network`    |

## Perintah

| Tugas               | Perintah                     |
| ------------------- | --------------------------- |
| Test PHP            | `php artisan test`          |
| Test berbasis Docker| `npm run test:docker`       |
| Bangun frontend     | `npm run build`             |
| Lint konfigurasi    | `npm run lint`              |
| Cek format          | `npm run format:check`      |
| Jalankan migrasi    | `php artisan migrate --force` |

## Referensi

| Kebutuhan     | File                   |
| ------------- | ---------------------- |
| Ruang produk  | `docs/PRODUCT.md`      |
| Token UI      | `docs/DESIGN.md`       |
| Arsitektur    | `docs/ARCHITECTURE.md` |
| Database      | `docs/DATABASE.md`     |
| Peran auth    | `docs/AUTH.md`         |
| Deploy        | `docs/DEPLOYMENT.md`   |
| Keputusan     | `docs/DECISIONS/`      |

## Konvensi

- Jangan commit `.env`, `vendor/`, `node_modules/`, `public/build/`, atau upload storage.
- Jalankan test di Docker jika host PHP tidak memiliki `pdo_sqlite`.
- Form laporan publik harus tetap dapat diakses: label di atas input, helper text di bawah, error server ditampilkan di dalam form.
- Akses role berbasis allow-list: `superadmin`, `kesiswaan`, `sarpras`, `wali_kelas`.
- Migrasi harus idempoten dan aman untuk data produksi.

## Atribusi Commit

Commit AI harus menyertakan:
`Co-Authored-By: Hermes Agent <noreply@nousresearch.com>`
