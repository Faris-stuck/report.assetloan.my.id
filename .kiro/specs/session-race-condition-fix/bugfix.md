# Bugfix: Session Race Condition in Tracking Controller

## Overview

A session race condition exists in `TrackingController.hasTrackingAccess()` where concurrent requests from the same session can both validate successfully but perform operations with inconsistent state.

---

## 1. Bug Condition

### Definition
The bug occurs when **multiple concurrent HTTP requests** from the same user session attempt to access a report within the tracking workflow. This commonly happens when:
- User has multiple browser tabs open to the same report tracking page
- User rapidly clicks buttons (addInfo, confirmComplete)
- Browser prefetches requests in the background
- Automatic page refresh runs concurrently with user action

### Pseudocode: isBugCondition(request, session)
```
concurrentRequests = COUNT(requests with same session_id) > 1
AND sessionIsValid(session) 
AND requestsAccessSameResource(requests, report_id)
```

### Observable Defective Behavior
When concurrent requests occur:
1. Both Request A and Request B call `hasTrackingAccess()` nearly simultaneously
2. Both threads read `track_verified_at` as valid (not expired)
3. Request A's `hasTrackingAccess()` completes first and calls `session()->forget()`
4. Request B's `hasTrackingAccess()` continues despite session being cleared
5. Request B may perform database operations with stale/no session context
6. Subsequent requests after the race fail with "Sesi tracking sudah habis" even though user is still within TTL
7. User experiences inconsistent behavior: one request succeeds while others in the race window fail mysteriously

### Impact
- **User Experience**: User sees unexplained session timeout errors
- **Data Integrity**: Potential for operations to proceed with invalid authorization state
- **Reliability**: Same action succeeds sometimes, fails other times (intermittent failure)

---

## 2. Root Cause Analysis

The root cause is a **non-atomic validation + side effect** pattern:

```php
// PROBLEMATIC PATTERN
private function hasTrackingAccess(Report $report): bool
{
    $verifiedAt = (int) session('track_verified_at', 0);
    $isFresh = $verifiedAt > 0 && now()->timestamp - $verifiedAt <= self::TRACKING_SESSION_TTL_SECONDS;

    if (! $isFresh) {
        session()->forget(['track_report_id', 'track_access_ok', 'track_verified_at']);  // <- Side effect in validator
        return false;
    }

    return session('track_report_id') === $report->id && session('track_access_ok') === true;
}
```

**Problem:**
- The method both validates AND performs side effects (clearing session)
- In concurrent scenarios, thread B may read valid session after thread A has already cleared it
- The validation check is spread across multiple method calls (read → check → forget → return)
- No atomic operation to ensure consistency between check and action

---

## 3. Expected Behavior

### After Fix
1. Session validation returns **pure boolean** without side effects
2. Session clearing happens **only once at error handler level** in each request
3. Concurrent requests:
   - All read consistent session state during validation
   - Only one request clears session (at controller level when validation fails)
   - Subsequent requests in the race see the cleared session and properly redirect
   - User gets single, consistent redirect rather than mysterious failures

### Pseudocode: expectedBehavior(result)
```
WHEN isBugCondition(request, session) = true
THEN hasTrackingAccess() = boolean (no side effects)
AND session cleared only at error handler level
AND all concurrent requests see consistent state after first one fails
AND user redirected exactly once with clear message
```

### Properties
- **P1 (No Side Effects)**: `hasTrackingAccess()` never calls `session()->forget()`, always returns boolean
- **P2 (Atomic Clearing)**: When session validation fails, it's cleared exactly once per request at error handler level
- **P3 (Consistency)**: Concurrent requests in race window either all see valid session or all see cleared session
- **P4 (Idempotency)**: Calling `hasTrackingAccess()` multiple times in same request returns same value

---

## 4. Preservation Requirements

These behaviors must be preserved after the fix:

### Requirement 4.1: Session Expiry Detection
**Current Behavior**: When `track_verified_at` is older than TTL (1800 seconds), the session is considered expired

**After Fix**: Must still detect and clear expired sessions
- If session created 30+ minutes ago, subsequent requests should be rejected
- Session should be cleared when expired
- User sees "Sesi tracking sudah habis" message

**Property: P5 (TTL Enforcement)**
```
FOR all requests where (now() - track_verified_at) > 1800 seconds
hasTrackingAccess() = false
AND session is cleared at error handler level
```

### Requirement 4.2: Report Ownership Validation
**Current Behavior**: User can only access the report they searched for - `track_report_id` in session must match `$report->id`

**After Fix**: Must still validate that accessed report matches authenticated report
- If user authenticates for Report A, they cannot access Report B
- Session stores specific report ID and must match request parameter

**Property: P6 (Report Ownership)**
```
FOR all requests where track_report_id != report->id
hasTrackingAccess() = false
```

### Requirement 4.3: Status Validation
**Current Behavior**: 
- `addInfo()` only works when report status is in [memerlukan_informasi, dibuka_kembali, menunggu_konfirmasi]
- `confirmComplete()` only works when status is menunggu_konfirmasi

**After Fix**: These validations must be preserved and still return appropriate error messages
- Wrong status receives "Aksi tidak tersedia untuk status laporan saat ini"
- Valid status proceeds normally

**Property: P7 (Status Guards)**
```
FOR addInfo: status NOT IN [memerlukan_informasi, dibuka_kembali, menunggu_konfirmasi]
THEN return error "Aksi tambah informasi tidak tersedia"

FOR confirmComplete: status != menunggu_konfirmasi
THEN return error "Laporan belum berada pada tahap menunggu konfirmasi"
```

