---
domain: testing
purpose: implementation-test-checklist
version: 1.0
updated: 2024-01-15
owner: qa-team
status: stable
---

# UI/UX Consistency Standards - Phase 1-2 Test Checklist

## Test Environment Setup

Before testing, ensure:
- Application is running: `php artisan serve`
- Database is migrated: `php artisan migrate`
- Sample data exists (run seeders if available)
- You're logged in as superadmin

---

## Page 1: /admin/master (Master Data)

### Visual Layout
- [ ] Page has header with title "Manajemen Master Data Referensi"
- [ ] "Tambah data tervalidasi" form is visible
- [ ] "Cari" search form appears below
- [ ] Table with data is displayed
- [ ] Pagination links at bottom

### Search & Filter Functionality
- [ ] Search input accepts text
- [ ] Status filter shows "Semua", "Aktif", "Nonaktif" options
- [ ] Clicking "Cari" button filters results
- [ ] Results show "Menampilkan X dari Y hasil" message
- [ ] Pagination preserves search/filter on page change
- [ ] "Reset" button clears all filters and search

### Modal Edit
- [ ] Clicking "Edit" button opens modal
- [ ] Modal title shows "Ubah [resource name]"
- [ ] Form fields are pre-filled with current data
- [ ] Modal has "Batal" and "Simpan" buttons
- [ ] Clicking "Batal" closes modal without changes
- [ ] Clicking "Simpan" submits form and closes modal
- [ ] Errors display inside modal after submit

### Keyboard Navigation
- [ ] Tab key moves through form fields
- [ ] Shift+Tab moves backwards
- [ ] Escape key closes modal
- [ ] Focus is trapped inside modal when open

### Responsive Design
- [ ] Desktop (1200+px): full width, good spacing
- [ ] Tablet (768px): fields stack appropriately
- [ ] Mobile (375px): single column, readable

### Test with Different Resource Types
- [ ] Test with `Kelas` (classes)
- [ ] Test with `Mata Pelajaran` (subjects)
- [ ] Test with `Unit Staf` (staff-units)
- [ ] Test with `Lokasi` (locations)
- [ ] Test with `Jenis Pelanggaran` (violation-types)
- [ ] Test with `Kategori Kerusakan` (damage-categories)

---

## Page 2: /admin/users (User Management)

### Visual Layout
- [ ] Page has header with title "Manajemen Pengguna"
- [ ] Create user form visible
- [ ] Search & filter form appears below
- [ ] Users table is displayed
- [ ] Pagination at bottom

### Search & Filter Functionality
- [ ] Search input works (try searching by name or email)
- [ ] "Peran" (Role) filter dropdown shows all roles
- [ ] "Status" filter shows Semua/Aktif/Nonaktif
- [ ] Filters can be combined (e.g., search + role + status)
- [ ] Results count shows filtered vs total
- [ ] "Reset" clears all filters

### Modal Edit
- [ ] Click "Edit" button opens modal
- [ ] Modal title shows "Ubah pengguna"
- [ ] All form fields are pre-filled:
  - [ ] Nama
  - [ ] Surel
  - [ ] Peran
  - [ ] HP
  - [ ] Aktif checkbox
- [ ] Password field is empty (can update if needed)
- [ ] Form submission works
- [ ] Modal closes after save

### Validation
- [ ] Email validation works
- [ ] Password minimum length validation (8 chars)
- [ ] Password must contain letters and numbers
- [ ] Error messages display inline in modal
- [ ] Duplicate email shows error

### Responsive
- [ ] Mobile: form fields stack properly
- [ ] Table scrolls horizontally on small screens

---

## Page 3: /admin/qrcodes (QR Code Management)

### Visual Layout
- [ ] Page has header "Manajemen Kode QR"
- [ ] Create QR form visible with type selector
- [ ] Search & filter form appears
- [ ] QR table displays with columns: Nama, Tipe, URL, Scan, Status, Aksi
- [ ] Pagination at bottom

### Create QR (Type Conditional Display)
- [ ] "Tipe" dropdown shows: Umum, Kelas, Lokasi
- [ ] When type = "Umum": no additional fields shown
- [ ] When type = "Kelas": class selector appears
- [ ] When type = "Lokasi": location selector appears
- [ ] Creating QR with different types works

### Search & Filter
- [ ] Search input finds QRs by name
- [ ] "Tipe" filter shows: Semua, Umum, Kelas, Lokasi
- [ ] "Status" filter shows: Semua, Aktif, Nonaktif
- [ ] Multiple filters work together
- [ ] Results count displayed

### Table & Actions
- [ ] Table shows all QR data clearly
- [ ] "Unduh" button downloads PNG
- [ ] "Nonaktif" button deactivates QR
- [ ] Status badges show Aktif/Nonaktif
- [ ] Confirmation dialog on deactivate

### Responsive
- [ ] URL column truncates on mobile
- [ ] Buttons stack on small screens

---

## Page 4: /admin/audit (Audit Log)

