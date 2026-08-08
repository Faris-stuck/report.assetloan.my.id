# Tasks: Consistency Audit & Verification

## Phase 1: Comprehensive Audit (Manual Verification Per Page)

### 1.1 Audit `/admin/users` Page
- [ ] **UI Elements**:
  - [ ] Search form displays (search by name/email, status filter, role filter)
  - [ ] Search form styling: `laporin-card mb-4`, `row g-3 align-items-end`
  - [ ] Search form buttons: Submit `btn btn-laporin`, Reset `btn btn-outline-secondary`
  - [ ] Table displays with responsive wrapper
  - [ ] Table headers clear, borders visible
  - [ ] Results info displays ("Menampilkan X dari Y hasil")
  - [ ] Empty state message if no results
  - [ ] Pagination displays below table
  - [ ] Edit buttons display (styled `btn btn-sm btn-outline-laporin`)
  - [ ] Delete buttons display (styled `btn btn-sm btn-outline-danger`)

- [ ] **Functionality**:
  - [ ] Search by name works
  - [ ] Search by email works
  - [ ] Status filter works
  - [ ] Role filter works
  - [ ] Pagination works
  - [ ] Filters preserved when paginating (.appends() works)
  - [ ] Edit button opens modal
  - [ ] Modal displays form pre-filled
  - [ ] Form labels have `for` attributes
  - [ ] Required fields marked (`required` class + attribute)
  - [ ] Form submit updates user
  - [ ] Success message displays after update
  - [ ] Modal closes after submit
  - [ ] Delete button shows confirm dialog
  - [ ] Delete works, user removed

- [ ] **Accessibility & Mobile**:
  - [ ] Form labels associated with inputs (for/id match)
  - [ ] Tab navigation works (form → modal → back)
  - [ ] Escape closes modal
  - [ ] Mobile: Form stacks vertically
  - [ ] Mobile: Table has horizontal scroll
  - [ ] Mobile: Buttons responsive
  - [ ] Errors display inline (`.invalid-feedback`)

- [ ] **Issues Found**: (Document any issues)
  - Issue 1: ...
  - Issue 2: ...

**Status**: [ ] Ready | [ ] Issues Found | [ ] Fixed

---

### 1.2 Audit `/admin/master/{resource}` (Kelas, Mapel, Unit Staf, Lokasi, Jenis Pelanggaran, Kategori Kerusakan)

- [ ] **UI Elements** (repeat for each resource):
  - [ ] Search form displays
  - [ ] Search form styling consistent with 1.1
  - [ ] Table displays resources
  - [ ] Edit buttons display
  - [ ] Delete buttons display
  - [ ] Status filter works

- [ ] **Functionality**:
  - [ ] Search works
  - [ ] Status filter works
  - [ ] Pagination works
  - [ ] Filters preserved
  - [ ] Edit button opens modal
  - [ ] Modal form pre-filled with resource data
  - [ ] Form fields match resource type (e.g., class name, subject name)
  - [ ] Form submit updates resource
  - [ ] Success message displays
  - [ ] Delete works

- [ ] **Consistency Check**:
  - [x] Modal styling same as `/admin/users` modal
  - [ ] Form styling same as `/admin/users` form
  - [ ] Button styling same
  - [ ] Table styling same
  - [ ] Search form styling same

- [ ] **Issues Found**:
  - Issue 1: ...

**Status**: [ ] Ready | [ ] Issues Found | [ ] Fixed

---

### 1.3 Audit `/admin/qrcodes` Page

- [ ] **UI Elements**:
  - [ ] Create form displays (type selector visible)
  - [ ] Type selector: General, Class, Location options visible
  - [ ] Conditional fields show (class_id if Class selected, location_id if Location selected)
  - [ ] Search/filter form displays
  - [ ] Table displays QR codes
  - [ ] Download button displays (`btn btn-sm btn-outline-laporin`)
  - [ ] Deactivate button displays (`btn btn-sm btn-outline-danger`)

- [ ] **Functionality**:
  - [ ] Type selector changes conditional fields correctly
  - [ ] Search works
  - [ ] Filter works (type filter, status filter, etc)
  - [ ] Download button downloads PNG file
  - [ ] Deactivate button shows confirm dialog
  - [ ] Deactivate updates QR code status
  - [ ] Pagination works
  - [ ] Filters preserved

- [ ] **Issues Found**:
  - Issue 1: ...

