# UI/UX Quick Reference Card

**Developer Cheat Sheet** - Print this or keep in your IDE notes

---

## Modal Edit Pattern - In 5 Minutes

### 1. Blade View (x-data + form + table + modal)
```blade
<div x-data="{
    baseUrl: '{{ url('/admin/resource') }}',
    editingId: null,
    field: '',
    openEdit(item) { 
        this.editingId = item.id; 
        this.field = item.field; 
        $dispatch('open-modal', 'edit-resource'); 
    }
}">
    <!-- Search/Filter Form Here -->
    <!-- Table with Edit Buttons Here -->
    <!-- Modal Form Here: <x-modal name="edit-resource" focusable> -->
</div>
```

### 2. Controller
```php
public function index(Request $request) {
    $query = Resource::query();
    if ($search = request('search')) {
        $query->where('name', 'like', "%{$search}%");
    }
    $items = $query->paginate(15);
    return view('admin.resource.index', compact('items'));
}

public function update(Request $request, Resource $item) {
    $item->update($request->validate([...]));
    return redirect()->route(...)->with('status', 'Berhasil');
}
```

### 3. Test
- [ ] Modal opens on Edit click
- [ ] Form populates with existing data
- [ ] Tab cycles within modal
- [ ] Escape closes without saving
- [ ] Submit updates database

---

## Search/Filter Pattern - In 3 Steps

### Step 1: Add Form Above Table
```blade
<form method="GET" action="{{ route('...') }}" class="row g-3 align-items-end">
    <div class="col-md-4">
        <label for="search">Cari</label>
        <input name="search" value="{{ request('search') }}" 
               placeholder="Nama..." maxlength="100">
    </div>
    <div class="col-md-3">
        <label for="status">Status</label>
        <select name="status">
            <option value="">Semua</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
        </select>
    </div>
    <button type="submit" class="btn btn-laporin">Filter</button>
    <a href="{{ route(...) }}" class="btn btn-outline-secondary">Reset</a>
</form>
```

### Step 2: Update Controller Query
```php
public function index(Request $request) {
    $query = Resource::query();
    
    if ($search = request('search')) {
        $query->where('name', 'like', "%{$search}%");
    }
    
    if ($status = request('status')) {
        $query->where('is_active', $status === 'active');
    }
    
    $items = $query->paginate(15);
    return view('...', compact('items'));
}
```

### Step 3: Fix Pagination
```blade
<!-- WRONG ❌ -->
{{ $items->links() }}

<!-- RIGHT ✅ -->
{{ $items->appends(request()->query())->links() }}
```

---

## Modal Structure Template

```blade
<x-modal name="edit-resource" focusable>
    <form method="POST" action="{{ route(...) }}">
        @csrf
        @method('PUT')
        
        <!-- Header -->
        <div class="px-4 py-3 border-bottom">
            <h2 class="h5">Ubah Resource</h2>
            <p class="text-muted small">Helper text di sini</p>
        </div>

        <!-- Body -->
        <div class="p-4">
            @if($errors->any())
                <div class="alert alert-danger">Error: periksa field</div>
            @endif
            
            <div class="mb-3">
                <label class="form-label required" for="name">Nama</label>
                <input id="name" name="name" x-model="field" class="form-control">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <!-- Footer -->
        <div class="px-4 py-3 border-top d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-outline-secondary" 
                    x-on:click="$dispatch('close-modal', 'edit-resource')">
                Batal
            </button>
            <button type="submit" class="btn btn-laporin">Simpan</button>
        </div>
    </form>
</x-modal>
```

---

## Critical Details ⚠️

### For Modal to Work
✅ Blade component: `<x-modal name="xxx" focusable>`  
✅ Alpine.js: `openEdit(item)` method  
✅ Button: `x-on:click="openEdit(@js($item))"`  
✅ Form method: `PUT` for update  
✅ CSRF token: `@csrf` + `@method('PUT')`

### For Search/Filter to Work
✅ Form method: `GET` (not POST)  
✅ Controller: Handle `request('search')` + `request('status')`  
✅ Pagination: Always use `.appends(request()->query())`  
✅ Reset link: `<a href="{{ route(...) }}">Reset</a>` (no params)

### For Accessibility to Work
✅ Modal has `focusable` attribute  
✅ All inputs have `<label>` with `for` attribute  
✅ Error text in `.invalid-feedback` div  
✅ Inputs have `id` that matches `for` in label

---

## Copy-Paste Snippets

### Edit Button in Table
```blade
<button type="button" class="btn btn-sm btn-outline-laporin"
    x-on:click="openEdit(@js($item))">
    Edit
</button>
```

### Form Input with Error
```blade
<div class="mb-3">
    <label class="form-label required" for="name">Nama</label>
    <input id="name" name="name" type="text" 
           class="form-control @error('name') is-invalid @enderror"
           x-model="field" required maxlength="150">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
```

### Result Count Display
```blade
@if(request('search') || request('status'))
    <p class="text-muted small">
        Menampilkan {{ $items->count() }} dari {{ $items->total() }} hasil
    </p>
@endif
```

---

## Debugging Checklist

| Issue | Fix |
|-------|-----|
| Modal tidak buka | Check modal name match `$dispatch('open-modal', 'name')` |
| Filter tidak apply | Check controller: `request('search')`? |
| Pagination reset filter | Use `.appends(request()->query())` |
| Focus tidak ke input | Add `focusable` to modal component |
| Tab keluar dari modal | Check x-modal component has focus trap |
| Escape tidak tutup modal | Check x-modal component has escape handler |

---

## Files to Reference

| Need | File |
|------|------|
| Design guide | `docs/UI_UX_STANDARDS.md` |
| Impl examples | `docs/UI_UX_IMPLEMENTATION_GUIDE.md` |
| Full templates | `.kiro/specs/ui-ux-consistency-standards/tasks.md` |
| Working example | `resources/views/admin/users/index.blade.php` |
| Modal component | `resources/views/components/modal.blade.php` |

---

## Common Patterns

### Pattern 1: Basic Search + Status Filter
```php
if ($search = request('search')) {
    $query->where('name', 'like', "%{$search}%");
}
if ($status = request('status')) {
    $query->where('is_active', $status === 'active');
}
```

### Pattern 2: Multiple Filters (AND)
```php
if ($search = request('search')) { $query->where(...); }
if ($role = request('role')) { $query->where('role', $role); }
if ($status = request('status')) { $query->where(...); }
// All filters must match (AND logic)
```

### Pattern 3: Date Range Filter
```php
if ($from = request('from_date')) {
    $query->whereDate('created_at', '>=', $from);
}
if ($to = request('to_date')) {
    $query->whereDate('created_at', '<=', $to);
}
```

---

## Performance Tips

- Index search/filter columns: `$table->index('name')`
- Max items per page: 15-20 (not more than 100)
- Use `select()` to limit columns if needed
- Lazy load relationships with `with()`

---

## Testing Commands

```bash
# Run tests
php artisan test

# Test specific page
php artisan test --filter TestUserAdmin

# Run on Docker
npm run test:docker

# Build & check
npm run build && npm run lint
```

---

**Quick Ref Version 1.0 | 2025**

**Questions?** See full guide in `docs/UI_UX_IMPLEMENTATION_GUIDE.md`
