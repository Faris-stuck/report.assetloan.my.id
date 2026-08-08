# Design: Session Race Condition Fix

## Overview

This design document outlines the implementation of the session race condition fix for the Tracking Controller. The fix applies the bug condition methodology to ensure concurrent requests are handled safely.

---

## 1. Architecture

### Current Architecture (Problematic)
```
Request A & B (concurrent, same session)
    ↓ ↓
hasTrackingAccess() [validator + side effect]
    ↓ ↓
Reads session state (both see valid)
    ↓ ↓
Request A: session()->forget() [SIDE EFFECT]
    ↓
Request B: [session already cleared, but continues with stale check results]
    ↓ ↓
Inconsistent outcomes
```

### New Architecture (Fixed)
```
Request A & B (concurrent, same session)
    ↓ ↓
hasTrackingAccess() [pure validator, no side effects]
    ↓ ↓
Returns boolean (both get consistent result)
    ↓ ↓
if (!valid) clearTrackingSession() [side effect only in controller]
    ↓ ↓
Atomic, consistent outcomes
```

---

## 2. Implementation Details

### 2.1 Refactor hasTrackingAccess() - Pure Validation Only

**File**: `app/Http/Controllers/TrackingController.php`

**Current Implementation** (Problematic):
```php
private function hasTrackingAccess(Report $report): bool
{
    $verifiedAt = (int) session('track_verified_at', 0);
    $isFresh = $verifiedAt > 0 && now()->timestamp - $verifiedAt <= self::TRACKING_SESSION_TTL_SECONDS;

    if (! $isFresh) {
        session()->forget(['track_report_id', 'track_access_ok', 'track_verified_at']);  // PROBLEM: side effect
        return false;
    }

    return session('track_report_id') === $report->id && session('track_access_ok') === true;
}
```

**New Implementation** (Pure Validation):
```php
private function hasTrackingAccess(Report $report): bool
{
    $verifiedAt = (int) session('track_verified_at', 0);
    $isFresh = $verifiedAt > 0 && now()->timestamp - $verifiedAt <= self::TRACKING_SESSION_TTL_SECONDS;

    // Return only boolean - no side effects
    if (! $isFresh) {
        return false;
    }

    return session('track_report_id') === $report->id && session('track_access_ok') === true;
}
```

**Changes**:
- Remove `session()->forget()` call from validation function
- Make function purely declarative: returns boolean based on state
- No mutations of session during validation
- Idempotent: calling multiple times returns same result

**Reasoning**:
- Separation of concerns: validation ≠ side effects
- Allows concurrent reads without interference
- Makes race condition impossible in validation phase
- Enables property-based testing (pure functions are easier to test)

---

### 2.2 Create clearTrackingSession() Helper

**New Private Method**:
```php
private function clearTrackingSession(): void
{
    session()->forget(['track_report_id', 'track_access_ok', 'track_verified_at']);
}
```

**Purpose**:
- Centralized location for session clearing logic
- Called only from error handlers in public methods
- Ensures all three keys are cleared atomically
- Makes it explicit that clearing is intentional side effect

**Called From**:
- `addInfo()`: When validation fails
- `confirmComplete()`: When validation fails

**Not Called From**:
- `hasTrackingAccess()` (now pure validation only)

---

### 2.3 Refactor addInfo() Method

**Current Implementation**:
```php
public function addInfo(Request $request, Report $report): RedirectResponse
{
    if (! $this->hasTrackingAccess($report)) {
        return redirect()
            ->route('track.form')
            ->withErrors(['access_code' => 'Sesi tracking sudah habis. Masukkan nomor laporan dan kode akses lagi.']);
    }

    if (! in_array($report->status, ['memerlukan_informasi', 'dibuka_kembali', 'menunggu_konfirmasi'], true)) {
        return back()->withErrors(['report' => 'Aksi tambah informasi tidak tersedia untuk status laporan saat ini.']);
    }

    // ... rest of implementation
}
```

**Updated Implementation**:
```php
public function addInfo(Request $request, Report $report): RedirectResponse
{
    // Validate session - pure check, no side effects
    if (! $this->hasTrackingAccess($report)) {
        // Clear session immediately on invalid state
        $this->clearTrackingSession();
        
        return redirect()
            ->route('track.form')
            ->withErrors(['access_code' => 'Sesi tracking sudah habis. Masukkan nomor laporan dan kode akses lagi.']);
    }

    if (! in_array($report->status, ['memerlukan_informasi', 'dibuka_kembali', 'menunggu_konfirmasi'], true)) {
        return back()->withErrors(['report' => 'Aksi tambah informasi tidak tersedia untuk status laporan saat ini.']);
    }

    // ... rest of implementation (unchanged)
}
```

