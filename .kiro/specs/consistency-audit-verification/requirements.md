# Requirements: Comprehensive Consistency Audit & Verification

## Overview

**Problem**: Aplikasi LAPORIN sudah memiliki modal, search/filter, buttons, dan berbagai fitur di Phase 1-3, namun perlu comprehensive audit untuk memastikan SEMUA halaman, menu, tombol, modal KONSISTEN, BENAR, dan BERFUNGSI dengan baik.

**Goal**: Comprehensive audit semua halaman untuk memastikan consistency di:
1. UI styling (buttons, forms, modals, tables, badges, spacing)
2. Functionality (search filters, modals, pagination, validation)
3. Accessibility (labels, focus trap, keyboard navigation)
4. Mobile responsiveness
5. Error handling

---

## Requirements per Halaman

### HALAMAN PUBLIC (GUEST)

#### `/` (Buat Laporan)
- WHEN: User membuka halaman
  - THEN: Form laporan ditampilkan dengan lengkap
  - THEN: Field tersedia (type, student_id, description, reporter_phone, attachments)
  - THEN: Semua field punya label yang jelas
  - THEN: Required fields ditandai dengan asterisk/badge `required`
  - THEN: Helper text tersedia di bawah setiap field (jika perlu)
  - THEN: Form responsive di mobile (full-width, stacked vertikal)
  - THEN: Submit button styled `btn btn-laporin` (green, white text)
  - THEN: Attachment preview/validation terlihat

#### `/lapor-pembullyan` (Panduan)
- WHEN: User membuka halaman
  - THEN: Content ditampilkan dengan proper formatting dan hierarchy
  - THEN: Navigation links tersedia (kembali ke home, FAQ, Lacak)
  - THEN: Responsive di semua ukuran layar
  - THEN: No broken links

#### `/faq` (FAQ)
- WHEN: User membuka halaman
  - THEN: FAQ content ditampilkan dengan accordion atau section format
  - THEN: Mobile responsive
  - THEN: Search/filter jika banyak item

#### `/lacak` (Lacak Laporan)
- WHEN: User submit tracking form
  - THEN: Results ditampilkan dengan report number, status, timeline
  - THEN: Add info form tersedia (jika status allows)
  - THEN: Confirm button tersedia (jika status allows)
  - THEN: Form validation client & server side
  - THEN: Empty state jika no results

### HALAMAN AUTH

#### `/login`
- WHEN: User access halaman
  - THEN: Form login ditampilkan (email, password)
  - THEN: Labels ada dengan `for` attribute
  - THEN: Required fields marked
  - THEN: Submit button `btn btn-laporin`
  - THEN: Error messages display jika login fail (inline, tidak di top)
  - THEN: "Remember me" checkbox tersedia
  - THEN: "Lupa password?" link tersedia
  - THEN: Form responsive

#### `/register` (jika ada)
- WHEN: User access halaman
  - THEN: Registration form ada
  - THEN: Email validation (format, uniqueness)
  - THEN: Password requirements clear
  - THEN: Success message setelah register

### HALAMAN INTERNAL (AUTHENTICATED)

#### `/dashboard`
- WHEN: User login, akses dashboard
  - THEN: Dashboard content ditampilkan sesuai role
  - THEN: Chart/stats display jika ada
  - THEN: Navigation links ke halaman role-specific
  - THEN: User info chip display (nama, role, avatar jika ada)
  - THEN: No 404 errors, all links working

#### `/dashboard/siswa` (Student Dashboard - jika ada)
- WHEN: Student akses dashboard
  - THEN: Student-specific content ditampilkan (points, violations, reports)

#### `/reports/{id}` (Report Detail)
- WHEN: Report page dibuka
  - THEN: Report detail ditampilkan (semua field)
  - THEN: Action buttons ditampilkan sesuai status (edit, process, reject, dll)
  - THEN: History/timeline ditampilkan
  - THEN: Note form tersedia
  - THEN: Attachments ditampilkan dengan download link
  - THEN: Mobile responsive
  - THEN: No broken attachment links

