# Requirements: Week 2-3 Mobile UI/UX Optimization

## Overview

**Problem**: Week 1 completed critical mobile fixes (form heights 44px, step dots, checkboxes, admin tables as cards, accordion forms). However, comprehensive audit (15 Critical, 38 Major, 41 Minor issues) revealed significant remaining mobile optimization needed across 12+ pages. Mobile users on 375px-414px screens face accessibility issues (touch targets, focus indicators), inconsistent form layouts, poor file upload validation feedback, and navigation discoverability problems.

**Goal**: Complete mobile-first responsive design optimization for all authenticated pages (dashboards, admin panel, role-specific workflows) and public pages. Achieve:
- WCAG AAA accessibility compliance (44x44px touch targets, keyboard navigation, focus indicators)
- Consistent mobile form patterns (grid optimization, helper text standardization)
- Improved file upload validation (image preview, size/type feedback)
- Complete navigation accessibility (dropdown discoverability, menu consistency)
- Verified Lighthouse mobile score (≥85%)
- Full regression test all 12+ pages on actual mobile devices

**Scope**: Optimization ONLY (no new features). Fixes organized by feature area, affecting:
- 8 admin pages (users, QR codes, audit log, master data × 6)
- 2 role-specific dashboards (Kesiswaan, Sarpras)
- 4 public pages (home/create report, guide, FAQ, tracking)
- 3 dashboard pages (main dashboard, profile, settings)
- 1 report detail page
- Navigation/navbar across all pages

---

## Feature Requirements

### 1. FORM GRID OPTIMIZATION (Mobile-First Responsive)

**Requirement 1.1**: All forms on mobile screens use mobile-first column layout

- WHEN: Form page loaded on 375px-414px mobile screen
  - THEN: Form fields stack vertically (single column, `col-12`)
  - THEN: All inputs/selects/textareas use 100% width (max-width: 100%)
  - THEN: No horizontal scrolling or overflow
  - THEN: Label appears above input (block display)
  - THEN: Helper text appears below input (small, muted)
  - THEN: Error message appears below input (red, `.invalid-feedback`)
  - THEN: Spacing consistent between fields (`row g-3` = 16px gap)
  - THEN: Buttons stack vertically if multiple (100% width on mobile)

**Requirement 1.2**: Forms adapt to tablet/desktop screens (col-sm-6 breakpoint)

- WHEN: Form loaded on 768px+ screen (tablet/desktop)
  - THEN: Form fields display in 2-column layout (`col-md-6`) where applicable
  - THEN: Single-column fields remain full-width (e.g., email, search, rich inputs)
  - THEN: Related field pairs sit side-by-side (e.g., first_name + last_name, start_date + end_date)
  - THEN: Buttons align horizontally with `gap-2` spacing
  - THEN: Layout smooth transition (no jarring shifts)

**Requirement 1.3**: All admin search/filter forms follow grid pattern

- WHEN: Admin search form displayed
  - THEN: On mobile: `col-12` for each field (vertical stack)
  - THEN: On tablet: `col-md-6 col-lg-3` for filter fields
  - THEN: Submit/Reset buttons stack vertically on mobile, inline on tablet+
  - THEN: Grid spacing consistent (`row g-3 align-items-end`)
  - THEN: Filter values preserved on submit (querystring intact)

**Requirement 1.4**: Create/edit forms in modals use responsive grid

- WHEN: Modal form displayed
  - THEN: On mobile: Form fields stack vertically
  - THEN: On tablet+: 2-column layout where applicable
  - THEN: Modal width responsive (100% - 20px margin on mobile, 500px max on tablet+)
  - THEN: Form scrollable if exceeds viewport height (max-height: calc(100vh - 200px), overflow-y: auto)

**Requirement 1.5**: File input fields display with preview and validation feedback

- WHEN: File upload input displayed (e.g., Sarpras repair photo)
  - THEN: Input labeled clearly ("Pilih foto pemeriksaan" or similar)
  - THEN: File type validation message visible below input (`text-muted`)
  - THEN: File size limit visible below input (e.g., "Max 5MB, format JPG/PNG")
  - THEN: After file selected, preview image displays (40px × 40px thumbnail)
  - THEN: If file invalid (format, size), error message displays (red, `.invalid-feedback`)
  - THEN: If file valid, checkmark or "✓" indicator shows (green, `.valid-feedback`)
  - THEN: File name displays below preview (truncated if long)

