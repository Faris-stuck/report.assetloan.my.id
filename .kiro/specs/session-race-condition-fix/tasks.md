# Implementation Plan: Session Race Condition Fix

## Overview

This plan follows the exploratory bugfix workflow:
1. Write tests BEFORE implementing fix to understand the bug (Bug Condition)
2. Write tests for non-buggy behavior to ensure preservation (Preservation Requirements)
3. Implement the fix based on understanding from steps 1-2
4. Verify all tests pass after fix

---

## Step 1: Bug Condition Exploration Test

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Concurrent Session Validation and Clearing
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the race condition exists
  - **Scoped PBT Approach**: Scope the property to concurrent request scenarios where both requests validate successfully but session clearing causes inconsistent state
  
  **Test Implementation Details** (from Bug Condition in design):
  - Create two concurrent requests from same session to `addInfo()` method
  - Both requests have valid session (within TTL, correct report ID, access_ok=true)
  - Both requests hit `hasTrackingAccess()` nearly simultaneously
  - Simulate timing where Request A's `hasTrackingAccess()` clears session before Request B checks
  - Assert that one request exhibits race condition symptoms: either
    - Request B sees cleared session even though it passed initial validation
    - Request B proceeds with inconsistent state
    - Subsequent requests fail mysteriously
  
  **Expected Behavior** (from expectedBehavior in design):
  - `hasTrackingAccess()` should return boolean without side effects
  - No session clearing should happen during validation
  - Concurrent requests should see consistent validation results
  - Session clearing should only happen once per request in error handler
  
  **Run Test On**: UNFIXED code
  - Execute test against current `TrackingController` with problematic `hasTrackingAccess()`
  
  **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Failure should show: "Session cleared inconsistently" or similar race condition symptom
  - Document the specific counterexample: concurrent request timing that demonstrates the issue
  
  **Mark Complete When**:
  - Test is written and executable
  - Test is run on unfixed code and fails
  - Failure output is documented showing the race condition
  - Counterexample is noted (e.g., "Request B proceeds after session cleared by Request A")
  
  _Requirements: 1.0 (Bug Condition), 3.0 (Root Cause), 5.0 (Solution Approach)_

---

## Step 2: Preservation Property Tests

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Single Request Behavior Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - **GOAL**: Capture and preserve existing behavior for non-buggy scenarios
  - **Do NOT write new behavior** - write tests for behavior that currently works
  
  **Observation Phase** (Run against UNFIXED code first):
  - Observe: Valid session + valid status in addInfo → note returns success message
  - Observe: Valid session + invalid status in addInfo → note returns error message
  - Observe: Expired session in addInfo → note returns redirect to track.form
  - Observe: Mismatched report ID → note returns redirect to track.form
  - Observe: Valid confirmComplete with status=menunggu_konfirmasi → note returns success
  - Observe: confirmComplete with invalid status → note returns error message
  
  **Test Scenarios** (from Preservation Requirements in design):
  
  **Preservation 4.1: Session Creation**
  - Given: User searches with valid credentials
  - When: Report found and access_code matches
  - Then: Session contains track_report_id, track_access_ok, track_verified_at
  - Property: For all valid search attempts, session is created with all three keys
  
  **Preservation 4.2: Session TTL Enforcement**
  - Given: Session created with track_verified_at = now()
  - When: Request made after 1800+ seconds
  - Then: hasTrackingAccess() returns false
  - Property: For all timestamps > TTL, validation returns false
  
  **Preservation 4.3: Report Ownership**
  - Given: User authenticated for Report A
  - When: User attempts to access Report B
  - Then: hasTrackingAccess(report_b) returns false
  - Property: For all mismatched report IDs, validation returns false
  
  **Preservation 4.4: Status Validation for addInfo**
  - Given: addInfo request with valid session
  - When: Report status not in [memerlukan_informasi, dibuka_kembali, menunggu_konfirmasi]
  - Then: Return error "Aksi tambah informasi tidak tersedia"
  - Property: For all disallowed statuses, addInfo shows error without modifying report
  
  **Preservation 4.5: Status Validation for confirmComplete**
  - Given: confirmComplete request with valid session
  - When: Report status != menunggu_konfirmasi
  - Then: Return error "Laporan belum berada pada tahap menunggu konfirmasi"
  - Property: For all non-menunggu_konfirmasi statuses, confirmComplete shows error
  
  **Preservation 4.6: Status Transitions**
  - Given: Valid addInfo request on memerlukan_informasi or menunggu_konfirmasi
  - When: Note submitted and request processed
  - Then: Report status changes to dibuka_kembali (or stays dibuka_kembali if already there)
  - Property: For all valid status changes, transitions happen as documented
  
  **Preservation 4.7: Notification Dispatch**
  - Given: Valid addInfo changing status to dibuka_kembali
  - When: Request succeeds
  - Then: kirimNotifikasiStatus() called with correct parameters
  - Property: For all valid status transitions, notifications are dispatched
  
  **Preservation 4.8: Audit Trail**
  - Given: Any successful status transition
  - When: Request completes
  - Then: ReportStatusHistory record created with previous_status, new_status, actor_type, public_note
  - Property: For all transitions, audit trail is recorded
  
  **Test Execution**:
  - Write property-based tests covering all 8 preservation requirements
  - Property-based approach recommended for stronger guarantees (generates many test cases)
  
  **Run Tests On**: UNFIXED code
  - Verify all preservation tests PASS on current implementation
  - This confirms baseline behavior to preserve
  
  **EXPECTED OUTCOME**: All tests PASS (this confirms baseline behavior)
  - All preservation properties hold true on unfixed code
  - This establishes what must not break during fix
  
  **Mark Complete When**:
  - All preservation tests written
  - All tests run on unfixed code
  - All tests pass (confirming baseline)
  - Test names and properties documented
  
  _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8 (All Preservation Requirements)_

