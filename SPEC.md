# SPEC.md — LAPORIN Overhaul

**Versi Dokumen:** 1.0
**Tanggal:** 2026-07-30
**Proyek:** LAPORIN SMK Taruna Bangsa Bekasi
**Status:** Draft Overhaul

---

## 1. Ringkasan Projekt

LAPORIN adalah kanal pelaporan daring untuk SMK Taruna Bangsa Bekasi yang menangani dua jenis laporan: **Pelanggaran Siswa / Perundungan** dan **Kerusakan Fasilitas**. Overhaul ini memperbaiki 6 area: sistem 4 peran (sudah ada, diperkuat), email wajib, deteksi duplikat, antarmuka penuh Bahasa Indonesia, penyederhanaan langkah 3 pelanggaran, dan notifikasi email via log driver.

---

## 2. Sistem 4 Peran

### 2.1 Peran yang Tersedia

| Kode Peran       | Label          | Hak Akses                                                                 |
|-------------------|----------------|---------------------------------------------------------------------------|
| `superadmin`      | Super Admin    | Semua laporan, semua menu admin, manajemen users, QR code, master data   |
| `kesiswaan`       | Kesiswaan      | Laporan pelanggaran; proses, tolak, selesai; kurangi poin siswa            |
| `sarpras`         | Sarpras        | Laporan kerusakan; proses (jadwalkan/selesaikan), tolak                     |
| `wali_kelas`      | Wali Kelas     | Laporan pelanggaran yang terkait kelas yang diampu; baca & catat saja      |

### 2.2 Rutinitas Setiap Peran

**Kesiswaan**
1. Laporan pelanggaran masuk (status: `menunggu_verifikasi`)
2. Verifikasi & proses — pilih siswa & jenis pelanggaran → poin dipotong otomatis
3. Selesaikan penanganan → status: `menunggu_konfirmasi`
4. Pelapor mengkonfirmasi selesai
5. Laporan berstatus `selesai`

**Sarpras**
1. Laporan kerusakan masuk
2. Pilih prioritas & jadwalkan perbaikan (opsional: foto sebelum/sesudah)
3. Jika foto perbaikan diunggah → status langsung `selesai`; jika belum → `sedang_ditangani`
4. Dapat menolak dengan alasan

**Wali Kelas**
- Melihat hanya laporan pelanggaran pada kelas yang diampu (via `homeroom_classes`)
- Tidak dapat mengubah status
- Dapat menambahkan catatan internal

### 2.3 Alur Status Laporan

```
menunggu_verifikasi
    ↓
sedang_ditangani  ←→  memerlukan_informasi (pelapor menambah info)
    ↓
menunggu_konfirmasi  ←→  dibuka_kembali
    ↓
selesai
```

Status `ditolak` bisa dicapai dari `menunggu_verifikasi` atau `sedang_ditangani`.

---

## 3. Form Laporan Publik — Langkah demi Langkah

Form terbagi 4 langkah (wizard). Navigasi maju/mundur dengan validasi per langkah.

### Langkah 1 — Identitas Pelapor

| Field                        | Wajib | Catatan                                                  |
|------------------------------|-------|----------------------------------------------------------|
| Jenis Pelapor                | Ya    | `siswa`, `guru`, `staff`                                |
| Nama Lengkap                 | Ya    | Maks 150 karakter                                        |
| Kelas (jika siswa)           | Ya    | Pilih dari daftar kelas aktif                            |
| No. Absen (jika siswa)       | Tidak | Angka 1–60                                               |
| Mata Pelajaran (jika guru)   | Ya    | Pilih dari daftar mapel aktif                            |
| Unit Staf (jika staff)       | Ya    | Pilih dari daftar unit staf aktif                        |
| **No. HP**                   | **Ya**| 8–15 digit; wajib diisi                                   |
| **Email**                    | **Ya**| **Diubah dari opsional → wajib;** validasi format email  |

