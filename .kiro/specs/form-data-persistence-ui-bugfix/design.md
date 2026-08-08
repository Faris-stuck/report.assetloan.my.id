# Form Data Persistence & Desktop UI/UX Unification - Design

## Overview

This design addresses three interconnected bugs in the LAPORIN public report form and admin workflows:

1. **Backend Data Persistence Defect** - The `damage_detail.priority` field is incorrectly populated from the `urgency` value during initial report creation, violating field independence and preventing independent priority management in admin workflows.

2. **Frontend Form Structure Fragmentation** - The multi-step form lacks cohesive visual presentation, step tracker accessibility, and clear validation error handling, reducing perceived form integrity and making errors hard to discover.

3. **Desktop UI/UX Gaps** - Desktop views use mobile-optimized spacing (16px) instead of desktop padding (32px), causing cramped layouts, poor alignment, and suboptimal readability compared to mobile-first design.

The fix involves three coordinated changes: fixing the backend persistence bug to ensure priority remains independent, improving form structure and validation UI, and implementing responsive desktop spacing.

---

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - initial damage report creation with urgency level, or viewing/processing reports on desktop layouts
- **Property (P)**: The desired behavior - priority stored independently (initially NULL), desktop forms properly spaced at 32px
- **Preservation**: Existing form submission success path, email notification system, role-based access control, existing report data integrity
- **PublicReportService**: The service in `app/Services/PublicReport/PublicReportService.php` responsible for creating new reports
- **DamageDetail Model**: The eloquent model in `app/Models/DamageDetail.php` representing damage report details with priority field
- **Report Model**: The eloquent model in `app/Models/Report.php` containing the urgency field for all reports
- **SarprasProcessor**: The service in `app/Services/Role/Sarpras/SarprasProcessor.php` that allows Sarpras staff to independently update priority
- **report-form.blade.php**: The main form view at `resources/views/public/report-form.blade.php` implementing the 4-step wizard
- **Step Tracker**: The progress indicator dots (1-4) shown above the form wizard
- **Form Validation Error**: Error messages displayed when step validation fails before advancing

---

## Bug Details

### Bug Condition

The bug manifests in three distinct areas:

**Area 1: Data Persistence Defect**
- When a public user creates a damage report and selects urgency level (e.g., "darurat") in step 3
- The form is submitted and a new Report record is created with `reports.urgency = "darurat"`
- However, the corresponding DamageDetail record is created with `damage_detail.priority = urgency_value` instead of remaining NULL
- This causes priority to always mirror urgency instead of being independently managed

**Area 2: Form Structure & Validation Issues**
- When a user fills out the 4-step form across multiple steps
- Conditional fields (e.g., Kelas Pelapor for Siswa type) may not be clearly hidden/disabled
- Form validation errors are displayed in a small container above the form
- Errors are easy to miss when the form is tall, and don't clearly identify which field failed
- Step tracker dots are small touch targets (< 44px) on mobile
- Step labels may wrap, causing layout instability

**Area 3: Desktop Layout & Spacing**
- When viewing the form or modal dialogs on desktop (1024px+)
- Form fields use mobile padding (16px via Bootstrap mb-3, p-3 classes)
- Modal dialogs use 16px padding instead of desktop-optimized 32px
- Buttons are not vertically centered with input fields
- Table cells lack proper minimum padding

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type DamageReportSubmission OR DesktopViewContext
  OUTPUT: boolean
  
  RETURN (
    // Area 1: Data Persistence
    (input.report_type = 'damage') AND
    (input.urgency ∈ ['rendah', 'sedang', 'tinggi', 'darurat']) AND
    (input.action = 'create_report') AND
    (damage_detail.priority = input.urgency_value instead of NULL)
  )
  OR (
    // Area 2: Form Structure/Validation
    (input.form_step ∈ [1, 2, 3, 4]) AND
    (input.validation_failed = true) AND
    (error_visibility = 'low' OR error_identification = 'unclear')
  )
  OR (
    // Area 3: Desktop Spacing
    (input.screen_width ≥ 1024px) AND
    (input.padding_applied < 32px)
  )
