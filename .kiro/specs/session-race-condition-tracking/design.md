# Session Race Condition Tracking - Bugfix Design

## Overview

The bug manifests when users submit tracking forms (`addInfo()` or `confirmComplete()`) with a valid session. Between the time the HTTP request is received and when `hasTrackingAccess()` validates the session, concurrent requests or session manipulation can invalidate the session, causing a false "session expired" error even though the session was valid when the user clicked submit. 

The core issue is that `hasTrackingAccess()` both validates AND clears the session. If a concurrent request clears the session first, the validation fails. Additionally, session state between validation and database operation is not atomic, creating a race condition window where the session can become invalid.

The fix involves:
1. Separating session validation (no side effects) from session clearing (happens only on error paths)
2. Validating session state once at the start of each method and caching it
3. Using a double-check pattern where we validate report ownership at operation time
4. Never clearing session inside validation helpers - only at error boundaries

## Glossary

- **Bug_Condition (C)**: The condition where session validation succeeds but session state changes (or is cleared) between validation and database operation
- **Property (P)**: Session validation and database operation should succeed together atomically, or both fail together
- **Preservation**: Legitimate session expiry detection and report ownership validation must continue working
- **TRACKING_SESSION_TTL_SECONDS**: The configured TTL of 1800 seconds (30 minutes) for tracking sessions
- **hasTrackingAccess()**: The function at line 154 in `TrackingController` that checks session freshness and ownership
- **track_verified_at**: The session key storing the timestamp when user verified with access code
- **track_report_id**: The session key storing which report the user is tracking

## Bug Details

### Bug Condition

The bug manifests when both of the following conditions are true:

1. A user submits a form request (`addInfo()` or `confirmComplete()`) with a session that is valid at the time the HTTP request is dispatched
2. Between the validation check in `hasTrackingAccess()` and the database operation in the calling method, either:
   - The session gets cleared by `hasTrackingAccess()` itself (line 160-161 calls `session()->forget()`)
   - Another concurrent request manipulates the session
   - The session manager's state becomes inconsistent

The current implementation has two race condition vectors:

**Vector 1: Session cleared during validation**
When `hasTrackingAccess()` is called and finds the session expired, it clears the session immediately (lines 160-161). If a concurrent request or middleware has already cleared or modified the session, the validation fails for all subsequent checks in that request, even though the session was valid when the request started.

**Vector 2: Validation-to-operation gap**
After `hasTrackingAccess()` returns true, the calling method (`addInfo()` or `confirmComplete()`) performs database operations. There is no guarantee that session state hasn't changed between the validation (line 150) and the operation (lines 61-89 in `addInfo()`, lines 95-118 in `confirmComplete()`).

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type Request with session
  OUTPUT: boolean
  
  LET sessionValidAt = session('track_verified_at')
  LET now_ms = now().timestamp
  LET isFresh = sessionValidAt > 0 AND (now_ms - sessionValidAt) <= 1800
  
  RETURN isFresh = true at request dispatch time
         AND session('track_report_id') = report.id at request dispatch time
         AND (
           hasTrackingAccess() is called AND causes session->forget()
           OR session state changes between hasTrackingAccess() call and operation execution
         )
         AND operation fails with "sesi tracking sudah habis" redirect
