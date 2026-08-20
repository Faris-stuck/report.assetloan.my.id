# Bugfix Requirements: Form Structure, Data Persistence, and UI/UX Unification

## Introduction

This bugfix addresses three interconnected issues in the LAPORIN application's public report form and admin workflows:

1. **Form Structure Fragmentation** - The multi-step form creates perceived separation, affecting user experience
2. **Data Persistence Defect** - The urgency level field value is stored in the main Report table, but the priority field in DamageDetail is populated from urgency instead of being independently persisted, causing data inconsistency when different users view reports
3. **Desktop UI/UX Issues** - Desktop layout lacks proper spacing, alignment, and visual hierarchy compared to mobile views

These issues combine to create a disjointed user experience where form data may not display consistently across different user roles and device types.

---

## Bug Analysis

### Current Behavior (Defect)

#### 1.1 Multi-Step Form Appears Separated
WHEN a user navigates the 4-step wizard form on the public report page
THEN the form appears visually and structurally separated across steps, making it difficult to understand the complete form structure at once and reducing perceived form coherence

#### 1.2 Urgency/Priority Data Not Independently Persisted
WHEN a public user selects urgency level "darurat" in step 3 of the form for a damage report
AND submits the report
THEN the urgency is correctly saved to `reports.urgency` column
BUT the `damage_detail.priority` field is set to the urgency value rather than being an independent field, causing the priority to always mirror urgency instead of allowing independent management

#### 1.3 DamageDetail.priority Displays Incorrectly in Staff Views
WHEN a Sarpras user views a report submitted with urgency "darurat"
THEN the report detail page shows priority badge using the derived urgency value
AND if an admin later changes the priority in the damage processing modal to a different value
THEN the display may not reflect independent priority management because priority was initially populated from urgency, not from independent persistence

#### 1.4 Desktop Layout Lacks Proper Spacing and Alignment
WHEN viewing the report form or list pages on desktop (1024px+)
THEN form fields have inconsistent left/right margins and padding
AND button alignment is not vertically centered with input fields
AND modal dialogs use mobile-optimized padding (same as mobile 16px) instead of desktop padding (32px)
AND table columns lack proper spacing between header and content cells

#### 1.5 Form Field Labeling Inconsistency
WHEN viewing conditional form fields (e.g., Kelas Pelapor only for Siswa)
THEN disabled/hidden fields still reserve space or create layout shifts
AND there is no clear visual indicator that fields are conditionally rendered
AND placeholder text varies in format across different field types

#### 1.6 Multi-Step Progress Indicator Unclear on Mobile
WHEN a mobile user views the form step tracker
THEN the step dots are small and difficult to tap (< 44px touch target)
AND the step labels below dots wrap, causing layout instability
AND current step hint text is too small (small-muted class)

#### 1.7 Form Validation Error Display Not Prominent
WHEN form validation fails on step 3 and the error is displayed
THEN the error message appears in a small container above the form
AND is easy to miss when the form is tall
AND does not clearly identify which field failed validation

#### 1.8 Desktop Modal Dialogs Use Mobile Padding
WHEN opening an edit modal on desktop (e.g., edit user in admin panel)
THEN the modal padding is 16px (mb-3, p-3) instead of 32px
AND this creates cramped, hard-to-scan content compared to desktop standards

---

## Expected Behavior (Correct)

#### 2.1 Form Structure Unified Visually
WHEN a user navigates the 4-step wizard form on the public report page
THEN each step appears as part of a cohesive form progression
AND step transitions are smooth without re-rendering the entire container
AND all form fields are aligned consistently across steps
AND the wizard panel maintains consistent padding and spacing (32px on desktop, 16px on mobile)

#### 2.2 Urgency Saved to Report Table, Priority Independently in DamageDetail
WHEN a public user selects urgency level "darurat" in step 3 for a damage report
AND submits the report
THEN urgency is saved to `reports.urgency` column
AND priority is initially set to NULL (not populated from urgency) in `damage_detail` table
AND a Sarpras user can independently set priority without it automatically mirroring urgency

#### 2.3 DamageDetail.priority Persists Independently in Admin Workflows
WHEN a Sarpras user updates a damage report and sets priority to "tinggi"
THEN the priority is saved to `damage_detail.priority` column as an independent value
AND the value persists across all views (report detail page, list pages, tracking page)
AND priority and urgency are treated as separate attributes
AND the priority badge on the detail page reflects the saved priority value, not urgency

#### 2.4 Desktop Layout Uses Proper Spacing and Alignment
WHEN viewing the report form on desktop (1024px+)
THEN form fields have consistent left/right padding of 32px
AND form label and input are vertically aligned at the same baseline
AND button groups below the form are centered with 8px gap spacing
AND modal dialogs use 32px padding on desktop (vs 16px on mobile)
AND table cells have proper padding (8px vertical, 12px horizontal minimum)

#### 2.5 Form Field Labeling Clear and Consistent
WHEN viewing conditional form fields (e.g., Kelas Pelapor only for Siswa)
THEN visible fields maintain consistent label formatting (required marker, font weight)
AND hidden fields do not reserve space or cause layout shifts (using `x-show` with `display: none`)
AND placeholder text follows consistent formatting across all input types
AND required fields are clearly marked with red asterisk and `required` class

