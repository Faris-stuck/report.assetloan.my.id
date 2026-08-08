# UI/UX STANDARDS - Modal Workflow & Search/Filter Pattern

## Overview

Dokumen ini mendefinisikan standardisasi UI/UX untuk dua komponen kritis:
1. **Modal Workflow**: Pola konsisten untuk semua aksi edit/action
2. **Search/Filter**: Pola konsisten untuk tabel/card dengan banyak items

Tujuan: Konsistensi UX, kemudahan maintenance, dan accessible interface di semua halaman.

---

## Part 1: Modal Workflow Standards

### Prinsip

- **One Modal, One Action**: Setiap aksi (Edit, Delete Confirm, Create, dll) menggunakan modal yang focused.
- **Consistent Structure**: Semua modal memiliki header, body, dan footer yang sama.
- **Accessible**: Focus trap, keyboard navigation, ARIA labels.
- **Alpine.js State**: Gunakan Alpine.js untuk state management, bukan page reload untuk form edit.

### Modal Structure

```html
<!-- Modal Container -->
<x-modal name="edit-{resource}" :show="old('edit_{resource}_id') ? true : false" focusable>
    <!-- Modal Header -->
    <div class="modal-header border-bottom px-4 py-3">
        <h2 class="modal-title h5">Judul Aksi</h2>
        <p class="text-muted small mb-0">Helper text yang jelaskan konteks aksi</p>
    </div>

    <!-- Modal Body -->
    <form method="POST" action="{{ route('resource.update', $resource) }}" class="modal-body p-4">
        @csrf
        @method('PUT')
        
        <!-- Error Alert (jika ada) -->
        @if(old('edit_{resource}_id') && $errors->any())
            <div class="alert alert-danger" role="alert">
                Periksa kembali field yang wajib diisi.
            </div>
        @endif

        <!-- Form Fields -->
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="field_id">Label</label>
                <input id="field_id" name="field" type="text" 
                       class="form-control @error('field') is-invalid @enderror"
                       placeholder="Contoh atau hint" required maxlength="150">
                @error('field')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <!-- More fields as needed -->
        </div>
    </form>

    <!-- Modal Footer -->
    <div class="modal-footer border-top px-4 py-3">
        <button type="button" class="btn btn-outline-secondary" 
                x-on:click="$dispatch('close-modal', '{resource}')">
            Batal
        </button>
        <button type="submit" form="edit-{resource}-form" class="btn btn-laporin">
            Simpan
        </button>
    </div>
</x-modal>
```

### Alpine.js State Management

Untuk setiap modal edit, gunakan Alpine.js untuk state:

```javascript
x-data="{
    baseUrl: '{{ url('/admin/resource') }}',
    editingResourceId: @js(old('edit_resource_id') ? (int) old('edit_resource_id') : null),
    field1: @json(old('edit_resource_id') ? old('field1') : ''),
    field2: @json(old('edit_resource_id') ? old('field2') : false),
    
    // Method untuk membuka modal dengan data yang sudah ada
    openEdit(resource) {
        this.editingResourceId = resource.id;
        this.field1 = resource.field1;
        this.field2 = !!resource.field2;
        $dispatch('open-modal', 'edit-resource');
    },
    
    // Method untuk reset state (saat menutup modal)
    resetForm() {
        this.editingResourceId = null;
        this.field1 = '';
        this.field2 = false;
    }
}"
```

### Modal Trigger Button

Trigger button di tabel atau list harus konsisten:

```html
<button type="button" class="btn btn-sm btn-outline-laporin"
    x-on:click="openEdit(@js([ 'id' => $resource->id, 'field1' => $resource->field1, 'field2' => $resource->field2 ]))"
>
    Edit
</button>
```

### Accessibility Requirements

Modal HARUS memenuhi:

- **Focus Management**: 
  - Focus auto-move ke first focusable element (biasanya first input)
  - Tab & Shift+Tab cycle dalam modal (tidak keluar)
  - Escape key menutup modal
  
- **Labels & ARIA**:
  - Semua input memiliki `<label>` dengan `for` dan `id` yang match
  - Modal memiliki `role="alertdialog"` jika berisi warning/confirm
  - Button clear labeled (bukan hanya icon)