END FUNCTION
```

### Examples

**Example 1: Session cleared by validation**
- User searches report ABC123 with code 123456, session stored: `track_report_id=5, track_verified_at=1700000000`
- At T=1700001000 (16 minutes later), user types comment and clicks "Tambah Informasi"
- Request arrives at `addInfo()`, session still valid (< 1800 seconds)
- `hasTrackingAccess()` line 150 checks: `isFresh` is true
- Then line 160-161 is skipped because `isFresh` is true, so validation returns true ✓
- **But wait**: if there's a concurrent request from another tab, or middleware interaction...
- The issue occurs when `hasTrackingAccess()` is called but the session has already been cleared elsewhere
- Expected: Form submission succeeds and note is created
- Actual: User gets "Sesi tracking sudah habis" error

**Example 2: Session state changes between check and operation**
- User is in tracking form with valid session
- User types a long comment (3000 chars max)
- At same time, browser makes background request that clears/modifies session
- User clicks submit, session validation passes
- During `ReportNote::create()` operation, session is no longer available for other checks
- Expected: Note created successfully
- Actual: Operation proceeds but session is inconsistent

**Example 3: Edge case - concurrent requests from same user**
- User opens tracking page in two tabs
- Tab 1: User fills in comment and submits before Tab 1 session expires
- Tab 2: User opens new search (which might reset tracking session)
- Tab 1 submit might fail because Tab 2 cleared the session
- Expected: Both operations should work independently or fail gracefully
- Actual: One request fails unexpectedly

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- When session is genuinely expired (> 1800 seconds since verification), system SHALL still clear session and redirect to search form
- When session contains wrong `track_report_id`, system SHALL still reject access
- When session `track_access_ok` flag is missing, system SHALL still reject access
- Form validation for report status SHALL continue to work (checking if status allows add_info or confirm_complete)
- Report ownership validation SHALL continue to work (DB model binding ensures we access the correct report)
- Database operations (ReportNote creation, status updates) SHALL continue to work correctly

**Scope:**
All inputs that do NOT involve the race condition scenario should be completely unaffected by this fix. This includes:
- Users with no session (direct URL access) - should still get "sesi tracking sudah habis" error
- Users with expired session (>1800 seconds) - should still get redirect to search form
- Users with correct session submitting valid data - should succeed (FIXED)
- Users with correct session but invalid report status - should still get status error (unchanged)
- Concurrent requests that don't interact with session - should continue working

## Hypothesized Root Cause

Based on the bug description and code analysis, the most likely causes are:

1. **Race Condition: Session clearing during validation**
   - The `hasTrackingAccess()` method is called by multiple code paths (line 55 in `addInfo()`, line 91 in `confirmComplete()`)
   - If session is expired, it calls `session()->forget()` on lines 160-161
   - Concurrent requests or middleware could trigger this while another request is in progress
   - The session is mutable global state, so concurrent access creates race conditions

2. **Validation-Operation Gap**
   - `hasTrackingAccess()` validates at one point in time
   - The actual database operation happens later (e.g., `ReportNote::create()` at line 69)
   - Session could be cleared or modified between these two points
   - No locking or atomic transaction ensures session consistency

3. **Session Manager State Inconsistency**
   - PHP/Laravel sessions are not atomic by default
   - Multiple concurrent requests can read/write session simultaneously
   - One request might clear session while another is validating it
   - Regeneration of session ID could also interfere

4. **Side Effect in Validation Helper**
   - The `hasTrackingAccess()` helper has side effects: it clears the session
   - Helpers should be pure validation functions without side effects
   - This violates the principle of separation of concerns
   - Makes the code harder to reason about and more prone to race conditions

5. **No Double-Check Pattern**
   - After `hasTrackingAccess()` passes, we don't re-verify before critical operations
   - A double-check pattern would catch if session was cleared between check and operation
   - Laravel model binding validates the report exists, but doesn't validate ownership

## Correctness Properties

Property 1: Bug Condition - Session validation and operation atomicity

_For any_ request where the bug condition holds (session is valid at dispatch time, but race condition causes session state change before operation completes), the fixed code SHALL perform session validation once at method entry, cache the validation result, and use that cached result for the entire request lifecycle. Session clearing SHALL ONLY happen at error boundaries, not during validation.

**Validates: Requirements 2.1, 2.2, 2.3**

Property 2: Preservation - Genuine expiry and ownership checks

_For any_ request where the bug condition does NOT hold (session is genuinely expired, wrong report ID, or no session at all), the fixed code SHALL produce the same error and redirect behavior as the original code, preserving all existing session expiry detection and report ownership validation.

**Validates: Requirements 3.1, 3.2, 3.3**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct, the fix involves restructuring session handling in `TrackingController`:

**File**: `app/Http/Controllers/TrackingController.php`

**Specific Changes**:

1. **Refactor hasTrackingAccess() to be pure validation (no side effects)**
   - Remove the `session()->forget()` call from line 160-161
   - Return only a boolean indicating if access is valid
   - This function should have zero side effects
   - Implementation: Check freshness and report ownership, return result

2. **Add session clearance at error paths only**
   - In `addInfo()`: Add session clearing before returning the error redirect (around line 53-56)
   - In `confirmComplete()`: Add session clearing before returning the error redirect (around line 91-94)
   - Create a private helper `clearTrackingSession()` to avoid duplication
   - This ensures session is only cleared when we're rejecting the request

3. **Implement double-check validation pattern**
   - At the start of `addInfo()` and `confirmComplete()`, perform validation once
   - Store the validation result in a local variable
   - If invalid, immediately clear session and redirect
   - If valid, continue with operation using the cached validation result
   - No re-checking of session during operation

4. **Validate report ownership before database operations**
   - After `hasTrackingAccess()` passes and we're about to do DB work
   - Verify that the report ID from the URL parameter matches `session('track_report_id')`
   - This is a safety check to catch any session tampering between validation and operation
   - Use the `$report` model binding which already validates the report exists

5. **Optional: Wrap critical section in transaction (for future robustness)**
   - Consider wrapping `ReportNote::create()` and `Report::update()` in a transaction
   - This ensures if anything fails mid-operation, we can rollback consistently
   - Not strictly required for the race condition fix but improves atomicity

6. **Document the session handling pattern**
   - Add clear comments explaining the validation-once pattern
   - Explain why session is cleared only at error paths
   - Document the double-check pattern being used

### Implementation Pseudocode

```
METHOD addInfo(Request $request, Report $report)
  // STEP 1: Validate session once at entry
  isAccessValid := this.hasTrackingAccess(report)
  
  // STEP 2: If invalid, clear session and redirect immediately
  IF NOT isAccessValid THEN
    this.clearTrackingSession()
    RETURN redirect to track.form with error "sesi tracking sudah habis"
  END IF
  
  // STEP 3: Validate report status (unchanged)
  IF report.status NOT IN ['memerlukan_informasi', 'dibuka_kembali', 'menunggu_konfirmasi'] THEN
    RETURN back with error about status
  END IF
  
  // STEP 4: Validate request data
  data := request.validate(['note' => ['required', 'string', 'max:3000']])
  
  // STEP 5: Create note (operation assumes valid session)
  ReportNote.create([
    'report_id' => report.id,
    'author_type' => 'reporter',
    'note' => data.note,
    'visibility' => 'internal'
  ])
  
  // ... rest of operation ...
  
  RETURN back with success status
