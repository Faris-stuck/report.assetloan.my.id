# Design: Week 2-3 Mobile UI/UX Optimization

## Design Decisions

### 1. Mobile-First Form Grid Strategy

**Decision**: All forms use `col-12 col-md-6 col-lg-4` responsive column pattern as base

**Rationale**:
- Mobile first (100% width on xs)
- Tablet-friendly (50% on md = 2 columns)
- Desktop clean (25-33% on lg = 3-4 columns)
- Consistent across all forms (standardizes experience)
- Bootstrap native (no custom CSS needed)

**Implementation Pattern**:
```html
<form method="POST" action="{{ route('...') }}">
  @csrf
  <div class="row g-3">
    <!-- Each field in col-12 col-md-6 by default -->
    <div class="col-12 col-md-6">
      <label for="field1" class="form-label required">Label</label>
      <input type="text" class="form-control" id="field1" name="field1" required>
      <small class="text-muted">Helper text</small>
    </div>

    <!-- Full-width fields (email, textarea, etc) use col-12 -->
    <div class="col-12">
      <label for="email" class="form-label required">Email</label>
      <input type="email" class="form-control" id="email" name="email" required>
    </div>

    <!-- Buttons: stacked on mobile, inline on tablet+ -->
    <div class="col-12">
      <button type="submit" class="btn btn-laporin">Simpan</button>
      <a href="{{ route('...') }}" class="btn btn-outline-secondary ms-2">Batal</a>
    </div>
  </div>
</form>
```

**Full-Width Fields**:
- Email, phone, description/textarea, file upload
- Search inputs (in filter forms)
- Any field that benefits from full width

**Two-Column Fields** (`col-12 col-md-6`):
- First name + Last name (pair together)
- Start date + End date (pair together)
- Most other standard inputs
- Dropdowns

### 2. Focus Indicator & Keyboard Navigation

**Decision**: Use CSS `:focus-visible` pseudo-class for keyboard focus, visible outline for all interactive elements

**Rationale**:
- `:focus-visible` differentiates keyboard vs mouse focus
- Outline clear and visible (2px+)
- Consistent across all browsers
- WCAG AAA compliant
- No interference with normal focus (`:focus` still used for styling)

**CSS Implementation**:
```css
/* Global focus-visible style */
:focus-visible {
  outline: 3px solid #228B22;  /* Laporin green */
  outline-offset: 2px;
}

/* Specific refinements */
button:focus-visible,
a:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible {
  outline: 3px solid #228B22;
  outline-offset: 2px;
}

/* Checkbox/Radio custom focus */
.form-check-input:focus-visible {
  border-color: #228B22;
  box-shadow: 0 0 0 0.25rem rgba(34, 139, 34, 0.25);
}
```

**Testing**:
- Navigate with Tab key: outline appears on each element
- Use mouse click: no outline (or minimal)
- Escape key: closes dropdowns/modals
- Enter key: submits forms or activates buttons

### 3. File Upload Validation & Preview Pattern

**Decision**: Client-side validation + server-side validation + inline preview

**Validation Flow**:
1. User selects file
2. Client JavaScript validates type (JPG/PNG) and size (< 5MB)
3. If valid: preview displays, submit allowed
4. If invalid: error message displays, file cleared
5. Server-side: re-validate on submit (never trust client)

**HTML Pattern**:
```html
<div class="mb-3">
  <label for="repair_photo" class="form-label required">Foto Pemeriksaan</label>
  <input type="file" class="form-control @error('repair_photo') is-invalid @enderror"
         id="repair_photo" name="repair_photo" accept="image/jpeg,image/png" required>
  
  <small class="text-muted d-block mt-2">
    Format: JPG atau PNG, Ukuran maksimal: 5MB
  </small>
  
  <div id="preview-container" class="mt-3" style="display: none;">
    <img id="preview-image" src="" alt="Preview" 
         style="max-width: 100px; max-height: 100px; border: 1px solid #ddd; padding: 4px;">
    <div>
      <small id="filename" class="text-muted d-block mt-2"></small>
      <small id="filesize" class="text-muted d-block"></small>
      <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="clearPreview()">
        Hapus
      </button>
    </div>
  </div>
  
  @error('repair_photo')
    <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
  @enderror
</div>
```

