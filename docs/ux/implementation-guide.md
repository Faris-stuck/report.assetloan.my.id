---
domain: ux
purpose: implementation-guide
version: 1.0
updated: 2024-01-15
owner: design-team
status: stable
---

# UI/UX Implementation Guide - Developer Quick Start

Panduan cepat untuk developer yang ingin mengimplementasikan modal workflow dan search/filter pattern di halaman baru atau konversi halaman existing.

---

## Quick Links

- **UI/UX Standards**: `docs/UI_UX_STANDARDS.md` - Lengkap dengan design principles
- **Decision Log**: `docs/DECISIONS/UI_UX_CONSISTENCY_DECISION.md` - Kenapa standardisasi ini
- **Tasks**: `.kiro/specs/ui-ux-consistency-standards/tasks.md` - Implementation roadmap

---

## Template 1: Modal Edit Pattern (Copy-Paste)

### Blade View Template

```blade
@extends('layouts.app')
@section('title','Resource')
@section('content')

<div class="page-header">
    <h1 class="page-title h2">Manajemen Resource</h1>
    <p class="page-subtitle">Kelola resource dengan edit/delete via modal</p>
</div>

<!-- Search & Filter Card -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('admin.resource.index') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Cari berdasarkan nama..." value="{{ request('search') }}" maxlength="100">
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
    <form method="POST" action="{{ route('admin.resource.store') }}" class="row g-3 align-items-end">
        @csrf
        
        <div class="col-md-4">
            <label class="form-label required" for="name">Nama</label>
            <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror"
                   placeholder="Nama resource" value="{{ old('name') }}" required maxlength="150">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label class="form-label" for="description">Deskripsi</label>
            <input id="description" name="description" type="text" class="form-control"
                   placeholder="Opsional" value="{{ old('description') }}" maxlength="500">
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check">
                <input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                <label for="is_active" class="form-check-label">Aktif</label>
            </div>
        </div>

        <div class="col-md-3">
            <button type="submit" class="btn btn-laporin w-100">Tambah</button>
        </div>
    </form>
</div>

<!-- List with Modal Edit -->
<div x-data="{
    baseUrl: '{{ url('/admin/resource') }}',
    editingId: @js(old('edit_id') ? (int) old('edit_id') : null),
    name: @json(old('edit_id') ? old('name') : ''),
    description: @json(old('edit_id') ? old('description') : ''),
    is_active: @js(old('edit_id') ? (bool) old('is_active') : true),
    
    openEdit(resource) {
        this.editingId = resource.id;
        this.name = resource.name;
        this.description = resource.description ?? '';
        this.is_active = !!resource.is_active;
        $dispatch('open-modal', 'edit-resource');
    }
}">
    
    <div class="laporin-card">
        <!-- Results Info -->
        @if(request('search') || request('status'))
            <div class="mb-3 pb-3 border-bottom">
                <p class="text-muted small mb-0">
                    Menampilkan {{ $resources->count() }} dari {{ $resources->total() }} hasil
                    @if(request('search'))
                        untuk pencarian "<strong>{{ request('search') }}</strong>"
                    @endif
                </p>
            </div>
        @endif

        <!-- Table -->
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Deskripsi</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resources as $r)
                        <tr>
                            <td><strong>{{ $r->name }}</strong></td>
                            <td class="text-muted">{{ $r->description ? substr($r->description, 0, 50) . '...' : '-' }}</td>
                            <td>
                                <span class="badge {{ $r->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $r->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-laporin"
                                    x-on:click="openEdit(@js($r))">
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('admin.resource.destroy', $r) }}" 
                                      style="display:inline" onsubmit="return confirm('Hapus resource ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                Belum ada resource.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-3">
            {{ $resources->appends(request()->query())->links() }}
        </div>
    </div>

    <!-- Modal Edit Form -->
    <x-modal name="edit-resource" :show="old('edit_id') ? true : false" focusable>
        <form method="POST" x-bind:action="editingId ? baseUrl + '/' + editingId : '#'" class="p-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_id" x-bind:value="editingId">

            <!-- Modal Header Info -->
            <div class="mb-4">
                <h2 class="h5 fw-bold mb-1">Ubah Resource</h2>
                <p class="text-muted small mb-0">Perbarui data resource sesuai kebutuhan</p>
            </div>

            <!-- Global Error Alert -->
            @if(old('edit_id') && $errors->any())
                <div class="alert alert-danger mb-3" role="alert">
                    <strong>Error:</strong> Periksa kembali field yang wajib diisi.
                </div>
            @endif

            <!-- Form Fields -->
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label required" for="edit_name">Nama</label>
                    <input id="edit_name" name="name" type="text" x-model="name"
                           class="form-control @error('name') is-invalid @enderror"
                           placeholder="Nama resource" required maxlength="150">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="edit_description">Deskripsi</label>
                    <input id="edit_description" name="description" type="text" x-model="description"
                           class="form-control" placeholder="Opsional" maxlength="500">
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input id="edit_is_active" class="form-check-input" type="checkbox" 
                               name="is_active" value="1" x-model="is_active">
                        <label for="edit_is_active" class="form-check-label">Aktif</label>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <button type="button" class="btn btn-outline-secondary"
                        x-on:click="$dispatch('close-modal', 'edit-resource')">
                    Batal
                </button>
                <button type="submit" class="btn btn-laporin">
                    Simpan
                </button>
            </div>
        </form>
    </x-modal>

</div>

@endsection
```