### HALAMAN ADMIN

#### `/admin/users`
- WHEN: User access halaman
  - THEN: Search form tersedia (search by name/email, status filter, role filter)
  - THEN: Pagination display dengan preserve filters
  - THEN: Table responsive dengan horizontal scroll di mobile
  - THEN: Results info "Menampilkan X dari Y hasil"
  - THEN: Empty state message jika no results
  - WHEN: Search/filter applied
    - THEN: Results filter correctly
    - THEN: Pagination preserve filters (querystring intact)
  - WHEN: Edit button clicked
    - THEN: Modal buka dengan form pre-filled
    - THEN: Modal focusable (Tab/Escape works)
    - THEN: Form fields punya labels dengan `for` attribute
    - THEN: Form validation inline
    - THEN: Modal form submit update user
    - THEN: Success message display
  - WHEN: Delete button clicked
    - THEN: Confirm dialog show (tidak langsung delete)
    - THEN: Delete works, user removed from table

#### `/admin/master/{resource}` (Master Data - Kelas, Mapel, Unit Staf, Lokasi, Jenis Pelanggaran, Kategori Kerusakan)
- WHEN: Access halaman
  - THEN: Search form ada, status filter ada
  - THEN: Table display resources dengan responsive
  - THEN: Pagination preserve filters
  - WHEN: Edit button clicked
    - THEN: Modal buka dengan form sesuai resource type
    - THEN: Form fields pre-filled
    - THEN: Form submit update resource
    - THEN: Success message display
  - WHEN: Delete button clicked
    - THEN: Confirm dialog show
    - THEN: Delete works atau soft delete

#### `/admin/qrcodes`
- WHEN: Create form display
  - THEN: Type selector show (General, Class, Location)
  - THEN: Conditional fields show (class_id atau location_id based on type)
  - THEN: Form validation works
- WHEN: Search/filter applied
  - THEN: Table filter correctly
  - THEN: Results display dengan QR code preview/thumbnail
- WHEN: Download button clicked
  - THEN: PNG file download berhasil
- WHEN: Deactivate button clicked
  - THEN: Confirm dialog show
  - THEN: Deactivate works, record updated

#### `/admin/audit` (Audit Log)
- WHEN: Audit page open
  - THEN: Search form ada (search text, action filter, date range)
  - THEN: Results display dengan timestamp, action, model, id, user
  - THEN: Pagination preserve filters
  - THEN: Timestamps formatted consistently
  - THEN: Mobile responsive

### HALAMAN ROLE-SPECIFIC

#### `/kesiswaan` (Kesiswaan Dashboard)
- WHEN: Kesiswaan user access halaman
  - THEN: Search form ada (search, status filter, date range)
  - THEN: Report cards display dengan status badge
  - THEN: Pagination atau infinite scroll
  - WHEN: Process button clicked
    - THEN: Form show dengan fields (student_id, violation_type_id, note)
    - THEN: Form modal atau new page
    - THEN: Form submit process violation
    - THEN: Success message
  - WHEN: Reject button clicked
    - THEN: Form show dengan reason (required)
    - THEN: Form modal atau new page
    - THEN: Form submit reject report
    - THEN: Success message

#### `/sarpras` (Sarpras Dashboard)
- WHEN: Sarpras user access halaman
  - THEN: Search form ada (search, status filter, priority filter, date range)
  - THEN: Report cards display dengan status badge
  - THEN: Pagination atau infinite scroll
  - WHEN: Process button clicked
    - THEN: Form show dengan fields (priority, scheduled_repair_at, repair_photo, note)
    - THEN: File upload validate image file type
    - THEN: File upload validate file size
    - THEN: Form modal atau new page
    - THEN: Form submit process damage
    - THEN: Success message
  - WHEN: Reject button clicked
    - THEN: Form show dengan reason (required)
    - THEN: Form submit reject
    - THEN: Success message