END METHOD

METHOD hasTrackingAccess(Report $report) : boolean
  verifiedAt := session('track_verified_at', 0)
  isFresh := verifiedAt > 0 AND (now().timestamp - verifiedAt) <= 1800
  
  // NOTE: DO NOT clear session here - this is validation only!
  // Session clearing happens at error paths in calling methods
  
  IF NOT isFresh THEN
    RETURN false
  END IF
  
  IF session('track_report_id') !== report.id THEN
    RETURN false
  END IF
  
  IF session('track_access_ok') !== true THEN
    RETURN false
  END IF
  
  RETURN true
END METHOD

PRIVATE METHOD clearTrackingSession()
  session().forget(['track_report_id', 'track_access_ok', 'track_verified_at'])
END METHOD
```

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the race condition on unfixed code, then verify the fix works correctly and preserves existing session validation behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the race condition BEFORE implementing the fix. Confirm or refute the root cause analysis.

**Test Plan**: Write integration tests that simulate rapid concurrent requests to the tracking endpoints. One request will trigger session validation while another attempts to clear or modify the session. Observe failures on UNFIXED code.

**Test Cases**:

1. **Concurrent Session Modification Test**: Simulate two concurrent requests where first request validates session and second modifies/clears it before first completes DB operation (will fail on unfixed code demonstrating race condition)

2. **Session Clearing During Validation Test**: Call `hasTrackingAccess()` and simultaneously modify session in another simulated request (will fail on unfixed code)

3. **Validation-to-Operation Gap Test**: Verify that between `hasTrackingAccess()` returning true and `ReportNote::create()` executing, if session is cleared, the operation still proceeds (demonstrating the gap)

4. **Rapid Form Submission Test**: Submit same form twice rapidly from same user (will cause one to fail on unfixed code due to session being cleared)

**Expected Counterexamples on Unfixed Code**:
- One concurrent request gets false "sesi tracking sudah habis" error even though session was valid
- Session is cleared by one request while another is still executing
- Possible causes confirmed: `session()->forget()` in `hasTrackingAccess()` causes race condition, validation-operation gap allows session state change

### Fix Checking

**Goal**: Verify that for all inputs where the race condition would occur, the fixed code now handles them correctly.

**Pseudocode:**
```
FOR ALL concurrentRequest IN rapidConcurrentRequests DO
  firstRequestTask := startAsyncTask(submitAddInfoForm, request1)
  secondRequestTask := startAsyncTask(modifySessionConcurrently, request2)
  
  firstResult := waitFor(firstRequestTask)
  secondResult := waitFor(secondRequestTask)
  
  ASSERT firstResult.statusCode IN [200, 302]  // Either succeeds or redirects gracefully
  ASSERT NOT firstResult.contains("sesi tracking sudah habis")  // No spurious timeout