---

### 2. ARIA LABELS & BUTTON ACCESSIBILITY

**Requirement 2.1**: All action buttons have clear aria-label attributes

- WHEN: Edit/Delete/Process/Reject/Download/Deactivate buttons rendered
  - THEN: Each button has `aria-label` attribute with descriptive action
  - THEN: aria-label format: "{action} {object}" (e.g., "Edit pengguna John Doe")
  - THEN: aria-label visible in screen readers, even if button text is icon-only
  - THEN: aria-label consistent across same button type on all pages

**Examples**:
- Edit button: `aria-label="Edit pengguna {{ $user->name }}"`
- Delete button: `aria-label="Hapus pengguna {{ $user->name }}"`
- Process button: `aria-label="Proses laporan #{{ $report->id }}"`
- Reject button: `aria-label="Tolak laporan #{{ $report->id }}"`
- Download button: `aria-label="Download laporan PDF"`
- Deactivate button: `aria-label="Nonaktifkan kode QR {{ $qrcode->code }}"`

**Requirement 2.2**: Icon-only buttons use aria-label (no visual text)

- WHEN: Button displays icon only (e.g., pencil icon for edit)
  - THEN: Button has `aria-label` with full action text
  - THEN: Icon has `aria-hidden="true"` (hide from screen readers)
  - THEN: Button remains accessible to keyboard (Tab, Enter)

**Requirement 2.3**: Dropdown buttons have aria-expanded state

- WHEN: Dropdown menu button triggered
  - THEN: Button has `aria-expanded` attribute
  - THEN: `aria-expanded="false"` when menu closed
  - THEN: `aria-expanded="true"` when menu open
  - THEN: Attribute updates dynamically (Alpine.js)

**Requirement 2.4**: Close/dismiss buttons labeled clearly

- WHEN: Modal close button or alert dismiss button displayed
  - THEN: Button has `aria-label="Tutup"` or similar
  - THEN: Button visually clear (X icon + hover effect)

---

### 3. FOCUS INDICATORS & KEYBOARD NAVIGATION

**Requirement 3.1**: All interactive elements have visible focus indicators

- WHEN: User navigates page with Tab key
  - THEN: Each focusable element displays visible focus indicator (outline)
  - THEN: Focus outline has sufficient contrast (≥3:1 against background)
  - THEN: Focus outline is 2px+ wide (visible, not tiny)
  - THEN: Focus outline color consistent (e.g., primary green or blue)
  - THEN: Focus indicator not obscured by other elements (z-index managed)

**Requirement 3.2**: Custom CSS for focus-visible (mobile keyboard focus)