---

## NAVBAR / MENU

### Guest Navbar
- `Buat Laporan` (active jika di `/`)
- `Panduan Lapor` (active jika di `/lapor-pembullyan`)
- `Alur Validasi` (anchor ke `#`)
- `Lacak` (active jika di `/lacak`)
- `FAQ` (active jika di `/faq`)
- Login button / login link

### Auth Navbar (Role-based)

**Semua Roles**:
- Dashboard (active jika di `/dashboard`)
- Profile (link ke `/profile`)
- Logout button

**Kesiswaan + Superadmin**:
- Kesiswaan (active jika di `/kesiswaan`)

**Sarpras + Superadmin**:
- Sarpras (active jika di `/sarpras`)

**Superadmin Only**:
- Panel Admin dropdown (active jika di `/admin/*`)
  - Pengguna
  - Kode QR
  - Catatan Audit
  - [separator]
  - Master Data dropdown
    - Kelas
    - Mapel
    - Unit Staf
    - Lokasi
    - Jenis Pelanggaran
    - Kategori Kerusakan

### Navbar Requirements
- THEN: Active link highlighting visible (different color/underline)
- THEN: Dropdown smooth open/close
- THEN: Mobile hamburger menu responsive
- THEN: All links working (no 404)
- THEN: Logout works
- THEN: Role-based menu items show/hidden correctly

---

## CONSISTENCY STANDARDS

### Button Styling
- **Primary**: `btn btn-laporin` (green, white text, solid)
- **Secondary**: `btn btn-outline-secondary` (gray outline)
- **Danger**: `btn btn-outline-danger` (red outline)
- **Size**: `btn-sm` untuk row actions, default (`btn-md`) untuk main actions
- **State**: `@disabled(condition)` untuk disabled buttons (greyed out, not clickable)
- **All buttons**:
  - Consistent padding (12-16px vertical, 16-24px horizontal)
  - Consistent border-radius (4px-6px)
  - Consistent spacing between buttons (8px gap dengan `gap-2`)
  - Hover state visible (darker shade atau shadow)
  - Active state clear

### Modal Styling
- **Component**: `<x-modal name="edit-resource" focusable>`
- **Structure**:
  - Header: title + helper text (jika ada)
  - Body: form atau content
  - Footer: Batal button + Simpan button (right-aligned)
- **All edit operations**:
  - Use modal (no page redirect)
  - Modal semi-transparent backdrop
  - Modal backdrop clickable untuk close (optional)
- **Modal focusable**:
  - Tab / Shift+Tab navigation within modal only
  - Escape key closes modal
  - First form field focused saat modal open
  - Last element Tab wraps ke first
- **Form fields dalam modal**:
  - Labels ada dengan `for` attribute
  - Required fields marked dengan `required` class di label + `required` attribute di input
  - Errors display dalam `.invalid-feedback` bawah field (red text)
  - Helper text dalam `<small class="text-muted">` bawah input (grey text)

### Form Styling
- **Labels**: `<label class="form-label">` dengan `for` attribute matching input id
- **Inputs**:
  - Text inputs: `class="form-control"`
  - Select: `class="form-select"`
  - Textarea: `class="form-control"`
  - Checkbox/Radio: `class="form-check-input"`
- **Required indicators**:
  - `required` attribute di input
  - `required` class di label
  - Asterisk (*) atau badge visible di label
- **Validation**:
  - Client-side validation (HTML5 attributes: `required`, `pattern`, `min`, `max`, etc)
  - Server-side validation (Laravel validation)
  - Error messages show inline dalam `.invalid-feedback` (red text)
  - Success state: green border atau checkmark
- **Helper text**:
  - `<small class="text-muted">` bawah input
  - Jelas dan berguna (contoh: "Format: DD/MM/YYYY")