> **Perubahan:** `reporter_email` dari `nullable` menjadi **`required`**. Validasi RFC+DNS dihilangkan (opsional untuk lingkungan development). Gunakan validasi `email` sederhana.

### Langkah 2 — Jenis Laporan

| Opsi                  | Deskripsi                                           | Dialihkan ke |
|-----------------------|-----------------------------------------------------|--------------|
| Pelanggaran Siswa     | Perundungan, kedisiplinan, pelanggaran tata tertib   | Kesiswaan    |
| Kerusakan Fasilitas   | Meja, proyektor, AC, toilet, listrik, dll.          | Sarpras      |

### Langkah 3 — Detail Kejadian (Disederhanakan)

#### Untuk Laporan Pelanggaran — Field Wajib:

| Field             | Wajib | Catatan                                      |
|-------------------|-------|----------------------------------------------|
| Judul Laporan      | Ya    | Maks 200 karakter                            |
| Urgensi           | Ya    | rendah / sedang / tinggi / darurat            |
| Kelas Kejadian    | Ya    | WAJIB untuk pelanggaran                       |
| Lokasi            | Tidak | Pilih dari daftar; atau isi "Lokasi Lainnya" |
| Tanggal Kejadian  | Ya    | Tidak boleh lebih dari hari ini              |
| Jam Kejadian      | Tidak | Format HH:MM                                |
| Kronologi         | Ya    | Maks 5000 karakter                           |

#### Field Opsional Pelanggaran (Dihapus dari Wajib):

> **Sebelum:** `victim_name`, `victim_class_id`, `alleged_actor_name`, `alleged_actor_class_id`, `witness_name`, `bullying_type`, `impact_description` — semuanya wajib atau cukup opsional.
>
> **Sesudah:** Semua field di bawah ini menjadi **OPSIONAL** (tetap dikumpulkan untuk informasi tambahan, tetapi TIDAK MEMBATASI pengiriman laporan):
> - Nama korban / pihak terdampak
> - Kelas korban
> - Nama terduga pelaku
> - Kelas terduga pelaku
> - Nama saksi
> - Jenis pelanggaran / perundungan
> - Dampak yang dirasakan

> **Rasio:** Pengirim bisa melapor hanya dengan judul + kronologi + tanggal. Field terperinci membantu investigasi tetapi tidak boleh memblokir pelaporan.

#### Untuk Laporan Kerusakan — Field:

| Field                   | Wajib | Catatan                          |
|-------------------------|-------|----------------------------------|
| Judul Laporan           | Ya    | Maks 200 karakter                |
| Urgensi                 | Ya    | rendah / sedang / tinggi / darurat|
| Lokasi                  | Tidak | Pilih atau isi lokasi lainnya     |
| Tanggal                 | Ya    | Tidak boleh lebih dari hari ini   |
| Jam                     | Tidak | Format HH:MM                      |
| Kronologi / Deskripsi   | Ya    | Maks 5000 karakter               |
| Nama Barang / Fasilitas | Ya    | Maks 150 karakter                 |
| Kategori Barang         | Tidak | Maks 100 karakter                 |
| Kondisi Kerusakan      | Ya    | Maks 2000 karakter                |
| Dugaan Penyebab         | Tidak | Maks 1000 karakter                |
| Prioritas Perbaikan     | Tidak | rendah / sedang / tinggi / darurat |

### Langkah 4 — Konfirmasi & Kirim

| Field       | Wajib | Catatan                                      |
|-------------|-------|----------------------------------------------|
| Persetujuan  | Ya    | Checkbox: "Saya menyatakan laporan ini benar" |
| CAPTCHA     | Ya    | Soal penjumlahan sederhana (a + b)            |

### Lampiran

- Maksimum 3 file
- Format: JPG, PNG, WEBP, PDF
- Ukuran maks: 4 MB per file
- Disimpan di private storage (bukan publik)

---

## 4. Deteksi Laporan Duplikat (Anti-Duplicate)