END FOR
```

**Test Plan**: Write concurrent integration tests that verify the fixed code handles simultaneous requests correctly. Each test submits a form request while another request attempts to clear/modify session.

**Test Cases**:

1. **Concurrent Submission Success**: Two rapid form submissions from same user with same report - both should succeed or fail consistently (not one succeed and one fail randomly)

2. **Session Clearing Isolation**: One request clears session (via logout or new search) while another is executing operation - second request should fail gracefully with clear error, not partial data

3. **Validation Result Isolation**: Cache validation result and verify it's used throughout the request lifecycle, even if session changes mid-operation

4. **Race Condition Elimination**: Repeatedly submit forms while session is being modified - verify no "spurious" timeout errors occur

### Preservation Checking

**Goal**: Verify that for all inputs where the race condition does NOT occur, the fixed code produces the same behavior as original code.

**Pseudocode:**
```
FOR ALL input IN nonRaceConditionInputs DO
  ASSERT fixedCode(input) = originalCode(input)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases where session expiry or ownership checks might be skipped
- It provides strong guarantees that legitimate failures still occur (genuinely expired sessions still get cleared)

**Test Plan**: Run PBT to generate random valid sessions, expired sessions, wrong report IDs, etc. Verify that the fixed code produces identical error messages and redirect behavior as the original code.

**Test Cases**:

1. **Genuine Expiry Preservation**: Generate sessions with `track_verified_at` more than 1800 seconds old - verify fixed code still clears session and redirects with "sesi tracking sudah habis"

2. **Wrong Report ID Preservation**: Generate valid session but with `track_report_id` not matching the accessed report - verify fixed code still rejects with same error

3. **Missing Session Preservation**: Generate requests with no session at all - verify fixed code still rejects

4. **Status Validation Preservation**: Generate requests with valid session but invalid report status - verify fixed code still rejects with status error (not session error)

5. **Database Operation Preservation**: Generate valid session and valid report status - verify fixed code still creates notes, updates statuses correctly as original code

6. **Notification Preservation**: Verify that notifications are still sent for status changes (side effect of status update)

### Unit Tests

- Test `hasTrackingAccess()` returns true/false correctly based on session state (no side effects)
- Test `hasTrackingAccess()` with fresh session, expired session, wrong report ID
- Test `clearTrackingSession()` clears all three session keys
- Test each branch of status validation in `addInfo()` and `confirmComplete()`
- Test request validation (note length, required fields)
- Test that `addInfo()` calls `clearTrackingSession()` on invalid access
- Test that `confirmComplete()` calls `clearTrackingSession()` on invalid access

### Property-Based Tests

- Generate random session states (fresh, expired, partial, corrupted)
- Verify that for each session state, behavior matches expected (accept or reject)
- Generate random report statuses and verify appropriate error messages
- Generate random concurrent request patterns and verify atomicity
- Test that session clearing only happens on error paths, never during successful operations

### Integration Tests

- Test complete flow: search → get session → submit form → operation succeeds
- Test session timeout: search → wait 1801 seconds → submit form → get timeout error
- Test wrong report: search for report A → try to access report B → get error
- Test concurrent operations: two users with same report tracking ID (different sessions)
- Test status transitions: verify each status change is handled correctly
- Test notifications: verify notifications are sent after status changes
