# LAPORIN - Quick Reference Checklist

**Use this document for daily development to ensure consistency.**

---

## ✅ BEFORE ADDING A NEW PAGE

- [ ] Page extends `layouts.app`
- [ ] Page has `@section('title', 'Page Title')`
- [ ] Page has page header: `.page-header` with kicker, title, subtitle
- [ ] All modals use `<x-modal>` component, NOT custom implementations
- [ ] All buttons follow button styling guide (see below)
- [ ] All forms follow form styling guide (see below)
- [ ] All tables use `.table-responsive` wrapper
- [ ] All search/filter forms follow standard pattern
- [ ] All pagination uses `.appends(request()->query())`
- [ ] Page tested on mobile, tablet, desktop
- [ ] All form inputs have associated labels with `for` attribute
- [ ] All required fields marked with `required` attribute and class

---

## ✅ BUTTON STYLING CHECKLIST

### Primary Action (Green)
```blade
<button class="btn btn-laporin">Action Label</button>
```
**Usage:** Main submit, save, create actions  
**Color:** Green gradient with shadow  
**Size:** Default or `btn-sm` for table actions

### Secondary Action (Gray)
```blade
<button class="btn btn-outline-secondary">Cancel</button>
<!-- OR for links -->
<a href="{{ route('...') }}" class="btn btn-outline-secondary">Reset</a>
```
**Usage:** Cancel, reset, back navigation  
**Color:** Gray outline  
**Size:** Match primary button

### Danger Action (Red)
```blade
<form method="POST" action="{{ route('...') }}" onsubmit="return confirm('Confirm?')">
    @csrf
    @method('DELETE')
    <button class="btn btn-outline-danger">Delete</button>
</form>
```
**Usage:** Delete, reject, destructive actions  
**Color:** Red outline  
**Features:** Must include confirmation dialog

### Form Button Layout
```blade
<!-- Single button -->
<button class="btn btn-laporin w-100">Save</button>

<!-- Multiple buttons -->
<div class="d-flex justify-content-end gap-2">
    <button type="button" class="btn btn-outline-secondary">Cancel</button>
    <button type="submit" class="btn btn-laporin">Save</button>
</div>
```

### Button Sizes
| Class | Usage |
|-------|-------|
| `.btn` (default) | Main form actions, modals, large buttons |
| `.btn-sm` | Table actions (edit, delete), inline buttons |
| `.w-100` | Full width buttons (usually on mobile) |
| `.flex-grow-1` | In flex container, grows to available space |

---

## ✅ FORM STYLING CHECKLIST

### Form Container
```blade
<div class="laporin-card">
    <form method="POST" action="{{ route('...') }}" class="row g-3">
        @csrf
        <!-- form fields -->
    </form>
</div>
```

### Single Field
```blade
<div class="col-md-6 col-lg-4">
    <label class="form-label required" for="field_name">Field Label</label>
    <input id="field_name" name="field_name" type="text" class="form-control @error('field_name') is-invalid @enderror" 
           value="{{ old('field_name') }}" required maxlength="100" placeholder="Placeholder text">
    @error('field_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
```

### Select Field
```blade
<div class="col-md-6 col-lg-4">
    <label class="form-label" for="status">Status</label>
    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="">Select option</option>
        @foreach($options as $option)
            <option value="{{ $option->id }}" @selected(old('status') == $option->id)>{{ $option->name }}</option>
        @endforeach
    </select>
    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
```

### Textarea Field
```blade
<div class="col-12">
    <label class="form-label" for="description">Description</label>
    <textarea id="description" name="description" class="form-control" maxlength="2000" 
              placeholder="Optional details">{{ old('description') }}</textarea>
</div>
```

### Checkbox Field
```blade
<div class="col-12">
    <div class="form-check">
        <input id="is_active" type="checkbox" class="form-check-input" name="is_active" 
               value="1" @checked(old('is_active', true))>
        <label for="is_active" class="form-check-label">Mark as active</label>
    </div>
</div>
```

### Responsive Column Sizing
| Screen | Column Class | Width |
|--------|--------------|-------|
| Mobile | `col-12` | Full width |
| Tablet (768px+) | `col-md-6` | Half width |
| Desktop (1200px+) | `col-lg-3` | Quarter width |
| Wide forms | `col-md-6 col-lg-4` | Half mobile, third desktop |

---

## ✅ SEARCH & FILTER FORM CHECKLIST

### Standard Search Form
```blade
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('page.index') }}" class="row g-3 align-items-end">
        <!-- Search input -->
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Search</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Search by name or email..." value="{{ request('search') }}" maxlength="100">
        </div>

        <!-- Filter 1 -->
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">All</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>

        <!-- Filter 2 -->
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="role">Role</label>
            <select id="role" name="role" class="form-select">
                <option value="">All</option>
                @foreach($roles as $r)
                    <option value="{{ $r }}" @selected(request('role') === $r)>{{ $r }}</option>
                @endforeach
            </select>
        </div>

        <!-- Buttons -->
        <div class="col-md-6 col-lg-4 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Search</button>
            <a href="{{ route('page.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>
```