**Status**: [ ] Ready | [ ] Issues Found | [ ] Fixed

---

### 1.4 Audit `/admin/audit` Page

- [ ] **UI Elements**:
  - [ ] Search form displays (search text, action filter, date range)
  - [ ] Table displays audit logs
  - [ ] Columns visible: timestamp, action, model, id, user, changes
  - [ ] Pagination displays

- [ ] **Functionality**:
  - [ ] Search works
  - [ ] Action filter works
  - [ ] Date range filter works
  - [ ] Timestamps formatted consistently
  - [ ] Pagination works
  - [ ] Filters preserved
  - [ ] All data readable (not truncated without scrolling)

- [ ] **Issues Found**:
  - Issue 1: ...

**Status**: [ ] Ready | [ ] Issues Found | [ ] Fixed

---

### 1.5 Audit `/kesiswaan` Page

- [ ] **UI Elements**:
  - [ ] Search form displays (search, status filter, date range)
  - [ ] Report cards display (or table, depending on design)
  - [ ] Status badge displays on cards (pending, processed, rejected)
  - [ ] Action buttons display on cards (Process, Reject, View)
  - [ ] Pagination displays

- [ ] **Functionality**:
  - [ ] Search works
  - [ ] Status filter works
  - [ ] Date range filter works
  - [ ] Process button opens modal
  - [ ] Modal form shows fields: student_id, violation_type_id, note
  - [ ] Form labels present
  - [ ] Required fields marked
  - [ ] Form submit processes violation (backend works)
  - [ ] Success message displays
  - [ ] Modal closes
  - [ ] Reject button opens modal
  - [ ] Reject modal shows reason field (required)
  - [ ] Reject form submit works
  - [ ] Pagination works
  - [ ] Filters preserved

- [ ] **Badge Styling**:
  - [ ] Status badges consistent (colors per status)
  - [ ] Badges readable (contrast ok)

- [ ] **Issues Found**:
  - Issue 1: ...

**Status**: [ ] Ready | [ ] Issues Found | [ ] Fixed

---

### 1.6 Audit `/sarpras` Page

- [ ] **UI Elements**:
  - [ ] Search form displays (search, status filter, priority filter, date range)
  - [ ] Report cards display
  - [ ] Status badge displays
  - [ ] Priority badge displays (if applicable)
  - [ ] Action buttons display (Process, Reject, View)

- [ ] **Functionality**:
  - [ ] Search works
  - [ ] Status filter works
  - [ ] Priority filter works
  - [ ] Date range filter works
  - [ ] Process button opens modal
  - [ ] Modal form shows fields: priority, scheduled_repair_at, repair_photo, note
  - [ ] File upload validates image type
  - [ ] File upload validates file size
  - [ ] Form labels present
  - [ ] Required fields marked
  - [ ] Form submit processes damage (backend works)
  - [ ] Success message displays
  - [ ] Modal closes
  - [ ] Reject button opens modal
  - [ ] Reject form submit works
  - [ ] Pagination works
  - [ ] Filters preserved

- [ ] **Issues Found**:
  - Issue 1: ...

**Status**: [ ] Ready | [ ] Issues Found | [ ] Fixed

---

### 1.7 Audit Public Pages

#### `/` (Buat Laporan)
- [ ] Form displays completely
- [ ] All fields visible (type, student_id, description, reporter_phone, attachments)
- [ ] Labels present for all fields
- [ ] Required fields marked
- [ ] Submit button displays (`btn btn-laporin`)
- [ ] Attachment preview/validation visible
- [ ] Form responsive (mobile: full-width, stacked)
- [ ] Error display inline (if form has validation)

#### `/lapor-pembullyan` (Panduan)
- [ ] Content displays with proper hierarchy
- [ ] Navigation links work (back, FAQ, Lacak)
- [ ] Responsive
- [ ] No broken links
- [ ] No console errors

#### `/faq` (FAQ)
- [ ] FAQ content displays (accordion or sections)
- [ ] Responsive
- [ ] No broken links

#### `/lacak` (Lacak Laporan)
- [ ] Tracking form displays
- [ ] Form fields visible (report_number atau reference_id)
- [ ] Form submit works
- [ ] Results display (report number, status, timeline)
- [ ] Add info form displays (if applicable)
- [ ] Confirm button displays (if applicable)
- [ ] Empty state message if no results

