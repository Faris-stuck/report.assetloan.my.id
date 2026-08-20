---
domain: ui
purpose: aria-conventions
version: 1.0
updated: 2024-01-15
owner: development-team
status: stable
---

# ARIA Label Convention Guide

## Overview

This document defines the standard format for `aria-label` attributes across LAPORIN application. ARIA labels provide accessible descriptions for buttons, links, and interactive elements, especially important for icon-only buttons or when visual text alone is insufficient for screen reader users.

---

## Basic Format

All action button `aria-label` attributes follow this pattern:

```
{action} {object} {identifier}
```

**Components**:
- **{action}**: The verb describing what the button does (Edit, Delete, Process, Reject, Download, Deactivate, View, etc.)
- **{object}**: The noun describing what is being acted upon (pengguna, kelas, laporan, kode QR, dll.)
- **{identifier}**: A unique identifier to disambiguate the specific item (name, ID, code, number)

---

## Examples by Button Type

### Edit Button
```html
<!-- User edit -->
<a href="{{ route('users.edit', $user->id) }}" 
   class="btn btn-sm btn-outline-primary"
   aria-label="Edit pengguna {{ $user->name }}">
  <i class="bi bi-pencil" aria-hidden="true"></i>
</a>

<!-- Class edit -->
<button class="btn btn-sm btn-outline-primary" 
        @click="editClass($class->id)"
        aria-label="Edit kelas {{ $class->class_name }}">
  <i class="bi bi-pencil" aria-hidden="true"></i>
</button>

<!-- Report edit -->
<a href="{{ route('reports.edit', $report->id) }}"
   class="btn btn-sm btn-outline-primary"
   aria-label="Edit laporan #{{ $report->id }}">
  <i class="bi bi-pencil" aria-hidden="true"></i>
</a>
```

### Delete Button
```html
<!-- User delete -->
<button type="button" 
        class="btn btn-sm btn-outline-danger"
        @click="openDeleteModal($user->id)"
        aria-label="Hapus pengguna {{ $user->name }}">
  <i class="bi bi-trash" aria-hidden="true"></i>
</button>

<!-- Class delete -->
<button type="button"
        class="btn btn-sm btn-outline-danger"
        @click="deleteClass($class->id)"
        aria-label="Hapus kelas {{ $class->class_name }}">
  <i class="bi bi-trash" aria-hidden="true"></i>
</button>

<!-- Multiple selection with count -->
<button type="button"
        class="btn btn-sm btn-outline-danger"
        aria-label="Hapus {{ $selectedCount }} item">
  <i class="bi bi-trash" aria-hidden="true"></i>
</button>
```

### Process / Approve Button (Role-Specific)
```html
<!-- Kesiswaan: Process violation report -->
<button type="button"
        class="btn btn-laporin"
        @click="openProcessModal($report->id)"
        aria-label="Proses laporan #{{ $report->id }}">
  <i class="bi bi-check2" aria-hidden="true"></i> Proses
</button>

<!-- Sarpras: Process damage report -->
<button type="button"
        class="btn btn-laporin"
        @click="openProcessModal($report->id)"
        aria-label="Proses laporan kerusakan #{{ $report->id }}">
  <i class="bi bi-check2" aria-hidden="true"></i> Proses
</button>
```

### Reject Button
```html
<!-- Reject violation report -->
<button type="button"
        class="btn btn-outline-danger"
        @click="openRejectModal($report->id)"
        aria-label="Tolak laporan #{{ $report->id }}">
  <i class="bi bi-x-circle" aria-hidden="true"></i>
</button>

<!-- Reject with reason -->
<button type="button"
        class="btn btn-outline-warning"
        @click="openRejectModal($report->id, 'incomplete')"
        aria-label="Tolak laporan #{{ $report->id }} - Data tidak lengkap">
  <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
</button>
```