**JavaScript Validation** (Alpine.js or inline):
```javascript
document.getElementById('repair_photo').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (!file) return;
  
  const validTypes = ['image/jpeg', 'image/png'];
  const maxSize = 5 * 1024 * 1024; // 5MB
  
  // Type validation
  if (!validTypes.includes(file.type)) {
    showError('Format tidak didukung. Gunakan JPG atau PNG.');
    e.target.value = '';
    return;
  }
  
  // Size validation
  if (file.size > maxSize) {
    showError(`File terlalu besar (${Math.round(file.size / 1024 / 1024)}MB). Maks 5MB.`);
    e.target.value = '';
    return;
  }
  
  // Preview
  const reader = new FileReader();
  reader.onload = function(event) {
    document.getElementById('preview-image').src = event.target.result;
    document.getElementById('filename').textContent = `File: ${file.name}`;
    document.getElementById('filesize').textContent = `Ukuran: ${(file.size / 1024).toFixed(1)}KB`;
    document.getElementById('preview-container').style.display = 'block';
  };
  reader.readAsDataURL(file);
});

function clearPreview() {
  document.getElementById('repair_photo').value = '';
  document.getElementById('preview-container').style.display = 'none';
}
```

### 4. ARIA Labels & Button Accessibility

**Decision**: Every action button has `aria-label` with descriptive text

**Pattern**:
```html
<!-- Edit button with aria-label -->
<a href="{{ route('users.edit', $user->id) }}" class="btn btn-sm btn-outline-primary"
   aria-label="Edit pengguna {{ $user->name }}">
  <i class="bi bi-pencil" aria-hidden="true"></i>
</a>

<!-- Delete button with aria-label -->
<button type="button" class="btn btn-sm btn-outline-danger" @click="openDeleteModal($user->id)"
        aria-label="Hapus pengguna {{ $user->name }}">
  <i class="bi bi-trash" aria-hidden="true"></i>
</button>

<!-- Process button with aria-label (role-specific) -->
<button type="button" class="btn btn-laporin" @click="openProcessModal($report->id)"
        aria-label="Proses laporan #{{ $report->id }}">
  Proses
</button>

<!-- Dropdown toggle with aria-expanded -->
<button class="btn btn-outline-secondary dropdown-toggle" type="button" 
        @click="dropdownOpen = !dropdownOpen" :aria-expanded="dropdownOpen">
  Panel Admin
</button>
```

**aria-label Format**:
- `"Edit {object} {identifier}"` (e.g., "Edit pengguna John Doe")
- `"Delete {object} {identifier}"` (e.g., "Delete kelas 10A")
- `"Process {object} {identifier}"` (e.g., "Process laporan #12345")
- `"Reject {object} {identifier}"` (e.g., "Reject laporan #12345")
- `"Download {object}"` (e.g., "Download laporan PDF")
- `"Deactivate {object} {identifier}"` (e.g., "Deactivate QR code ABC123")

### 5. Helper Text Standardization

**Decision**: `<small class="text-muted">` HTML element for all helper text

