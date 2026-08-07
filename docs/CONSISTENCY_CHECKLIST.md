# UI/UX Consistency Checklist for Future Pages

Use this checklist when creating new pages or modifying existing ones to ensure consistency with LAPORIN design standards.

---

## Pre-Development: Read These First

1. ✅ Review `docs/DESIGN.md` - Design decisions
2. ✅ Review `docs/CODING_STANDARDS.md` - Code patterns
3. ✅ Review this checklist - UI patterns
4. ✅ Review `resources/views/admin/users/index.blade.php` - Reference implementation

---

## Button Styling Checklist

### Primary Button
```blade
<button class="btn btn-laporin">Label</button>
```
- [ ] Uses `.btn .btn-laporin` for primary action
- [ ] Green color (#228B22) with white text
- [ ] Solid fill (not outline)
- [ ] Font weight normal

### Secondary Button  
```blade
<button class="btn btn-outline-secondary">Label</button>
```
- [ ] Uses `.btn .btn-outline-secondary` for secondary action
- [ ] Gray outline, no fill
- [ ] Paired with primary button in modals/forms

### Danger Button
```blade
<button class="btn btn-sm btn-outline-danger">Delete</button>
```
- [ ] Uses `.btn .btn-outline-danger` for destructive actions
- [ ] Red outline, no fill
- [ ] Usually paired with confirmation dialog

### Small Buttons (Row Actions)
```blade
<button class="btn btn-sm btn-outline-laporin">Edit</button>
<button class="btn btn-sm btn-outline-danger">Delete</button>
```
- [ ] Uses `.btn-sm` for table actions
- [ ] Grouped with `gap-2` for spacing
- [ ] Placed in `text-end` cell

### Disabled State
```blade
<button class="btn btn-laporin" @disabled(! $canSubmit)>Action</button>
```
- [ ] Uses `@disabled()` directive (not disabled attribute)
- [ ] Greyed out appearance automatic
- [ ] Not clickable

---

## Form Styling Checklist

### Form Structure
```blade
<form method="POST" action="{{ route('...') }}">
  @csrf
  <div class="row g-3">
    <!-- form fields here -->
  </div>
</form>
```
- [ ] Always include `@csrf` for CSRF protection
- [ ] Use `method="POST"` (or `PUT`/`DELETE` via `@method`)
- [ ] Grid system: `<div class="row g-3">`
- [ ] Gap between fields: `g-3` (16px)

### Form Labels
```blade
<label class="form-label required" for="name">Field Label</label>
```
- [ ] Label class: `.form-label`
- [ ] Required fields: add `required` class (shows red asterisk via CSS)
- [ ] `for` attribute matches input `id`
- [ ] Label text clear and actionable

### Text Input
```blade
<input id="name" name="name" class="form-control @error('name') is-invalid @enderror" 
       value="{{ old('name') }}" required maxlength="150" placeholder="Example: John">
```
- [ ] Class: `.form-control`
- [ ] Error class: `.is-invalid` (conditional)
- [ ] Value: `old('name')` for persistence
- [ ] Attribute: `required` (HTML5)
- [ ] Attribute: `maxlength="X"` for text inputs
- [ ] Placeholder: helpful but not label replacement

### Select Input
```blade
<select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
  <option value="">-- Choose Option --</option>
  @foreach($options as $opt)
    <option value="{{ $opt->id }}" @selected(old('status') == $opt->id)>{{ $opt->name }}</option>
  @endforeach
</select>
```
- [ ] Class: `.form-select`
- [ ] Error class: `.is-invalid` (conditional)
- [ ] Include placeholder option (empty value)
- [ ] Use `@selected()` to preserve value
- [ ] Option values always match old value type

### Textarea
```blade
<textarea id="description" name="description" class="form-control" 
          rows="4" maxlength="2000" required>{{ old('description') }}</textarea>
```
- [ ] Class: `.form-control`
- [ ] Include `rows="X"` attribute
- [ ] Include `maxlength="X"` for length limits
- [ ] Content inside tags for pre-fill

### Checkbox
```blade
<div class="form-check">
  <input id="agree" class="form-check-input" type="checkbox" name="agree" value="1" @checked(old('agree', false))>
  <label for="agree" class="form-check-label">I agree to terms</label>
</div>
```
- [ ] Wrapper: `.form-check`
- [ ] Input class: `.form-check-input`
- [ ] Label class: `.form-check-label`
- [ ] Use `@checked()` to preserve value

### Error Messages
```blade
@error('email')
  <div class="invalid-feedback">{{ $message }}</div>
@enderror
```
- [ ] Class: `.invalid-feedback` (always with parent `.is-invalid`)
- [ ] Display: `d-block` (automatic in Bootstrap)
- [ ] Color: red (#dc3545) automatic
- [ ] Message: server-provided or custom

### Helper Text
```blade
<small class="text-muted">Format: DD/MM/YYYY</small>
```
- [ ] Element: `<small>` tag (semantic)
- [ ] Class: `.text-muted` (grey, secondary text)
- [ ] Not required (nice-to-have info only)
- [ ] Placed below input

### Form Grid Columns
```blade
<div class="col-12">...</div>           <!-- Full width (mobile) -->
<div class="col-md-6">...</div>         <!-- Half width (tablet+) -->
<div class="col-md-6 col-lg-3">...</div>  <!-- 1/4 width (desktop+) -->
```
- [ ] Always start with `col-12` (mobile-first)
- [ ] Add breakpoints: `col-md-X col-lg-Y`
- [ ] Group related fields in same row
- [ ] Full-width inputs on mobile `col-12`

---

## Modal Styling Checklist

### Modal Structure
```blade
<x-modal name="edit-resource" :show="old('edit_id') ? true : false" focusable>
  <form method="POST" x-bind:action="validAction" class="p-4">
    @csrf
    @method('PUT')
    <!-- modal content -->
  </form>
</x-modal>
```
- [ ] Component: `<x-modal>`
- [ ] Name: unique identifier for JavaScript
- [ ] Show: conditional (usually `old('edit_id') ? true : false`)
- [ ] Focusable: always add (enables focus trap)
- [ ] Form padding: `class="p-4"`
- [ ] Form action: always valid (no '#' fallback)

### Modal Header
```blade
<div class="mb-4">
  <h2 class="h5 fw-bold mb-1">Edit Resource</h2>
  <p class="text-muted small mb-0">Update the resource details below.</p>
</div>
```
- [ ] Heading: `.h5 .fw-bold`
- [ ] Subtitle: `.text-muted .small` (optional)
- [ ] Spacing: `mb-4` between header and form

### Modal Form Fields
```blade
<div class="row g-3">
  <div class="col-12">
    <label class="form-label required" for="edit_name">Name</label>
    <input id="edit_name" name="name" x-model="formData.name" 
           class="form-control @error('name') is-invalid @enderror" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
</div>
```
- [ ] Grid: `<div class="row g-3">`
- [ ] Full-width in modal: `col-12`
- [ ] Form fields: same pattern as page forms
- [ ] X-Model: bind to Alpine data
- [ ] Errors: display inline

### Modal Footer
```blade
<div class="d-flex justify-content-end gap-2 pt-3 border-top">
  <button type="button" class="btn btn-outline-secondary" x-on:click="$dispatch('close-modal', 'edit-resource')">
    Batal
  </button>
  <button type="submit" class="btn btn-laporin">
    Simpan
  </button>
</div>
```
- [ ] Flex: `.d-flex .justify-content-end`
- [ ] Gap: `.gap-2` (8px between buttons)
- [ ] Border: `.pt-3 .border-top` (separator)
- [ ] Order: Batal (left), Simpan (right)
- [ ] Close button: uses `x-on:click="$dispatch('close-modal', '...')"`
- [ ] Submit button: type="submit"

---

## Search/Filter Form Checklist

### Search Form Container
```blade
<form method="GET" action="{{ route('page.index') }}" class="row g-3 align-items-end">
  <!-- search fields -->
</form>
```
- [ ] Method: GET (for filtering)
- [ ] Grid: `row g-3 align-items-end` (aligns inputs with buttons)
- [ ] Action: current page route

### Search Input
```blade
<div class="col-md-6 col-lg-4">
  <label class="form-label" for="search">Cari</label>
  <input id="search" name="search" type="text" class="form-control" 
         placeholder="Cari nama atau email..." value="{{ request('search') }}" maxlength="100">
</div>
```
- [ ] Column width: responsive (`col-md-6 col-lg-4`)
- [ ] Placeholder: "Cari nama atau email..." (standardized)
- [ ] Value: preserve with `request('search')`
- [ ] Maxlength: reasonable limit (100 chars)

### Filter Dropdowns
```blade
<div class="col-md-6 col-lg-2">
  <label class="form-label" for="status">Status</label>
  <select id="status" name="status" class="form-select">
    <option value="">Semua</option>
    <option value="active" @selected(request('status') === 'active')>Aktif</option>
    <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
  </select>
</div>
```
- [ ] Include empty option: value="" label "Semua"
- [ ] Use `@selected()` to preserve value
- [ ] Column width: narrower than search (2-3 cols)

### Search Buttons
```blade
<div class="col-md-6 col-lg-2 d-flex gap-2">
  <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
  <a href="{{ route('page.index') }}" class="btn btn-outline-secondary">Reset</a>
</div>
```
- [ ] Layout: flex container `.d-flex .gap-2`
- [ ] Submit button: `.btn .btn-laporin .flex-grow-1`
- [ ] Reset link: `.btn .btn-outline-secondary` (not button)
- [ ] Reset href: route without params (clears filters)

---

## Table Styling Checklist

### Table Wrapper
```blade
<div class="table-responsive">
  <table class="table align-middle">
    <!-- table content -->
  </table>
</div>
```
- [ ] Wrapper: `.table-responsive` (enables horizontal scroll on mobile)
- [ ] Table classes: `.table .align-middle` (vertical alignment)

### Table Headers
```blade
<thead>
  <tr>
    <th>Name</th>
    <th>Email</th>
    <th>Status</th>
    <th class="text-end">Actions</th>
  </tr>
</thead>
```
- [ ] Headers in `<thead>`
- [ ] Action column: `class="text-end"`
- [ ] Header text: title case

### Table Rows
```blade
<tbody>
  @forelse($items as $item)
    <tr>
      <td>{{ $item->name }}</td>
      <td>{{ $item->email }}</td>
      <td><span class="badge text-bg-success">Active</span></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-laporin">Edit</button>
        <button class="btn btn-sm btn-outline-danger">Delete</button>
      </td>
    </tr>
  @empty
    <tr>
      <td colspan="4" class="text-center text-muted py-4">Belum ada data.</td>
    </tr>
  @endforelse
</tbody>
```
- [ ] Action column: `class="text-end text-nowrap"`
- [ ] Action buttons: `.btn-sm` size
- [ ] Empty state: colspan full width
- [ ] Empty text: "Belum ada data"
- [ ] Empty padding: `py-4` (vertical space)

---

## Badge Styling Checklist

### Status Badges
```blade
<span class="badge text-bg-success">Active</span>
<span class="badge text-bg-warning">Pending</span>
<span class="badge text-bg-danger">Rejected</span>
<span class="badge text-bg-info">Processing</span>
```
- [ ] Class: `.badge .text-bg-XXX`
- [ ] Colors per status:
  - Success (green): completed, active, verified
  - Warning (yellow): pending, review needed, sedang
  - Danger (red): rejected, inactive, error
  - Info (blue): processing, in progress
  - Secondary (grey): inactive, archived, low priority

### Priority Badges (Damage Reports)
```blade
<span class="badge text-bg-secondary">Rendah</span>    <!-- Low -->
<span class="badge text-bg-warning">Sedang</span>    <!-- Medium -->
<span class="badge text-bg-danger">Tinggi</span>    <!-- High -->
<span class="badge text-bg-danger">Darurat</span>   <!-- Urgent -->
```
- [ ] Color codes:
  - secondary (grey): rendah
  - warning (yellow): sedang
  - danger (red): tinggi, darurat

---

## Responsive Design Checklist

### Column Grid
```blade
<div class="row g-3">
  <div class="col-12">Full width on mobile</div>
  <div class="col-md-6">Half width on tablet+</div>
  <div class="col-md-6 col-lg-3">Quarter width on desktop+</div>
</div>
```
- [ ] Mobile first: start with `col-12`
- [ ] Tablet: `col-md-6` (768px+)
- [ ] Desktop: `col-lg-4` or `col-lg-3` (1024px+)
- [ ] Never use small only

### Flex Layout
```blade
<div class="d-flex gap-2 flex-wrap">
  <button class="btn btn-laporin">Action 1</button>
  <button class="btn btn-outline-secondary">Action 2</button>
</div>
```
- [ ] Flex: `.d-flex .gap-2`
- [ ] Wrap: `.flex-wrap` (buttons stack on mobile)
- [ ] Responsive: buttons automatically adjust

### Table Responsiveness
```blade
<div class="table-responsive">
  <table class="table">...</table>
</div>
```
- [ ] Wrapper: `.table-responsive`
- [ ] Auto horizontal scroll on mobile
- [ ] No manual width limits

### Modal on Mobile
```blade
<x-modal name="edit" maxWidth="lg"><!-- content --></x-modal>
```
- [ ] Modal: automatically responsive
- [ ] Max width: `lg` (large), `xl` (extra large)
- [ ] Mobile: full screen or near full screen (automatic)

---

## Accessibility Checklist

### Form Labels
```blade
<label class="form-label required" for="name">Name</label>
<input id="name" name="name" class="form-control" required>
```
- [ ] Label: always present (not placeholder-only)
- [ ] For attribute: matches input id
- [ ] Required class: for required fields
- [ ] Required attribute: on input (HTML5)

### Keyboard Navigation
- [ ] Tab: cycles through all interactive elements
- [ ] Shift+Tab: reverse navigation
- [ ] Escape: closes modal (automatic via Alpine)
- [ ] Enter: submits form (automatic via HTML5)

### Focus Management
- [ ] Focus visible: all interactive elements (automatic)
- [ ] Modal focus trap: first field focused on open
- [ ] Focus outline: not removed (default browser behavior)

### Color Usage
- [ ] Not color-only indicators (use text labels too)
- [ ] Badges: include text label (not just color)
- [ ] Buttons: text label (not icon-only without aria-label)

### Error Messages
```blade
@error('email')
  <div class="invalid-feedback">{{ $message }}</div>
@enderror
```
- [ ] Linked to field: via parent `.is-invalid` class
- [ ] Clear message: explains what's wrong
- [ ] Visible: always display on error

---

## Code Quality Checklist

### HTML/Blade
- [ ] No inline styles (use Bootstrap utilities)
- [ ] Semantic HTML (label, button, form tags)
- [ ] ID attributes: unique per page
- [ ] No hardcoded text (use {{ }} for translation)
- [ ] Blade directives: @csrf, @method, @error, @forelse

### Bootstrap Utilities
- [ ] Use utilities over custom CSS
- [ ] Consistent spacing: g-3, gap-2, mb-3, mt-2
- [ ] Consistent padding: p-3, p-lg-4
- [ ] Consistent colors: text-muted, text-danger, text-success
- [ ] No Tailwind CSS classes

### Security
- [ ] CSRF token: @csrf on all forms
- [ ] Method override: @method('PUT'/@method('DELETE') for non-POST
- [ ] Input escape: {{ }} not {!! !!} (prevent XSS)
- [ ] Old value: old('field') for persistence
- [ ] URL routes: route() not hardcoded URL strings

### Performance
- [ ] Images: lazy load if possible
- [ ] Scripts: defer or async attributes
- [ ] CSS: no duplicate class names
- [ ] Database: n+1 query prevention (eager load relations)

---

## Quick Copy-Paste Templates

### Complete Form Template
```blade
@extends('layouts.app')
@section('content')
<div class="page-header">
  <h1 class="page-title h2">Create Resource</h1>
</div>

<div class="laporin-card">
  <form method="POST" action="{{ route('resource.store') }}">
    @csrf
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label required" for="name">Name</label>
        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name') }}" required maxlength="150">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-6">
        <label class="form-label" for="description">Description</label>
        <input id="description" name="description" class="form-control @error('description') is-invalid @enderror"
               value="{{ old('description') }}" maxlength="500">
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Optional, up to 500 characters.</small>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-laporin">Create</button>
        <a href="{{ route('resource.index') }}" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </div>
  </form>
</div>
@endsection
```

### Complete Search Form Template
```blade
<form method="GET" action="{{ route('resource.index') }}" class="laporin-card mb-4">
  <div class="row g-3 align-items-end">
    <div class="col-md-6 col-lg-4">
      <label class="form-label" for="search">Cari</label>
      <input id="search" name="search" type="text" class="form-control"
             placeholder="Cari nama atau email..." value="{{ request('search') }}" maxlength="100">
    </div>
    <div class="col-md-6 col-lg-2">
      <label class="form-label" for="status">Status</label>
      <select id="status" name="status" class="form-select">
        <option value="">Semua</option>
        <option value="active" @selected(request('status') === 'active')>Aktif</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
      </select>
    </div>
    <div class="col-md-6 col-lg-4 d-flex gap-2">
      <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
      <a href="{{ route('resource.index') }}" class="btn btn-outline-secondary">Reset</a>
    </div>
  </div>
</form>
```

---

## Need Help?

- **Design questions**: See `docs/DESIGN.md`
- **Code examples**: See `resources/views/admin/users/index.blade.php`
- **Bootstrap docs**: https://getbootstrap.com/docs/5.3/
- **Alpine.js docs**: https://alpinejs.dev/

---

**Last Updated**: 2026-01-08
**Spec**: consistency-audit-verification
**Status**: ✅ Phase 3 Complete