- WHEN: User focuses on input/button via Tab key
  - THEN: `:focus-visible` CSS applied (not just `:focus`)
  - THEN: Outline color: primary green (#228B22) or fallback blue
  - THEN: Outline width: 2px or 3px
  - THEN: Outline offset: 2px (small gap between element and outline)
  - THEN: Works on all input types: text, select, checkbox, radio, button, link

**Requirement 3.3**: Tab navigation order logical and complete

- WHEN: User navigates page with Tab key
  - THEN: Tab order follows visual left-to-right, top-to-bottom
  - THEN: All interactive elements are focusable (links, buttons, inputs, dropdowns)
  - THEN: No skipped elements (Tab doesn't jump unexpectedly)
  - THEN: No infinite loops (Tab can leave modal, form, etc.)
  - THEN: Shift+Tab navigates backwards correctly
  - THEN: Tab within modal doesn't escape (focus trap works)

**Requirement 3.4**: Escape key closes modals and dropdowns

- WHEN: Modal is open and user presses Escape
  - THEN: Modal closes
  - THEN: Focus returns to element that opened modal (button)
  - THEN: No error in console

- WHEN: Dropdown menu open and user presses Escape
  - THEN: Dropdown closes
  - THEN: Focus returns to dropdown toggle button

**Requirement 3.5**: Enter key submits forms and activates buttons

- WHEN: Form focused and user presses Enter
  - THEN: Form submits (no need to focus submit button)
  - THEN: Works in text inputs (Enter submits form)
  - THEN: Exception: textarea (Enter adds new line, Ctrl+Enter or click submits)

- WHEN: Button focused and user presses Enter (or Space)
  - THEN: Button activation triggered (link navigation or submit)
  - THEN: No error in console

---

### 4. FILE UPLOAD VALIDATION & PREVIEW

**Requirement 4.1**: File upload input validates file type on change

- WHEN: File selected in file input (especially Sarpras repair photo)
  - THEN: File type validated against allowed types (JPG, PNG)
  - THEN: If valid: file preview displays (thumbnail image)
  - THEN: If invalid: error message displays (red, `.invalid-feedback`)
  - THEN: Error message specific: "Format tidak didukung. Gunakan JPG atau PNG." or similar
  - THEN: Invalid file not submitted with form

**Requirement 4.2**: File size validation with helpful feedback

- WHEN: File selected in file input
  - THEN: File size checked against limit (e.g., 5MB for images)
  - THEN: If valid: success indicator (green checkmark or border)
  - THEN: If invalid: error message displays with file size info
  - THEN: Error message specific: "File terlalu besar (10MB). Maks 5MB." or similar
  - THEN: File size limit clearly labeled below input before selection

**Requirement 4.3**: File preview displays after successful validation

- WHEN: Valid file selected
  - THEN: Image thumbnail displays below input (40px × 40px)
  - THEN: File name displays below thumbnail (truncated if >30 chars)
  - THEN: "Clear" or remove link appears next to preview
  - THEN: Clicking remove clears file input and preview
  - THEN: Preview styling consistent (border, padding, shadow)

**Requirement 4.4**: Multiple file uploads show list of files

- WHEN: Form accepts multiple file attachments
  - THEN: Each selected file displays in a list
  - THEN: Each list item shows file name + size + remove button
  - THEN: Total files and total size displays below list
  - THEN: Can remove individual files from list
  - THEN: Can add more files by clicking input again

---

### 5. HELPER TEXT STANDARDIZATION

**Requirement 5.1**: All helper text uses consistent `<small class="text-muted">` styling

- WHEN: Form displays helper text (e.g., "Min 8 characters", "Format: DD/MM/YYYY")
  - THEN: HTML structure: `<small class="text-muted">{{ $helperText }}</small>`
  - THEN: Appears directly below input element
  - THEN: Text color: muted gray (#6c757d or Bootstrap `text-muted`)
  - THEN: Font size: small (14px or `0.875rem`)
  - THEN: Spacing: 4px gap above small (margin-top: 4px)
  - THEN: Optional info (not required to complete form)

**Requirement 5.2**: Helper text differentiates from error messages

- WHEN: Both helper text and error message could display
  - THEN: Helper text shows by default (below input, gray)
  - THEN: Error message replaces helper text when validation fails (red, `.invalid-feedback`)
  - THEN: Error message has sufficient contrast (≥4.5:1 against background)
  - THEN: No confusion between helper text (hint) and error (problem)

**Requirement 5.3**: Required field indicators use consistent pattern

- WHEN: Form field is required
  - THEN: Label includes `required` class: `<label class="form-label required">`
  - THEN: Required attribute on input: `required` HTML attribute
  - THEN: Visible indicator: asterisk (*) or "Wajib diisi" badge
  - THEN: Consistent styling across all forms
  - THEN: Not relying on color alone (text + icon/symbol)

**Requirement 5.4**: Placeholder text doesn't replace labels

- WHEN: Form input displayed
  - THEN: Label tag present (not just placeholder)
  - THEN: Placeholder used for example/hint only (e.g., "contoh@email.com")
  - THEN: Placeholder text not same as label
  - THEN: Works for screen readers (label accessible, placeholder not)

---

### 6. NAVIGATION & MENU ACCESSIBILITY

**Requirement 6.1**: Guest navbar links clear and accessible

- WHEN: Guest user views navbar
  - THEN: Links: "Buat Laporan", "Panduan Lapor", "Alur Validasi", "Lacak", "FAQ"
  - THEN: Active link visually highlighted (color, underline, or badge)
  - THEN: Login button prominent (right-aligned or highlighted)
  - THEN: All links keyboard accessible (Tab, Enter)
  - THEN: No broken links (all routes valid)

**Requirement 6.2**: Admin dropdown menu discoverable and accessible

- WHEN: Superadmin views navbar
  - THEN: "Panel Admin" dropdown visible with down-arrow indicator
  - THEN: On hover/focus, submenu slides open
  - THEN: Submenu items clearly visible (high contrast)
  - THEN: Submenu closes on click away or Escape
  - THEN: Mobile: Dropdown toggles with tap (not hover)
  - THEN: All submenu items keyboard accessible
  - THEN: aria-expanded attribute on dropdown toggle

**Requirement 6.3**: Master Data submenu grouped clearly

- WHEN: Admin dropdown open
  - THEN: "Master Data" submenu shows with arrow (▶)
  - THEN: On hover/focus, Master Data submenu slides open
  - THEN: Submenu items: Kelas, Mapel, Unit Staf, Lokasi, Jenis Pelanggaran, Kategori Kerusakan
  - THEN: All items clickable (no broken links)
  - THEN: Mobile: Nested dropdowns work (tap to open/close)

**Requirement 6.4**: Role-based navbar items show/hide correctly

- WHEN: User logs in with specific role
  - THEN: Dashboard always shows
  - THEN: Kesiswaan shows only for kesiswaan + superadmin
  - THEN: Sarpras shows only for sarpras + superadmin
  - THEN: Admin shows only for superadmin
  - THEN: Profile + Logout always show
  - THEN: Unauthorized routes redirect (middleware enforced)

**Requirement 6.5**: Mobile navbar hamburger menu responsive

- WHEN: Screen width < 768px
  - THEN: Navbar collapses to hamburger menu (≡ icon)
  - THEN: Menu toggles with tap/click
  - THEN: Menu slides out from left or appears as offcanvas
  - THEN: All links visible and clickable in menu
  - THEN: Menu closes on link click
  - THEN: Menu closes on tap outside
  - THEN: Backdrop semi-transparent (optional)
  - THEN: No content shift when menu opens (fixed layout)

---

### 7. TOUCH TARGET OPTIMIZATION

**Requirement 7.1**: All clickable elements meet 44x44px minimum

- WHEN: All interactive elements measured
  - THEN: Buttons: minimum 44px × 44px (padding + border)
  - THEN: Links: minimum 44px height (line-height + padding)
  - THEN: Checkboxes/Radio: minimum 44px × 44px (custom if custom styling)
  - THEN: Dropdowns: minimum 44px height
  - THEN: File input: minimum 44px height
  - THEN: Table row actions: buttons minimum 44px × 44px or spaced with 8px+ gaps

**Requirement 7.2**: Touch target spacing adequate

- WHEN: Multiple touch targets adjacent (e.g., Edit + Delete buttons)
  - THEN: Minimum 8px gap between targets (or 44px center-to-center)
  - THEN: Padding around buttons prevents accidental overlap
  - THEN: No stacking or overlapping (clear visual separation)
  - THEN: Mobile: spacing comfortable for thumb interaction

**Requirement 7.3**: Row actions on tables have adequate spacing

- WHEN: Table row displays edit/delete/action buttons
  - THEN: Each button: minimum 32px × 32px (with padding to reach 44px touch area)
  - THEN: Gap between buttons: 8px minimum
  - THEN: Buttons grouped in single `<td>` with `text-end` alignment
  - THEN: No wrapping or text overlap with button text
  - THEN: Entire row clickable space is 44px+ height (with padding)

---

### 8. GUEST LAYOUT & PUBLIC PAGES

**Requirement 8.1**: Guest layout component consistent across public pages

- WHEN: Public pages (home, guide, FAQ, tracking) load
  - THEN: Guest navbar visible with consistent styling
  - THEN: Footer visible with consistent styling (if exists)
  - THEN: Content area uses responsive layout (container max-width)
  - THEN: Padding/margin consistent with authenticated pages
  - THEN: Mobile layout responsive (single column, full-width)

**Requirement 8.2**: Public form pages follow form grid pattern

- WHEN: `/` (create report form), `/lacak` (tracking form) load
  - THEN: Form uses `col-12 col-md-6 col-lg-4` grid pattern
  - THEN: Form stack vertically on mobile, 2-column on tablet+
  - THEN: Submit button: full-width on mobile, auto-width on tablet+
  - THEN: Form responsive (no horizontal scroll)
  - THEN: Helper text below inputs

**Requirement 8.3**: Public guide/FAQ pages responsive

- WHEN: `/lapor-pembullyan` (guide) and `/faq` load
  - THEN: Content stack vertically on mobile
  - THEN: Headings, paragraphs, lists responsive
  - THEN: Accordion sections (if used) expand/collapse on tap
  - THEN: No horizontal scroll
  - THEN: Images scale responsively (max-width: 100%)

---

### 9. TYPOGRAPHY & READABILITY (Mobile Focus)

**Requirement 9.1**: Font sizes optimized for mobile readability

- WHEN: Page content viewed on mobile screen
  - THEN: Body text: 16px minimum (1rem)
  - THEN: Small text: 14px (0.875rem) for helper text, labels
  - THEN: Headings: h1 = 28px, h2 = 24px, h3 = 20px (responsive scaling)
  - THEN: Labels: 14px, bold or semi-bold (600 weight)
  - THEN: No text smaller than 12px except icons/decorative

**Requirement 9.2**: Line height adequate for readability

- WHEN: Text content displayed
  - THEN: Body text line-height: 1.5 to 1.6 (24px for 16px font)
  - THEN: Labels line-height: 1.4 to 1.5
  - THEN: Headings line-height: 1.2 to 1.3
  - THEN: No cramped spacing (readable, not too tight)
  - THEN: No excessive spacing (not too loose)

**Requirement 9.3**: Letter spacing natural (no squishing/stretching)

- WHEN: Form labels, button text, headings displayed
  - THEN: Letter spacing: normal (not condensed or expanded)
  - THEN: Words clearly separated (not mushed together)
  - THEN: Text not truncated or overflowing

---

### 10. MOBILE DEVICE TESTING VERIFICATION

**Requirement 10.1**: All pages tested on iPhone 12 (390px) equivalent

- WHEN: App accessed on iPhone 12 or browser simulating 390px
  - THEN: All pages load without 404 errors
  - THEN: All content visible (no hidden or truncated)
  - THEN: No horizontal scrolling
  - THEN: Forms stack vertically and submit correctly
  - THEN: Modals display and close correctly
  - THEN: Buttons clickable (44x44px target verified)
  - THEN: Images scale correctly (no distortion)
  - THEN: Performance acceptable (< 3 second load)

**Requirement 10.2**: All pages tested on Samsung Galaxy S21 (360px) equivalent

- WHEN: App accessed on Samsung Galaxy S21 or browser simulating 360px
  - THEN: All pages render correctly at 360px width
  - THEN: No overlapping elements or text
  - THEN: Forms display and function normally
  - THEN: Buttons/links clickable at 44px minimum
  - THEN: No missing or broken functionality

**Requirement 10.3**: Landscape orientation responsive

- WHEN: Mobile device rotated to landscape (834px width on iPhone 12)
  - THEN: Layout adapts to landscape (wider columns, if space allows)
  - THEN: No cut-off content or overflow
  - THEN: Forms remain usable (not too tall)
  - THEN: Modals scroll if needed (don't exceed viewport)

**Requirement 10.4**: Touch events work correctly

- WHEN: User interacts via touch (tap, swipe, long-press)
  - THEN: Buttons respond to tap (no double-tap needed)
  - THEN: Dropdowns toggle with single tap (not hover-dependent)
  - THEN: Modals open/close with tap (not double-tap)
  - THEN: Links navigate with single tap
  - THEN: No delay (touch events instant, < 300ms)

---

### 11. LIGHTHOUSE MOBILE SCORE OPTIMIZATION

**Requirement 11.1**: Lighthouse Performance score ≥ 85

- WHEN: Lighthouse audit run on mobile
  - THEN: Performance score ≥ 85
  - THEN: First Contentful Paint (FCP) < 2 seconds
  - THEN: Largest Contentful Paint (LCP) < 2.5 seconds
  - THEN: Cumulative Layout Shift (CLS) < 0.1
  - THEN: No unused CSS/JavaScript
  - THEN: Images optimized (no oversized)

**Requirement 11.2**: Lighthouse Accessibility score ≥ 95

- WHEN: Lighthouse audit run on mobile
  - THEN: Accessibility score ≥ 95
  - THEN: No color contrast issues
  - THEN: All inputs have labels
  - THEN: All images have alt text (if applicable)
  - THEN: Form labels associated correctly
  - THEN: Focus indicators visible

**Requirement 11.3**: Lighthouse Best Practices score ≥ 90

- WHEN: Lighthouse audit run on mobile
  - THEN: Best Practices score ≥ 90
  - THEN: No console errors (JavaScript)
  - THEN: HTTPS enforced
  - THEN: User experience optimized (no popups covering content)
  - THEN: Secure headers present

**Requirement 11.4**: Lighthouse SEO score ≥ 90 (Guest pages focus)

- WHEN: Lighthouse audit run on public pages (guest)
  - THEN: SEO score ≥ 90
  - THEN: Meta description present and unique
  - THEN: Viewport meta tag present
  - THEN: Mobile-friendly (responsive design)
  - THEN: Structured data correct (if applicable)

---

### 12. REGRESSION TESTING & PAGE COVERAGE

**Requirement 12.1**: All public pages tested

- Public pages: 4
  - `/` (Buat Laporan)
  - `/lapor-pembullyan` (Panduan)
  - `/faq` (FAQ)
  - `/lacak` (Lacak Laporan)

**Requirement 12.2**: All auth pages tested

- Auth pages: 2
  - `/login` (Login)
  - `/register` (Register, if exists)

**Requirement 12.3**: All dashboard pages tested

- Dashboard pages: 3
  - `/dashboard` (Main Dashboard)
  - `/profile` (Profile)
  - `/dashboard/settings` (Settings, if exists)
  - `/reports/{id}` (Report Detail)

**Requirement 12.4**: All admin pages tested

- Admin pages: 8
  - `/admin/users` (Users)
  - `/admin/master/classes` (Kelas)
  - `/admin/master/subjects` (Mapel)
  - `/admin/master/staff-units` (Unit Staf)
  - `/admin/master/locations` (Lokasi)
  - `/admin/master/violation-types` (Jenis Pelanggaran)
  - `/admin/master/damage-categories` (Kategori Kerusakan)
  - `/admin/qrcodes` (QR Codes)
  - `/admin/audit` (Audit Log)

**Requirement 12.5**: All role-specific pages tested

- Role-specific pages: 2
  - `/kesiswaan` (Kesiswaan Dashboard)
  - `/sarpras` (Sarpras Dashboard)

**Requirement 12.6**: Test coverage totals 17+ pages

- Total pages: 4 public + 2 auth + 3 dashboard + 9 admin + 2 role-specific = 20 pages minimum
- Each page: mobile responsive, keyboard accessible, touch targets adequate, no console errors

---

## SUCCESS CRITERIA

✅ **Form Grid Optimization**: All forms use `col-12 col-md-6` mobile-first pattern, stack vertically on mobile, 2-column on tablet+

✅ **ARIA Labels**: All action buttons (Edit, Delete, Process, Reject, Download, Deactivate) have descriptive `aria-label` attributes

✅ **Focus Indicators**: All interactive elements have visible `:focus-visible` CSS indicators with ≥3:1 contrast

✅ **File Upload Validation**: File inputs show type/size validation, preview image, and error feedback

✅ **Helper Text**: All helper text uses `<small class="text-muted">` consistently

✅ **Navigation**: Navbar links accessible, dropdowns discoverable, role-based items shown/hidden correctly, hamburger menu responsive

✅ **Touch Targets**: All clickable elements ≥44x44px with 8px+ spacing between adjacent targets

✅ **Guest Layout**: Public pages responsive and consistent with authenticated pages

✅ **Typography**: Font sizes ≥16px body, ≥14px labels, line heights 1.5+ for readability

✅ **Mobile Device Testing**: All 20+ pages tested on iPhone 12 (390px) and Samsung Galaxy S21 (360px), landscape orientation works, touch events instant

✅ **Lighthouse Scores**: Performance ≥85, Accessibility ≥95, Best Practices ≥90, SEO ≥90

✅ **Regression Testing**: All 20+ pages tested, no broken links, no console errors, no horizontal scroll

---

## Out of Scope

- New features or functionality
- Database changes or migrations
- Backend logic refactoring (only frontend/view changes)
- Changing existing color scheme or branding
- Redesigning entire page layouts (only optimizing existing layouts for mobile)
- Dark mode implementation (future phase)
- Performance optimization beyond Lighthouse recommendations
- Third-party library upgrades (only use existing dependencies)

---

## Notes

- Week 1 completed critical fixes (form heights, step dots, checkboxes, admin cards, accordions). This spec builds on that foundation.
- Focus on mobile-first approach: design for 375px-414px, enhance for larger screens.
- All changes made in Blade views (resources/views/), components (resources/views/components/), and CSS (resources/css/app.css or Tailwind config).
- Indonesian UI copy (no English unless necessary).
- WCAG AAA compliance target (exceeds AA minimum).
- Test on real devices or high-fidelity browser emulation (not just responsive design mode).
- All tasks are optimization only—no breaking changes, all existing functionality preserved.
