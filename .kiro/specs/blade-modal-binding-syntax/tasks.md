# Implementation Plan

## Overview

This plan fixes the Blade modal binding syntax bug where nested blade echo tags `{{ }}` inside a `:show` attribute cause a ParseError. The fix removes the echo tags and passes the PHP expression directly to the component attribute.

---

- [ ] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Modal Component ParseError
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bug exists
  - **Scoped PBT Approach**: For this syntax bug, we verify the ParseError occurs when rendering the view
  - Test that loading the `admin.users.index` view throws ParseError with "unexpected token '<'" message
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS with ParseError (this proves the bug exists)
  - Document the error: ParseError at line 114 with "syntax error, unexpected token '<'"
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.2_

- [ ] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Modal Behavior Preserved
  - **IMPORTANT**: Follow observation-first methodology
  - Since this is a syntax bug preventing rendering, we document the expected behaviors that MUST be preserved after the fix:
    - Modal auto-shows when `old('edit_user_id')` has a value (validation error scenario)
    - Modal remains hidden when `old('edit_user_id')` is null or empty
    - Edit button opens modal and populates form fields via Alpine.js
    - Modal close functionality works (button and Escape key)
  - These behaviors are verified manually/integration tests after fix since the bug prevents rendering
  - _Requirements: 3.1, 3.2, 3.3_

- [ ] 3. Fix for Blade modal binding syntax error

  - [ ] 3.1 Implement the fix
    - Open `resources/views/admin/users/index.blade.php`
    - Locate the `<x-modal>` component with the malformed `:show` attribute (approximately line 114)
    - Change `:show="{{ old('edit_user_id') ? 'true' : 'false' }}"` to `:show="old('edit_user_id') ? true : false"`
    - Remove the blade echo tags `{{ }}` from the attribute value
    - Change string values `'true'` and `'false'` to boolean values `true` and `false`
    - _Bug_Condition: isBugCondition(bladeTemplate) where :show attribute contains nested {{ }} tags_
    - _Expected_Behavior: View compiles successfully, modal receives boolean PHP expression directly_
    - _Preservation: Modal auto-show, edit button functionality, close behavior remain unchanged_
    - _Requirements: 2.1, 2.2, 3.1, 3.2, 3.3_

  - [ ] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Modal Component Renders Without ParseError
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Verify the `admin.users.index` view renders without ParseError
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: 2.1, 2.2_

  - [ ] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Modal Behavior Preserved
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Verify all preserved behaviors work correctly:
      - Normal page load: modal is hidden by default
      - Edit button click: modal opens with correct user data populated
      - Validation error: modal auto-shows on page reload
      - Modal close: dismisses modal correctly
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all modal interactions work as expected after fix

- [ ] 4. Checkpoint - Ensure all tests pass
  - Verify the Blade view compiles without errors
  - Verify the admin users index page loads successfully (200 status)
  - Verify modal show/hide behavior works correctly
  - Verify edit user flow works end-to-end
  - Ask the user if questions arise