- [ ] **Issues Found**:
  - Issue 1: ...

**Status**: [ ] Ready | [ ] Issues Found | [ ] Fixed

---

### 1.8 Audit Navbar & Menu

#### Guest Navbar
- [ ] Menu items display: Buat Laporan, Panduan Lapor, Alur Validasi, Lacak, FAQ
- [ ] Active link highlighted (current page)
- [ ] All links working (no 404)
- [ ] Login button displays
- [ ] Responsive (hamburger menu on mobile)

#### Auth Navbar (Test with different roles)
- [ ] Dashboard link displays (all roles)
- [ ] Profile link displays (all roles)
- [ ] Logout button displays (all roles)
- [ ] Active link highlighted

**Kesiswaan User**:
- [ ] Kesiswaan link displays
- [ ] Sarpras link HIDDEN (no access)
- [ ] Admin dropdown HIDDEN (no access)

**Sarpras User**:
- [ ] Sarpras link displays
- [ ] Kesiswaan link HIDDEN (no access)
- [ ] Admin dropdown HIDDEN (no access)

**Superadmin User**:
- [ ] Kesiswaan link displays
- [ ] Sarpras link displays
- [ ] Admin dropdown displays
- [ ] Admin dropdown items: Pengguna, Kode QR, Catatan Audit
- [ ] Master Data dropdown displays with items: Kelas, Mapel, Unit Staf, Lokasi, Jenis Pelanggaran, Kategori Kerusakan
- [ ] All links in dropdown working

- [ ] **Mobile**:
  - [ ] Hamburger menu displays on mobile
  - [ ] Menu opens/closes smoothly
  - [ ] Menu items accessible

- [ ] **Issues Found**:
  - Issue 1: ...

**Status**: [ ] Ready | [ ] Issues Found | [ ] Fixed

---

### 1.9 Comprehensive Consistency Check Across All Pages

- [ ] **Button Styling**:
  - [ ] Primary buttons: `btn btn-laporin` (green)
  - [ ] Secondary buttons: `btn btn-outline-secondary` (grey)
  - [ ] Danger buttons: `btn btn-outline-danger` (red)
  - [ ] Small buttons: `btn btn-sm` on row actions
  - [ ] Disabled buttons: greyed out, not clickable
  - [ ] All buttons same padding, radius, spacing

- [ ] **Form Styling**:
  - [ ] Labels: `form-label`, `for` attribute present
  - [ ] Inputs: `form-control` class
  - [ ] Selects: `form-select` class
  - [ ] Required fields: `required` attribute + class
  - [ ] Errors: `.invalid-feedback` display
  - [ ] Helper text: `<small class="text-muted">`
  - [ ] Grid: `row g-3` spacing consistent

- [ ] **Modal Styling**:
  - [ ] All modals: `x-modal` component
  - [ ] Header: title + helper text
  - [ ] Body: form or content
  - [ ] Footer: Batal + Simpan buttons
  - [ ] Focus trap works (Tab, Escape)
  - [ ] All modals have same styling

- [ ] **Search Form Styling**:
  - [ ] Container: `laporin-card mb-4`
  - [ ] Grid: `row g-3 align-items-end`
  - [ ] Input columns: responsive (`col-md-6 col-lg-X`)
  - [ ] Values preserved
  - [ ] Buttons: Submit + Reset
  - [ ] All search forms same styling

- [ ] **Table Styling**:
  - [ ] Wrapper: `table-responsive`
  - [ ] Table: `table align-middle`
  - [ ] Results info visible
  - [ ] Empty state message
  - [ ] Pagination preserve filters
  - [ ] Row actions: `btn btn-sm` styled correctly

- [ ] **Badge Styling**:
  - [ ] Status badges: `badge text-bg-XXX`
  - [ ] Colors consistent per status:
    - pending: warning (orange/yellow)
    - processed: info (blue)
    - completed: success (green)
    - rejected: danger (red)
  - [ ] All badges readable

- [ ] **Spacing & Padding**:
  - [ ] Form fields: `g-3` gap
  - [ ] Buttons: `gap-2` between them
  - [ ] Cards: consistent padding
  - [ ] Modals: consistent padding
  - [ ] All spacing consistent

- [ ] **Responsive Design**:
  - [ ] Mobile (< 768px): single column, full-width
  - [ ] Tablet (768px+): 2 columns
  - [ ] Desktop (1024px+): full layout
  - [ ] Forms stack vertically on mobile
  - [ ] Tables scroll horizontally on mobile
  - [ ] Modals fit mobile screens

