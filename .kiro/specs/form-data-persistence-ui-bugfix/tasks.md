# Implementation Plan: Form Data Persistence & Desktop UI/UX Bugfix

## Overview

This task list implements the three-area fix for form data persistence, form structure improvements, and desktop UI/UX unification. Tasks are ordered by dependency with backend fixes first, followed by frontend improvements, then validation.

**Estimated Total Effort**: 12-14 hours  
**Priority**: High (critical data persistence bug, medium UX improvements)  
**Target Completion**: This sprint

---

## Area 1: Backend Data Persistence Fix (2-3 hours)

### 1.1 Examine PublicReportService Priority Logic

- [x] 1.1 **Explore** - Debug Priority Persistence Bug
  - **Property 1: Bug Condition** - Priority Data Persistence Defect
  - **CRITICAL**: This test MUST FAIL on unfixed code - it proves the priority bug exists
  - **GOAL**: Surface counterexample that shows priority mirrors urgency instead of being independent
  - Navigate to `app/Services/PublicReport/PublicReportService.php` lines 53-56
  - Observe current code: `'priority' => $validated['priority'] ?? $validated['urgency']`
  - Write a test that creates a damage report with urgency="darurat"
  - Query database: verify `damage_detail.priority = 'darurat'` (this is the bug - it should be NULL)
  - Write test as a property-based test that generates random urgency values [rendah, sedang, tinggi, darurat]
  - For each urgency value, submit report and verify priority equals urgency (on unfixed code, will always fail the expected behavior)
  - Expected failure on unfixed code: `damage_detail.priority != NULL` (it equals urgency value)
  - Document counterexample: "priority was set to urgency value instead of NULL"
  - Mark complete when test is written, run, and failure documented
  - _Requirements: 1.2, 2.2_

### 1.2 Fix PublicReportService to Initialize Priority as NULL

- [x] 1.2 Implement Priority NULL Initialization Fix
  - File: `app/Services/PublicReport/PublicReportService.php`
  - Line 56: Remove the priority fallback logic
  - **Current code**:
    ```php
    'priority' => $validated['priority'] ?? $validated['urgency'],
    ```
  - **New code**:
    ```php
    'priority' => null,  // Initially NULL - Sarpras staff sets independently
    ```
  - **Rationale**: Priority is admin-only. Public form collects urgency only. Let database default handle NULL.
  - Add comment above DamageDetail creation:
    ```php
    // Priority initialized to NULL on creation; Sarpras staff sets priority independently via process modal
    ```
  - Run `php artisan test` to verify no existing tests break
  - _Bug_Condition: isBugCondition where urgency populated priority instead of NULL_
  - _Expected_Behavior: damage_detail.priority = NULL on creation_
  - _Requirements: 1.2, 2.2_

### 1.3 Verify Database Migration Supports Nullable Priority

- [x] 1.3 Check DamageDetail Migration for Priority Nullability
  - File: Check migrations for `damage_details` table creation
  - Look for migration with: `$table->enum('priority', [...])->default('sedang')`
  - Verify the column definition allows NULL values OR has nullable() modifier
  - **If current migration has**: `->default('sedang')` - this might force a default value
  - **Action**: If needed, create new migration to change priority to nullable
    ```php
    Schema::table('damage_details', function (Blueprint $table) {
        $table->enum('priority', ['rendah', 'sedang', 'tinggi', 'darurat'])->default(null)->nullable()->change();
    });
    ```
  - **DO NOT run migration yet** - wait for full fix validation
  - Document current migration state
  - _Requirements: 1.2, 2.2_

### 1.4 Verify SarprasProcessor Handles Independent Priority Updates

- [x] 1.4 Examine SarprasProcessor for Priority Update Logic
  - File: `app/Services/Role/Sarpras/SarprasProcessor.php`
  - Find the `process()` or `updatePriority()` method
  - Verify priority updates are saved correctly to `damage_detail.priority`
  - Confirm no logic depends on urgency value for priority updates
  - Check that priority updates don't affect reports.urgency
  - Verify update persists in all views (detail page, list, tracking)
  - If any issues found, document them for follow-up task
  - _Requirements: 2.3, 3.5_