### Key Points
- ✅ Always use `method="GET"`
- ✅ Use `col-md-6 col-lg-X` for responsive layout
- ✅ Use `class="row g-3 align-items-end"` for alignment
- ✅ Preserve values with `value="{{ request('field') }}"`
- ✅ Use `@selected()` for select dropdowns
- ✅ Reset button links to clean route
- ✅ Submit button uses `.btn-laporin`
- ✅ Reset button uses `.btn-outline-secondary`

---

## ✅ MODAL CHECKLIST

### Opening a Modal
```blade
<!-- In page x-data -->
<div x-data="{
    editingId: null,
    editData: {},
    
    openEdit(item) {
        this.editingId = item.id;
        this.editData = item;
        $dispatch('open-modal', 'edit-item');
    }
}">
    <!-- Button to open -->
    <button type="button" class="btn btn-sm btn-outline-laporin"
        x-on:click="openEdit(@js($item))">
        Edit
    </button>

    <!-- Modal component -->
    <x-modal name="edit-item" :show="old('edit_id') ? true : false" focusable>
        <form method="POST" x-bind:action="editingId ? baseUrl + '/' + editingId : '#'" class="p-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_id" x-bind:value="editingId">

            <!-- Header -->
            <div class="mb-4">
                <h2 class="h5 fw-bold mb-1">Edit Item</h2>
                <p class="text-muted small mb-0">Update the details below</p>
            </div>

            <!-- Error Alert -->
            @if(old('edit_id') && $errors->any())
                <div class="alert alert-danger mb-3">Please check the required fields.</div>
            @endif

            <!-- Form Fields -->
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label required" for="edit_name">Name</label>
                    <input id="edit_name" name="name" x-model="editData['name']" 
                           class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <button type="button" class="btn btn-outline-secondary"
                        x-on:click="$dispatch('close-modal', 'edit-item')">
                    Cancel
                </button>
                <button type="submit" class="btn btn-laporin">
                    Save
                </button>
            </div>
        </form>
    </x-modal>
</div>
```

### Modal Key Points
- ✅ Use `<x-modal name="identifier" focusable>`
- ✅ Pass `show` prop to control initial state
- ✅ Always include header with title + description
- ✅ Add error alert if validation fails
- ✅ Use `.p-4` for padding inside modal
- ✅ Use x-bind for dynamic action URL
- ✅ Use x-model for form field binding
- ✅ Footer buttons: Batal (close) + Simpan (submit)
- ✅ Escape key and outside click automatically close modal

---

## ✅ TABLE DISPLAY CHECKLIST

### Complete Table Example
```blade
<!-- Results info (optional, when filtered) -->
@if(request('search') || request('status'))
    <div class="mb-3 pb-3 border-bottom">
        <p class="text-muted small mb-0">
            Menampilkan {{ $items->count() }} dari {{ $items->total() }} hasil
        </p>
    </div>
@endif

<!-- Table -->
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Email</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td><strong>{{ $item->name }}</strong></td>
                    <td><span class="badge text-bg-success">{{ $item->status }}</span></td>
                    <td>{{ $item->email }}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-laporin">Edit</button>
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">No items found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="mt-3">{{ $items->appends(request()->query())->links() }}</div>
```

### Key Points
- ✅ Wrap with `.table-responsive` for mobile scrolling
- ✅ Use `@forelse` to handle empty state
- ✅ Show results count when filters active
- ✅ Use `.text-end` for right-aligned columns (actions, amounts)
- ✅ Use `.badge .text-bg-{color}` for status display
- ✅ Actions in last column, right-aligned
- ✅ Use `.appends(request()->query())` for pagination
- ✅ Empty state in colspan across all columns

---

## ✅ STATUS BADGE COLORS

| Status | CSS Class | Color | Usage |
|--------|-----------|-------|-------|
| menunggu_verifikasi | `.status-menunggu_verifikasi` | Yellow | Waiting for review |
| memerlukan_informasi | `.status-memerlukan_informasi` | Purple | Needs more info |
| dibuka_kembali | `.status-dibuka_kembali` | Purple | Reopened |
| sedang_ditangani | `.status-sedang_ditangani` | Blue | In progress |
| menunggu_konfirmasi | `.status-menunggu_konfirmasi` | Purple | Awaiting approval |
| selesai | `.status-selesai` | Green | Completed |
| ditolak | `.status-ditolak` | Red | Rejected |

**Usage:**
```blade
<span class="badge text-bg-success">Selesai</span>
<!-- OR -->
<span class="status-pill status-selesai">Selesai</span>
```

---

## ✅ RESPONSIVE BREAKPOINTS GUIDE

```blade
<!-- Mobile First: Build for mobile, enhance for desktop -->

<!-- Single column on mobile, two on tablet, three on desktop -->
<div class="col-md-6 col-lg-4">Content</div>

<!-- Hide on mobile, show on tablet+ -->
<div class="d-none d-md-block">Visible on tablet+</div>

<!-- Full width on mobile, half on tablet -->
<div class="col-md-6">Content</div>

<!-- Gap/spacing that changes -->
<div class="row g-2 g-lg-3">Items with smaller gap on mobile</div>
```