### 4.1 Strategi Deteksi

Laporan dianggap **duplikat potensial** jika memenuhi SEMUA kondisi berikut dalam waktu **24 jam** terakhir:

1. **Tipe pelapor sama** (`reporter_type`)
2. **Nama pelapor sama** (case-insensitive)
3. **Tanggal kejadian sama** (`incident_date`)
4. **judul sama atau sangat mirip** (levenshtein distance ≤ 5 karakter)

Atau:

1. **Nomor HP sama** (apakah reporter_phone ada di laporan yang dibuat dalam 24 jam)

### 4.2 Penanganan Duplikat

Jika duplikat terdeteksi:

1. Sistem tetap **menerima** laporan (jangan blokir pelapor)
2. Di halaman sukses, tampilkan **peringatan**: *"Laporan dengan data serupa sudah dikirim dalam 24 jam terakhir. Tim kami akan menggabungkan penanganan jika diperlukan."*
3. Status laporan tetap `menunggu_verifikasi` — tidak ada perubahan alur
4. Catatan internal otomatis ditambahkan: *"Potensial duplikat — periksa laporan terkait."*

### 4.3 Implementasi

Tambahkan method di `PublicReportController`:

```php
private function checkDuplicate(array $validated): ?Report
{
    $window = now()->subHours(24);

    // Duplikat via HP
    if (!empty($validated['reporter_phone'])) {
        $byPhone = Report::where('reporter_phone', $validated['reporter_phone'])
            ->where('created_at', '>=', $window)
            ->first();
        if ($byPhone) return $byPhone;
    }

    // Duplikat via nama + tipe + tanggal + judul serupa
    $byData = Report::where('reporter_type', $validated['reporter_type'])
        ->where('reporter_name', $validated['reporter_name'])
        ->where('incident_date', $validated['incident_date'])
        ->where('created_at', '>=', $window)
        ->get()
        ->first(function (Report $r) use ($validated) {
            return levenshtein(mb_strtolower($r->title), mb_strtolower($validated['title'])) <= 5;
        });

    return $byData;
}
```

Panggil di `store()`, sebelum `createReportWithRandomNumber()`. Simpan hasil duplikat ke `session(['potential_duplicate_report_id' => $duplicate?->id])`.

---

## 5. Antarmuka Bahasa Indonesia Penuh

### 5. Cakupan

Seluruh teks antarmuka harus dalam Bahasa Indonesia yang baik dan baku. Berikut area yang perlu dicek dan diperbarui:

| Lokasi              | Teks Lama (Contoh)           | Standar Baru                              |
|---------------------|------------------------------|-------------------------------------------|
| Label field         | "Email"                      | "Email" (sudah benar)                     |
| Placeholder         | "Opsional"                   | "Wajib diisi" / "Opsional" sesuai konteks |
| Pesan validasi      | "Email wajib diisi"          | "Email wajib diisi agar sekolah dapat menghubungi." |
| Tombol              | "Submit"                     | "Kirim Laporan"                           |
| Notifikasi          | "Report created successfully"| "Laporan berhasil dikirim."               |
| Status              | "pending"                    | "Menunggu Verifikasi"                     |
| Error page          | "404 Not Found"              | "Halaman Tidak Ditemukan"                  |
| Breadcrumb / nav    | "Home"                       | "Beranda"                                 |

### 5.2 Daftar Cek Teks Indonesia

- [ ] Semua label form dalam Bahasa Indonesia
- [ ] Semua placeholder field dalam Bahasa Indonesia
- [ ] Semua pesan validasi error dalam Bahasa Indonesia
- [ ] Semua tombol (submit, cancel, back, next) dalam Bahasa Indonesia
- [ ] Semua flash message / session success/error dalam Bahasa Indonesia
- [ ] Semua label navigasi navbar dalam Bahasa Indonesia
- [ ] Semua teks status laporan (menunggu_verifikasi, sedang_ditangani, dll.) dalam Bahasa Indonesia
- [ ] Semua teks di halaman sukses setelah pengiriman laporan
- [ ] Teks email notifikasi (jika applicable)
- [ ] Tooltip dan aria-label dalam Bahasa Indonesia