### Visual Layout
- [ ] Page has header "Catatan Audit"
- [ ] Search & filter form visible
- [ ] Audit table displays columns: Waktu, Aktor, Aksi, Model, ID
- [ ] Pagination at bottom

### Search & Filter
- [ ] Search input works (search by actor or action)
- [ ] "Aksi" filter dropdown populated with action types
- [ ] "Dari" (from date) input works
- [ ] "Sampai" (to date) input works
- [ ] Date range filtering works
- [ ] Multiple filters can combine
- [ ] Results count shows filtered vs total

### Table Display
- [ ] Timestamp displays correctly and is formatted nicely
- [ ] Action shows as blue badge
- [ ] Model type shows clearly
- [ ] ID shows as #NNN format

### Pagination
- [ ] Filters preserved when navigating pages
- [ ] "Reset" button clears all filters

### Responsive
- [ ] Desktop: all columns visible
- [ ] Tablet: columns readable
- [ ] Mobile: table scrolls if needed

---

## Cross-Browser Testing

Test all pages in:
- [ ] Chrome/Chromium (latest)
- [ ] Firefox (latest)
- [ ] Safari (if available)
- [ ] Edge (if available)

### Expected Results
- [ ] Search/filter works consistently
- [ ] Modals open/close smoothly
- [ ] No JavaScript errors in console
- [ ] Styling is consistent

---

## Accessibility Testing

### Keyboard Navigation
- [ ] Can tab through all form inputs
- [ ] Can submit forms with keyboard only
- [ ] Modals are fully keyboard navigable
- [ ] Escape closes modals
- [ ] Focus is visible (not hidden)

### Screen Reader (if available)
- [ ] Form labels are associated with inputs
- [ ] Button purposes are clear
- [ ] Error messages are announced
- [ ] Modal purpose is announced

### Visual
- [ ] Text contrast is good (dark on light)
- [ ] Text is readable at all sizes
- [ ] Colors are not the only indicator (badges have text)

---

## Performance Testing

### Speed Tests
- [ ] Search with 100+ results: < 500ms
- [ ] Filter application: instant
- [ ] Modal open/close: smooth (no lag)
- [ ] Pagination click: < 1 second response

### Network (DevTools Network Tab)
- [ ] Page load: reasonable bundle size
- [ ] Search queries: efficient (check SQL in Laravel log)
- [ ] No N+1 queries detected

---

## Data Integrity Testing

### Create Operations
- [ ] Create a new item (master, user, QR, etc.)
- [ ] Data is saved correctly to database
- [ ] Validation prevents invalid data

### Read Operations
- [ ] Opened data matches database
- [ ] Search returns correct results
- [ ] Filters exclude correct items

### Update Operations
- [ ] Open modal, change data, save
- [ ] Changes appear in table immediately
- [ ] Database updated correctly
- [ ] Modal can be opened again with new data

### Delete Operations
- [ ] Delete item shows confirmation
- [ ] Canceling confirmation does not delete
- [ ] Confirming deletes the item
- [ ] Item no longer appears in table

---

## Error Handling

### Network Errors
- [ ] Search with no results shows empty message
- [ ] Server errors show appropriate message
- [ ] Validation errors display in form/modal

### Edge Cases
- [ ] Search with special characters
- [ ] Search with very long strings
- [ ] Filter with no matching results
- [ ] Edit with empty required fields
- [ ] Delete with related records (if applicable)

---

## Regression Testing

### Existing Functionality
- [ ] Create new users (non-modal path still works if applicable)
- [ ] Export/Download functions (QR PNG download works)
- [ ] Navigation to other pages works
- [ ] Logout still works
- [ ] Session timeout works

---

## Test Results Summary

| Page | Search | Filter | Modal | Pagination | Mobile | Status |
|------|--------|--------|-------|------------|--------|--------|
| /admin/master | [ ] | [ ] | [ ] | [ ] | [ ] | ⬜ |
| /admin/users | [ ] | [ ] | [ ] | [ ] | [ ] | ⬜ |
| /admin/qrcodes | [ ] | [ ] | [ ] | [ ] | [ ] | ⬜ |
| /admin/audit | [ ] | [ ] | [ ] | [ ] | [ ] | ⬜ |

**Status Legend**: ⬜ Not Started | 🟨 In Progress | 🟩 Passed | 🟥 Failed

---

## Critical Issues Found

(Record any critical issues that prevent functionality)

### Issue #1
- **Page**: _______________
- **Feature**: _______________
- **Description**: _______________
- **Severity**: Critical / High / Medium / Low

---

## Minor Issues / Suggestions

(Record non-critical issues and suggestions for improvement)

### Issue #1
- **Page**: _______________
- **Observation**: _______________

---

## Sign-Off

- **Tester Name**: _______________
- **Date**: _______________
- **Overall Status**: ✅ PASS / ⚠️ PASS WITH NOTES / ❌ FAIL

**Notes**:
_______________________________________________________________________________
_______________________________________________________________________________

