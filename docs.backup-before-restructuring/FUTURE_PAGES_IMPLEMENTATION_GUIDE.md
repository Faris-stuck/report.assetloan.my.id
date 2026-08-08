# Panduan Implementasi Halaman Baru

Dokumen ini menjelaskan pattern konsisten untuk mengembangkan halaman baru di LAPORIN, terutama untuk halaman dengan daftar data yang memerlukan pencarian dan filter.

## Keputusan Design: Modal vs Card vs Table

### Kapan Menggunakan Modal
- **Edit/Action**: Semua interaksi edit atau delete HARUS menggunakan modal, bukan halaman terpisah
- **Quick Actions**: Konfirmasi atau input cepat (contoh: reject dengan alasan singkat)
- **Responsive**: Modal responsive di semua ukuran layar (Bootstrap `modal-fullscreen-sm-down`)
- **Focus Management**: Modal menangkap focus (trap), pengguna tidak bisa bypass ke konten di belakang

**Contoh**: Edit pengguna, hapus item, reject laporan

### Kapan Menggunakan Card-Based List
- **Report Pages**: Halaman role-specific (Kesiswaan, Sarpras) yang menampilkan data dengan forms terkait
- **Form Inline**: Setiap card berisi form action langsung, bukan perlu ke halaman lain
- **Status-Dependent**: Tampilan form berubah sesuai status report (contoh: "Perlu Diproses" vs "Selesai")
- **Mobile Friendly**: Cards stack vertikal di mobile, lebih mudah diklik

**Contoh**: `/kesiswaan/index`, `/sarpras/index`, `/admin/reports`

### Kapan Menggunakan Table
- **Admin Pages**: Manajemen data yang bersifat CRUD standar
- **Data Intensive**: Banyak kolom/field, butuh overview cepat
- **Consistent Actions**: Semua baris punya action yang sama (edit, delete)
- **Sortable**: Kolom bisa diurutkan (future enhancement)

**Contoh**: `/admin/users`, `/admin/violation-types`, `/admin/locations`

---

## Checklist Standar untuk Setiap Halaman Baru

### Phase 1: Planning
- [ ] Tentukan tipe list: modal, card, atau table?
- [ ] Identifikasi search fields (field mana saja bisa dicari?)
  - Contoh: report_number, title, description, name, email
- [ ] Identifikasi filter fields (filter apa saja yang tersedia?)
  - Contoh: status, priority, date_range, category, assigned_to
- [ ] Tentukan relasi: ada field yang join table lain?
  - Contoh: priority dari `damage_detail` table, bukan dari `reports`

### Phase 2: Database & Model
- [ ] Pastikan model punya fillable fields yang tepat
- [ ] Pastikan relasi Eloquent sudah didefinisikan (BelongsTo, HasOne, HasMany)
- [ ] Untuk fields di related table, siapkan subquery atau `with()` eager loading

### Phase 3: Controller/Service
- [ ] Build base query: `Report::where('type', 'xxx')`
- [ ] Implement search: `where('field', 'like', "%{search}%")`
- [ ] Implement filter: validate value, kemudian `where('status', $status)`
- [ ] Untuk filter relasi: gunakan `whereHas('relation', function(...))`
- [ ] Validate input: hanya terima nilai yang diizinkan (allowlist)
- [ ] Paginate dengan `paginate(15)` untuk cards atau `paginate(25)` untuk tables

### Phase 4: View
- [ ] Buat search/filter form di atas list
- [ ] Gunakan Bootstrap grid (`col-md-6 col-lg-2`) untuk responsive form
- [ ] Preserve filter state: `value="{{ request('field') }}"`
- [ ] Pagination harus append query params: `.appends(request()->query())->links()`
- [ ] Show results count: "Menampilkan X dari Y hasil"
- [ ] Empty state: "Belum ada data..." jika tidak ada hasil

### Phase 5: Testing
- [ ] Search filters data correctly (test dengan multiple fields)
- [ ] Filter works individually (test setiap filter sendiri)
- [ ] Combined filters work (test search + filter + date range)
- [ ] Pagination preserves filters (check query string di pagination links)
- [ ] Invalid filter values are ignored (tidak crash, tidak show wrong data)
- [ ] Empty results show proper message
- [ ] Mobile responsive (test di < 768px)

---

## Template: Card-Based List + Search/Filter

Gunakan template ini untuk halaman seperti `/kesiswaan/index` dan `/sarpras/index`.

### Controller/Service Pattern