### Controller Logic

```php
<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    // Display list with search/filter
    public function index(Request $request)
    {
        $query = Resource::query();

        // Search: across name and description
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Paginate with filter preserved
        $resources = $query->paginate(15);

        return view('admin.resource.index', compact('resources'));
    }

    // Store new resource
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:resources',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        Resource::create($validated);

        return redirect()->route('admin.resource.index')
                        ->with('status', 'Resource berhasil ditambah.');
    }

    // Update resource (via modal form)
    public function update(Request $request, Resource $resource)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:resources,name,' . $resource->id,
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $resource->update($validated);

        return redirect()->route('admin.resource.index')
                        ->with('status', 'Resource berhasil diperbarui.');
    }

    // Delete resource
    public function destroy(Resource $resource)
    {
        $resource->delete();

        return redirect()->route('admin.resource.index')
                        ->with('status', 'Resource berhasil dihapus.');
    }
}
```

---

## Template 2: Search/Filter Only (For existing modal pages)

Jika page sudah punya modal edit, tinggal tambahkan search/filter form:

```blade
<!-- Add this ABOVE the table/list -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('admin.existing-page.index') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Cari nama, email, dll..." value="{{ request('search') }}" maxlength="100">
        </div>

        <div class="col-md-6 col-lg-3">
            <label class="form-label" for="filter">Filter</label>
            <select id="filter" name="filter" class="form-select">
                <option value="">Semua</option>
                <option value="filter1" @selected(request('filter') === 'filter1')>Option 1</option>
                <option value="filter2" @selected(request('filter') === 'filter2')>Option 2</option>
            </select>
        </div>

        <div class="col-md-6 col-lg-3 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
            <a href="{{ route('admin.existing-page.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Then add to controller index() -->
// In Controller index() method:
$query = Model::query();

if ($search = request('search')) {
    $query->where('field1', 'like', "%{$search}%")
          ->orWhere('field2', 'like', "%{$search}%");
}

if ($filter = request('filter')) {
    $query->where('filter_field', $filter);
}

$items = $query->paginate(15);

// In pagination link:
{{ $items->appends(request()->query())->links() }}
```

---

## Common Patterns

### Pattern 1: Search + Single Filter

```blade
<!-- Controller -->
$query->where('name', 'like', "%{request('search')}%")
      ->where('category', request('category'));
```

### Pattern 2: Search + Multiple Filters (AND Logic)