### Download Button
```html
<!-- Download PDF report -->
<a href="{{ route('reports.download', $report->id) }}"
   class="btn btn-sm btn-outline-secondary"
   download
   aria-label="Download laporan #{{ $report->id }} PDF">
  <i class="bi bi-download" aria-hidden="true"></i>
</a>

<!-- Download Excel list -->
<a href="{{ route('reports.export-excel') }}"
   class="btn btn-outline-secondary"
   aria-label="Download daftar laporan ke Excel">
  <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Export
</a>

<!-- Download QR code -->
<a href="{{ route('qrcodes.download', $qrcode->id) }}"
   class="btn btn-sm btn-outline-secondary"
   aria-label="Download kode QR {{ $qrcode->code }}">
  <i class="bi bi-download" aria-hidden="true"></i>
</a>
```

### Deactivate / Activate Button
```html
<!-- Deactivate QR code -->
<button type="button"
        class="btn btn-sm btn-outline-warning"
        @click="deactivateQRCode($qrcode->id)"
        aria-label="Nonaktifkan kode QR {{ $qrcode->code }}">
  <i class="bi bi-power" aria-hidden="true"></i>
</button>

<!-- Reactivate user -->
<button type="button"
        class="btn btn-sm btn-outline-success"
        @click="reactivateUser($user->id)"
        aria-label="Aktifkan kembali pengguna {{ $user->name }}">
  <i class="bi bi-check-circle" aria-hidden="true"></i>
</button>
```

### View / Open Button
```html
<!-- View report details -->
<a href="{{ route('reports.show', $report->id) }}"
   class="btn btn-sm btn-outline-info"
   aria-label="Lihat detail laporan #{{ $report->id }}">
  <i class="bi bi-eye" aria-hidden="true"></i>
</a>

<!-- Open modal dialog -->
<button type="button"
        class="btn btn-outline-secondary"
        @click="openDetailsModal($item->id)"
        aria-label="Buka detail {{ $item->name }}">
  <i class="bi bi-arrow-right" aria-hidden="true"></i>
</button>
```

### Close / Dismiss Button
```html
<!-- Modal close button -->
<button type="button"
        class="btn-close"
        @click="closeModal()"
        aria-label="Tutup dialog">
</button>

<!-- Alert dismiss -->
<button type="button"
        class="btn-close"
        @click="dismissAlert()"
        aria-label="Tutup pemberitahuan">
</button>
```

### Dropdown Toggle (with aria-expanded)
```html
<!-- Admin panel dropdown -->
<button class="btn btn-outline-secondary dropdown-toggle"
        type="button"
        @click="adminDropdownOpen = !adminDropdownOpen"
        :aria-expanded="adminDropdownOpen"
        aria-label="Buka menu panel admin">
  Panel Admin
</button>

<!-- Mobile menu toggle -->
<button class="navbar-toggler"
        type="button"
        @click="mobileMenuOpen = !mobileMenuOpen"
        :aria-expanded="mobileMenuOpen"
        aria-label="Buka menu navigasi">
  <span class="navbar-toggler-icon"></span>
</button>

<!-- Master Data submenu toggle -->
<button class="dropdown-item dropdown-toggle"
        @click.prevent="masterDataOpen = !masterDataOpen"
        :aria-expanded="masterDataOpen"
        aria-label="Buka submenu Master Data">
  Master Data
</button>
```

---

## Important Rules

### 1. Icon-Only Buttons MUST Have aria-label
```html
<!-- ✗ WRONG: Icon only, no aria-label -->
<button class="btn btn-outline-primary">
  <i class="bi bi-pencil"></i>
</button>

<!-- ✓ CORRECT: aria-label + aria-hidden on icon -->
<button class="btn btn-outline-primary"
        aria-label="Edit pengguna John Doe">
  <i class="bi bi-pencil" aria-hidden="true"></i>
</button>
```

### 2. Icons Must Have aria-hidden="true"
```html
<!-- ✗ WRONG: Icon is read by screen reader -->
<button aria-label="Edit pengguna">
  <i class="bi bi-pencil"></i>
</button>

<!-- ✓ CORRECT: Icon hidden from screen readers -->
<button aria-label="Edit pengguna">
  <i class="bi bi-pencil" aria-hidden="true"></i>
</button>
```