### Common Breakpoints
| Class | Screen Size | Usage |
|-------|-------------|-------|
| `col-12` | All | Full width |
| `col-md-6` | 768px+ | Half width on tablet/desktop |
| `col-lg-4` | 1200px+ | Third width on desktop |
| `d-md-block` | 768px+ | Hide on mobile |
| `g-3` | All | Gap size |
| `g-lg-4` | 1200px+ | Larger gap on desktop |

---

## ✅ FORM VALIDATION CHECKLIST

### HTML5 Validation Attributes
```blade
<!-- Required field -->
<input name="email" required>

<!-- Email validation -->
<input name="email" type="email">

<!-- Password: min 8 chars, letters + numbers -->
<input name="password" type="password" minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}">

<!-- Text: max 150 characters -->
<input name="name" type="text" maxlength="150">

<!-- Phone: allow digits, +, -, (), spaces -->
<input name="phone" type="tel" pattern="[0-9+() .-]+">

<!-- Number: between 1-100 -->
<input name="priority" type="number" min="1" max="100">

<!-- Date: no past dates -->
<input name="date" type="date" min="{{ today() }}">

<!-- DateTime: no past dates/times -->
<input name="datetime" type="datetime-local" min="{{ now()->format('Y-m-d\TH:i') }}">
```

### Server-Side Validation Display
```blade
<!-- Field-level error -->
<input class="form-control @error('email') is-invalid @enderror">
@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror

<!-- Form-level errors -->
@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </div>
@endif
```

---

## ✅ ACCESSIBILITY CHECKLIST

- [ ] All `<input>` have associated `<label>` with matching `for`/`id`
- [ ] All form `@error()` messages are visible and descriptive
- [ ] All buttons have descriptive text (not just icons)
- [ ] All required fields marked with `required` attribute
- [ ] Color not used alone to convey information (use icons/text too)
- [ ] Links are distinguishable (underline or color change on hover)
- [ ] Modal can be closed with Escape key
- [ ] Modal has focus trap (Tab cycles through focusables)
- [ ] Images have descriptive alt text (if any)
- [ ] Page has descriptive title in `@section('title')`

---

## ✅ COMMON MISTAKES TO AVOID

| Mistake | ✅ Correct |
|---------|----------|
| Using custom modal instead of `<x-modal>` | Always use `<x-modal>` component |
| Forgetting `@csrf` in forms | Always include `@csrf` in POST/PUT/DELETE |
| Not preserving filters in pagination | Use `.appends(request()->query())` |
| Using different button colors for same action type | Use consistent button classes |
| Empty state only in "no results" not in empty list | Show empty state even with filters |
| Using old() values without checking | Always use `value="{{ old('field') }}"` |
| Inconsistent form field spacing | Use `class="row g-3"` consistently |
| Form submit button not full width on mobile | Use `w-100` or `flex-grow-1` |
| Modal without focusable attribute | Always add `focusable` to modals |
| Not showing validation errors in modals | Always display `@error()` messages |
| Forgetting `@method('PUT')` for updates | Include for non-GET forms |
| Table not responsive on mobile | Wrap with `<div class="table-responsive">` |

---

## ✅ QUICK COPYPASTA SNIPPETS

### Minimal Page Template
```blade
@extends('layouts.app')
@section('title','Page Name')
@section('content')

<div class="page-header">
    <div>
        <span class="page-kicker">Module</span>
        <h1 class="page-title h2 mt-2">Page Title</h1>
        <p class="page-subtitle">Descriptive subtitle here.</p>
    </div>
</div>

<div class="laporin-card">
    <!-- Content -->
</div>

@endsection
```

### Minimal Modal Template
```blade
<x-modal name="my-modal" focusable>
    <form method="POST" action="{{ route('...') }}" class="p-4">
        @csrf
        <div class="mb-4">
            <h2 class="h5 fw-bold">Modal Title</h2>
            <p class="text-muted small">Description</p>
        </div>
        
        <div class="row g-3 mb-3">
            <div class="col-12">
                <label class="form-label required" for="field">Field</label>
                <input id="field" name="field" class="form-control" required>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <button type="button" class="btn btn-outline-secondary" x-on:click="$dispatch('close-modal', 'my-modal')">Cancel</button>
            <button type="submit" class="btn btn-laporin">Save</button>
        </div>
    </form>
</x-modal>
```

### Minimal Search Form
```blade
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('page.index') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Search</label>
            <input id="search" name="search" type="text" class="form-control" 
                   value="{{ request('search') }}" maxlength="100">
        </div>
        <div class="col-md-6 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Search</button>
            <a href="{{ route('page.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>
```

---

## 📞 NEED HELP?

Refer to these files for detailed information:
- **Audit Report:** `AUDIT_REPORT.md`
- **Technical Details:** `TECHNICAL_FINDINGS.md`
- **Action Plan:** `FIXES_ACTION_PLAN.md`
- **Design System:** `docs/DESIGN.md`

---

**Last Updated:** 2024  
**Status:** Ready to use  
**Version:** 1.0