### 1.5 Verify Database Transactions and Error Handling

- [x] 1.5 Test Priority Persistence in Error Scenarios
  - Test: Create damage report, verify priority NULL even if other fields have errors
  - Test: Update priority via SarprasProcessor, verify it persists independently
  - Test: Rollback transaction, verify priority changes are reversed
  - Run test suite: `php artisan test`
  - Verify no regressions in existing damage report tests
  - _Requirements: 2.2, 2.3_

---

## Area 2: Frontend Form Structure & Validation Improvements (5-6 hours)

### 2.1 Improve Form Validation Error Display

- [x] 2.1 **Explore** - Form Validation Error Visibility
  - **Property 2: Bug Condition** - Validation Error Display Clarity
  - **CRITICAL**: This test documents current (buggy) error behavior on unfixed code
  - **GOAL**: Surface counterexample showing errors are small, not prominent, hard to find
  - Open `resources/views/public/report-form.blade.php`
  - Find `.invalid-step-hint` container (search for `invalid-step-hint`)
  - Inspect styling: likely uses `.small-muted` classes (low contrast)
  - Write test that triggers validation error on step 3
  - Observe: Error message displayed in small container above form
  - Write property-based test: for all validation failures on each step (1-4), verify error behavior
  - Expected observation on unfixed code: 
    - Error text is small (12px or less)
    - Muted color (low contrast)
    - Error doesn't identify which field failed
    - Container width is constrained (hard to read)
  - Document counterexample: "Validation error was not clearly visible"
  - Mark complete when observations documented
  - _Requirements: 1.7, 2.7_

### 2.2 Enhance Error Display with Alert Styling

- [x] 2.2 Update Form Validation Error Styling
  - File: `resources/views/public/report-form.blade.php`
  - Find section: `<div class="invalid-step-hint"...` (search for `x-show="stepError"`)
  - Replace entire error container with Bootstrap alert styling:
    ```blade
    <div class="alert alert-danger mt-3 mb-3" x-show="stepError" x-transition>
        <div class="d-flex align-items-start">
            <i class="fas fa-exclamation-circle me-2 mt-1"></i>
            <div>
                <strong>Lengkapi formulir dengan benar</strong>
                <div class="mt-2 small" x-text="stepErrorMessage"></div>
            </div>
        </div>
    </div>
    ```
  - Verify Bootstrap alert-danger styling is applied (red background, white text, high contrast)
  - Update Alpine.js variable `stepErrorMessage` to include field name if possible
  - Update validation logic to capture field label when error occurs
  - _Requirements: 1.7, 2.7_

### 2.3 Add Scroll-Into-View for Validation Errors

- [x] 2.3 Implement Error Scroll Behavior
  - File: `resources/views/public/report-form.blade.php`
  - Find Alpine.js `next()` method that validates current step
  - After validation fails and `stepError = true`, add scroll logic:
    ```javascript
    if (this.stepError) {
        // Scroll error into view after a brief delay for DOM update
        setTimeout(() => {
            const errorEl = document.querySelector('[x-show="stepError"]');
            if (errorEl) {
                errorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    }
    ```
  - Test: Trigger validation error on step 3, verify error container scrolls into center of viewport
  - Test on mobile (375px) and desktop (1024px)
  - _Requirements: 1.7, 2.7_

### 2.4 Improve Step Tracker Accessibility