END FUNCTION
```

### Examples

**Example 1 - Priority Data Loss (Area 1):**
- Public reporter submits damage report with urgency "darurat"
- `reports.urgency` is correctly set to "darurat"
- `damage_detail.priority` is incorrectly set to "darurat" (from urgency)
- Sarpras staff later updates priority to "tinggi"
- The two fields are now different, but priority was not independently persisted initially
- **Expected**: Both fields independent from creation

**Example 2 - Form Validation Error Visibility (Area 2):**
- User fills step 1-3, reaches step 4
- User skips required field (e.g., doesn't enter CAPTCHA answer)
- Form shows validation error in small alert above form
- User scrolls down and doesn't see the error message
- **Expected**: Error message prominent, scrolled into view, identifies field clearly

**Example 3 - Desktop Layout Cramping (Area 3):**
- User views form on 1366px desktop display
- Form fields have 16px left/right padding (same as mobile)
- Modal dialogs have 16px padding (p-3), appear cramped
- Table header cells lack proper spacing
- **Expected**: 32px padding on desktop, buttons centered vertically with inputs

**Edge Case 1 - Mobile Responsive Still Works:**
- User views same form on 375px mobile display
- Form maintains 16px padding and single-column layout
- All fields remain functional and tappable (44px touch targets)
- **Expected**: Mobile layout unchanged

**Edge Case 2 - Existing Report Priority Update:**
- Sarpras staff views old damage report
- Priority was previously set to urgency value (bug)
- Staff updates priority to different value
- The update correctly saves to damage_detail.priority
- **Expected**: Update persists correctly regardless of initial state

---

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Form submission success path - reports created, detail records created, emails sent
- Mobile responsive design - all mobile functionality preserved
- Existing admin workflows - all role-based processing unchanged
- Role-based access control - auth middleware and navbar checks continue working
- Report tracking flow - tracking form and status display unchanged
- QR code functionality - QR creation, scanning, and increment logic unchanged
- File upload validation - file type, size, count validation unchanged
- Email notification system - email sending and fallback logic unchanged

**Scope:**
All inputs that do NOT involve damage report creation, form validation error display, or desktop layout should be completely unaffected by this fix. This includes:
- Mouse clicks on action buttons in admin workflows
- Report tracking and status display for non-damage reports
- Role-specific access restrictions and navbar rendering
- Existing damage reports created before this fix
- Mobile form experience on devices < 768px

### Non-Buggy Input Behavior

For inputs that do NOT trigger the bug condition (¬C(X)):

**Violation Reports** (not damage):
- Priority field does not exist in BullyingDetail
- Form submission and processing unchanged
- All existing violation workflows unchanged

**Damage Report Priority Updates** (admin workflows):
- Sarpras staff can independently update priority via modal
- Priority updates persist correctly in all views
- Admin workflows use the saved priority value
- No changes to update logic required

**Form on Mobile** (< 768px):
- All fields display in single column
- Mobile padding (16px) continues to be correct
- Touch targets remain 44px or larger
- Form submission and validation logic unchanged

**Role Access & Authorization**:
- Superadmin, Kesiswaan, Sarpras, Wali_Kelas access control preserved
- Navbar role checks continue working
- Page-level restrictions unchanged

---

## Hypothesized Root Cause

Based on the bug description and code analysis, the root causes are:

### Area 1: Priority Data Persistence Defect

**Root Cause:** Line 56 in `PublicReportService.php` creates DamageDetail with:
```php
'priority' => $validated['priority'] ?? $validated['urgency']
```

This uses urgency as a fallback when priority is not in the validated data. Since the public form step 3 collects urgency (not priority), this always falls back to urgency value. The priority field should be initially set to NULL (database default), allowing independent management later.

**Why it occurs:**
- Priority is an admin-only field, not user-facing in the public form
- The fallback logic treats urgency as a temporary priority value
- No distinction between initial creation (should be NULL) and admin updates (should use admin-provided value)

### Area 2: Form Structure & Validation Issues

**Root Cause 1 - Form Validation Error Display:**
- Error message rendered in small `.invalid-step-hint` container with `x-show`
- Container styled with minimal styling, low contrast
- No scroll-into-view behavior when error occurs
- Error doesn't specify which field failed

**Root Cause 2 - Step Tracker Accessibility:**
- Step dots use basic button styling without guaranteed 44px minimum on mobile
- Step labels can wrap on narrow mobile devices
- Current step hint uses `.small-muted` class (too small, too faded)

**Root Cause 3 - Conditional Field Layout Shifts:**
- Hidden fields use `x-show="reporter==='siswa'"` with display: none
- But hidden fields still reserve space in the layout
- No clear visual indicator that fields are conditionally shown/hidden

### Area 3: Desktop Layout & Spacing Defects

**Root Cause 1 - Form Padding:**
- Wizard panel uses Bootstrap utility classes `.p-3 p-lg-4`
- `p-3` = 16px (1rem), `p-lg-4` = 24px (1.5rem)
- Neither reaches 32px (2rem) desktop standard
- Desktop breakpoint is `lg: 992px`, but 32px padding needed at 1024px+

**Root Cause 2 - Modal Padding:**
- Edit modals use `.p-3` (16px) consistently
- Desktop modals should use `.p-lg-5` or custom desktop breakpoint
- Forms within modals inherit the parent padding, creating cramped layout

**Root Cause 3 - Button Alignment:**
- Buttons in `.bottom-action` section use flex layout
- Buttons don't have defined vertical alignment with preceding input fields
- Field height and button height may not align visually

**Root Cause 4 - Table Spacing:**
- Table cells use Bootstrap defaults (8px vertical, 12px horizontal)
- No desktop-specific increased spacing
- Header and content rows lack visual separation

---

## Correctness Properties

Property 1: Bug Condition - Data Independence on Initial Creation

_For any_ damage report creation where a public user selects urgency level in step 3 and submits the form, the fixed PublicReportService SHALL initialize `damage_detail.priority` to NULL (not populated from urgency), allowing Sarpras staff to independently set priority in admin workflows without any initial mirroring of urgency.

**Validates: Requirements 2.2, 2.3**

Property 2: Bug Condition - Desktop Form Spacing

_For any_ form or modal viewed on desktop screen width ≥ 1024px, the fixed frontend implementation SHALL apply 32px padding (Bootstrap p-lg-5 or equivalent), ensure buttons are vertically centered with input fields, ensure table cells have minimum 8px vertical and 12px horizontal padding, and ensure step tracker dots have minimum 44px touch targets.

**Validates: Requirements 2.4, 2.6, 2.8**

Property 3: Bug Condition - Form Validation Error Visibility

_For any_ form step validation failure, the fixed frontend implementation SHALL display error messages in a prominent alert box with clear field identification, scroll the error into view when validation fails, use alert-danger styling with high contrast text, and prevent step advancement until errors are resolved.

**Validates: Requirements 2.5, 2.7**

Property 4: Preservation - Form Submission Success Path

_For any_ valid form submission (all steps completed, all validations pass), the fixed code SHALL create Report record with urgency, create DamageDetail or BullyingDetail record with correct data, store attachments with correct metadata, send email notification if email provided, and redirect to success page with report number and access code. The behavior SHALL be identical to the original unfixed code.

**Validates: Requirements 3.1, 3.2, 3.3**

Property 5: Preservation - Admin Workflows

_For any_ Sarpras staff member processing an existing damage report in the admin panel, the fixed code SHALL allow independent priority updates via the process modal, persist priority correctly to damage_detail.priority, display saved priority in all views (detail page, list pages, tracking), and maintain all existing workflow transitions (scheduled repair, completed repair, rejection).

**Validates: Requirements 3.4, 3.5, 3.6, 3.7**

---

## Fix Implementation

### Area 1: Backend Data Persistence Fix

**File**: `app/Services/PublicReport/PublicReportService.php`

**Function**: `create()` method (line 26) and data creation section (lines 53-56)

**Specific Changes**:

1. **Remove Priority Fallback to Urgency**:
   - Line 56 currently: `'priority' => $validated['priority'] ?? $validated['urgency']`
   - Change to: `'priority' => null` (rely on database default)
   - Rationale: Priority is admin-only field. Initial creation should leave it NULL. Admin updates set it independently via SarprasProcessor.

2. **Database Migration (if needed)**:
   - Current migration already defines: `$table->enum('priority', [...])` with `->default('sedang')`
   - Update migration to: `->default(null)->nullable()` if default='sedang' causes issues
   - Rationale: Allows true NULL state for initial creation

3. **Documentation in Code**:
   - Add comment above DamageDetail creation: "Priority initialized to NULL on creation; Sarpras staff sets priority independently via process modal"

### Area 2: Frontend Form Structure Improvements

**File**: `resources/views/public/report-form.blade.php`

**Changes 1 - Unified Multi-Step Form Presentation**:
1. Maintain 4-step wizard structure (already cohesive)
2. Ensure wizard panel (.wizard-panel) has consistent padding across all steps
3. Verify step content area has stable height with no layout shifts
4. Use Alpine.js `x-show` (already done) with display:none to hide step content

**Changes 2 - Improved Step Tracker Accessibility**:
1. Increase step dot button size:
   - Change `.step-dot` from current size to minimum 44px width/height on mobile
   - Use CSS: `min-width: 44px; min-height: 44px;`
2. Stabilize step labels:
   - Wrap step labels in fixed-height container to prevent wrapping
   - Use `max-width: 100px` to constrain label width
   - Use `white-space: nowrap` with text truncation if needed
3. Improve current step hint:
   - Change from `.small-muted` to `.small` with higher contrast
   - Increase font size from 12px to 14px

**Changes 3 - Better Form Validation Error Display**:
1. Replace `.invalid-step-hint` styling:
   - Add Bootstrap alert styling: `class="alert alert-danger"`
   - Make container wider and more prominent
   - Use better color contrast (alert-danger is high contrast)
2. Add scroll-into-view behavior:
   - When `x-show="stepError"`, trigger: `element.scrollIntoView({ behavior: 'smooth', block: 'center' })`
   - Add to Alpine.js `next()` method after validation fails
3. Identify specific fields in error message:
   - Modify `validateCurrentStep()` to capture first invalid field's label
   - Include field name in error message: "Lengkapi field '{fieldLabel}' atau perbaiki format."

**Changes 4 - Consistent Conditional Field Rendering**:
1. Ensure hidden conditional fields use `x-show` (already done)
2. Add CSS to prevent layout shifts:
   - For fields with `x-show`: wrap in container with `display: contents` when hidden
   - Or use `x-cloak` to prevent initial flash
3. Clear visual indication:
   - Add aria-live="polite" to step sections for screen reader updates
   - Ensure disabled state is visually distinct (grayed out)

### Area 3: Desktop UI/UX Improvements

**File**: `resources/views/public/report-form.blade.php` and CSS files

**Changes 1 - Form Padding for Desktop**:
1. Wizard panel padding:
   - Current: `p-3 p-lg-4`
   - Change to: `p-3 p-md-4 p-lg-5` (or custom media query)
   - Rationale: p-lg-5 = 32px at lg breakpoint (992px+)
2. Form fields spacing:
   - Ensure all input groups inherit padding from parent
   - Add explicit left/right margin if needed for alignment

**Changes 2 - Modal Dialog Spacing**:
1. Edit modals for users, classes, etc.:
   - Add responsive padding: `.modal-body` use `p-3 p-lg-5`
   - Forms within modals inherit proper spacing
2. Process damage modal (in Sarpras workflow):
   - Same padding treatment as other modals

**Changes 3 - Button Alignment**:
1. Bottom action buttons:
   - Ensure buttons have `min-height: 44px` (mobile touch target)
   - Use vertical flex alignment: `align-items: center`
   - Ensure baseline alignment with preceding input fields
2. Button grouping:
   - Use gap spacing consistently (already done with `gap-2`)
   - On desktop, buttons should have 8px gap

**Changes 4 - Table Spacing**:
1. Report list tables:
   - Header padding: `8px vertical, 12px horizontal` (minimum)
   - Content cell padding: `8px vertical, 12px horizontal`
   - Desktop breakpoint: increase to `12px vertical, 16px horizontal`

**CSS Media Query Example**:
```css
/* Desktop spacing adjustments (1024px+) */
@media (min-width: 1024px) {
  .wizard-panel {
    padding: 2rem; /* 32px */
  }
  
  .modal-body {
    padding: 2rem; /* 32px */
  }
  
  .btn {
    min-height: 44px;
  }
  
  table td, table th {
    padding: 12px 16px; /* Increased from 8px 12px */
  }
}
```

---

## Testing Strategy

### Validation Approach

The testing strategy follows a three-phase approach: first, surface counterexamples that demonstrate the bugs on unfixed code; second, verify the fixes work correctly; third, confirm existing behavior is preserved.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate each bug on UNFIXED code. Confirm or refute the root cause analysis.

**Test Plan for Area 1 - Data Persistence**:
- Create a test that submits a public damage report with urgency="darurat"
- Query the database to verify `reports.urgency = 'darurat'`
- Query `damage_detail.priority` and verify it equals urgency_value (this confirms the bug)
- Expected counterexample: `damage_detail.priority = 'darurat'` instead of NULL

**Test Plan for Area 2 - Form Validation**:
- Submit step 3 with missing required field
- Verify error message appears in `.invalid-step-hint`
- Measure `.invalid-step-hint` styling (should be low contrast on unfixed code)
- Verify error doesn't clearly identify which field failed
- Expected counterexample: Error message is small, not prominently displayed

**Test Plan for Area 3 - Desktop Spacing**:
- Open form on desktop viewport (1024px)
- Inspect `.wizard-panel` computed padding (should be 24px = p-lg-4)
- Inspect modal `.modal-body` computed padding (should be 16px = p-3)
- Compare to 32px desktop standard
- Expected counterexample: Padding is 16-24px instead of 32px

**Test Cases**:
1. **Damage Report Priority Test**: Submit damage report, verify priority NULL (will fail on unfixed code)
2. **Form Error Visibility Test**: Verify error styling and scroll behavior (will fail on unfixed code)
3. **Desktop Padding Test**: Verify 32px padding at 1024px viewport (will fail on unfixed code)
4. **Mobile Touch Target Test**: Verify step dots are 44px on mobile (may fail on unfixed code)

**Expected Counterexamples**:
- Database shows priority = urgency value, not NULL
- Error messages are small and easy to miss
- Desktop padding is 24px or less, not 32px
- Step dots may be smaller than 44px

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed code produces the expected behavior.

**Pseudocode**:
```
// Area 1: Data Independence
FOR ALL damage_reports WHERE isBugCondition(report) DO
  result := PublicReportService.create(report_with_urgency)
  ASSERT result.damage_detail.priority IS NULL
  ASSERT result.report.urgency = submitted_urgency_value