### Requirement 4.4: Notification Flow
**Current Behavior**: Status changes trigger notification emails via `kirimNotifikasiStatus()`

**After Fix**: Notifications must still be sent for legitimate status transitions
- When report moves to `dibuka_kembali`, notification is sent
- When report moves to `selesai`, notification is sent
- Notifications not sent for rejected/invalid requests

**Property: P8 (Notification Dispatch)**
```
FOR valid addInfo request changing status: notification sent with "dibuka_kembali" label
FOR valid confirmComplete request: notification sent with "selesai" label
FOR rejected requests: no notification sent
```

---

## 5. Solution Approach

### High-Level Strategy
1. **Separate Concerns**: Split `hasTrackingAccess()` into pure validation + optional clearing
2. **Remove Side Effects**: Make `hasTrackingAccess()` return boolean without session manipulation
3. **Centralize Clearing**: Move session clearing to controller methods at error handler level
4. **Atomic Handling**: Ensure validation and clearing happen atomically per request

### Implementation Changes

#### Step 5.1: Refactor hasTrackingAccess()
- **Change**: Remove `session()->forget()` call
- **Make it**: Pure validation function that only reads and returns boolean
- **Ensures**: No side effects during validation phase

#### Step 5.2: Create clearTrackingSession() Helper
- **New Method**: `private clearTrackingSession(): void`
- **Purpose**: Centralized place to clear all tracking session keys
- **Called Only At**: Error handler level in controller methods

#### Step 5.3: Update addInfo() Method
- Validate session once at entry
- Clear session immediately if invalid and return redirect
- Continue with normal operation if valid
- This prevents stale validation between methods

#### Step 5.4: Update confirmComplete() Method
- Same pattern as addInfo()
- Validate once, clear if needed, proceed if valid

### Why This Fixes the Race Condition
1. **Reading Phase**: Multiple concurrent requests read session independently (consistent state)
2. **Decision Phase**: Each request independently validates based on what it read
3. **Action Phase**: Each request either proceeds or clears session based on its validation
4. **Result**: Even if race occurs, all requests see consistent session state within their own execution
5. **Key Benefit**: Session clearing is now idempotent and only happens in response to invalid state

---

## 6. Testing Strategy

### Property-Based Test 1: Bug Condition (Exploration)
**Property 1: Bug Condition** - Concurrent requests with concurrent validation+clearing

**Goal**: Demonstrate the bug exists
- Simulate concurrent requests to addInfo() and confirmComplete()
- Both read valid session simultaneously
- First one clears session via hasTrackingAccess()
- Second one still sees it as called but session is cleared
- Assert: Second request fails or exhibits inconsistent behavior

**Expected**: Test FAILS on unfixed code (proves bug exists)

### Property-Based Test 2: Preservation
**Property 2: Preservation** - Non-concurrent requests still work correctly

**Goal**: Ensure existing single-request behavior is preserved
- Single request to addInfo() with valid session succeeds
- Single request to addInfo() with expired session redirects properly
- Single request to confirmComplete() with valid session succeeds
- Status validation still works
- Notification still sent on valid requests

**Expected**: Tests PASS on unfixed code (captures current working behavior)

### Additional Test Cases
- Session TTL enforcement still works
- Report ownership validation still works
- Status validation still works
- Notifications sent for valid transitions
- Error messages displayed correctly

---

## 7. Acceptance Criteria

### Criterion 7.1: Pure Validation Function
**When**: `hasTrackingAccess()` is called
**Then**: It returns boolean without calling `session()->forget()`
**And**: Multiple calls within same request return same value

### Criterion 7.2: Atomic Session Clearing
**When**: `hasTrackingAccess()` returns false
**Then**: Session is cleared exactly once per request
**And**: Clearing happens at error handler level in the controller method
**And**: Not within the validation function itself

### Criterion 7.3: Concurrent Request Handling
**When**: Multiple concurrent requests occur from same session
**Then**: All requests proceed through validation independently
**And**: No race condition exists for session state
**And**: User gets single, consistent error message (not multiple failures)

### Criterion 7.4: Session TTL Preserved
**When**: Session is older than 1800 seconds
**Then**: `hasTrackingAccess()` returns false
**And**: Session is cleared at error handler level
**And**: User sees "Sesi tracking sudah habis" message

### Criterion 7.5: Report Ownership Preserved
**When**: User accesses different report than authenticated session
**Then**: `hasTrackingAccess()` returns false
**And**: Proper redirect occurs

### Criterion 7.6: Status Validation Preserved
**When**: Report status doesn't allow requested operation
**Then**: Appropriate error message is shown
**And**: Database is not modified
**And**: No notification sent

### Criterion 7.7: Notifications Still Sent
**When**: Valid addInfo request changes report status
**Then**: `kirimNotifikasiStatus()` is called with correct parameters
**And**: User sees success message
**And**: Status history is recorded

---

## References

**Implementation File**: `app/Http/Controllers/TrackingController.php`

**Related Configuration**:
- Session TTL: `TRACKING_SESSION_TTL_SECONDS = 1800`
- Session Keys: `track_report_id`, `track_access_ok`, `track_verified_at`

**Related Models**:
- `App\Models\Report`
- `App\Models\ReportNote`
- `App\Models\ReportStatusHistory`