#### 2.6 Multi-Step Progress Indicator Accessible and Clear
WHEN a user views the form step tracker
THEN step dots have minimum 44px touch target on mobile
AND step labels are stable and do not wrap
AND current step hint text is readable (14px, higher contrast than small-muted)
AND active step dot is clearly highlighted with distinct color and styling

#### 2.7 Form Validation Error Display Prominent and Specific
WHEN form validation fails on any step
THEN an error message appears in a highlighted alert box
AND error message identifies the specific field that failed (if possible)
AND error container is scrolled into view when validation fails
AND error styling uses alert-danger with clear text (not muted)

#### 2.8 Desktop Modal Dialogs Use Desktop Padding
WHEN opening a modal dialog on desktop
THEN modal uses 32px padding (p-4 or p-lg-4 in Bootstrap 5)
AND modal content is properly spaced and readable
AND modal maintains consistent styling across all modals (edit users, edit master data, process reports)

---

## Unchanged Behavior (Regression Prevention)

#### 3.1 Form Submission Success Path Unchanged
WHEN a user completes all 4 steps and submits a valid report
THEN the form successfully creates a Report record with all data
AND BullyingDetail or DamageDetail records are created correctly
AND email notification is sent if email provided
AND user is redirected to success page with report number and access code
AND no existing reports are affected

#### 3.2 Mobile Responsive Design Preserved
WHEN viewing the form on mobile (< 768px)
THEN form maintains single-column layout
AND step tracker remains compact
THEN all fields remain functional and tappable
AND form submission still works as expected

#### 3.3 Existing Admin Workflows Unaffected
WHEN a Kesiswaan or Sarpras user processes an existing report
THEN all existing reports continue to display correctly
AND process/reject modals remain functional
AND form submission in modals continues to work
AND no audit logs or status history changes

#### 3.4 Role-Based Access Control Preserved
WHEN accessing role-specific pages (Kesiswaan, Sarpras, Admin)
THEN role authorization continues to work as before
AND navbar role checks continue to function
AND page-level access restrictions remain intact

#### 3.5 Report Tracking Flow Unchanged
WHEN a public user enters report number and access code in the tracking form
THEN report tracking page loads correctly
AND report status displays accurately
AND update form (add info, mark resolved) continues to work

#### 3.6 QR Code Generation and Scanning Unchanged
WHEN a QR code is scanned and creates a report
THEN report is created with qr_code_id set correctly
AND scan count increments properly
AND all QR code-specific logic continues to work

#### 3.7 File Upload and Attachment Logic Unchanged
WHEN a user uploads attachments in step 4
THEN files are validated (type, size, count)
THEN files are stored securely
THEN attachment records are created with correct metadata
THEN existing attachments display and download correctly

#### 3.8 Email Notification System Unchanged
WHEN a report is submitted with an email address
THEN confirmation email is sent to the provided address
THEN email contains correct report number and access code
THEN if email fails, a fallback message still displays to user
AND no email delivery logic is changed

---

## Bug Condition Derivation

From the requirements above, the bug condition and preservation goal are:

**Bug Condition C(X)** - Identifies inputs that trigger the bug:
```
C(X) = (
  (report_type = 'damage') AND 
  (urgency_level ∈ ['rendah', 'sedang', 'tinggi', 'darurat']) AND
  (form_is_submitted = true) AND
  (viewed_by_role ∈ ['sarpras', 'kesiswaan', 'superadmin'])
)
```

**Key Observations:**
- Urgency is set in the form (step 3) by public reporter
- Urgency should be independent from priority in DamageDetail
- Desktop users experience layout/spacing issues that don't affect functionality
- Multi-step form structure affects UX but not data persistence

**Property Specification** - Correct behavior for buggy inputs:
```
// Property: Fix Checking - Data Independence
FOR ALL damage_reports WHERE C(X) DO
  saved_report.urgency ← from_reports_table
  saved_priority ← from_damage_detail_table
  ASSERT saved_priority = NULL  // Initially independent
  ASSERT saved_report.urgency ≠ saved_priority (after admin updates priority)
END FOR

// Property: Fix Checking - Desktop UI Spacing
FOR ALL pages WHERE screen_width ≥ 1024px DO
  form_padding ← 32px
  modal_padding ← 32px
  field_alignment ← vertical-center
  ASSERT all_spacing_consistent()
  ASSERT touch_targets ≥ 44px
END FOR
```

**Preservation Goal** - Expressed in structured pseudocode:
```
// Property: Preservation Checking - Form Functionality
FOR ALL valid_forms WHERE NOT C(X) OR form_submitted DO
  ASSERT F'(form_data) = F(form_data)
  // Form submission behavior unchanged
  // Validation rules unchanged
  // Success redirect path unchanged
  // Email notification logic unchanged
END FOR

// Property: Preservation Checking - Role Access
FOR ALL role_checks WHERE auth_middleware_active DO
  ASSERT F'(access_check) = F(access_check)
  // No changes to auth behavior
  // Role-based access control preserved
END FOR
```

---

## Summary

This bugfix requires three coordinated changes:

1. **Backend** - Ensure `damage_detail.priority` is independently persisted (initially NULL, not populated from urgency)
2. **Frontend** - Improve desktop layout spacing and modal padding to meet desktop UX standards
3. **Frontend** - Enhance form UI/UX with better validation error handling and progress indication

All changes maintain backward compatibility with existing reports and workflows while fixing the identified defects.