---

## Step 3: Implement the Fix

- [x] 3. Fix for Session Race Condition
  - Apply the fix based on understanding from exploration tests (step 1) and preservation tests (step 2)
  - _Bug_Condition: Multiple concurrent requests with non-atomic validation + session clearing_
  - _Expected_Behavior: Pure validation function returns boolean without side effects_
  - _Preservation: All preservation requirements 4.1-4.8 must still hold_
  - _Requirements: 5.0 (Solution Approach), 5.1-5.4 (Implementation Changes), 6.0 (Acceptance Criteria)_

  - [x] 3.1 Refactor hasTrackingAccess() to pure validation
    - Remove `session()->forget()` call from validation function
    - Return only boolean (no side effects)
    - Document that this is now a pure validation function
    - **Code Changes**:
      - Delete: `session()->forget(['track_report_id', 'track_access_ok', 'track_verified_at']);`
      - Keep: All boolean checks and return statements
    - **File**: `app/Http/Controllers/TrackingController.php`
    - _Bug_Condition: hasTrackingAccess validates AND clears non-atomically_
    - _Expected_Behavior: hasTrackingAccess returns boolean without mutations_
    - _Preservation: TTL, ownership, and access checks still work_
    - _Requirements: 5.1, 2.1_

  - [x] 3.2 Create clearTrackingSession() private helper method
    - Add new private method that clears all three session keys
    - Called only from error handlers in public methods
    - Centralizes session clearing logic
    - **Code Changes**:
      ```php
      private function clearTrackingSession(): void
      {
          session()->forget(['track_report_id', 'track_access_ok', 'track_verified_at']);
      }
      ```
    - **Location**: After `confirmComplete()` method, before `hasTrackingAccess()`
    - **File**: `app/Http/Controllers/TrackingController.php`
    - _Expected_Behavior: Atomic clearing from single location_
    - _Requirements: 5.2_

  - [x] 3.3 Update addInfo() method to clear session at error handler level
    - After `hasTrackingAccess()` validation check, add `clearTrackingSession()` call
    - Only clears when validation fails (returns false)
    - All other logic remains unchanged
    - **Code Changes**:
      ```php
      if (! $this->hasTrackingAccess($report)) {
          $this->clearTrackingSession();  // ADD THIS LINE
          return redirect()->route('track.form')
              ->withErrors([...]);
      }
      ```
    - **File**: `app/Http/Controllers/TrackingController.php`
    - **Unchanged**: All status checks, note creation, status transitions, notifications
    - _Expected_Behavior: Session cleared atomically with validation result_
    - _Preservation: All status validation, notifications, audit trail_
    - _Requirements: 5.3, 4.4, 4.6, 4.7, 4.8_

  - [x] 3.4 Update confirmComplete() method to clear session at error handler level
    - Same pattern as addInfo()
    - After `hasTrackingAccess()` validation check, add `clearTrackingSession()` call
    - Only clears when validation fails (returns false)
    - All other logic remains unchanged
    - **Code Changes**:
      ```php
      if (! $this->hasTrackingAccess($report)) {
          $this->clearTrackingSession();  // ADD THIS LINE
          return redirect()->route('track.form')
              ->withErrors([...]);
      }
      ```
    - **File**: `app/Http/Controllers/TrackingController.php`
    - **Unchanged**: Status check for menunggu_konfirmasi, status transition, notifications
    - _Expected_Behavior: Session cleared atomically with validation result_
    - _Preservation: All status validation, notifications, audit trail_
    - _Requirements: 5.4, 4.5, 4.6, 4.7, 4.8_