---

## 6. Notifikasi Email via Log Driver

### 6.1 Arsitektur

Laravel mail menggunakan driver `log` untuk lingkungan development dan staging. Email tidak dikirim ke MTA (Mail Transfer Agent), melainkan **ditulis ke file `storage/logs/laravel.log`** dalam format yang dapat dibaca petugas.

Keuntungan:
- Tidak membutuhkan konfigurasi SMTP
- Petugas bisa memantau semua email notifikasi lewat file log
- Mudah dibaca dan di-audit

### 6.2 Konfigurasi

**.env:**
```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS=laporin@smktarunabangsa.sch.id
MAIL_FROM_NAME="LAPORIN SMK Taruna Bangsa"
```

`.env.example` harus diperbarui untuk mencerminkan ini.

### 6.3 Skenario Email yang Dikirim

| Event                            | Penerima              | Subjek (ID)                              | Isi Ringkasan                                |
|-----------------------------------|----------------------|------------------------------------------|----------------------------------------------|
| Laporan berhasil dikirim           | Pelapor (email)      | "LAPORIN — Laporan {nomor} Diterima"      | Nomor laporan, kode akses, link lacak         |
| Status berubah → sedang_ditangani | Pelapor              | "LAPORIN — Laporan {nomor} Sedang Ditangani" | Status baru, catatan publik             |
| Status berubah → menunggu_konfirmasi | Pelapor           | "LAPORIN — Laporan {nomor} Menunggu Konfirmasi" | Langkah selanjutnya untuk pelapor |
| Status berubah → selesai          | Pelapor              | "LAPORIN — Laporan {nomor} Selesai"       | Pengumuman selesai, langkah opsional          |
| Laporan ditolak                   | Pelapor              | "LAPORIN — Laporan {nomor} Ditolak"      | Alasan penolakan                             |

> **Catatan:** Email hanya dikirim jika pelapor mengisi email (dan email tersebut valid). Jika email kosong, notifikasi tetap dicatat di log sistem (log lokal) tetapi tidak menyebabkan error.

### 6.4 Implementasi Mailables

Buat struktur berikut:

```
app/Mail/
├── ReportReceived.php        # Laporan diterima
├── ReportStatusChanged.php    # Status berubah
└── ReportRejected.php        # Laporan ditolak
```

**Contoh: ReportReceived mailable**

```php
// app/Mail/ReportReceived.php
class ReportReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Report $report,
        public string $accessCode,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("LAPORIN — Laporan {$this->report->report_number} Diterima")
            ->view('emails.report-received')
            ->with(['report' => $this->report, 'accessCode' => $this->accessCode]);
    }
}
```

**View: resources/views/emails/report-received.blade.php**
```blade
<!DOCTYPE html>
<html lang="id">
<body>
  <h2>Laporan Anda Diterima</h2>
  <p>Nomor Laporan: <strong>{{ $report->report_number }}</strong></p>
  <p>Kode Akses: <strong>{{ $accessCode }}</strong></p>
  <p>Gunakan nomor laporan dan kode akses untuk melacak status di <a href="{{ route('track.form') }}">halaman lacak</a>.</p>
</body>
</html>
```

### 6.5 Pengiriman di Controller

Di `PublicReportController::store()`, setelah laporan berhasil dibuat:

```php
// Kirim email via log driver (hanya jika email terisi)
if (!empty($report->reporter_email)) {
    Mail::to($report->reporter_email)->send(new ReportReceived($report, $accessCode));
}
```

Di `KesiswaanController::process()`, `complete()`, `reject()` dan `SarprasController`:

```php
if (!empty($report->reporter_email)) {
    Mail::to($report->reporter_email)->send(new ReportStatusChanged($report));
}
```

### 6.6 Format di Log

Hasil di `storage/logs/laravel.log`:

```
[2026-07-30 10:15:00] local.INFO: Emailqueued {#1234
  "mailer": "log",
  "to": ["pelapor@email.com"],
  "subject": "LAPORIN — Laporan LAP-2H7K9M-Q4Z8BJ Diterima",
  "body": "..."
}
```

---

## 7. Hak Akses & Kebijakan (ReportPolicy)

### 7.1 Aturan `view`

| Peran        | Kondisi                                          |
|--------------|--------------------------------------------------|
| `superadmin` | Semua laporan                                     |
| `kesiswaan`  | Laporan bertipe `violation`                       |
| `sarpras`    | Laporan bertipe `damage`                         |
| `wali_kelas` | `violation` AND `related_class_id` ada di kelas diampu |

### 7.2 Aturan `comment`

| Peran        | Kondisi                                          |
|--------------|--------------------------------------------------|
| `kesiswaan`  | `report_type === violation`                      |
| `sarpras`    | `report_type === damage`                         |
| `wali_kelas` | `report_type === violation` DAN kelas diampu      |

### 7.3 Aturan `updateStatus`

| Peran        | Kondisi                              |
|--------------|--------------------------------------|
| `superadmin` | Ya (selalu)                          |
| `kesiswaan`  | Ya, jika violation dan status sesuai  |
| `sarpras`    | Ya, jika damage dan status sesuai    |
| `wali_kelas` | **Tidak** (baca & catat saja)        |

---

## 8. Perubahan Skema Database

### 8.1 Reports Table

Tidak ada perubahan kolom baru yang diperlukan. Kolom `reporter_email` sudah ada dan berubah dari nullable → required di level aplikasi (validation rule), bukan migration (karena kolom sudah ada dan boleh NULL di DBMS untuk data historic).

### 8.2 Duplikat Tracking

Tidak perlu kolom baru. Deteksi duplikat dilakukan secara on-the-fly dengan query + session storage (tanpa kolom tabel tambahan).

---

## 9. Daftar Perubahan (Changelog Overhaul)

| #  | Area                  | Sebelum                              | Sesudah                                             |
|----|-----------------------|--------------------------------------|-----------------------------------------------------|
| 1  | Email pelapor         | `nullable`                           | **`required`** + validasi email sederhana           |
| 2  | Deteksi duplikat      | Tidak ada                            | Cek 24 jam via HP + nama+tanggal+judul             |
| 3  | UI Bahasa Indonesia   | Sebagian sudah ID, perlu diperiksa   | Lengkap, konsisten, seluruh teks dalam Bahasa Indonesia |
| 4  | Langkah 3 pelanggaran  | Banyak field wajib/opsional          | Hanya judul, urgensi, kelas, lokasi, tanggal, kronologi wajib; sisanya opsional |
| 5  | Notifikasi email      | Tidak ada                            | Mail driver `log`; 5 skenario email; Mailable class |
| 6  | Wali kelas            | Belum ada menu sendiri               | Dapat menambahkan catatan pada laporan pelanggaran kelasnya |

---

## 10. Ketergantungan & Catatan Teknis

- **Levenshtein distance:** Gunakan fungsi `levenshtein()` PHP native (ekstensi mbstring wajib aktif)
- **Mail driver:** `log` — tidak perlu SMTP; cukup pastikan `storage/logs/` writable
- **Queue (opsional):** Jika ingin kirim email async, gunakan `Mail::to(...)->queue(...)` dengan driver queue yang dikonfigurasi
- **File lampiran:** Disimpan di `storage/app/private/report-attachments/{report_id}/` — tidak bisa di-download langsung via URL publik

---

*\* Dokumen ini adalah spesifikasi fungsional. Implementasi kode dilakukan berdasarkan SPEC.md ini.*