### 3. aria-expanded for Dropdowns
```html
<!-- ✗ WRONG: No aria-expanded state -->
<button class="dropdown-toggle">Menu</button>

<!-- ✓ CORRECT: aria-expanded tracks state -->
<button class="dropdown-toggle"
        :aria-expanded="dropdownOpen"
        @click="dropdownOpen = !dropdownOpen">
  Menu
</button>
```

### 4. Use Indonesian Language Consistently
```html
<!-- ✗ WRONG: Mixed languages -->
<button aria-label="Edit user John">
  <i class="bi bi-pencil" aria-hidden="true"></i>
</button>

<!-- ✓ CORRECT: Indonesian only -->
<button aria-label="Edit pengguna John">
  <i class="bi bi-pencil" aria-hidden="true"></i>
</button>
```

### 5. Be Specific About What's Being Acted Upon
```html
<!-- ✗ WRONG: Too vague -->
<button aria-label="Hapus">
  <i class="bi bi-trash" aria-hidden="true"></i>
</button>

<!-- ✓ CORRECT: Clear what's being deleted -->
<button aria-label="Hapus pengguna John Doe">
  <i class="bi bi-trash" aria-hidden="true"></i>
</button>
```

### 6. Don't Repeat Visible Text in aria-label
```html
<!-- ✗ WRONG: Redundant with visible text -->
<button aria-label="Simpan perubahan">
  Simpan perubahan
</button>

<!-- ✓ CORRECT: Visible text sufficient, no aria-label needed -->
<button>
  Simpan perubahan
</button>

<!-- ✓ ALSO CORRECT: aria-label for icon-only button -->
<button aria-label="Simpan perubahan">
  <i class="bi bi-save" aria-hidden="true"></i>
</button>
```

---

## Common Action Words (Indonesian)

| Action | Usage | Example |
|--------|-------|---------|
| Edit / Ubah | Modify existing item | "Edit pengguna John Doe" |
| Hapus | Remove item | "Hapus kelas 10A" |
| Proses | Process/approve report | "Proses laporan #12345" |
| Tolak | Reject report | "Tolak laporan #12345" |
| Unduh | Download file | "Unduh laporan PDF" |
| Nonaktifkan | Deactivate item | "Nonaktifkan kode QR ABC123" |
| Aktifkan | Activate item | "Aktifkan pengguna John" |
| Lihat | View details | "Lihat detail laporan #12345" |
| Buka | Open modal/page | "Buka detail pengguna" |
| Tutup | Close dialog/menu | "Tutup dialog" |
| Tambah | Add new item | "Tambah pengguna baru" |
| Ekspor | Export data | "Ekspor ke Excel" |

---

## Implementation Checklist

When adding a new button or interactive element:

- [ ] Button has `aria-label` if icon-only OR unclear action
- [ ] `aria-label` follows "{action} {object} {identifier}" format
- [ ] Icons have `aria-hidden="true"`
- [ ] Dropdown toggles have `aria-expanded` attribute
- [ ] `aria-expanded` updates when state changes (Alpine.js binding)
- [ ] Text is Indonesian (not English or mixed)
- [ ] Label is specific and clear
- [ ] No redundancy with visible text
- [ ] Keyboard accessible (Tab, Enter, Escape)

---

## Testing aria-label

### Browser DevTools
1. Right-click button → Inspect
2. Look for `aria-label` in HTML
3. Screen reader simulation: use Chrome Accessibility Audit

### Screen Reader (NVDA, JAWS)
1. Enable screen reader
2. Tab to button
3. Verify: screen reader announces aria-label (not "button" alone)
4. Example: "Edit pengguna John Doe, button"

### Accessibility Audit (Wave, Lighthouse)
1. Open page in Chrome
2. DevTools → Lighthouse → Accessibility
3. Check: "All buttons have visible text"
4. Verify: aria-labels counted as "visible text" for icon-only buttons

---

## Notes

- This convention applies across **all pages**: admin, public, authenticated
- Consistency is key: same action type uses same phrasing everywhere
- Always pair aria-label with `aria-hidden="true"` on decorative icons
- For dropdown menus, `aria-expanded` state is critical for accessibility
- Test with keyboard navigation (Tab key) on all pages
- Regularly audit with accessibility tools (Wave, Lighthouse)

</content>
</invoke>