END FOR

// Area 2: Error Visibility
FOR ALL form_submissions WHERE validation_fails DO
  ASSERT error_displayed_in_alert_box()
  ASSERT error_message_identifies_field()
  ASSERT error_scrolled_into_view()
END FOR

// Area 3: Desktop Spacing
FOR ALL pages WHERE screen_width ≥ 1024px DO
  ASSERT form_padding = 32px
  ASSERT modal_padding = 32px
  ASSERT button_height ≥ 44px
  ASSERT button_baseline_aligned_with_inputs()
  ASSERT table_cell_padding ≥ 12px_vertical
END FOR
```

**Test Plan**:
1. **Unit Test - Priority Initialization**:
   - Create damage report via PublicReportService
   - Assert `damage_detail.priority` is NULL
   - Assert `report.urgency` equals submitted value

2. **Unit Test - Error Display**:
   - Trigger validation failure in step 3
   - Assert error appears in `.alert.alert-danger`
   - Assert error message includes field name

3. **Integration Test - Full Flow**:
   - Submit complete damage report form
   - Verify report created with urgency
   - Verify damage_detail created with NULL priority
   - Verify Sarpras can update priority independently
   - Verify priority persists in all views

4. **Frontend Test - Responsive Spacing**:
   - Render form at 1024px
   - Inspect computed styles for padding
   - Assert 32px padding applied

5. **Accessibility Test**:
   - Verify step dots are 44px+ on mobile
   - Verify error messages readable with high contrast
   - Verify keyboard navigation through form steps works

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed code produces the same result as the original.

**Pseudocode**:
```
// Preservation: Form Submission Success
FOR ALL valid_forms WHERE all_fields_completed AND no_validation_errors DO
  ASSERT F'(form_data) = F(form_data)
  // Form submission behavior unchanged
  // Report created with correct data
  // Detail records created correctly
  // Emails sent if email provided
  // Success redirect works