- [x] 2.4 Enhance Step Dots and Labels
  - File: `resources/views/public/report-form.blade.php`
  - Find step tracker section with dots and labels
  - Search for `.step-dot` or similar button styling
  - **Change 1 - Increase Step Dot Size**:
    - Current: likely `btn-sm` or similar small size
    - Change to minimum 44px width/height:
      ```blade
      <button ... class="btn step-dot" style="min-width: 44px; min-height: 44px;" ...>
      ```
  - **Change 2 - Stabilize Step Labels**:
    - Find label container below dots
    - Add max-width and text truncation:
      ```blade
      <div class="step-label" style="max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
          {{ $label }}
      </div>
      ```
  - **Change 3 - Improve Current Step Hint**:
    - Find text like "Langkah 1 dari 4" or similar
    - Change from `.small-muted` to `.small` (remove muted class)
    - Increase font size from 12px to 14px if needed:
      ```blade
      <p class="small mt-2" style="font-size: 14px;">Langkah {{ $currentStep }} dari 4</p>
      ```
  - Test on mobile (375px) and tablet (768px) to verify touch targets are tappable
  - _Requirements: 1.6, 2.6_

### 2.5 Standardize Conditional Field Rendering

- [x] 2.5 Fix Conditional Field Layout Stability
  - File: `resources/views/public/report-form.blade.php`
  - Search for fields with `x-show` directive (e.g., Kelas Pelapor for Siswa)
  - Verify all conditional fields use `x-show` (not `x-if`)
  - For fields that should not reserve space when hidden, wrap in container:
    ```blade
    <div x-show="reporter==='siswa'" x-transition>
        {{-- Field content --}}
    </div>
    ```
  - Add CSS to prevent layout shift:
    ```css
    [x-show] {
        display: contents;  /* Don't reserve space */
    }
    
    [x-show="false"] {
        display: none;  /* Hide element completely */
    }
    ```
  - **Alternative approach** if `display: contents` not supported:
    - Use `display: none` and `display: block` for visibility
    - Accept minor layout shift (already present in unfixed code)
  - Test: Toggle reporter type between "siswa" and "pegawai" on mobile and desktop
  - Verify layout is stable, no jumping elements
  - _Requirements: 1.5, 2.5_

### 2.6 Add Visual Indicators for Required Fields

- [x] 2.6 Standardize Required Field Marking
  - File: `resources/views/public/report-form.blade.php`
  - Search for all form labels with required fields
  - Ensure each required label includes red asterisk:
    ```blade
    <label>Nama Laporan <span class="text-danger">*</span></label>
    ```
  - Add `required` class to all required inputs:
    ```blade
    <input ... class="form-control required" required>
    ```
  - Verify color contrast of red asterisk (WCAG AA)
  - Test: Visual consistency of all required field markers
  - _Requirements: 2.5_

---

## Area 3: Desktop Layout & Spacing Improvements (3-4 hours)

### 3.1 Fix Form Padding for Desktop

- [-] 3.1 Update Wizard Panel Responsive Padding
  - File: `resources/views/public/report-form.blade.php`
  - Find `.wizard-panel` or main form container
  - Current classes likely: `p-3 p-lg-4` (16px on mobile, 24px at lg breakpoint)
  - Change to: `p-3 p-md-4 p-lg-5` (16px on mobile, 24px at md, 32px at lg)
  - Verify padding applies to form fields and bottom action buttons
  - Add media query if needed:
    ```css
    @media (min-width: 992px) {
        .wizard-panel {
            padding: 2rem; /* 32px */
        }
    }
    ```
  - Test at breakpoints: 375px, 768px, 992px, 1024px, 1366px
  - Verify form fields remain aligned and don't overflow
  - _Requirements: 2.4_

### 3.2 Update Modal Dialog Padding

- [~] 3.2 Enhance Modal Desktop Spacing
  - File: `resources/views/admin/users/index.blade.php` (edit user modal)
  - File: `resources/views/admin/master/index.blade.php` (edit master data modal)
  - File: Any other modals (edit processes in Sarpras, etc.)
  - Find `.modal-body` sections
  - Current classes likely: `p-3` (16px)
  - Change to: `p-3 p-lg-5` (16px on mobile, 32px at lg breakpoint)
  - Verify form fields inside modals inherit proper spacing
  - Test: Open modals on desktop, verify 32px padding and readability
  - _Requirements: 2.8_

### 3.3 Improve Button Alignment with Input Fields