**Why**:
- Semantic HTML (assistive tech understands it's supplementary)
- Bootstrap native styling (no custom CSS)
- Consistent appearance across forms
- Readable (muted gray, sufficient contrast)

**Pattern**:
```html
<div class="mb-3">
  <label for="password" class="form-label required">Kata Sandi</label>
  <input type="password" class="form-control" id="password" name="password" required>
  <small class="text-muted">Minimal 8 karakter, kombinasi huruf dan angka</small>
</div>

<div class="mb-3">
  <label for="birthdate" class="form-label">Tanggal Lahir</label>
  <input type="date" class="form-control" id="birthdate" name="birthdate">
  <small class="text-muted">Format: DD/MM/YYYY</small>
</div>
```

**Key Rules**:
- Always display below input (except if error message replaces it)
- Use muted gray color (text-muted)
- Keep it short (1-2 sentences max)
- Optional information only (not critical instructions)
- Same styling across all forms (consistency)

### 6. Touch Target & Spacing Optimization

**Decision**: Minimum 44x44px for all touch targets, 8px gap between adjacent targets

**Why 44x44px**:
- WCAG recommendation (mobile accessibility)
- Comfortable for thumb/finger on mobile
- Accounts for padding + border
- Standard across iOS and Android design

**Implementation**:
```html
<!-- Button with min 44px height -->
<button class="btn btn-laporin" style="min-height: 44px; min-width: 44px;">
  Simpan
</button>

<!-- Row actions with proper spacing -->
<td class="text-end">
  <a href="#" class="btn btn-sm btn-outline-primary" style="min-width: 44px; min-height: 44px;">
    <i class="bi bi-pencil" aria-hidden="true"></i>
  </a>
  <a href="#" class="btn btn-sm btn-outline-danger ms-2" style="min-width: 44px; min-height: 44px;">
    <i class="bi bi-trash" aria-hidden="true"></i>
  </a>
</td>
```

**Button Padding Calculation**:
- Default button: `padding: 0.375rem 0.75rem` = ~44px with default font
- If button smaller: add `padding: 0.5rem 0.75rem` to reach 44px
- Text buttons: account for line-height in height calculation

### 7. Navigation & Menu Accessibility

**Guest Navbar**:
```html
<nav class="navbar navbar-expand-lg bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/">LAPORIN</a>
    <button class="navbar-toggler" type="button" @click="mobileMenuOpen = !mobileMenuOpen"
            :aria-expanded="mobileMenuOpen" aria-label="Toggle navigation menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" :class="{'show': mobileMenuOpen}">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="/" @click="activeLink = '/'">Buat Laporan</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/lapor-pembullyan">Panduan Lapor</a>
        </li>
        <!-- ... more links ... -->
        <li class="nav-item">
          <a class="btn btn-laporin ms-2" href="/login">Login</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
```

**Admin Dropdown** (Authenticated):
```html
<!-- With Alpine.js dropdown -->
<div class="dropdown" @click.outside="adminDropdownOpen = false">
  <button class="btn btn-outline-secondary dropdown-toggle"
          @click="adminDropdownOpen = !adminDropdownOpen"
          :aria-expanded="adminDropdownOpen">
    Panel Admin
  </button>
  <ul class="dropdown-menu" :class="{'show': adminDropdownOpen}">
    <li><a class="dropdown-item" href="/admin/users">Pengguna</a></li>
    <li><a class="dropdown-item" href="/admin/qrcodes">Kode QR</a></li>
    <li><a class="dropdown-item" href="/admin/audit">Catatan Audit</a></li>
    <li><hr class="dropdown-divider"></li>
    
    <!-- Master Data Submenu -->
    <li class="dropend">
      <a class="dropdown-item dropdown-toggle" href="#"
         @click.prevent="masterDataOpen = !masterDataOpen"
         :aria-expanded="masterDataOpen">
        Master Data
      </a>
      <ul class="dropdown-menu" :class="{'show': masterDataOpen}">
        <li><a class="dropdown-item" href="/admin/master/classes">Kelas</a></li>
        <li><a class="dropdown-item" href="/admin/master/subjects">Mapel</a></li>
        <li><a class="dropdown-item" href="/admin/master/staff-units">Unit Staf</a></li>
        <li><a class="dropdown-item" href="/admin/master/locations">Lokasi</a></li>
        <li><a class="dropdown-item" href="/admin/master/violation-types">Jenis Pelanggaran</a></li>
        <li><a class="dropdown-item" href="/admin/master/damage-categories">Kategori Kerusakan</a></li>
      </ul>
    </li>
  </ul>
</div>
```

### 8. Mobile Responsive Breakpoints

**Strategy**: Mobile-first (no prefix = mobile), then enhance with media queries

**Bootstrap Breakpoints Used**:
- `col-12`: Mobile (default, 100% width)
- `col-md-6`: Tablet (768px+, 50% width)
- `col-lg-4`: Desktop (992px+, 33% width)

**Form Grid Examples**:
```html
<!-- 2-column on tablet+ -->
<div class="col-12 col-md-6">...</div>

<!-- Full-width always -->
<div class="col-12">...</div>

<!-- 3-column on desktop (admin master data) -->
<div class="col-12 col-md-6 col-lg-4">...</div>
```

### 9. Accessibility Testing Approach

**Keyboard Navigation Test**:
1. Tab through entire page
2. Verify focus outline visible on each interactive element
3. Escape key closes modals/dropdowns
4. Enter key submits forms
5. No focus trap (unless intentional, e.g., modal)

**Screen Reader Test** (if NVDA or JAWS available):
1. All inputs have labels (read correctly)
2. Buttons have text or aria-label (read correctly)
3. Error messages associated with fields
4. Modal title announced
5. Form landmarks identified

**Color Contrast Test**:
- Use WebAIM contrast checker
- Target: 4.5:1 for normal text, 3:1 for large text
- Button text vs background: ≥4.5:1
- Helper text (muted gray): ≥4.5:1
- Border/outline colors: ≥3:1

### 10. Testing & Validation Approach

**Manual Testing Per Page**:
1. Load page in mobile browser (DevTools device mode)
2. Verify responsive layout (no horizontal scroll)
3. Verify touch targets (≥44x44px)
4. Verify keyboard navigation (Tab works)
5. Verify form submission (no console errors)
6. Verify modal open/close (Escape works)
7. Verify accessibility (labels present, focus visible)

**Automated Testing** (PHP tests):
1. Page loads 200 status
2. Responsive classes present (col-12, col-md-6, etc)
3. Inputs have labels
4. Buttons have aria-label or text
5. No duplicate IDs (valid HTML)
6. No broken links

**Real Device Testing**:
1. iPhone 12 (390px) or equivalent
2. Samsung Galaxy S21 (360px) or equivalent
3. Landscape orientation
4. Touch interaction (tap, swipe)
5. Network speed (slow 3G if possible)

---

## Implementation Guidelines

### Component Pattern Library

**Modal Component** (`x-modal`):
```html
@props(['name', 'focusable' => true])
<div x-data="{ open: @entangle($name) }"
     x-show="open"
     x-transition
     class="modal"
     :class="{'show d-block': open}"
     @keydown.escape="open = false"
     role="dialog"
     aria-modal="true"
     {{ $focusable ? '@click.outside="open = false"' : '' }}>
  <div class="modal-backdrop" style="display: block;"></div>
  <div class="modal-dialog">
    <div class="modal-content">
      {{ $slot }}
    </div>
  </div>
</div>
```

**Search/Filter Form Component**:
```html
@props(['action' => '#', 'method' => 'GET'])
<form method="{{ $method }}" action="{{ $action }}" class="laporin-card mb-4">
  @csrf
  <div class="row g-3 align-items-end">
    {{ $slot }}
    
    <!-- Default action buttons -->
    <div class="col-12 col-md-6 col-lg-3">
      <button type="submit" class="btn btn-laporin w-100">Cari</button>
    </div>
  </div>
</form>
```

### CSS Class Naming

**Utility-First Approach**:
- Use Bootstrap utilities first (spacing, layout, colors)
- Minimize custom CSS
- Custom classes only for complex patterns

**Spacing Classes** (Bootstrap grid):
- `g-3`: 16px gap between grid items
- `mb-3`: 16px bottom margin
- `ms-2`: 8px left margin (margin-start)
- `gap-2`: 8px gap (flex/grid)

**Responsive Classes**:
- `col-12`: 100% width (mobile default)
- `col-md-6`: 50% width (tablet+)
- `col-lg-4`: 33% width (desktop+)
- `d-none d-md-block`: hidden on mobile, visible on tablet+

### Form Validation Display

**Server-Side Errors**:
```html
<div class="mb-3">
  <label for="email" class="form-label required">Email</label>
  <input type="email" class="form-control @error('email') is-invalid @enderror"
         id="email" name="email" value="{{ old('email') }}" required>
  
  @error('email')
    <div class="invalid-feedback d-block">{{ $message }}</div>
  @enderror
  
  <small class="text-muted">Contoh: user@example.com</small>
</div>
```

**Client-Side HTML5 Validation**:
- `required` attribute
- `type="email"` for email validation
- `min/max` for numbers
- `pattern` for custom formats
- Browser shows native error messages (fallback)

---

## Success Criteria

✅ All forms use `col-12 col-md-6` responsive grid pattern
✅ All focus indicators visible with `:focus-visible` CSS
✅ All file uploads show validation + preview
✅ All action buttons have descriptive `aria-label`
✅ All helper text uses `<small class="text-muted">`
✅ All navigation items accessible and discoverable
✅ All touch targets ≥44x44px with proper spacing
✅ All pages responsive on 375px-414px screens
✅ All keyboard navigation works (Tab, Escape, Enter)
✅ Lighthouse accessibility score ≥95