END FOR

// Preservation: Admin Workflows
FOR ALL existing_reports WHERE Sarpras_staff_processing DO
  ASSERT F'(admin_update) = F(admin_update)
  // Priority update logic unchanged
  // Status transitions work
  // Notifications send correctly
END FOR

// Preservation: Mobile Responsive
FOR ALL forms WHERE screen_width < 768px DO
  ASSERT F'(mobile_layout) = F(mobile_layout)
  // All form fields responsive
  // Touch targets 44px
  // Single column layout
END FOR
```

**Test Plan**:
1. **Regression Test - Form Submission**:
   - Submit complete valid form (violation and damage)
   - Verify report created with all data
   - Verify detail records created
   - Verify email sent
   - Verify redirect to success page
   - Assert behavior identical to unfixed code

2. **Regression Test - Admin Workflows**:
   - Process existing damage report as Sarpras
   - Update priority to different value
   - Verify priority persists in database
   - Verify priority displays correctly in detail view
   - Verify status transitions work
   - Assert behavior identical to unfixed code

3. **Regression Test - Mobile Responsive**:
   - View form at 375px, 480px, 768px
   - Verify all fields functional
   - Verify touch targets 44px+
   - Verify single column layout maintained
   - Assert layout behavior identical to unfixed code

4. **Regression Test - Role Access**:
   - Verify Superadmin can access master data forms
   - Verify Sarpras can only process damage reports
   - Verify Kesiswaan can only process violation reports
   - Assert access control identical to unfixed code

### Unit Tests

- Test PublicReportService.create() with damage report, verify priority NULL
- Test PublicReportService.create() with violation report, verify no priority field
- Test SarprasProcessor.process() with priority update, verify persistence
- Test form validation error message generation, verify field identification
- Test responsive padding calculations at various breakpoints

### Property-Based Tests

- Generate random damage reports with all urgency values, verify priority always NULL
- Generate random form submissions at various breakpoints, verify padding correct
- Generate random priority updates in Sarpras workflow, verify independence from urgency
- Generate random Sarpras operations, verify no unintended changes to report data

### Integration Tests

- Full user journey: submit damage report → Sarpras processes → updates priority → verify displays correctly
- Form submission with validation errors at each step
- Desktop and mobile form completion
- Email notification generation for submitted reports
- Role-based access to report processing pages