- [~] 3.3 Align Bottom Action Buttons
  - File: `resources/views/public/report-form.blade.php`
  - Find `.bottom-action` or similar button container
  - Ensure buttons have consistent height with input fields:
    ```html
    <button class="btn btn-primary" style="min-height: 44px; height: 44px;">
        Lanjut
    </button>
    ```
  - Use flexbox alignment:
    ```css
    .bottom-action {
        display: flex;
        align-items: center;
        gap: 0.5rem;  /* 8px gap */
    }
    ```
  - Verify buttons align at same baseline as form inputs
  - Test on mobile and desktop
  - _Requirements: 2.4_

### 3.4 Enhance Table Cell Spacing

- [~] 3.4 Improve Table Spacing for Readability
  - Files: `resources/views/kesiswaan/index.blade.php`, `resources/views/sarpras/index.blade.php`
  - Find table elements: `<table>`, `<thead>`, `<tbody>`
  - Current padding likely: 8px vertical, 12px horizontal (Bootstrap defaults)
  - Add desktop-specific spacing:
    ```css
    @media (min-width: 1024px) {
        table td, table th {
            padding: 12px 16px; /* Increased from 8px 12px */
        }
    }
    ```
  - Verify table headers have adequate spacing
  - Ensure columns don't feel cramped on desktop
  - Test on 1024px and 1366px viewports
  - _Requirements: 2.4_

### 3.5 Create Desktop Spacing CSS Media Query

- [~] 3.5 Add Comprehensive Desktop Media Query
  - File: Create new CSS file or update existing `public/css/custom.css`
  - Add comprehensive media query for 1024px+ breakpoint:
    ```css
    /* Desktop UI/UX Improvements (1024px+) */
    @media (min-width: 1024px) {
        /* Form spacing */
        .wizard-panel {
            padding: 2rem; /* 32px */
        }
        
        /* Modal spacing */
        .modal-body {
            padding: 2rem; /* 32px */
        }
        
        /* Button alignment */
        .btn {
            min-height: 44px;
        }
        
        .bottom-action {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Table spacing */
        table td, table th {
            padding: 12px 16px;
        }
        
        /* Form field alignment */
        .form-group {
            margin-bottom: 1.5rem;
        }
    }
    ```
  - Link CSS in main layout file if not already included
  - Verify CSS loads correctly via browser DevTools
  - _Requirements: 2.4, 2.8_

---

## Validation & Testing (2-3 hours)

### 4.1 **Verify** Bug Condition Exploration Test Now Passes

- [~] 4.1 Validate Priority Fix with Original Test
  - **Property 1: Expected Behavior** - Priority Data Independence
  - **IMPORTANT**: Re-run the test from task 1.1 - do NOT write a new test
  - Run test: `php artisan test --filter="priority"`
  - Expected outcome: Test PASSES (proves fix works)
  - Verify database state: Create test damage report, check `damage_detail.priority = NULL`
  - Verify form submission still works correctly
  - _Requirements: 1.2, 2.2_

### 4.2 **Verify** Preservation Tests Still Pass

- [~] 4.2 Validate Form Submission Regression Tests
  - **Property 2: Preservation** - Form Functionality Unchanged
  - **IMPORTANT**: Run existing regression tests - do NOT skip
  - Test form submission with complete valid data:
    - Submit violation report (BullyingDetail) - verify created
    - Submit damage report (DamageDetail) - verify created with priority NULL
    - Verify email notification sent (if email provided)
    - Verify redirect to success page
  - Run test suite: `php artisan test`
  - Verify no test failures or regressions
  - _Requirements: 3.1, 3.2, 3.3_

### 4.3 Test Priority Update Workflow (Sarpras)

- [~] 4.3 Validate Admin Priority Update Flow
  - Manual test: Login as Sarpras user
  - Create or find existing damage report
  - Open report detail page
  - Click "Proses" button (open process modal)
  - Update priority from NULL to "tinggi"
  - Save update
  - Verify priority displays correctly on detail page
  - Verify priority displays correctly on list page
  - Verify priority persists after page refresh
  - _Requirements: 2.3, 3.5, 3.6_

### 4.4 Test Form Validation Error Display

