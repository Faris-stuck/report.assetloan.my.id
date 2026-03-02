# PERBAIKAN TOOLTIP DIAGRAM "STATUS PEMINJAMAN" - ADMIN DASHBOARD

**Status:** ✅ SELESAI  
**Tanggal:** 2 Maret 2026  
**File Modified:** `/admin/dashboard.html`  
**Scope:** Tooltip pada grafik batang "Status Peminjaman"

---

## 🎯 Tujuan

Mengubah tooltip dari **static format** menjadi **dynamic format** yang otomatis mengambil label status dari database.

---

## ❌ Masalah Sebelumnya

**Tooltip selalu menampilkan:**
```
Jumlah Peminjaman: 13 peminjaman
Jumlah Peminjaman: 5 peminjaman
Jumlah Peminjaman: 3 peminjaman
Jumlah Peminjaman: 2 peminjaman
```

Semua bar menampilkan teks yang sama, padahal seharusnya menampilkan **label status yang berbeda**.

---

## ✅ Solusi yang Diterapkan

### File: `/admin/dashboard.html`

**Lokasi:** Function `renderStatusChart(statusData)`, bagian tooltip configuration

**Sebelum:**
```javascript
tooltip: {
    y: { formatter: val => val + ' peminjaman' }
}
```

**Sesudah:**
```javascript
tooltip: {
    y: { 
        formatter: (val, { series, seriesIndex, dataPointIndex, w }) => {
            // Get category label from xaxis (Status label)
            const categories = w.config.xaxis.categories;
            const statusLabel = categories[dataPointIndex];
            // Return formatted tooltip: "Jumlah [Status]: [Count]"
            return `Jumlah ${statusLabel}: ${val}`;
        }
    }
}
```

---

## 📊 Hasil Akhir - Tooltip Dinamis

Sekarang ketika cursor diarahkan ke setiap bar, tooltip menampilkan:

| Bar | Tooltip |
|---|---|
| **Menunggu Persetujuan** | `Jumlah Menunggu Persetujuan: 2` |
| **Sedang Dipinjam** | `Jumlah Sedang Dipinjam: 5` |
| **Dikembalikan** | `Jumlah Dikembalikan: 13` |
| **Ditolak** | `Jumlah Ditolak: 3` |

Setiap tooltip **dinamis** berdasarkan category label yang berasal dari **database**.

---

## 🔍 Cara Kerja

### 1. Data Flow
```
Database peminjaman
    ↓
Query: COUNT(*) GROUP BY status
    ↓
API: /api/admin/dashboard-stats.php
    ↓
JSON Response dengan status counts
    ↓
JavaScript: statusData object
    ↓
Chart options: xaxis.categories = status labels
    ↓
Tooltip Formatter: Ambil category label + value
    ↓
Tooltip: "Jumlah [Status]: [Count]"
```

### 2. Tooltip Formatter Parameters

```javascript
formatter: (val, { series, seriesIndex, dataPointIndex, w }) => {
    // val = nilai bar yang di-hover (jumlah transaksi)
    // dataPointIndex = index bar yang di-hover
    // w.config.xaxis.categories = array label-label status
    // w.config.xaxis.categories[dataPointIndex] = label status bar yang di-hover
}
```

### 3. Build Tooltip String

```javascript
const categories = w.config.xaxis.categories;  // ['Menunggu Persetujuan', 'Sedang Dipinjam', ...]
const statusLabel = categories[dataPointIndex]; // 'Dikembalikan' (untuk bar ke-3)
return `Jumlah ${statusLabel}: ${val}`;          // 'Jumlah Dikembalikan: 13'
```

---

## 💡 Contoh Implementasi

### Input (dari database):
```json
{
  "menunggu_persetujuan": 2,
  "sedang_dipinjam": 5,
  "dikembalikan": 13,
  "ditolak": 3
}
```

### JavaScript Processing:
```javascript
const statusData = {
    'Menunggu Persetujuan': 2,
    'Sedang Dipinjam': 5,
    'Dikembalikan': 13,
    'Ditolak': 3
};

const labels = Object.keys(statusData);
// labels = ['Menunggu Persetujuan', 'Sedang Dipinjam', 'Dikembalikan', 'Ditolak']

const values = Object.values(statusData);
// values = [2, 5, 13, 3]

// Chart options xaxis.categories = labels
// Tooltip formatter menggunakan labels[dataPointIndex]
```

### Tooltip Output (saat di-hover):
- Bar 0: `Jumlah Menunggu Persetujuan: 2`
- Bar 1: `Jumlah Sedang Dipinjam: 5`
- Bar 2: `Jumlah Dikembalikan: 13`
- Bar 3: `Jumlah Ditolak: 3`

---

## ✨ Keunggulan Implementasi

✅ **Dinamis**: Tooltip label otomatis dari category xaxis  
✅ **Data-driven**: Tidak ada hardcoding teks  
✅ **Real-time**: Tooltip berubah otomatis jika data database berubah  
✅ **Clean Code**: Menggunakan ApexCharts built-in parameter  
✅ **Maintainable**: Mudah di-update atau dimodifikasi  
✅ **No Breaking Changes**: Chart dan rendering tetap normal  

---

## 🧪 Testing

### Manual Testing:
1. Login ke `/admin/dashboard.html` sebagai admin
2. Arahkan cursor ke grafik "Status Peminjaman"
3. Verifikasi setiap bar menampilkan tooltip yang berbeda dengan format:
   - `Jumlah [Status]: [Count]`
4. Cek 4 bar masing-masing memiliki tooltip yang sesuai

### Browser Developer Tools:
```javascript
// Di console, cek ApexCharts config
console.log(statusChartInstance.opts.xaxis.categories);
// Output: ['Menunggu Persetujuan', 'Sedang Dipinjam', 'Dikembalikan', 'Ditolak']
```

---

## 📚 Update di File

**File:** `/admin/dashboard.html`  
**Session:** 2 Maret 2026  
**Line Range:** ~670-688  
**Change Type:** Configuration update (tooltip formatter)

---

## 🎉 Implementation Complete

Tooltip pada grafik "Status Peminjaman" sekarang **menampilkan label status yang dinamis** berdasarkan data database, dengan format:

`Jumlah [Status]: [Count]`

Semua teks adalah **dinamis dari database**, bukan hardcoded.
