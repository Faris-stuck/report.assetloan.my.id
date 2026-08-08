# Tasks: Week 2-3 Mobile UI/UX Optimization

## Wave 1 - Foundation & Global Patterns (Ready - No Dependencies)

### Task 2.1: Create Focus Indicator CSS & Test (1.5h)

**Objective**: Implement global `:focus-visible` CSS for keyboard accessibility

**Details**:
- Add CSS to `resources/css/app.css` or component library
- Define `:focus-visible` styles (3px outline, green #228B22, offset 2px)
- Test outline visible on: buttons, links, inputs, selects, checkboxes
- Test outline NOT visible on mouse click (only keyboard Tab)
- Test outline doesn't obscure element (sufficient z-index)
- Verify contrast ≥3:1 (outline vs background)

**Acceptance Criteria**:
- ✅ CSS classes applied globally
- ✅ All interactive elements have visible focus outline when tabbed
- ✅ Outline visible in browser DevTools (Properties > Computed)
- ✅ Escape key closes modals when focused
- ✅ No console errors on any page

**Dependencies**: None

---

### Task 2.2: Standardize Helper Text Component (1h)

**Objective**: Create reusable helper text pattern `<small class="text-muted">`

**Details**:
- Audit existing helper text across forms (check for inconsistencies)
- Document current pattern (if any)
- Standardize to: `<small class="text-muted">{{ $helperText }}</small>`
- Ensure consistent styling (muted gray, 14px font)
- Test helper text appears below all form fields
- Test helper text doesn't interfere with error messages

**Acceptance Criteria**:
- ✅ All helper text uses `<small class="text-muted">`
- ✅ Styling consistent across all forms
- ✅ Helper text readable (sufficient contrast)
- ✅ No helper text appears when error message shows

**Dependencies**: None

---

### Task 2.3: Document ARIA Label Convention (1h)

**Objective**: Define standard aria-label format for all action buttons

**Details**:
- Create documentation: aria-label format specification
- Format examples: "{action} {object} {identifier}"
- Create shared Blade component helper if needed
- Test aria-label visible in DevTools (screen reader simulation)
- Document where aria-label used (Edit, Delete, Process, Reject, Download, Deactivate)

**Acceptance Criteria**:
- ✅ Documentation created in design guide
- ✅ Format consistent across all pages
- ✅ aria-label present on all icon-only buttons
- ✅ aria-hidden="true" on icons (not read by screen readers)

**Dependencies**: None

---

### Task 2.4: Add ARIA Labels to Guest Navbar (0.5h)

**Objective**: Add accessible labels to navigation links (public pages)

**Details**:
- Add aria-label or verify text labels present
- Add aria-expanded to logout button
- Test Tab navigation through navbar
- Test screen reader reads all link labels

**Acceptance Criteria**:
- ✅ All navbar links have clear labels
- ✅ Active link visually highlighted
- ✅ Tab navigation works through navbar
- ✅ Logout button aria-label present

**Dependencies**: None

---

## Wave 2 - Form Grid Optimization (After Wave 1)

### Task 2.5: Optimize Public Form Pages Grid (2h)

**Objective**: Update `/` (create report) and `/lacak` (tracking) forms to responsive grid

**Details**:
- Audit current layout (screenshot on mobile)
- Update form fields to `col-12 col-md-6` pattern
- Stack vertically on mobile (< 768px)
- 2-column on tablet+ (≥768px)
- Apply to:
  - `/` (Buat Laporan form)
  - `/lacak` (Lacak Laporan form)
- Test responsive (DevTools device mode: iPhone 12)
- Test form submission works on mobile

**Acceptance Criteria**:
- ✅ Forms stack vertically on 390px width
- ✅ Forms 2-column on 768px+ width
- ✅ No horizontal scroll on any breakpoint
- ✅ All inputs full-width on mobile
- ✅ Buttons stack or inline appropriately
- ✅ Form submission successful on mobile

**Dependencies**: Task 2.1, 2.2

---

### Task 2.6: Optimize Admin Search/Filter Forms (2h)

**Objective**: Update all admin search forms to responsive grid pattern

**Details**:
- Audit existing search forms across:
  - `/admin/users`
  - `/admin/qrcodes`
  - `/admin/audit`
  - `/admin/master/*` (6 pages)
- Update to: `col-12 col-md-6 col-lg-3` for filter fields
- Buttons: `col-12` on mobile, grouped inline on tablet+
- Test filters preserve values on submit
- Test pagination preserves filters (querystring)
- Test responsive on mobile (390px)

**Acceptance Criteria**:
- ✅ Search forms stack on mobile (single column)
- ✅ Search forms 2-column on tablet, 3-4 column on desktop
- ✅ Filter values preserved (querystring intact)
- ✅ Pagination preserve filters with `.appends()`
- ✅ Reset button works (clears all filters)
- ✅ Results display below form

**Dependencies**: Task 2.1, 2.2

---

### Task 2.7: Optimize Modal Forms (1.5h)

**Objective**: Apply responsive grid to all modal forms (edit operations)

**Details**:
- Audit modal forms across admin pages
- Update form fields in modals to responsive grid
- Mobile: stack vertically
- Tablet+: 2-column where applicable
- Test modal width responsive (100% - 20px on mobile, 500px max on desktop)
- Test form scrollable if exceeds viewport
- Test modal close (Escape key)
- Test Tab navigation within modal (focus trap)

**Acceptance Criteria**:
- ✅ Modal forms responsive on mobile
- ✅ Modal width adapts to screen size
- ✅ Form fields properly spaced
- ✅ Tab navigation trapped within modal
- ✅ Escape key closes modal
- ✅ Form submits correctly

**Dependencies**: Task 2.1, 2.2

---

### Task 2.8: Standardize Report Detail Page Layout (1h)

**Objective**: Optimize `/reports/{id}` detail page for mobile

**Details**:
- Audit current report detail layout
- Apply responsive grid to report info sections
- Stack vertically on mobile
- Multi-column on desktop
- Test all buttons accessible (44x44px)
- Test modal form for adding notes
- Test attachment download links work

**Acceptance Criteria**:
- ✅ Report detail responsive on mobile
- ✅ All sections stack vertically on mobile
- ✅ Buttons properly spaced (44x44px)
- ✅ Attachment links work
- ✅ Note form accessible in modal

**Dependencies**: Task 2.1, 2.2, 2.7

---

## Wave 3 - File Upload & Accessibility (After Wave 2)

### Task 2.9: Implement File Upload Validation & Preview (2.5h)

**Objective**: Add file type/size validation and preview to Sarpras repair photo

**Details**:
- Update Sarpras form: `/sarpras` dashboard, edit damage form
- Add file upload field: `repair_photo`
- Implement validation:
  - File type: JPG, PNG only
  - File size: max 5MB
- Implement preview:
  - Show thumbnail (40x40px) after selection
  - Display file name (truncated if long)
  - Display file size (in KB)
  - Add "Clear" button to remove preview
- Server-side validation: re-validate on submit
- Test on mobile (tap to select file)
- Test error messages display clearly

**Acceptance Criteria**:
- ✅ File type validation works (rejects other formats)
- ✅ File size validation works (rejects > 5MB)
- ✅ Preview displays for valid files
- ✅ Error messages clear and specific
- ✅ Clear button removes preview
- ✅ Server-side validation prevents invalid upload

**Dependencies**: Task 2.1, 2.2

---

### Task 2.10: Add ARIA Labels to Admin Action Buttons (2h)

**Objective**: Add descriptive aria-label to all Edit/Delete/Process/etc buttons

**Details**:
- Add aria-label to buttons across:
  - `/admin/users` (Edit, Delete)
  - `/admin/qrcodes` (Edit, Download, Deactivate, Delete)
  - `/admin/audit` (no actions, skip)
  - `/admin/master/*` (Edit, Delete × 6 pages)
  - `/kesiswaan` (Process, Reject)
  - `/sarpras` (Process, Reject)
- Format: aria-label="{action} {object} {identifier}"
- Add aria-hidden="true" to icons
- Test screen reader reads labels correctly
- Test Tab navigation through buttons

**Acceptance Criteria**:
- ✅ All action buttons have aria-label
- ✅ aria-label consistent format
- ✅ Icons have aria-hidden="true"
- ✅ Screen reader reads labels (if tested)
- ✅ Tab navigation works through buttons

**Dependencies**: Task 2.3

---

### Task 2.11: Add ARIA Labels to Admin Dropdown Navigation (1h)

**Objective**: Add aria-label and aria-expanded to admin dropdown menus

**Details**:
- Update admin navbar dropdown button
- Add aria-label: "Toggle admin panel menu" or similar
- Add aria-expanded: toggles with dropdown state
- Master Data submenu: aria-expanded for nested dropdown
- Test aria-expanded updates when dropdown opens/closes
- Test nested dropdowns work on mobile (touch)
- Test Tab navigation through dropdown items

**Acceptance Criteria**:
- ✅ Dropdown button has aria-label
- ✅ aria-expanded toggles correctly
- ✅ Nested dropdown accessible
- ✅ Mobile: dropdowns work with tap
- ✅ Tab navigation works through all items

**Dependencies**: Task 2.3

---

### Task 2.12: Verify Touch Targets ≥44x44px on All Pages (2h)

**Objective**: Audit and fix all clickable elements to meet 44x44px minimum

**Details**:
- Audit all pages for touch target sizes
- Check: buttons, links, checkboxes, radio buttons, file inputs
- Measure: effective clickable area (including padding)
- Pages to audit:
  - All public pages (4)
  - All admin pages (9)
  - All role-specific pages (2)
  - Dashboard pages (3)
- Add padding if any element < 44px
- Ensure 8px gap between adjacent targets
- Test with Lighthouse accessibility audit
- Document any exceptions (if unavoidable)

**Acceptance Criteria**:
- ✅ All buttons ≥44x44px
- ✅ All links ≥44px height
- ✅ All form inputs ≥44px height
- ✅ All checkboxes/radios ≥44x44px
- ✅ Gap between targets ≥8px
- ✅ No overlapping touch targets

**Dependencies**: Task 2.1, 2.7

---

## Wave 4 - Navigation & Accessibility (After Wave 3)

### Task 2.13: Optimize Guest Navbar for Mobile (1h)

**Objective**: Make guest navbar responsive and accessible on mobile

**Details**:
- Update navbar: check responsive design (< 768px)
- On mobile: hamburger menu with overlay
- Menu items: Buat Laporan, Panduan, Alur, Lacak, FAQ
- Mobile: vertical menu stack
- Desktop: horizontal menu
- Active link highlighting visible
- Login button prominent
- Test hamburger toggle on 390px width
- Test link navigation

**Acceptance Criteria**:
- ✅ Navbar responsive on mobile
- ✅ Hamburger menu toggles
- ✅ Menu items accessible
- ✅ Active link highlighted
- ✅ Login button visible
- ✅ No horizontal scroll

**Dependencies**: Task 2.1

---

### Task 2.14: Optimize Admin Navbar Dropdown for Mobile (1.5h)

**Objective**: Make admin dropdown menu accessible and discoverable on mobile

**Details**:
- Update admin dropdown: responsive for mobile
- Desktop: hover shows submenu
- Mobile: tap toggles submenu (not hover)
- Master Data: nested dropdown
- Mobile: tap Master Data to toggle sub-items
- Test on 390px width (iPad portrait)
- Test Tab navigation (keyboard accessible)
- Verify aria-expanded toggles

**Acceptance Criteria**:
- ✅ Dropdown toggles with tap on mobile
- ✅ Nested submenu accessible on mobile
- ✅ Tab navigation works
- ✅ Escape key closes dropdown
- ✅ Active links highlighted
- ✅ All items clickable

**Dependencies**: Task 2.1, 2.11

---

### Task 2.15: Add Focus Trap to Modal Components (1h)

**Objective**: Ensure Tab navigation stays within modal (focus trap)

**Details**:
- Update modal component: focus trap logic
- When modal open: Tab cycles through focusable elements
- Last element Tab → first element (wraps)
- First element Shift+Tab → last element (wraps backward)
- Escape key closes modal, focus returns to trigger
- Test on all modals (edit forms, delete confirm, etc)
- Test on mobile and desktop

**Acceptance Criteria**:
- ✅ Tab navigation trapped within modal
- ✅ Shift+Tab navigates backward
- ✅ Tab wraps to first element
- ✅ Escape closes modal
- ✅ Focus returns to trigger button
- ✅ No focus escape to background

**Dependencies**: Task 2.1

---

### Task 2.16: Test Keyboard Navigation on All Pages (1.5h)

**Objective**: Verify Tab/Escape/Enter key navigation works on all pages

**Details**:
- Test public pages (4):
  - Tab through navbar, form, submit
  - Escape closes any modals
  - Enter submits form
- Test admin pages (9):
  - Tab through search form, results table, action buttons
  - Enter submits search
  - Escape closes modals
- Test role-specific pages (2):
  - Tab through report list, action buttons
  - Escape closes action modals
- Test dashboard pages (3):
  - Tab through content
- Document any issues
- Fix broken Tab order if found

**Acceptance Criteria**:
- ✅ Tab order logical (left-to-right, top-to-bottom)
- ✅ All interactive elements focusable
- ✅ No focus trap (unless intentional modal)
- ✅ Escape closes modals
- ✅ Enter submits forms
- ✅ No console errors

**Dependencies**: Task 2.1, 2.15

---

## Wave 5 - Responsive Layout & Guest Pages (After Wave 4)

### Task 2.17: Optimize Guest Guide Page (`/lapor-pembullyan`) (1h)

**Objective**: Make guide page responsive and properly formatted

**Details**:
- Audit current layout
- Ensure responsive on mobile (< 768px)
- Stack content vertically on mobile
- Images scale responsively (max-width: 100%)
- No horizontal scroll
- Typography readable (font sizes ≥16px)
- Links accessible (underlined or highlighted)
- Test on iPhone 12 (390px)

**Acceptance Criteria**:
- ✅ Content responsive on mobile
- ✅ No horizontal scroll
- ✅ Images scale properly
- ✅ Typography readable
- ✅ All links work
- ✅ Lighthouse performance ≥85

**Dependencies**: Task 2.1

---

### Task 2.18: Optimize Guest FAQ Page (`/faq`) (1h)

**Objective**: Make FAQ page responsive and accessible

**Details**:
- Audit current layout
- If accordion: accordion toggle works on mobile (tap, not hover)
- Stack content vertically on mobile
- Questions readable with ≥16px font
- Search/filter (if exists) responsive
- No horizontal scroll
- Test on mobile (390px)

**Acceptance Criteria**:
- ✅ FAQ responsive on mobile
- ✅ Accordion (if used) toggles with tap
- ✅ No horizontal scroll
- ✅ Typography readable
- ✅ Links/buttons accessible

**Dependencies**: Task 2.1

---

### Task 2.19: Optimize Tracking Form Page (`/lacak`) (1.5h)

**Objective**: Make tracking form responsive and fully functional

**Details**:
- Audit current layout
- Apply responsive grid to form (already in Task 2.5, verify here)
- Form responsive on mobile
- Results display responsive
- Timeline/status history readable on mobile
- Add form accessible on mobile (if available)
- No horizontal scroll
- Test on mobile (390px)

**Acceptance Criteria**:
- ✅ Form responsive on mobile
- ✅ Results responsive
- ✅ Timeline readable
- ✅ No horizontal scroll
- ✅ Buttons accessible (44x44px)
- ✅ Form submission works

**Dependencies**: Task 2.5

---

## Wave 6 - Dashboard & Profile Pages (After Wave 5)

### Task 2.20: Optimize Main Dashboard (`/dashboard`) (1.5h)

**Objective**: Make dashboard responsive and properly structured

**Details**:
- Audit dashboard layout
- Ensure responsive on mobile
- Cards/sections stack vertically on mobile
- Grid adapts: `col-12 col-md-6 col-lg-4` pattern
- Charts scale responsively
- Navigation links accessible
- No horizontal scroll
- Test on mobile (390px)

**Acceptance Criteria**:
- ✅ Dashboard responsive on mobile
- ✅ Cards stack vertically
- ✅ Charts readable
- ✅ No horizontal scroll
- ✅ Links accessible
- ✅ Touch targets ≥44x44px

**Dependencies**: Task 2.1

---

### Task 2.21: Optimize Profile Page (`/profile`) (1h)

**Objective**: Make profile page responsive

**Details**:
- Audit profile layout
- Update profile form to responsive grid
- Stack vertically on mobile
- 2-column on tablet+
- Update password form separately
- Test on mobile (390px)
- Test form submission

**Acceptance Criteria**:
- ✅ Profile form responsive on mobile
- ✅ Form stack vertically
- ✅ No horizontal scroll
- ✅ Form submission works
- ✅ Helper text present

**Dependencies**: Task 2.1, 2.2

---

## Wave 7 - Lighthouse & Final Testing (After Wave 6)

### Task 2.22: Run Lighthouse Audit & Fix Performance Issues (2h)

**Objective**: Achieve Lighthouse scores (Performance ≥85, Accessibility ≥95, Best Practices ≥90)

**Details**:
- Run Lighthouse audit on mobile (DevTools)
- Test public pages (4):
  - Score goals: P≥85, A≥95, BP≥90, SEO≥90
- Test admin pages (selected 3):
  - Score goals: P≥85, A≥95, BP≥90
- Document issues found
- Fix high-priority issues:
  - Unused CSS/JS
  - Image optimization
  - Font optimization
  - Console errors
- Re-run audit to verify improvements
- Document final scores

**Acceptance Criteria**:
- ✅ Performance ≥85 (all pages)
- ✅ Accessibility ≥95 (all pages)
- ✅ Best Practices ≥90 (all pages)
- ✅ SEO ≥90 (public pages)
- ✅ No console errors
- ✅ No critical Lighthouse issues

**Dependencies**: All prior tasks

---

### Task 2.23: Mobile Device Testing - iPhone 12 Equivalent (2h)

**Objective**: Test all pages on actual mobile device or high-fidelity emulator

**Details**:
- Use iPhone 12 (390px) or browser emulation
- Test each page:
  - Load page (verify 200 status, no 404)
  - Verify all content visible
  - Verify no horizontal scroll
  - Verify buttons clickable (44x44px)
  - Verify forms work
  - Verify modals open/close
- Pages to test (20+):
  - 4 public pages
  - 2 auth pages
  - 3 dashboard pages
  - 9 admin pages
  - 2 role-specific pages
- Document any issues
- Fix critical issues

**Acceptance Criteria**:
- ✅ All 20+ pages load without 404
- ✅ No horizontal scroll on any page
- ✅ All content visible
- ✅ Buttons/links clickable
- ✅ Forms submit correctly
- ✅ Modals functional

**Dependencies**: All prior tasks

---

### Task 2.24: Mobile Device Testing - Samsung Galaxy S21 Equivalent (1.5h)

**Objective**: Test all pages on 360px width (Android device equivalent)

**Details**:
- Use Samsung Galaxy S21 (360px) or browser emulation
- Test critical pages (12-15 selected):
  - All admin pages (9)
  - 3 role-specific dashboard pages
- Verify layout works at 360px
- Verify no overlapping elements
- Verify buttons accessible
- Verify form layout appropriate
- Document any issues specific to 360px
- Fix critical issues

**Acceptance Criteria**:
- ✅ Critical pages responsive at 360px
- ✅ No overlapping or truncated content
- ✅ Buttons accessible (44x44px)
- ✅ Forms properly spaced
- ✅ No horizontal scroll

**Dependencies**: Task 2.23

---

### Task 2.25: Landscape Orientation Testing (1h)

**Objective**: Verify all pages work in landscape orientation

**Details**:
- Test iPhone 12 landscape (834px width)
- Test Samsung Galaxy S21 landscape (720px width)
- Pages to test (10 selected):
  - Create report form
  - Admin users page
  - Report detail
  - Kesiswaan dashboard
  - Sarpras dashboard
- Verify layout adapts to landscape
- Verify no cut-off content
- Verify forms still usable (not too tall)
- Verify modals scroll if needed
- Document any issues

**Acceptance Criteria**:
- ✅ Layout adapts to landscape
- ✅ No cut-off content
- ✅ Forms usable in landscape
- ✅ Modals scrollable if needed
- ✅ Touch targets still accessible

**Dependencies**: Task 2.23, 2.24

---

### Task 2.26: Regression Test - All Pages No Broken Links (1.5h)

**Objective**: Verify no broken links and all functionality preserved

**Details**:
- Test all 20+ pages
- Verify all links functional (200 status, not 404)
- Verify all buttons functional
- Verify all modals open/close
- Verify all forms submit
- Verify all dropdowns toggle
- Verify search filters work
- Verify pagination works
- Verify mobile and desktop rendering consistent
- Document any regressions
- Fix critical issues

**Acceptance Criteria**:
- ✅ All links return 200 status (no 404)
- ✅ All buttons functional
- ✅ All modals functional
- ✅ All forms functional
- ✅ All dropdowns functional
- ✅ No console errors

**Dependencies**: All prior tasks

---

### Task 2.27: Accessibility Compliance Verification (1.5h)

**Objective**: Final accessibility audit (WCAG AAA target)

**Details**:
- Run WAVE accessibility audit on 5 representative pages
  - Public page (home)
  - Admin page (users)
  - Role-specific page (kesiswaan)
  - Report detail
  - Dashboard
- Check:
  - All form inputs have labels (associated with `for` attribute)
  - All buttons have text or aria-label
  - All links have text
  - Color contrast ≥4.5:1 for text
  - Focus indicators visible
  - No duplicate IDs
  - No skipped heading levels
- Document issues
- Fix any critical accessibility issues
- Document final audit results

**Acceptance Criteria**:
- ✅ All inputs have labels
- ✅ All buttons/links have descriptive text
- ✅ Color contrast ≥4.5:1
- ✅ Focus indicators visible
- ✅ No duplicate IDs
- ✅ Heading hierarchy correct
- ✅ WAVE audit: 0 errors (or documented exceptions)

**Dependencies**: All prior tasks

---

### Task 2.28: Documentation & Final Verification (1h)

**Objective**: Document changes and verify spec completion

**Details**:
- Create summary of all changes made
- Document page-by-page verification results
- Note any limitations or exceptions
- Verify all requirements met:
  - ✅ Form grid optimization
  - ✅ ARIA labels
  - ✅ Focus indicators
  - ✅ File upload validation
  - ✅ Helper text standardization
  - ✅ Navigation accessibility
  - ✅ Touch target verification
  - ✅ Guest layout optimization
  - ✅ Typography optimization
  - ✅ Mobile device testing
  - ✅ Lighthouse scores
  - ✅ Regression testing
- Final checklist completion
- Update spec status: COMPLETE

**Acceptance Criteria**:
- ✅ All requirements documented as met or documented as exception
- ✅ Summary of changes prepared
- ✅ Test results documented
- ✅ No outstanding critical issues

**Dependencies**: All prior tasks

---

## Wave Dependencies Summary

```
Wave 1 (Ready - No Dependencies)
├─ Task 2.1: Focus Indicator CSS
├─ Task 2.2: Helper Text Standardization
├─ Task 2.3: ARIA Label Convention
└─ Task 2.4: Guest Navbar ARIA Labels

Wave 2 (After Wave 1)
├─ Task 2.5: Public Form Grid (depends: 2.1, 2.2)
├─ Task 2.6: Admin Search Forms (depends: 2.1, 2.2)
├─ Task 2.7: Modal Forms (depends: 2.1, 2.2)
└─ Task 2.8: Report Detail (depends: 2.1, 2.2, 2.7)

Wave 3 (After Wave 2)
├─ Task 2.9: File Upload Validation (depends: 2.1, 2.2)
├─ Task 2.10: Admin Action ARIA Labels (depends: 2.3)
├─ Task 2.11: Admin Dropdown ARIA (depends: 2.3)
├─ Task 2.12: Touch Target Audit (depends: 2.1, 2.7)

Wave 4 (After Wave 3)
├─ Task 2.13: Guest Navbar Mobile (depends: 2.1)
├─ Task 2.14: Admin Dropdown Mobile (depends: 2.1, 2.11)
├─ Task 2.15: Modal Focus Trap (depends: 2.1)
└─ Task 2.16: Keyboard Navigation Test (depends: 2.1, 2.15)

Wave 5 (After Wave 4)
├─ Task 2.17: Guide Page (`/lapor-pembullyan`) (depends: 2.1)
├─ Task 2.18: FAQ Page (depends: 2.1)
└─ Task 2.19: Tracking Page (depends: 2.5)

Wave 6 (After Wave 5)
├─ Task 2.20: Dashboard (depends: 2.1)
└─ Task 2.21: Profile Page (depends: 2.1, 2.2)

Wave 7 (After Wave 6)
├─ Task 2.22: Lighthouse Audit (depends: all Wave 1-6)
├─ Task 2.23: iPhone Testing (depends: all Wave 1-6)
├─ Task 2.24: Android Testing (depends: 2.23)
├─ Task 2.25: Landscape Testing (depends: 2.23, 2.24)
├─ Task 2.26: Regression Testing (depends: all Wave 1-6)
├─ Task 2.27: Accessibility Verification (depends: all Wave 1-6)
└─ Task 2.28: Documentation (depends: all Wave 1-7)
```

---

## Estimated Timeline

- **Wave 1**: 4.5 hours (Ready immediately)
- **Wave 2**: 6.5 hours (After Wave 1)
- **Wave 3**: 6 hours (After Wave 2)
- **Wave 4**: 5 hours (After Wave 3)
- **Wave 5**: 3.5 hours (After Wave 4)
- **Wave 6**: 2.5 hours (After Wave 5)
- **Wave 7**: 10 hours (After Wave 6)

**Total**: ~37.5 hours (~5-6 days full-time, or ~2-3 weeks part-time)

---

## Quality Gate Criteria

Before marking phase complete, verify:

✅ All 28 tasks completed
✅ Lighthouse scores met (P≥85, A≥95, BP≥90, SEO≥90)
✅ All 20+ pages tested on mobile
✅ No console errors (JavaScript)
✅ No broken links (all 200 status)
✅ No horizontal scroll on mobile
✅ All touch targets ≥44x44px
✅ All keyboard navigation works (Tab, Escape, Enter)
✅ WCAG AAA accessibility compliance (or documented exceptions)
✅ Regression testing: all existing functionality preserved
✅ Real device testing completed (iPhone 12 + Samsung Galaxy S21 equivalent)

---

## Success Definition

Phase 2-3 complete when:
- ✅ All 28 tasks completed
- ✅ All 20+ pages responsive and accessible on 375px-414px mobile screens
- ✅ Lighthouse mobile scores ≥85 (Performance), ≥95 (Accessibility)
- ✅ Full regression test passed (no regressions from Week 1 fixes)
- ✅ Documentation updated with all changes
- ✅ Ready for production deployment