- [~] 4.4 Validate Error Message Prominence
  - Manual test: Open public report form
  - Fill step 1-2 correctly
  - Go to step 3, leave required field empty
  - Click "Lanjut" to trigger validation
  - Verify error message appears in alert-danger box
  - Verify error message is scrolled into view
  - Verify error message is readable (high contrast)
  - Verify field is identified in error message
  - Test on mobile (375px) and desktop (1024px)
  - _Requirements: 1.7, 2.7_

### 4.5 Test Desktop Layout Spacing

- [~] 4.5 Validate Desktop Padding and Alignment
  - Browser test: Open form on desktop (1024px viewport)
  - Using DevTools inspector, check `.wizard-panel` padding
  - Verify padding = 32px (2rem) on all sides
  - Verify form fields are aligned and readable
  - Verify buttons are vertically centered with inputs
  - Open modal dialog (edit user, edit master data)
  - Verify modal padding = 32px on desktop
  - Verify modal content is not cramped
  - Test at multiple breakpoints: 992px, 1024px, 1366px
  - _Requirements: 2.4, 2.8_

### 4.6 Test Step Tracker Touch Targets

- [~] 4.6 Validate Step Dot Accessibility
  - Mobile test: Open form on 375px viewport
  - Click on step dot (e.g., step 2)
  - Using DevTools, inspect computed width/height of step dot
  - Verify minimum 44px width and 44px height
  - Verify step labels don't wrap
  - Verify step labels are readable
  - Test tapping each step dot - should navigate without missing
  - _Requirements: 1.6, 2.6_

### 4.7 Integration Test - Full Report Submission

- [~] 4.7 End-to-End Report Submission Test
  - Complete full workflow:
    1. Open public report form
    2. Fill all 4 steps (select Damage report type)
    3. Select urgency "darurat" in step 3
    4. Submit complete form
    5. Verify report created successfully
    6. Verify damage_detail.priority = NULL (not "darurat")
    7. Login as Sarpras user
    8. Find the report in admin list
    9. Open report detail
    10. Click "Proses" and set priority to "tinggi"
    11. Verify priority now shows "tinggi" on detail page
    12. Verify urgency still shows "darurat" (independent)
  - Verify all form submission success indicators present
  - Verify email notification sent (if configured)
  - _Requirements: 2.2, 2.3, 3.1, 3.2_

### 4.8 Mobile Responsive Verification

- [~] 4.8 Verify Mobile Layout Unchanged
  - Mobile test: Open form on 375px viewport
  - Verify all fields display in single column
  - Verify form submission works on mobile
  - Verify step navigation works on mobile
  - Verify touch targets are 44px+
  - Verify padding is still 16px (not changed)
  - Verify mobile layout is identical to before fix
  - _Requirements: 3.2_

### 4.9 Accessibility Testing

- [~] 4.9 Test Keyboard Navigation and Screen Readers
  - Keyboard test: Navigate form using Tab, Shift+Tab, Enter keys
  - Verify able to tab through all form fields
  - Verify able to activate buttons with Enter key
  - Verify able to close modals with Escape key
  - Screen reader test (NVDA or similar):
    - Open form with NVDA
    - Verify all inputs have labels announced
    - Verify validation errors announced
    - Verify modal content structure clear
    - Verify step tracker announces current step
  - Verify color contrast of all text (WCAG AA minimum)
  - _Requirements: 2.1, 2.6, 2.7_

### 4.10 Run Full Test Suite

- [~] 4.10 Execute Complete Test Suite
  - Command: `php artisan test`
  - Verify all tests pass
  - Verify no regressions in existing tests
  - Document test results
  - If any failures, fix and re-run
  - _Requirements: All_

### 4.11 Test Docker Build and CI/CD

- [~] 4.11 Verify Docker Build and CI Pipeline
  - Command: `npm run test:docker`
  - Verify Docker image builds successfully
  - Verify all tests pass in Docker environment
  - Verify CI/CD pipeline passes (GitHub Actions if configured)
  - Document any Docker-specific issues
  - _Requirements: All_