```php
public function index(): View
{
    $query = Report::where('report_type', 'violation');

    // Search across multiple fields
    if ($search = request('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('report_number', 'like', "%{$search}%")
              ->orWhere('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Status filter - validate against allowlist
    $allowedStatuses = ['menunggu_verifikasi', 'memerlukan_informasi', 'dibuka_kembali', 'sedang_ditangani', 'menunggu_konfirmasi', 'selesai', 'ditolak'];
    if ($status = request('status')) {
        if (in_array($status, $allowedStatuses, true)) {
            $query->where('status', $status);
        }
    }

    // Date range filter
    if ($from_date = request('from_date')) {
        $query->whereDate('created_at', '>=', $from_date);
    }

    if ($to_date = request('to_date')) {
        $query->whereDate('created_at', '<=', $to_date);
    }

    return view('role.index', [
        'reports' => $query->latest()->paginate(15),
        // ... other data
    ]);
}
```

### View Pattern

```blade
<!-- Page Header -->
<div class="page-header">
    <div>
        <span class="page-kicker">Role Name</span>
        <h1 class="page-title h2 mt-2">Page Title</h1>
        <p class="page-subtitle">Subtitle atau deskripsi halaman</p>
    </div>
</div>

<!-- Search & Filter Form -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('role.index') }}" class="row g-3 align-items-end">
        <!-- Search Field -->
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Cari nomor atau judul..." value="{{ request('search') }}" maxlength="100">
        </div>

        <!-- Status Filter -->
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                <option value="menunggu_verifikasi" @selected(request('status') === 'menunggu_verifikasi')>Menunggu Verifikasi</option>
                <option value="selesai" @selected(request('status') === 'selesai')>Selesai</option>
                <!-- ... other options -->
            </select>
        </div>

        <!-- Date Range -->
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="from_date">Dari</label>
            <input id="from_date" name="from_date" type="date" class="form-control" value="{{ request('from_date') }}">
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="to_date">Sampai</label>
            <input id="to_date" name="to_date" type="date" class="form-control" value="{{ request('to_date') }}">
        </div>

        <!-- Action Buttons -->
        <div class="col-md-6 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
            <a href="{{ route('role.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Results Info (only show if filters are active) -->
@if(request('search') || request('status') || request('from_date') || request('to_date'))
    <div class="laporin-card mb-3 pb-3 border-bottom">
        <p class="text-muted small mb-0">
            Menampilkan {{ $items->count() }} dari {{ $items->total() }} hasil
            @if(request('search'))
                untuk pencarian "<strong>{{ request('search') }}</strong>"
            @endif
        </p>
    </div>
@endif

<!-- Content List -->
<div class="report-card-list">
    @forelse($items as $item)
        <!-- Card/Row Content -->
    @empty
        <div class="laporin-card text-center py-5 text-muted">Belum ada data.</div>
    @endforelse
</div>

<!-- Pagination with Preserved Filters -->
<div class="mt-3">{{ $items->appends(request()->query())->links() }}</div>
```

---

## Template: Table-Based List + Search/Filter

Gunakan template ini untuk halaman admin seperti `/admin/users`, `/admin/violation-types`.

### Controller Pattern

```php
public function index(): View
{
    $query = User::where('role', 'kesiswaan');

    if ($search = request('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    $allowedRoles = ['kesiswaan', 'sarpras', 'wali_kelas'];
    if ($role = request('role')) {
        if (in_array($role, $allowedRoles, true)) {
            $query->where('role', $role);
        }
    }

    if ($status = request('status')) {
        $query->where('is_active', $status === 'active');
    }

    return view('admin.users.index', [
        'users' => $query->latest()->paginate(25),
    ]);
}
```

### View Pattern

```blade
<!-- Search & Filter -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Cari nama atau email..." value="{{ request('search') }}" maxlength="100">
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="role">Peran</label>
            <select id="role" name="role" class="form-select">
                <option value="">Semua</option>
                @foreach($roles as $r)
                    <option value="{{ $r }}" @selected(request('role') === $r)>{{ str_replace('_', ' ', $r) }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6 col-lg-4 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Results Info -->
@if(request('search') || request('role') || request('status'))
    <div class="mb-3 pb-3 border-bottom">
        <p class="text-muted small mb-0">
            Menampilkan {{ $users->count() }} dari {{ $users->total() }} hasil
        </p>
    </div>
@endif

<!-- Table -->
<div class="laporin-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Kolom 1</th>
                    <th>Kolom 2</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td><!-- Actions --></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center py-4 text-muted">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<div class="mt-3">{{ $users->appends(request()->query())->links() }}</div>
```

---

## Pattern: Filter Relasi (Contoh: Priority dari DamageDetail)

Ketika filter mereferensikan field di related table:

```php
// Model Relationship
class Report extends Model
{
    public function damageDetail(): HasOne
    {
        return $this->hasOne(DamageDetail::class);
    }
}

// Controller: Filter by related table field
$allowedPriorities = ['rendah', 'sedang', 'tinggi', 'darurat'];
if ($priority = request('priority')) {
    if (in_array($priority, $allowedPriorities, true)) {
        $query->whereHas('damageDetail', function ($q) use ($priority) {
            $q->where('priority', $priority);
        });
    }
}
```

---

## Pattern: Date Range Filter

### Single Date
```php
if ($date = request('date')) {
    $query->whereDate('created_at', $date);
}
```