- **Error Handling**:
  - Error message langsung di bawah field (dalam `.invalid-feedback`)
  - Global error alert di atas form jika ada validation failure
  - `is-invalid` class pada field yang error

### Modal Animations

Gunakan Tailwind transitions yang konsisten (dari component/modal.blade.php):

```javascript
x-transition:enter="ease-out duration-150"
x-transition:enter-start="opacity-0 translate-y-2"
x-transition:enter-end="opacity-100 translate-y-0"
x-transition:leave="ease-in duration-100"
x-transition:leave-start="opacity-100 translate-y-0"
x-transition:leave-end="opacity-0 translate-y-2"
```

**Prinsip**: Smooth tapi cepat, tidak mengganggu fokus.

---

## Part 2: Search/Filter Pattern Standards

### Prinsip

- **Always Available**: Search/filter harus ada di halaman dengan 20+ items.
- **Consistent UI**: Search box + optional filters, consistent styling, consistent behavior.
- **Responsive**: Desktop: horizontal layout, Mobile: vertical/dropdown.
- **Performance**: Server-side filtering untuk scalability.
- **Pagination Aware**: Filter dan search bekerja bersama pagination.

### Search/Filter Container Structure

```html
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('admin.resource.index') }}" class="row g-3 align-items-end">
        <!-- Search Box -->
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text"
                   class="form-control"
                   placeholder="Cari berdasarkan nama, email, dll"
                   value="{{ request('search') }}"
                   maxlength="100">
            <small class="text-muted">Pencarian real-time: nama, email, atau identitas lainnya</small>
        </div>

        <!-- Filter 1: Status -->
        <div class="col-md-6 col-lg-3">
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                <option value="active" @selected(request('status') === 'active')>Aktif</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
            </select>
        </div>

        <!-- Filter 2: Role (jika applicable) -->
        <div class="col-md-6 col-lg-3">
            <label class="form-label" for="role">Peran</label>
            <select id="role" name="role" class="form-select">
                <option value="">Semua</option>
                @foreach($availableRoles as $role)
                    <option value="{{ $role }}" @selected(request('role') === $role)>
                        {{ str_replace('_', ' ', $role) }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Action Buttons -->
        <div class="col-md-6 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Filter</button>
            <a href="{{ route('admin.resource.index') }}" class="btn btn-outline-secondary">
                Reset
            </a>
        </div>
    </form>
</div>
```

### Search/Filter Query Parameters

Server HARUS handle query parameters:

```php
// Controller
$search = request('search');
$status = request('status');
$role = request('role');
$perPage = request('per_page', 15);

$query = Resource::query();

// Search
if ($search) {
    $query->where('name', 'like', "%{$search}%")
          ->orWhere('email', 'like', "%{$search}%");
}

// Status filter
if ($status === 'active') {
    $query->where('is_active', true);
} elseif ($status === 'inactive') {
    $query->where('is_active', false);
}

// Role filter
if ($role && in_array($role, User::ROLES)) {
    $query->where('role', $role);
}

$resources = $query->paginate($perPage);
```

### Results Display

Tampilkan hasil yang jelas:

```html
<!-- Results Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        @if(request('search') || request('status') || request('role'))
            <p class="text-muted small mb-0">
                Menampilkan {{ $resources->count() }} dari {{ $resources->total() }} hasil
                @if(request('search'))
                    untuk pencarian "<strong>{{ request('search') }}</strong>"
                @endif
            </p>
        @endif
    </div>
    
    <!-- Pagination Info -->
    <div class="text-muted small">
        Halaman {{ $resources->currentPage() }} dari {{ $resources->lastPage() }}
    </div>
</div>

<!-- Table -->
<div class="table-responsive">
    <table class="table">
        <!-- Header and body -->
    </table>
</div>

<!-- Pagination -->
<div class="mt-3">
    {{ $resources->appends(request()->query())->links() }}
</div>
```

### Advanced Filter Pattern (Optional)

Untuk halaman dengan filter kompleks, gunakan collapsible advanced filter:

```html
<div class="laporin-card mb-4">
    <div x-data="{ showAdvanced: false }">
        <!-- Basic Search -->
        <div class="row g-3 align-items-end mb-3">
            <div class="col-md-8">
                <label class="form-label" for="search">Cari</label>
                <input id="search" name="search" type="text" class="form-control"
                       placeholder="Nama, email, atau identitas..." value="{{ request('search') }}">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
                <button type="button" class="btn btn-outline-secondary"
                        x-on:click="showAdvanced = !showAdvanced">
                    Filter Lanjut
                </button>
            </div>
        </div>

        <!-- Advanced Filters (Collapsible) -->
        <div x-show="showAdvanced" class="border-top pt-3" x-transition>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="created_from">Dibuat dari</label>
                    <input id="created_from" name="created_from" type="date" class="form-control"
                           value="{{ request('created_from') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="created_to">Dibuat sampai</label>
                    <input id="created_to" name="created_to" type="date" class="form-control"
                           value="{{ request('created_to') }}">
                </div>
                <!-- More advanced filters -->
            </div>
        </div>
    </div>
</div>
```

### Mobile Responsiveness

- **Desktop (md)**: Horizontal layout dengan search box + 2-3 filters + buttons
- **Tablet (sm)**: Vertical stack dengan full-width inputs
- **Mobile**: Dropdown atau hidden filter panel

```html
<!-- Mobile-first: default vertical -->
<!-- md: horizontal via col-md-X -->
<div class="col-12 col-md-6 col-lg-4">
    <!-- Full width on mobile, proportional on desktop -->
</div>
```

### Performance Considerations

1. **Debounce Search**: Jika menggunakan AJAX search, gunakan 300-500ms debounce
2. **Server-side Pagination**: Jangan load semua data di client
3. **Index Database**: Column yang di-filter/search harus indexed
4. **Query Limit**: Max 100 items per page untuk performance

---

## Part 3: Pages to Standardize

### Priority 1: Core Admin Pages (MUST)

| Page | Type | Current | Action |
|------|------|---------|--------|
| `/admin/users` | Users | Modal ✅ | Keep as is + add search/filter |
| `/admin/master` | Master Data | Inline ❌ | Convert inline to modal + add search/filter |
| `/admin/qrcodes` | QR Codes | ? | Convert to modal + add search/filter |
| `/admin/audit` | Audit Log | ? | Add search/filter for review |

### Priority 2: Role Pages (SHOULD)

| Page | Type | Search Needed | Action |
|------|------|---------------|--------|
| `/kesiswaan/reports` | Report List | High volume | Add search/filter |
| `/sarpras/reports` | Report List | High volume | Add search/filter |

### Priority 3: Future Pages (NICE TO HAVE)

- Any new management page should follow these standards automatically

---

## Part 4: Implementation Checklist

### For Modal Implementation

- [ ] Create component or use x-modal blade component
- [ ] Define Alpine.js data object with all form fields
- [ ] Implement openEdit() method to populate form
- [ ] Handle CSRF token properly
- [ ] Add focus management (x-on:focus in modal component)
- [ ] Test keyboard navigation (Tab, Shift+Tab, Escape)
- [ ] Validate form client-side (required, pattern) and server-side
- [ ] Display errors in modal (not page reload)
- [ ] Test on mobile (modal should be responsive)

### For Search/Filter Implementation

- [ ] Add search input with placeholder text
- [ ] Define filters needed (status, role, date range, etc)
- [ ] Handle query parameters in controller
- [ ] Filter data server-side
- [ ] Display result count and pagination
- [ ] Add "Reset" button to clear filters
- [ ] Make form responsive (vertical on mobile, horizontal on desktop)
- [ ] Add helper text for search/filter inputs
- [ ] Test pagination with filters applied
- [ ] Test performance with large dataset (1000+ records)

---

## Part 5: Code Examples

### Complete Modal Edit Example