```blade
<!-- Controller -->
$query->where('name', 'like', "%{request('search')}%")
      ->where('status', request('status'))
      ->where('role', request('role'));
```

### Pattern 3: Search + Date Range Filter

```blade
<!-- View -->
<div class="col-md-6 col-lg-2">
    <label class="form-label" for="from_date">Dari</label>
    <input id="from_date" name="from_date" type="date" class="form-control"
           value="{{ request('from_date') }}">
</div>
<div class="col-md-6 col-lg-2">
    <label class="form-label" for="to_date">Sampai</label>
    <input id="to_date" name="to_date" type="date" class="form-control"
           value="{{ request('to_date') }}">
</div>

<!-- Controller -->
if ($fromDate = request('from_date')) {
    $query->whereDate('created_at', '>=', $fromDate);
}
if ($toDate = request('to_date')) {
    $query->whereDate('created_at', '<=', $toDate);
}
```

### Pattern 4: Advanced Filter (Collapsible)

```blade
<div class="laporin-card mb-4" x-data="{ showAdvanced: false }">
    <!-- Basic Search -->
    <div class="row g-3 align-items-end mb-3">
        <div class="col-md-8">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Nama, email..." value="{{ request('search') }}">
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
            <div class="col-md-6">Filter 1</div>
            <div class="col-md-6">Filter 2</div>
        </div>
    </div>
</div>
```

---

## Checklist untuk New Page

### Frontend
- [ ] Create search/filter form dengan input + selects + buttons
- [ ] Create table dengan edit buttons (trigger modal)
- [ ] Create modal dengan form fields
- [ ] Add Alpine.js x-data dengan openEdit() method
- [ ] Test focus trap dalam modal
- [ ] Test keyboard navigation (Tab, Shift+Tab, Escape)

### Backend
- [ ] Add query parameters handling di controller index()
- [ ] Build dynamic query dengan filters
- [ ] Return paginated results dengan appends(query)
- [ ] Add validation di store/update methods
- [ ] Check database indexes untuk search fields

### Testing
- [ ] Search filters correctly
- [ ] Filters work individually and together
- [ ] Pagination preserves filters
- [ ] Modal opens/closes properly
- [ ] Form submission works
- [ ] Mobile responsive

---

## Troubleshooting

### Modal tidak membuka
```php
// Pastikan Alpine.js dispatch event benar:
$dispatch('open-modal', 'edit-resource');  // 'edit-resource' harus match modal name="{{ 'edit-resource' }}"
```

### Filter tidak di-preserve di pagination
```php
// Gunakan appends() saat render pagination:
{{ $items->appends(request()->query())->links() }}
// Bukan:
{{ $items->links() }}  // ❌ akan hilang filter
```

### Search tidak case-insensitive
```php
// MySQL/MariaDB case-insensitive by default, tapi gunakan LIKE untuk consistency:
$query->where('name', 'like', "%{$search}%");  // ✅ case-insensitive
```

### Focus tidak masuk modal saat dibuka
```php
// Pastikan x-modal component memiliki focusable attribute:
<x-modal name="edit-resource" focusable>  // ✅
// Dan komponen memiliki focus trap logic di x-init
```

---

## Resources

- **UI/UX_STANDARDS.md** - Full design guidelines
- **views/admin/users/index.blade.php** - Working example (modal + search)
- **components/modal.blade.php** - Modal component implementation
- **DESIGN.md** - Design tokens

---

## Quick Reference: Routes

Standard RESTful routes untuk resource management:

```php
Route::prefix('admin')->group(function () {
    Route::resource('resource', ResourceController::class);
    // Generates:
    // GET    /admin/resource           -> index (list + search/filter)
    // POST   /admin/resource           -> store (create)
    // GET    /admin/resource/{id}      -> show (optional)
    // PUT    /admin/resource/{id}      -> update (modal form)
    // DELETE /admin/resource/{id}      -> destroy (delete)
});
```

---

**Last Updated**: 2025  
**For Questions**: Lihat UI_UX_STANDARDS.md atau tanya di team
