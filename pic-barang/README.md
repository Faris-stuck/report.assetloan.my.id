# PIC Barang – Modul Terpisah

Modul ini **terpisah** dari admin, manager, dan user. UI (navbar, profil, header) mengikuti pola yang sama dengan role lain.

## Struktur Folder

```
pic-barang/
├── dashboard.html              # Dashboard + statistik + tabel peminjaman aktif
├── profil.html                 # Profil user (nama, email, NRP)
├── update-barang/
│   └── update-barang.html      # Daftar barang + edit (kategori, lokasi, stok, kondisi)
├── pengembalian/
│   └── pengembalian-barang.html # Daftar sedang dipinjam + proses pengembalian
└── README.md
```

## Navbar (sama di semua halaman)

- **Dashboard** → `dashboard.html`
- **Update Barang** → `update-barang/update-barang.html`
- **Pengembalian** → `pengembalian/pengembalian-barang.html`
- **Profil** → `profil.html`

## Koneksi Database

- Semua data menggunakan **database `peminjaman`** (lihat `api/koneksi.php`).
- Tabel: `users`, `barang`, `peminjaman`, `detail_peminjaman`.

## API (terpisah dari admin/manager/user)

Semua endpoint PIC Barang ada di **`api/pic_barang/`** (tidak digabung dengan api/admin, api/user, api/approver):

| File | Fungsi |
|------|--------|
| `dashboard-stats.php` | Statistik dashboard (sedang dipinjam, dikembalikan hari ini, total barang, stok menipis) |
| `profile.php` | Data profil user dari session |
| `barang-get.php` | Daftar barang |
| `barang-detail.php` | Detail barang by ID |
| `barang-update.php` | Update data barang |
| `pengembalian-list.php` | Daftar peminjaman status "Sedang Dipinjam" |
| `process-return.php` | Proses pengembalian (status + kembalikan stok) |

Setiap API memvalidasi role **`pic_barang`** via session (SessionValidator).

## Role & Login

- Role di database: `users.role` = `'pic_barang'` (enum di schema).
- Setelah login, user dengan role `pic_barang` diarahkan ke `pic-barang/dashboard.html`.
- Redirect dan validasi role di: `auth/login.html`, `assets/js/auth/role-validator.js`.
