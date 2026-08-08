# Design: Consistency Audit & Verification

## Design Decisions

### 1. When to Use Modal vs Page Redirect

**Decision**: All edit/action operations use modal (no page redirect)

**Rationale**:
- Faster user experience (no page load)
- Context preserved (user stays on same page)
- Easier to implement with Alpine.js (already in project)
- Common pattern in modern web apps

**Implementation**:
- Simple forms (< 5 fields): Modal
- Complex multi-step: Consider page redirect (future consideration)
- Delete/Confirm: Always modal with confirmation dialog

**Examples**:
- Edit user: Modal with form
- Edit master data: Modal with form
- Edit QR code settings: Modal with form
- Delete item: Confirm modal, then delete
- Create item (if simple): Modal, else new page

### 2. When to Use Table vs Card-Based List

**Decision**: Keep existing pattern
- Admin pages: Table (filterable, sortable, compact)
- Role-specific pages (Kesiswaan, Sarpras): Card-based list (visual, detailed)
- Public pages: Form or simple display

**Rationale**:
- Admin tables are for bulk operations (need compactness)
- Role-specific cards are for workflow (need context & details)
- Consistent with existing design

### 3. Component Library Strategy

**Decision**: Use existing component library consistently
- Blade components: `x-modal`, `x-button`, `x-form-field`, etc
- Bootstrap 5 utilities: grid, spacing, colors, text utilities
- Alpine.js: Modal toggle, form validation hints, dropdown menu
- Tailwind tokens: Primary color (#228B22 green), secondary colors

**Standardization**:
- Create shared component library in `resources/views/components/` if not exists
- Components must support:
  - Disabled state
  - Loading state (for submit buttons)
  - Error state (for inputs)
  - Dark mode (optional, future)

### 4. Consistency Strategy

**Design Pattern Hierarchy**:
1. Semantic HTML (form, input, label, button, etc)
2. Bootstrap 5 utilities (grid, spacing, colors, text)
3. Blade components (modal, form-field, button-group, etc)
4. Alpine.js (interactivity, focus trap, keyboard nav)
5. Custom CSS (only if utilities insufficient)

**Color Palette**:
- Primary (Laporin Green): #228B22
- Success: #198754
- Danger: #dc3545
- Warning: #ffc107
- Info: #0dcaf0
- Secondary: #6c757d

**Spacing Scale** (Bootstrap utilities):
- `g-0`: 0px
- `g-1`: 0.25rem (4px)
- `g-2`: 0.5rem (8px)
- `g-3`: 1rem (16px)
- `g-4`: 1.5rem (24px)
- `g-5`: 3rem (48px)

**Typography**:
- Headings: Bootstrap heading utilities (h1-h6)
- Body text: 16px (1rem)
- Small text: 14px (0.875rem)
- Form labels: 14px, bold

**Form Validation Pattern**:
- Server-side primary (Laravel validation)
- Client-side hints (HTML5 attributes)
- Error display:
  - Inline in form (`.invalid-feedback` div)
  - Field border turns red
  - Icon indicator optional
- Success state: Green border or checkmark

### 5. Page Template Structure

All authenticated pages follow this structure:

```
1. Page Header
   - Kicker/Breadcrumb (optional)
   - Page Title (h1)
   - Subtitle/Description (p, muted)

2. Create/Add Form (if applicable)
   - Single row form
   - Inline button (submit)
   - Uses modal OR new page (depends on complexity)

3. Search/Filter Form (if 20+ items)
   - Container: laporin-card with shadow
   - Grid: row g-3 align-items-end
   - Filters: status, date range, search text
   - Buttons: Submit + Reset
   - Values preserved on submit

4. Results Display
   - Table (admin pages)
   - Cards (role-specific pages)
   - Results count info
   - Empty state message

5. Pagination (if applicable)
   - Below results
   - Preserve filters with .appends()
   - Responsive layout

6. Modal (if applicable)
   - Edit form modal
   - Confirm/Delete modal
   - Always focusable (focus trap)
```

### 6. Navbar Structure

**Guest Navbar**:
```
[Logo] | Buat Laporan | Panduan Lapor | Alur Validasi | Lacak | FAQ | [Login]
```
- Active link highlighted (color change or underline)
- Login button right-aligned

**Auth Navbar** (role-based, superadmin shown):
```
[Logo] | Dashboard | Kesiswaan | Sarpras | Admin ▼ | Profile | [Logout]
  Kesiswaan
  Sarpras
  ─────────────
  Admin Dropdown:
    - Pengguna
    - Kode QR
    - Catatan Audit
    - ───────────────
    - Master Data ▶
      - Kelas
      - Mapel
      - Unit Staf
      - Lokasi
      - Jenis Pelanggaran
      - Kategori Kerusakan
```
- Dropdown smooth open/close
- Active link highlighted
- Mobile: Hamburger menu
- Role-based visibility (middleware enforced)

### 7. Accessibility Strategy

**WCAG AA Target**:
- Color contrast: 4.5:1 for text, 3:1 for graphics
- All form inputs have labels
- All buttons have clear labels
- Modal focus trap works
- Keyboard navigation complete (Tab, Shift+Tab, Escape, Enter)
- Error messages linked to fields
- No color-only indicators (use text + icons)

**Implementation**:
- Semantic HTML always
- Labels with `for` attributes
- `aria-describedby` for error messages (optional)
- `focusable` attribute on modals (Alpine.js)
- `required` attribute on required fields
- Clear error messages

### 8. Error Handling Strategy

**Validation Flow**:
1. Client-side hint: HTML5 attributes (required, pattern, min, max)
2. Server-side validation: Laravel validation rules
3. Error display: Inline in form, red text, `.invalid-feedback`
4. Old input preserved: `old('field')` in Blade
5. Redirect back with errors: `withErrors()` in controller

**Error Message Format**:
- Field-specific: "Email tidak valid" (friendly, actionable)
- Global: "Terjadi kesalahan. Coba lagi." (generic system error)
- Not technical: Avoid "ValidationException: ..." messages

**Null/Empty Handling**:
- Empty table: "Belum ada data"
- No search results: "Tidak ada hasil ditemukan untuk 'xyz'"
- Invalid input: Silently ignore (allowlist validation)

### 9. Responsive Design Strategy

**Breakpoints** (Bootstrap):
- `xs` (< 576px): Mobile
- `sm` (576px+): Small device
- `md` (768px+): Tablet
- `lg` (992px+): Desktop
- `xl` (1200px+): Large desktop

**Mobile-First Approach**:
- Base layout: single column, full-width
- Add complexity as screen grows
- Test on actual devices (iPhone, Android)

**Key Responsive Elements**:
- Forms: Stack vertikal on mobile
- Tables: Horizontal scroll on mobile
- Grids: `col-12 col-md-6 col-lg-4` pattern
- Modals: Full-screen or 90% on mobile
- Navbar: Hamburger menu < 768px

### 10. Testing Strategy

**Manual Testing Per Page**:
1. Load page (check for 404, console errors)
2. Test search/filter (values preserved)
3. Test buttons (links work, modals open)
4. Test form (validation works, submit works)
5. Test modal (open/close, Tab/Escape works)
6. Test pagination (filters preserved)
7. Test responsive (mobile, tablet, desktop)
8. Test accessibility (Tab navigation, labels visible)

**Automated Testing** (PHP tests):
1. Page loads correctly (200 status)
2. Form validation works (both client & server)
3. Modal displays form correctly
4. Search/filter preserves values
5. Pagination preserve filters
6. Authorization checks work

**No new features introduced - only verification & fixes**

---

## Implementation Guidelines

### Component Naming & Organization

```
resources/views/
├── components/
│   ├── modal.blade.php              (x-modal)
│   ├── button.blade.php             (x-button)
│   ├── form-field.blade.php         (x-form-field)
│   ├── search-form.blade.php        (x-search-form)
│   ├── table.blade.php              (x-table)
│   ├── pagination.blade.php         (x-pagination)
│   ├── badge.blade.php              (x-badge)
│   └── navbar.blade.php             (x-navbar)
├── layouts/
│   ├── guest.blade.php              (public pages)
│   ├── app.blade.php                (authenticated pages)
│   └── admin.blade.php              (admin pages)
├── pages/
│   ├── admin/
│   ├── kesiswaan/
│   ├── sarpras/
│   ├── public/
│   └── ...
```

### Styling Approach

**CSS Methodology**:
- Use Bootstrap 5 utilities first
- Custom CSS only if utilities insufficient
- CSS classes organized by concern (layout, spacing, color, text)
- Avoid inline styles

**Example Button Usage**:
```html
<!-- Primary button -->
<button class="btn btn-laporin">Simpan</button>

<!-- Secondary button -->
<button class="btn btn-outline-secondary">Batal</button>

<!-- Danger button small -->
<button class="btn btn-sm btn-outline-danger">Hapus</button>

<!-- Disabled button -->
<button class="btn btn-laporin" @disabled(!$canSubmit)>Proses</button>
```

### Form Structure Pattern

```html
<form method="POST" action="{{ route('...') }}">
  @csrf
  <div class="row g-3">
    <!-- Text input -->
    <div class="col-md-6">
      <label for="name" class="form-label required">Nama</label>
      <input type="text" class="form-control @error('name') is-invalid @enderror" 
             id="name" name="name" value="{{ old('name') }}" required>
      @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
      <small class="text-muted">Contoh: John Doe</small>
    </div>

    <!-- Select input -->
    <div class="col-md-6">
      <label for="role" class="form-label required">Role</label>
      <select class="form-select @error('role') is-invalid @enderror" 
              id="role" name="role" required>
        <option value="">-- Pilih Role --</option>
        @foreach ($roles as $role)
          <option value="{{ $role->id }}" @selected(old('role') === $role->id)>
            {{ $role->name }}
          </option>
        @endforeach
      </select>
      @error('role')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>
  </div>

  <div class="row g-3 mt-3">
    <div class="col-12">
      <button type="submit" class="btn btn-laporin">Simpan</button>
      <a href="{{ route('...') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
  </div>
</form>
```

### Search/Filter Form Pattern

```html
<form method="GET" action="{{ route('...') }}" class="laporin-card mb-4">
  <div class="row g-3 align-items-end">
    <div class="col-md-6 col-lg-3">
      <label for="search" class="form-label">Cari</label>
      <input type="text" class="form-control" id="search" name="search" 
             value="{{ request('search') }}" placeholder="Nama, email, dll">
    </div>

    <div class="col-md-6 col-lg-3">
      <label for="status" class="form-label">Status</label>
      <select class="form-select" id="status" name="status">
        <option value="">-- Semua Status --</option>
        <option value="active" @selected(request('status') === 'active')>Aktif</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
      </select>
    </div>

    <div class="col-md-6 col-lg-3">
      <button type="submit" class="btn btn-laporin w-100">Cari</button>
    </div>

    <div class="col-md-6 col-lg-3">
      <a href="{{ route('...') }}" class="btn btn-outline-secondary w-100">Reset</a>
    </div>
  </div>
</form>
```

---

## Technical Specifications

### Browser Support
- Chrome/Chromium (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Android)

### Performance
- Page load < 3 seconds
- Modal open/close < 200ms
- Search filter < 500ms
- No unnecessary network requests
- Lazy load images (if applicable)

### Security
- All user input escaped (Blade `{{ }}`)
- CSRF protection on all forms (`@csrf`)
- SQL injection prevention (Eloquent ORM)
- XSS prevention (Content-Security-Policy header)
- Authentication checks on all routes

### Database Indexes
- Foreign keys should have indexes
- Frequently searched fields should have indexes
- Search queries should use indexes

---

## Success Criteria for Design

✅ All pages follow consistent layout pattern (header, form, results, modal)
✅ All pages use modal for edit operations (no page redirect)
✅ All pages use responsive design (mobile, tablet, desktop)
✅ All pages have proper error handling & validation
✅ All pages are accessible (keyboard, labels, focus)
✅ Navbar structure clear & role-based visibility correct
✅ Component library organized & reusable
✅ No inline styles, CSS organization clear
✅ Form patterns consistent across app
✅ Styling approach (Bootstrap first, custom CSS last)