- **Grid**:
  - `row g-3` untuk spacing antar field
  - `col-md-6` atau `col-lg-4` untuk responsive columns
  - Full-width di mobile

### Search/Filter Form Styling
- **Container**: `laporin-card mb-4` (white bg, shadow, rounded)
- **Grid**: `row g-3 align-items-end` (spacing consistent)
- **Input columns**: `col-md-6 col-lg-3` atau `col-lg-4` responsive
- **Values preserved**:
  - Text inputs: `value="{{ request('field') }}"`
  - Select: `@selected(request('field') === $value)`
  - Checkbox: `@checked(request('checked'))`
  - Date: `value="{{ request('date_from') }}"`
- **Buttons**:
  - Submit button: `btn btn-laporin` (primary)
  - Reset link: `btn btn-outline-secondary` atau `<a href="{{ route(...) }}" class="btn btn-outline-secondary">Reset</a>`
  - Buttons grouped di `col-auto` atau `col-md-12` di mobile
- **Spacing**: `gap-2` consistent antar elemen

### Table Styling
- **Wrapper**: `<div class="table-responsive">`
- **Table**: `class="table align-middle"`
  - Headers: `<thead>` dengan borders
  - Rows: `<tbody>`
  - Striped: `table-striped` optional
- **Results info**:
  - "Menampilkan X dari Y hasil" above atau below table
  - Optional: "Per page: 10 | 25 | 50"
- **Empty state**:
  - If no results: `<tr><td colspan="X" class="text-center text-muted py-4">Belum ada data</td></tr>`
- **Pagination**:
  - Below table: `.appends(request()->query())->links()`
  - Preserve filters: `->appends($request->query())`
  - Responsive: stacked vertikal di mobile
- **Row actions**:
  - `btn btn-sm btn-outline-laporin` untuk Edit
  - `btn btn-sm btn-outline-danger` untuk Delete
  - Actions grouped dalam `<td>` dengan `text-end`
  - Gap-2 antar button (8px)

### Badge Styling
- **Status badges**: `<span class="badge text-bg-XXX">`
- **Colors per status**:
  - pending: `text-bg-warning` (yellow/orange)
  - processed: `text-bg-info` (blue)
  - completed: `text-bg-success` (green)
  - rejected: `text-bg-danger` (red)
- **All badges**:
  - Readable text color (white atau dark based on background)
  - Consistent padding (4px 8px)
  - Consistent border-radius (3px-4px)
  - Aligned dengan center di table cells

### Responsive Design
- **Mobile (< 768px)**:
  - Single column
  - Full width buttons, inputs, cards
  - Forms stack vertically
  - Tables punya horizontal scroll wrapper
  - Modals full-screen atau 90% width
  - Navbar hamburger menu
- **Tablet (768px - 1023px)**:
  - 2 columns untuk grids
  - 50% width forms
  - Tables scrollable
  - Navbar collapse ke hamburger
- **Desktop (1024px+)**:
  - Full layout design
  - Multi-column forms
  - Tables full-width
  - Navbar expanded

### Accessibility
- **Labels**:
  - All inputs have associated `<label>` dengan `for` attribute
  - `for` attribute matches input `id` exactly
  - Screen readers read labels correctly
- **Modal**:
  - `focusable` attribute active (focus trap works)
  - Keyboard navigation: Tab/Shift+Tab works
  - Escape key closes modal
- **Keyboard Navigation**:
  - Tab navigates through all interactive elements
  - Shift+Tab navigates backwards
  - Escape closes modal/dialog
  - Enter submits form
  - Form inputs accessible via keyboard
- **Error Messages**:
  - Error messages linked to form field (via `aria-describedby` atau visually clear)
  - Error messages readable (red text, sufficient contrast)
  - Error messages actionable (tell user what to fix)
- **Helper Text**:
  - Jelas dan berguna
  - Not essential info (nice-to-have, not blocking)
  - Sufficient contrast (grey text readable)