**Changes**:
- Keep validation check at beginning
- Add explicit `clearTrackingSession()` call after failed validation
- Clearing only happens once per request in this method
- All other logic remains unchanged

**Why This Works**:
- In concurrent scenario: Request A reads valid → proceeds or fails on status check
- Request B reads valid (same state at moment of read) → proceeds or fails on status check
- If race causes one to clear session, next request's validation will return false
- No more mysteriously delayed session clearing

---

### 2.4 Refactor confirmComplete() Method

**Current Implementation**:
```php
public function confirmComplete(Report $report): RedirectResponse
{
    if (! $this->hasTrackingAccess($report)) {
        return redirect()
            ->route('track.form')
            ->withErrors(['access_code' => 'Sesi tracking sudah habis. Masukkan nomor laporan dan kode akses lagi.']);
    }

    if ($report->status !== 'menunggu_konfirmasi') {
        return back()->withErrors(['report' => 'Laporan belum berada pada tahap menunggu konfirmasi.']);
    }

    // ... rest of implementation
}
```

**Updated Implementation**:
```php
public function confirmComplete(Report $report): RedirectResponse
{
    // Validate session - pure check, no side effects
    if (! $this->hasTrackingAccess($report)) {
        // Clear session immediately on invalid state
        $this->clearTrackingSession();
        
        return redirect()
            ->route('track.form')
            ->withErrors(['access_code' => 'Sesi tracking sudah habis. Masukkan nomor laporan dan kode akses lagi.']);
    }

    if ($report->status !== 'menunggu_konfirmasi') {
        return back()->withErrors(['report' => 'Laporan belum berada pada tahap menunggu konfirmasi.']);
    }

    // ... rest of implementation (unchanged)
}
```

**Changes**:
- Same pattern as `addInfo()`
- Validation check followed by explicit session clear
- All status checks and notification logic remain unchanged

---

## 3. Bug Condition & Expected Behavior

### Bug Condition: isBugCondition(request, session)
```
(Multiple concurrent requests with same session_id) 
AND (hasTrackingAccess validates and modifies session non-atomically)
AND (Read phase and write phase are not synchronized)
```

### Expected Behavior: expectedBehavior(result)

**For Concurrent Requests:**
```
WHEN isBugCondition = true (concurrent requests detected)
THEN hasTrackingAccess() returns boolean without side effects
AND Each request independently validates based on session state at time of read
AND Session clearing only happens once per request in error handler
AND All concurrent requests see consistent outcome (either all valid or all invalid)
```

**Properties After Fix:**
- **P1**: `hasTrackingAccess()` is a pure function (no session mutations)
- **P2**: Session clearing happens only in controller error handlers
- **P3**: Concurrent requests don't interfere with each other's validation
- **P4**: Session TTL is still enforced
- **P5**: Report ownership validation still works
- **P6**: Status validation still prevents invalid operations
- **P7**: Notifications still sent for valid transitions
- **P8**: All error messages still displayed correctly

---

## 4. Preservation Requirements

The following behavior must be preserved exactly:

### Preservation Requirement 4.1: Session Creation
**Behavior**: When user searches for report with valid credentials, session is created with three keys
- `track_report_id`: The ID of the authenticated report
- `track_access_ok`: Boolean true indicating successful authentication
- `track_verified_at`: Current Unix timestamp for TTL calculation

**Preserved**: No changes to `search()` method or session creation logic

### Preservation Requirement 4.2: Session TTL Enforcement
**Behavior**: Session expires after 1800 seconds (30 minutes)
- Validation: `now()->timestamp - track_verified_at <= 1800`
- Clearing: When TTL exceeded, session is cleared

**Preserved**: TTL logic remains in `hasTrackingAccess()`, only removing side effect

### Preservation Requirement 4.3: Report Ownership
**Behavior**: User accessing tracked report must match the authenticated report
- Validation: `track_report_id === $report->id`
- Prevents: User from accessing Report B after authenticating for Report A

**Preserved**: Ownership check in `hasTrackingAccess()` is unchanged