```blade
@extends('layouts.app')
@section('content')

<!-- Header -->
<div class="page-header">
    <h1 class="page-title h2">Manajemen Resource</h1>
</div>

<!-- Filter & Search Card -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('admin.resource.index') }}" class="row g-3">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" class="form-control"
                   value="{{ request('search') }}" maxlength="100">
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                <option value="active" @selected(request('status') === 'active')>Aktif</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
            </select>
        </div>
        <div class="col-md-6 col-lg-3 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Filter</button>
            <a href="{{ route('admin.resource.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Create Form Card -->
<div class="laporin-card mb-4">
    <h2 class="h5 fw-bold mb-3">Tambah Resource</h2>
    <form method="POST" action="{{ route('admin.resource.store') }}" class="row g-3">
        @csrf
        <div class="col-md-6">
            <label class="form-label required" for="name">Nama</label>
            <input id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <button class="btn btn-laporin">Tambah</button>
        </div>
    </form>
</div>

<!-- List with Modal Edit -->
<div x-data="{
    baseUrl: '{{ url('/admin/resource') }}',
    editingId: @js(old('edit_id') ? (int) old('edit_id') : null),
    name: @json(old('edit_id') ? old('name') : ''),
    is_active: @js(old('edit_id') ? (bool) old('is_active') : true),
    
    openEdit(resource) {
        this.editingId = resource.id;
        this.name = resource.name;
        this.is_active = !!resource.is_active;
        $dispatch('open-modal', 'edit-resource');
    }
}">
    <div class="laporin-card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resources as $r)
                        <tr>
                            <td>{{ $r->name }}</td>
                            <td><span class="badge {{ $r->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $r->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-laporin"
                                    x-on:click="openEdit(@js($r))">Edit</button>
                                <form method="POST" action="{{ route('admin.resource.destroy', $r) }}" onsubmit="return confirm('Hapus?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $resources->appends(request()->query())->links() }}</div>
    </div>

    <!-- Modal Edit -->
    <x-modal name="edit-resource" :show="old('edit_id') ? true : false" focusable>
        <form method="POST" x-bind:action="editingId ? baseUrl + '/' + editingId : '#'" class="p-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_id" x-bind:value="editingId">

            <div class="mb-3">
                <h2 class="h5">Ubah Resource</h2>
                <p class="text-muted small mb-0">Perbarui data resource sesuai kebutuhan</p>
            </div>

            @if(old('edit_id') && $errors->any())
                <div class="alert alert-danger mb-3">Periksa kembali field yang wajib diisi.</div>
            @endif

            <div class="mb-3">
                <label class="form-label required" for="edit_name">Nama</label>
                <input id="edit_name" name="name" x-model="name" class="form-control @error('name') is-invalid @enderror"
                       required maxlength="150">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input id="edit_is_active" class="form-check-input" type="checkbox" name="is_active" value="1" x-model="is_active">
                    <label for="edit_is_active" class="form-check-label">Aktif</label>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" x-on:click="$dispatch('close-modal', 'edit-resource')">Batal</button>
                <button type="submit" class="btn btn-laporin">Simpan</button>
            </div>
        </form>
    </x-modal>
</div>

@endsection
```

---

## Part 6: Migration Path

### Phase 1: Documentation (NOW)
- Publish this UI/UX Standards document
- Create component library examples

### Phase 2: Core Pages (WEEK 1-2)
- Migrate `/admin/master` from inline to modal + add search/filter
- Add search/filter to `/admin/users` (keep modal, add search/filter)
- Apply same pattern to `/admin/qrcodes` and `/admin/audit`

### Phase 3: Role Pages (WEEK 3)
- Add search/filter to `/kesiswaan/reports` and `/sarpras/reports`

### Phase 4: Ongoing
- All new pages MUST follow these standards
- Review existing pages during maintenance

---

## Part 7: Testing Checklist

### Modal Testing
- [ ] Modal opens with proper focus
- [ ] Tab navigation cycles within modal
- [ ] Escape key closes modal
- [ ] Form validation shows errors inline
- [ ] Submit updates data correctly
- [ ] Pagination works after update
- [ ] Mobile: modal responsive and readable

### Search/Filter Testing
- [ ] Search box filters results correctly
- [ ] Filter dropdown filters results correctly
- [ ] Multiple filters work together (AND logic)
- [ ] Reset button clears all filters
- [ ] Result count displays correctly
- [ ] Pagination works with filters applied
- [ ] Query parameters persist in URL
- [ ] Mobile: filters accessible and usable

---

## References

- **DESIGN.md**: Design tokens dan principles
- **CODING_STANDARDS.md**: PHP/Blade/JS standards
- **component/modal.blade.php**: Modal component implementation
- **views/admin/users/index.blade.php**: Reference modal implementation