- **Color Contrast**:
  - WCAG AA compliant (4.5:1 untuk text, 3:1 untuk graphics)
  - No relies on color alone (use icons, patterns, text labels)
- **Semantic HTML**:
  - Form elements: `<form>`, `<label>`, `<input>`, `<select>`, etc
  - Tables: `<table>`, `<thead>`, `<tbody>`, `<tr>`, `<th>`, `<td>`
  - Buttons: `<button>` atau `<a role="button">`

### Error Handling
- **Required field validation**:
  - Client-side: HTML5 `required` attribute (browser validation)
  - Server-side: Laravel validation `required` rule
  - Error message display inline dalam form (red text, `.invalid-feedback`)
  - Field styling berubah (red border)
- **Format validation**:
  - Email: `email` rule + HTML5 `type="email"`
  - Phone: custom pattern jika ada
  - Date: `date` rule + HTML5 `type="date"` atau `type="text"`
  - Number: `numeric` rule + HTML5 `type="number"`
- **Server-side validation errors**:
  - Show inline dalam form (tidak di top alert)
  - Redirect back ke form dengan `withErrors()`
  - Old input preserved: `old('field')`
  - Specific field error message jelas
- **Global validation errors**:
  - Non-field-specific errors (system errors, authentication, etc)
  - Show di top alert (dismissible) dengan `alert alert-danger`
- **Empty state**:
  - Table: "Belum ada data" centered, muted
  - Search results: "Tidak ada hasil ditemukan untuk '...'"
  - List: "Belum ada item"
- **Invalid input**:
  - Silently ignored (allowlist validation)
  - XSS prevention: all user input escaped dengan `{{ }}` atau `{{{ }}}` di Blade
  - CSRF protection: all forms punya `@csrf` token
- **404 Pages**:
  - Custom 404 page dengan user-friendly message
  - Link back to home / previous page
  - Professional design consistent dengan app

---

## SUCCESS CRITERIA

✅ **UI Consistency**:
- All pages follow consistent button styling (colors, sizes, states)
- All pages follow consistent modal styling (header, body, footer, focus trap)
- All pages follow consistent form styling (labels, inputs, validation, errors)
- All search/filter forms follow consistent structure
- All tables follow consistent styling (headers, rows, pagination)
- All badges follow consistent colors per status
- All spacing/padding consistent (grid, gaps)

✅ **Functionality**:
- All edit operations use modal (no page redirect)
- All forms validate client & server side
- All search/filter forms preserve values & filters
- All pagination preserve filters
- All buttons functional (no broken links, submit works)
- All modals open/close properly (Tab/Escape works)

✅ **Mobile Responsiveness**:
- All pages responsive mobile (< 768px)
- All forms stack vertically di mobile
- All tables punya horizontal scroll di mobile
- All modals fit mobile screens
- Navbar hamburger works di mobile

✅ **Accessibility**:
- All inputs have associated labels
- All modals focusable (Tab/Escape works)
- All keyboard navigation works (Tab, Shift+Tab, Escape, Enter)
- All error messages clear & linked to fields
- All helper text readable & helpful
- Color contrast WCAG AA compliant

✅ **Error Handling**:
- Required field validation works
- Format validation works (email, phone, date, etc)
- Server-side validation errors display inline
- Global errors display in top alert
- Empty state messages clear
- 404 pages handled gracefully

✅ **Quality Assurance**:
- No broken links (all 200 status)
- No 404 errors (except intentional)
- No console errors (JavaScript)
- No broken attachments
- Menu punya active link highlighting
- All functionality tested & working

---

## Notes

- Aplikasi sudah punya sebagian besar elemen ini dari Phase 1-3, spec ini adalah verification & standardization
- Fokus pada consistency (semua halaman mengikuti pola yang sama)
- No new features, cuma audit & fix existing
- Mobile-first approach
- User-friendly Indonesian copy