### Preservation Requirement 4.4: Status Validation for addInfo
**Behavior**: addInfo only works on reports in specific statuses
- Allowed Statuses: `memerlukan_informasi`, `dibuka_kembali`, `menunggu_konfirmasi`
- Error Message: "Aksi tambah informasi tidak tersedia untuk status laporan saat ini."

**Preserved**: Status check logic completely unchanged

### Preservation Requirement 4.5: Status Validation for confirmComplete
**Behavior**: confirmComplete only works when status is `menunggu_konfirmasi`
- Allowed Status: Only `menunggu_konfirmasi`
- Error Message: "Laporan belum berada pada tahap menunggu konfirmasi."

**Preserved**: Status check logic completely unchanged

### Preservation Requirement 4.6: Status Transitions
**Behavior**: When user performs action, report status changes appropriately
- **addInfo on memerlukan_informasi or menunggu_konfirmasi**: Status → `dibuka_kembali`
- **addInfo on dibuka_kembali**: Status stays `dibuka_kembali` (no duplicate transition)
- **confirmComplete on menunggu_konfirmasi**: Status → `selesai`

**Preserved**: All status transition logic completely unchanged

### Preservation Requirement 4.7: Notification Dispatch
**Behavior**: Status changes trigger email notifications
- **dibuka_kembali transition**: Notification sent with message about additional information
- **selesai transition**: Notification sent with message about completion

**Preserved**: All calls to `kirimNotifikasiStatus()` remain unchanged

### Preservation Requirement 4.8: Audit Trail
**Behavior**: Status changes are recorded in ReportStatusHistory
- **Recorded Fields**: previous_status, new_status, actor_type, public_note
- **Created For**: Every status transition

**Preserved**: All ReportStatusHistory::create() calls remain unchanged

---

## 5. Testing Approach

### Test 1: Bug Condition Exploration (Property 1)
**Type**: Property-Based Test
**Goal**: Demonstrate bug exists in unfixed code

**Test Scenario**: Concurrent requests
```php
// Pseudocode for property-based test
foreach concurrent_request_pair in concurrent_request_pairs:
    // Both requests hit addInfo() with valid session simultaneously
    $result_a = call_addInfo_concurrent(request_a, report)
    $result_b = call_addInfo_concurrent(request_b, report)
    
    // At least one should exhibit race condition symptom
    // (one succeeds while other fails mysteriously, not due to status/TTL)
    assert(shows_race_condition_symptom($result_a, $result_b))
```

**Expected**: Test FAILS on unfixed code (proves bug exists)

### Test 2: Preservation (Property 2)
**Type**: Property-Based Test
**Goal**: Ensure single-request behavior is preserved

**Test Scenarios**:
1. Valid session + valid status for addInfo → succeeds
2. Valid session + invalid status → returns error
3. Expired session → returns redirect to track.form
4. Mismatched report ID → returns redirect
5. Valid confirmComplete → succeeds
6. Invalid status for confirmComplete → error

**Expected**: All tests PASS on unfixed code (captures working behavior)

### Test 3: After Fix Verification
**Test**: Run both Property 1 and Property 2 again after implementation

**Expected**:
- **Property 1** (Bug Condition): Now PASSES (bug is fixed, race condition resolved)
- **Property 2** (Preservation): Still PASSES (no regressions)

---

## 6. Code Review Checklist

- [ ] `hasTrackingAccess()` contains NO calls to `session()->forget()`
- [ ] `hasTrackingAccess()` is pure - returns only boolean
- [ ] `clearTrackingSession()` method created and private
- [ ] `addInfo()` calls `clearTrackingSession()` when validation fails
- [ ] `confirmComplete()` calls `clearTrackingSession()` when validation fails
- [ ] All existing error messages unchanged
- [ ] All status transition logic unchanged
- [ ] All notification calls preserved
- [ ] All ReportStatusHistory recording logic preserved
- [ ] No changes to `search()` or session creation
- [ ] No changes to error message texts

---

## 7. Risk Assessment

**Risk Level**: LOW

**Why**:
- Changes are surgical and localized to one method and two call sites
- Behavior is functionally equivalent for single-request scenarios
- No changes to database schema or migrations
- No changes to external APIs or interfaces
- Pure function change (easier to test and reason about)
- Preservation tests capture existing behavior

**Rollback Strategy**:
- If issues arise, can revert `TrackingController.php` to previous version
- No database state changes, no migrations
- No session structure changes