### Date Range
```php
if ($from_date = request('from_date')) {
    $query->whereDate('created_at', '>=', $from_date);
}

if ($to_date = request('to_date')) {
    $query->whereDate('created_at', '<=', $to_date);
}
```

### DateTime Range (dengan waktu)
```php
if ($from_datetime = request('from_datetime')) {
    $query->where('created_at', '>=', $from_datetime);
}

if ($to_datetime = request('to_datetime')) {
    $query->where('created_at', '<=', Carbon::parse($to_datetime)->endOfDay());
}
```

---

## Kesalahan Umum Yang Harus Dihindari

### ❌ Tidak Validate Filter Value
```php
// SALAH - bisa accept nilai arbitrary
$query->where('status', request('status'));
```

✅ **BENAR** - validate terlebih dahulu
```php
$allowed = ['menunggu_verifikasi', 'selesai', 'ditolak'];
if ($status = request('status')) {
    if (in_array($status, $allowed, true)) {
        $query->where('status', $status);
    }
}
```

### ❌ Forget appends() pada Pagination
```blade
<!-- SALAH - filter params hilang saat pagination -->
{{ $items->links() }}
```

✅ **BENAR** - append query string
```blade
{{ $items->appends(request()->query())->links() }}
```

### ❌ Search pada Relasi Tanpa `with()`
```php
// SALAH - N+1 query problem
$query->whereHas('user', function ($q) use ($search) {
    $q->where('name', 'like', "%{$search}%");
});
```

✅ **BENAR** - eager load relasi
```php
$query->with('user')
      ->whereHas('user', function ($q) use ($search) {
          $q->where('name', 'like', "%{$search}%");
      });
```

### ❌ Tidak Preserve Filter State di View
```blade
<!-- SALAH - filter tidak ter-select saat user kembali -->
<input name="search" type="text" placeholder="Cari...">
<select name="status">
    <option value="selesai">Selesai</option>
</select>
```

✅ **BENAR** - preserve value dengan request()
```blade
<input name="search" type="text" placeholder="Cari..." value="{{ request('search') }}">
<select name="status">
    <option value="selesai" @selected(request('status') === 'selesai')>Selesai</option>
</select>
```

### ❌ Form Action Tidak Sesuai
```blade
<!-- SALAH - action ke halaman lain -->
<form action="{{ route('reports.search') }}">
```

✅ **BENAR** - action ke halaman yang sama
```blade
<form method="GET" action="{{ route('reports.index') }}">
```

---

## Testing Checklist

Untuk setiap halaman baru dengan search/filter:

- [ ] Search filters correctly (1 result when searching specific term)
- [ ] Search is case-insensitive (search "LAPOR" = search "lapor")
- [ ] Search across multiple fields works (search finds matches in title, description, etc)
- [ ] Empty search shows all items
- [ ] Each filter works individually
- [ ] Combined filters work (search + status + date range all apply)
- [ ] Invalid filter values are silently ignored (doesn't crash)
- [ ] Pagination shows correct page size (15 untuk cards, 25 untuk tables)
- [ ] Pagination links preserve filters (check query string in href)
- [ ] Reset button goes to clean URL without params
- [ ] Results count shows "X dari Y hasil"
- [ ] Empty state shows "Belum ada..." message
- [ ] Mobile responsive (<768px filters stack vertically)
- [ ] No SQL errors in logs
- [ ] No console errors (F12 Developer Tools)

---

## Reference Implementation

Lihat contoh implementasi:
- **Card-Based**: `/kesiswaan/index`, `/sarpras/index`
- **Table-Based**: `/admin/users/index`, `/admin/violation-types/index`
- **Controller**: `app/Services/Role/Kesiswaan/KesiswaanService.php`
- **Tests**: `tests/Feature/KesiswaanReportFilterTest.php`

---

## FAQ

### Berapa item per halaman?
- Cards: 15 items (lebih compact)
- Tables: 25 items (lebih dapat ditampilkan)
- Adjust sesuai UX needs

### Search harus real-time atau submit form?
- LAPORIN menggunakan **submit form** (tidak real-time)
- User klik tombol "Cari" untuk filter
- Lebih efisien, lebih mudah testing, consistent UX

### Bagaimana dengan filter kompleks (multiple checkboxes)?
- Status: gunakan dropdown (bukan checkbox) - lebih praktis
- Categories: gunakan dropdown multi-select atau multiple checkboxes
- Priority: gunakan dropdown (1 value)
- Tags: gunakan checkboxes (multiple values)

### Apakah field pencarian case-sensitive?
- Tidak, gunakan `like` pattern matching (ILIKE untuk PostgreSQL)
- Laravel handle ini secara default

### Apakah perlu loading state saat filter?
- Tidak (form submit biasa, tidak AJAX)
- Page reload saat filter applied

---

Untuk pertanyaan atau revisi, hubungi tim development.