- [ ] **Accessibility**:
  - [ ] All inputs have labels (for/id match)
  - [ ] Modal focus trap works
  - [ ] Tab navigation works through all pages
  - [ ] Escape closes modals
  - [ ] Errors linked to fields (visual or aria)
  - [ ] Helper text readable
  - [ ] Color contrast WCAG AA

- [ ] **Error Handling**:
  - [ ] Required field validation works
  - [ ] Format validation works (email, phone, date)
  - [ ] Server-side validation errors display inline
  - [ ] Old values preserved after error
  - [ ] Empty state messages clear
  - [ ] 404 pages handled gracefully
  - [ ] No console JavaScript errors
  - [ ] No broken links
  - [ ] All attachments download correctly

- [ ] **Issues Found**:
  - Issue 1: ...
  - Issue 2: ...
  - Issue 3: ...

**Status**: [ ] Ready | [ ] Issues Found | [ ] Fixed

---

## Phase 2: Fix Issues (Per Issue)

### 2.1 [Issue Title from 1.X]
- [ ] Document the issue (what's wrong, where, why)
- [ ] Apply fix in codebase
- [ ] Test the fix works
- [ ] Verify no regression in related pages
- [ ] Commit changes with message: "Fix: [issue title]"

**Issue Description**: ...
**Root Cause**: ...
**Fix Applied**: ...
**Testing**: ...
**Regression Check**: ...

---

### 2.2 [Issue Title from 1.X]
(Repeat 2.1 format for each issue)

---

## Phase 3: Final Verification

### 3.1 Full Regression Test
- [ ] All pages load without errors (200 status)
- [ ] All links working (no 404)
- [ ] All forms validate client & server side
- [ ] All buttons functional
- [ ] All modals open/close correctly
- [ ] All search/filter works
- [ ] All pagination works with filters preserved
- [ ] Mobile responsive (test on actual device or browser zoom)
- [ ] Keyboard navigation works (Tab, Escape, Enter)
- [ ] No console JavaScript errors
- [ ] No broken attachments
- [ ] Accessibility check (labels, focus, error messages)

**Issues**: 
- ...

**Status**: [ ] All Pass | [ ] Issues Remain

---

### 3.2 Create Consistency Checklist

- [ ] Create document: `docs/CONSISTENCY_CHECKLIST.md`
- [ ] Document all consistency standards applied
- [ ] Provide checkbox checklist for future development
- [ ] Include examples for each pattern (button, form, modal, table, etc)
- [ ] Include code snippets for copy-paste
- [ ] Include screenshot examples (optional)

**Checklist Contents**:
- Button styling checklist
- Form styling checklist
- Modal styling checklist
- Search form checklist
- Table checklist
- Responsive design checklist
- Accessibility checklist
- Error handling checklist

---

### 3.3 Update Documentation

- [ ] Update `docs/CODING_STANDARDS.md` with:
  - Consistency standards
  - Component naming conventions
  - CSS organization rules
  - Form patterns
  - Modal patterns
  - Button patterns

- [ ] Create `docs/FUTURE_PAGES_IMPLEMENTATION_GUIDE.md` with:
  - Page template structure
  - Component library usage
  - Form styling pattern
  - Search form pattern
  - Modal pattern
  - Mobile responsive requirements
  - Accessibility requirements
  - Code examples
  - When to use modal vs page redirect

**Documentation Files Updated**:
- [ ] docs/CODING_STANDARDS.md
- [ ] docs/FUTURE_PAGES_IMPLEMENTATION_GUIDE.md

---

## Completion Checklist

✅ Phase 1: All 9 audit tasks completed (issues documented)
✅ Phase 2: All issues fixed & verified
✅ Phase 3: Final regression test passed
✅ Phase 3: Consistency checklist created
✅ Phase 3: Documentation updated

**Final Status**: [ ] Complete | [ ] Blocked

---

## Notes

- Use manual testing per page (visual inspection + clicking)
- Document all issues found in Phase 1 before fixing
- Focus on consistency (all pages same pattern)
- No new features - only audit & fixes
- Test on actual mobile device if possible
- Browser console should have no errors
- All 200 status for successful pages (no 404)
- Mobile hamburger menu hamburger icon should be visible < 768px
