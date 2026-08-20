# Session Race Condition Fix - Spec Documentation

## Quick Start

This spec documents the fix for a **session race condition** in the Tracking Controller (`app/Http/Controllers/TrackingController.php`) where concurrent requests could cause inconsistent session state management.

## Documents

### 1. [bugfix.md](./bugfix.md) - Requirements & Problem Analysis
**Read this first** to understand:
- What the bug is and when it occurs (Bug Condition)
- What happens currently (Observable Defective Behavior)
- Why it happens (Root Cause Analysis)
- What should happen instead (Expected Behavior)
- How to test for it (Testing Strategy)
- What must not break (Preservation Requirements)

**Key Sections**:
- Section 1: Bug Condition (when race occurs)
- Section 2: Root Cause (why non-atomic validation + side effect)
- Section 3: Expected Behavior (properties that must hold)
- Section 4: Preservation Requirements (existing behavior to keep)

### 2. [design.md](./design.md) - Implementation Design
**Read this** to understand:
- Architecture before and after fix
- Specific code changes needed
- How changes fix the bug
- What tests verify the fix
- Risk assessment

**Key Sections**:
- Section 1: Architecture (visual comparison)
- Section 2: Implementation Details (line-by-line changes)
- Section 3: Bug Condition & Expected Behavior (technical specs)
- Section 4: Preservation Requirements (what we verify doesn't break)

### 3. [tasks.md](./tasks.md) - Implementation Tasks
**Follow this** step-by-step to:
- Write exploration test demonstrating the bug
- Write preservation tests before implementing
- Implement the fix
- Verify all tests pass

**Key Steps**:
1. Bug Condition Exploration Test (fails on unfixed code)
2. Preservation Property Tests (passes on unfixed code)
3. Implement the fix
4. Verify exploration test now passes
5. Verify preservation tests still pass
6. Checkpoint: all tests pass

## The Fix at a Glance

### Problem
```php
// OLD: hasTrackingAccess() does BOTH validation AND clearing
private function hasTrackingAccess(Report $report): bool
{
    // ... validation logic ...
    if (! $isFresh) {
        session()->forget([...]);  // ← Side effect in validator!
        return false;
    }
    return session(...) === ...;
}
```

In concurrent scenarios, this causes race conditions where one request clears the session while another is still validating.

### Solution
```php
// NEW: hasTrackingAccess() is pure validation only
private function hasTrackingAccess(Report $report): bool
{
    // ... validation logic only ...
    if (! $isFresh) {
        return false;  // ← No side effects, just return boolean
    }
    return session(...) === ...;
}

// NEW: clearTrackingSession() handles clearing separately
private function clearTrackingSession(): void
{
    session()->forget([...]);  // ← Side effect only in error handler
}

// NEW: addInfo() and confirmComplete() clear session at error handler level
public function addInfo(Request $request, Report $report): RedirectResponse
{
    if (! $this->hasTrackingAccess($report)) {
        $this->clearTrackingSession();  // ← Clear only when invalid
        return redirect()->route('track.form')->withErrors([...]);
    }
    // ... rest of logic ...
}
```

### Why This Works
- **Before**: Concurrent requests both read valid session, but one clears it mid-flight
- **After**: Each request validates independently, clearing happens atomically only when invalid
- **Result**: No more race condition, consistent behavior across concurrent requests

## Properties Verified

### Property 1: Bug Condition (Exploration)
- Tests that concurrent requests show race condition on unfixed code
- Tests that fix eliminates race condition
- **Status**: Will be set when test is run

### Property 2: Preservation
- Tests that single-request behavior unchanged
- Validates all 8 preservation requirements still work
- **Status**: Will be set when test is run

## Acceptance Criteria

From [design.md Section 6](./design.md#6-code-review-checklist):

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

## Files to Modify

- `app/Http/Controllers/TrackingController.php` - Main implementation

## Files to Create

- `tests/Feature/TrackingControllerRaceConditionTest.php` - Test file for exploration and preservation tests

## Testing

Create tests following the exploratory bugfix workflow in [tasks.md](./tasks.md):

1. **Exploration Test** (will fail on unfixed code):
   - Demonstrates concurrent request race condition
   - Shows how session is cleared non-atomically
   - Verifies fix eliminates this behavior

2. **Preservation Tests** (will pass on unfixed code):
   - Session TTL enforcement works
   - Report ownership validation works
   - Status validation works
   - Notifications still sent
   - Audit trail still recorded
   - All error messages still correct

## Context & Background

**Bug Discovery**: Concurrent requests to report tracking features fail mysteriously even within TTL, while single requests work fine.

**Root Cause**: `hasTrackingAccess()` validates AND clears session without synchronization, causing race condition.

**Impact**: Users see unexplained session timeouts, inconsistent behavior in concurrent scenarios.

**Fix Scope**: Surgical change to separate concerns (validation ≠ side effects), only touches 4 methods in one controller.

---

## Next Steps

1. Read [bugfix.md](./bugfix.md) to understand the problem
2. Read [design.md](./design.md) to understand the solution
3. Follow [tasks.md](./tasks.md) to implement and verify