---

## Step 4: Verify Bug Condition Test Now Passes

- [x] 4.1 Verify bug condition exploration test now passes
  - **Property 1: Expected Behavior** - Concurrent Session Validation Fixed
  - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
  - The test from task 1 encodes the expected behavior (pure validation, atomic clearing)
  - When this test passes, it confirms the race condition is fixed
  - **Test Execution**:
    - Run the bug condition exploration test from step 1 against FIXED code
    - Test should now pass (no race condition)
  - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - No more race condition symptoms
    - Concurrent requests handled consistently
    - Session state remains consistent across concurrent access
  - **Verification**:
    - Check test output shows all concurrent request pairs pass
    - Confirm no counterexamples demonstrate the bug
    - Document that race condition is resolved
  - _Requirements: 6.0 (Acceptance Criteria), 2.1, 2.2, 2.3_

---

## Step 5: Verify Preservation Tests Still Pass

- [x] 5.1 Verify preservation tests still pass
  - **Property 2: Preservation** - Non-Buggy Behavior Unchanged
  - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
  - **Test Execution**:
    - Run all preservation tests from step 2 against FIXED code
    - Tests should all still pass (no regressions)
    - Session TTL enforcement still works
    - Report ownership validation still works
    - Status validation still works
    - Notifications still sent
    - Audit trail still recorded
  - **EXPECTED OUTCOME**: All tests PASS (confirms no regressions)
    - Preservation 4.1: Session creation with all three keys ✓
    - Preservation 4.2: TTL enforcement after 1800 seconds ✓
    - Preservation 4.3: Report ownership validation ✓
    - Preservation 4.4: addInfo status validation ✓
    - Preservation 4.5: confirmComplete status validation ✓
    - Preservation 4.6: Status transitions occur correctly ✓
    - Preservation 4.7: Notifications dispatched for valid transitions ✓
    - Preservation 4.8: Audit trail recorded for transitions ✓
  - **Verification**:
    - Confirm all 8 preservation properties still hold
    - Check error messages still display correctly
    - Verify no database schema changes needed
    - Confirm no new dependencies introduced
  - _Requirements: 4.0 (All Preservation), 6.0 (Acceptance Criteria)_

---

## Step 6: Checkpoint - All Tests Pass

- [x] 6.1 Checkpoint: Ensure all tests pass
  - Run all tests together (exploration + preservation)
  - Confirm no conflicts or issues
  - Document final test results
  - **Expected Results**:
    - Bug Condition Test (Property 1): PASSES on fixed code ✓
    - Preservation Tests (Property 2): PASS on fixed code ✓
    - No regressions detected ✓
    - No new issues introduced ✓
  - **Verification Steps**:
    - `php artisan test` passes completely
    - No test failures or errors
    - All assertions pass
    - Code review checklist passed
  - **When Issues Arise**:
    - Analyze test failure output
    - Determine if fix is incorrect or test is incorrect
    - Fix implementation or test accordingly
    - Re-run all tests
  - **Mark Complete When**:
    - All tests pass on fixed code
    - No regressions in preservation tests
    - Code review checklist completed
    - Implementation ready for deployment
  - _Requirements: 6.0 (Acceptance Criteria - all 7 criteria)_

---

## Test File Location

Create test file at: `tests/Feature/TrackingControllerRaceConditionTest.php`

This file should contain:
- Bug Condition Property Test (from step 1)
- Preservation Property Tests (from step 2)
- Verification tests for steps 4-5

---

## Summary of Changes

### Files Modified
- `app/Http/Controllers/TrackingController.php`
  - Refactor `hasTrackingAccess()` - remove side effects
  - Add `clearTrackingSession()` - new helper method
  - Update `addInfo()` - add clearing at error handler
  - Update `confirmComplete()` - add clearing at error handler

### Files Created
- `tests/Feature/TrackingControllerRaceConditionTest.php` - all property-based tests

### Files Not Modified
- No database migrations
- No model changes
- No configuration changes
- No route changes
- Session structure unchanged