### 4.12 Browser Compatibility Testing

- [~] 4.12 Test on Multiple Browsers
  - Test on Chrome (latest) - form, modal, style rendering
  - Test on Firefox (latest) - form, modal, style rendering
  - Test on Safari (latest) - form, modal, style rendering
  - Test on Edge (latest) - form, modal, style rendering
  - Verify no browser-specific issues
  - Verify form submission works on all browsers
  - Verify styling consistent across browsers
  - _Requirements: 2.1-2.8, 3.1-3.8_

---

## Checkpoint & Sign-Off

### 5.1 Final Validation

- [~] 5.1 **Checkpoint** - All Tests Pass and Fix Verified
  - Ensure all tests from 4.1-4.12 are complete and documented
  - Ensure no regressions in existing functionality
  - Ensure backend priority fix verified on multiple environments
  - Ensure frontend layout improvements visible on desktop
  - Ensure form validation errors prominent and helpful
  - Ensure mobile experience unchanged
  - All tasks marked complete ✓

### 5.2 Documentation and Code Review

- [~] 5.2 Document Changes and Request Review
  - Update inline code comments with fix rationale
  - Document all file changes in commit message
  - Include "Co-Authored-By: Hermes Agent <noreply@nousresearch.com>"
  - Request code review from team lead
  - Document any edge cases discovered during testing
  - Create PR with complete test results

### 5.3 Production Readiness

- [~] 5.3 Final Production Checklist
  - [~] Database migration tested (if needed)
  - [~] All tests passing locally
  - [~] All tests passing in Docker
  - [~] No console errors in browser
  - [~] Performance impact assessed (none expected)
  - [~] Security review complete (no changes to auth)
  - [~] Accessibility verified (WCAG AA)
  - Ready for production deployment ✓

---

## Success Criteria

✅ **This bugfix is successful when:**

1. **Backend Fix Validated**
   - New damage reports created with priority = NULL
   - Existing damage reports with priority data unchanged
   - Sarpras priority updates persist independently

2. **Frontend Form Improvements**
   - Validation errors displayed prominently with clear identification
   - Errors scroll into view automatically
   - Step tracker dots are 44px+ (accessible touch targets)
   - Form transitions smooth between steps

3. **Desktop Layout Improvements**
   - Desktop forms use 32px padding (not 16px)
   - Modal dialogs properly spaced on desktop
   - Buttons vertically centered with inputs
   - Tables have proper cell spacing

4. **Preservation Maintained**
   - Form submission success path unchanged
   - Mobile responsive layout unchanged
   - Admin workflows continue functioning
   - All role-based access control preserved
   - Email notifications working

5. **Tests Passing**
   - All existing tests still pass
   - New backend priority tests pass
   - Form validation tests pass
   - Integration tests pass
   - No regressions identified

---

## Dependencies & Prerequisites

- PHP artisan CLI available
- Node.js and npm for frontend builds
- Docker installed (for test:docker command)
- Access to test database (SQLite for unit tests)
- Browser DevTools for responsive testing
- Screen reader software (NVDA/JAWS) for accessibility testing

## Files Modified Summary

### Backend
- `app/Services/PublicReport/PublicReportService.php` - Priority initialization fix
- `app/Services/Role/Sarpras/SarprasProcessor.php` - Verify independent updates
- Database migration - If priority nullable fix needed

### Frontend
- `resources/views/public/report-form.blade.php` - Form structure, error display, step tracker
- `resources/views/admin/users/index.blade.php` - Modal padding
- `resources/views/admin/master/index.blade.php` - Modal padding
- `resources/views/kesiswaan/index.blade.php` - Table spacing
- `resources/views/sarpras/index.blade.php` - Table spacing
- `public/css/custom.css` - Desktop media query

### Tests
- `tests/Feature/PublicReportSubmissionTest.php` - Priority initialization test
- `tests/Feature/FormValidationTest.php` - Error display test
- `tests/Feature/ResponsiveLayoutTest.php` - Desktop spacing test

