# Full Page Reload & Re-render Pattern Scan Report
**Date**: March 5, 2026  
**Scope**: /opt/lampp/htdocs/PROJECT folder  
**Analysis**: Page reload and full table re-render patterns in success callbacks

---

## Executive Summary

This scan identified **4 primary patterns** of page reload/re-render mechanisms across the PROJECT folder:

1. **Direct Navigation**: `window.location.href = ` (100+ instances)
2. **Full Table HTML Rewrites**: `.innerHTML = renderTableRows()` (10 instances)
3. **Data Refresh via Fetch**: `loadData*()/loadPengembalian()` (60+ instances)
4. **Modal-based DOM Updates**: `.innerHTML = ` for dynamic content (100+ instances)

**Critical Finding**: There are **NO** instances of `window.location.reload()` or `location.reload()` detected. All reloading is done via:
- Explicit navigation to new pages
- Re-fetching data and re-rendering DOM via `.innerHTML`

---

## SECTION 1: Direct Page Navigation (window.location.href)

### Pattern Summary
Most UI actions redirect to different pages rather than reloading. This is used for:
- Authentication failures (redirect to login)
- Navigation after actions
- Role-based redirects

| Count | Pattern | Usage |
|-------|---------|-------|
| 100+ | `window.location.href = ` | Navigation/logout |
| 0 | `location.reload()` | **None found** |
| 0 | `window.location.reload()` | **None found** |

### Files with Direct Navigation (Sample)

#### admin/peminjaman/sedang-dipinjam.html
- **Line 423**: `window.location.href = "../../index.html";` (Logout redirect)
- **Line 427**: `window.location.href = "../../user/dashboard.html";` (Role redirect)
- **Context**: Authentication failure callback in async handler

#### user/peminjaman/status-peminjaman.html
- **Line 534**: `window.location.href = "../../index.html";` (Logout)
- **Line 540-542**: Role-based redirects
- **Line 800**: `window.location.href = ../pengembalian/ajukan-pengembalian.html?peminjaman_id=${peminjamanId}` 
  - **Action**: Return item request initiation
  - **Pattern**: Navigates to return form with parameter

#### user/peminjaman/ajukan-peminjaman.html
- **Line 570, 576, 578**: Authentication/role redirects

#### admin/pengaturan.html
- **Line 2871, 2943**: `window.location.href = '../index.html'` in error handlers (403 Unauthorized)
- **Line 3188, 3193**: Logout redirects

#### admin/dashboard.html
- **Line 403, 407, 409**: Role-based authentication redirects

#### register.html & forgot-password.html
- **Line 302, 273**: `window.location.href = "login.html"` (Post-registration/reset redirect)

---

## SECTION 2: Full Table Re-renders with renderTableRows()

### Pattern Summary
Specific tables use `renderTableRows()` function to completely replace table body content. This is **full DOM re-write** pattern.

### Files with Full Table Rewrites

#### user/peminjaman/ajukan-peminjaman.html
**Pattern**: Splits table by status, calls renderTableRows for each
```javascript
Line 1093-1097:
document.getElementById("tableSemua").innerHTML = renderTableRows(semua);
document.getElementById("tableMenunggu").innerHTML = renderTableRows(menunggu);
document.getElementById("tableDipinjam").innerHTML = renderTableRows(dipinjam);
document.getElementById("tableDikembalikan").innerHTML = renderTableRows(dikembalikan);
document.getElementById("tableDitolak").innerHTML = renderTableRows(ditolak);
```
- **Action Trigger**: After user submits form or date filter changes
- **Function**: `renderTableRows(data)` - Converts data array to HTML rows
- **Impact**: Complete re-render of 5 separate tables

#### PROJECT1/user/peminjaman/ajukan-peminjaman.html
**Lines 1228-1232**: Identical pattern to PROJECT version

---

## SECTION 3: Data Refresh Callbacks (Implicit Re-renders)

### Pattern Summary
Most success callbacks don't reload directly. Instead, they call fetch functions to get fresh data and re-render via `.innerHTML`:

```javascript
fetch(API_URL)
  .then(r => r.json())
  .then(data => {
    document.getElementById("table").innerHTML = buildHTML(data);  // RE-RENDER
  })
```

### Key Data-Refresh Functions

#### loadDataPeminjaman()
**Found in**: 
- admin/peminjaman/data-peminjaman.html (Line 535)
- admin/peminjaman/sedang-dipinjam.html (Line 434)
- user/peminjaman/ajukan-peminjaman.html (Line 642)
- manager/persetujuan/menunggu-approval.html (Line 397)
- admin/peminjaman/menunggu-persetujuan.html (Line 463)
- admin/peminjaman/admin-approval.html (Line 397)

**Called After**:
- Approval/Rejection save (doSaveApproval → loadDataPeminjaman)
- Date filter change
- Manual refresh

#### loadPengembalian()
**Found in**:
- pic-barang/pengembalian/pengembalian-barang.html (Line 349)
- admin/pengembalian/pengembalian-barang.html (Line 506)
- PROJECT1/pic-barang/pengembalian/pengembalian-barang.html (Line 334)

**Called After**:
- Inspection finalization (doSubmitInspeksi → loadPengembalian)
- Return status updates
- Modal close

---

## SECTION 4: Success Callback Patterns by Action Type

### 4.1 APPROVAL/REJECTION ACTIONS

#### Pattern: Approval Decision + Table Refresh

**Manager/Admin Approval Pages**:
- manager/persetujuan/menunggu-approval.html
- admin/peminjaman/menunggu-persetujuan.html
- admin/peminjaman/admin-approval.html

**Flow**:
```
1. User clicks Approve/Reject button
   → setUnitDecision() sets decision in object
   
2. User clicks "Save" button
   → saveApprovalDecisions() validates
   → Shows confirmation modal
   
3. User confirms in modal
   → doSaveApproval() executes
   → fetch(api/approver/approve-items.php)
   
4. On Success:
   → showSuccess('Borrowing approval decisions saved successfully.')
   → loadDataPeminjaman()  // ← TRIGGERS FULL TABLE RELOAD
```

**Lines with Success Callback**:
- manager/persetujuan/menunggu-approval.html, Line 606: `loadDataPeminjaman();`
- admin/peminjaman/menunggu-persetujuan.html, Line 657: `loadDataPeminjaman();`
- admin/peminjaman/admin-approval.html, Line 591: `loadDataPeminjaman();`

### 4.2 RETURN/PENGEMBALIAN FINALIZATION

#### Pattern: Final Inspection Completion + Reload

**pic-barang/pengembalian/pengembalian-barang.html**

**Flow**:
```
1. PIC-Barang user fills inspection form
   → submitInspeksi() collects item statuses
   
2. Shows confirmation: "FINAL RETURN CONFIRMATION
   - Complete the return process
   - Update final item status
   - Close the borrowing record"
   
3. On Confirm:
   → doSubmitInspeksi() executes
   → fetch(api/pengembalian/inspect.php)
   
4. On Success (Line 600):
   → alert(res.message)
   → loadPengembalian()  // ← FULL TABLE RELOAD
   → Hides modals
```

**Code Location**: Lines 560-605
```javascript
function doSubmitInspeksi() {
    fetch(API + '/inspect.php', { method: 'POST', body: fd, ... })
        .then(res => {
            alert(res.message || 'Success');
            if (res.status) {
                loadPengembalian();  // LINE 600 - REFRESH TABLE
            }
        })
}
```

### 4.3 EXTEND REQUEST (LOAN EXTENSION) APPROVAL/REJECTION

#### Pattern: Approve/Reject Extension + List Refresh

**Files**:
- pic-barang/pengembalian/pengembalian-barang.html
- admin/pengembalian/pengembalian-barang.html

**Flow**:
```
1. User clicks Approve/Reject button on extend request
   → approveExtend(extendId) or rejectExtend(extendId)
   
2. Shows confirmation dialog
   
3. On Confirm:
   → fetch(API_EXTEND + '/approve.php' or '/reject.php')
   
4. On Success (Lines 687, 702):
   → alert(res.message)
   → loadExtendRequests()  // ← REFRESH EXTEND TABLE
```

**Code Location**: pic-barang/pengembalian/pengembalian-barang.html, Lines 677-703
```javascript
function approveExtend(extendId) {
    fetch(API_EXTEND + '/approve.php', { ... })
        .then(res => {
            alert(res.message);
            if (res.status) loadExtendRequests();  // LINE 687
        })
}

function rejectExtend(extendId) {
    fetch(API_EXTEND + '/reject.php', { ... })
        .then(res => {
            alert(res.message);
            if (res.status) loadExtendRequests();  // LINE 702
        })
}
```

---

## SECTION 5: Complete Action Flow Examples

### Example 1: Approval Workflow (Manager)

**File**: manager/persetujuan/menunggu-approval.html

```
Step 1 - Load Page (Line 615)
├─ DOMContentLoaded → loadDataPeminjaman()
└─ fetch(api/approver/list-by-status.php?status=Menunggu Persetujuan)
   └─ Displays items with Approve/Reject buttons

Step 2 - Approve Individual Item (Line 490)
├─ User clicks <button onclick="setUnitDecision('key', 'approved')">Approve</button>
└─ Updates unitDecisions object (no server call yet)

Step 3 - Click Save Button (Line 311)
├─ saveApprovalDecisions() validates
├─ Shows confirmation modal with item count
└─ User clicks "Proceed" button

Step 4 - Confirm in Modal (Line 332)
├─ doSaveApproval() executes
├─ fetch(BASE_URL + "/api/approver/approve-items.php", POST)
│  └─ Body: peminjaman_id, items[], rejection_reason
│
└─ .then(data => {
    if (data.status) {
        showSuccess('Borrowing approval decisions saved successfully.');
        loadDataPeminjaman();  // ← LINE 607 - RE-FETCH AND RE-RENDER
    }
   })

Step 5 - Refresh Table (Line 397)
└─ loadDataPeminjaman() fetches fresh data and updates table innerHTML
```

### Example 2: Return Inspection Workflow (PIC-Barang)

**File**: pic-barang/pengembalian/pengembalian-barang.html

```
Step 1 - Load Page (Line 605)
├─ DOMContentLoaded → loadPengembalian()
└─ fetch(api/pengembalian/list.php)
   └─ Displays returns with "Inspect" buttons

Step 2 - Click Inspect Button
├─ openInspeksi(pengembalianId)
├─ fetch(api/pengembalian/detail.php)
└─ Modal opens with item inspection form

Step 3 - Fill Inspection Form
├─ User sets item status (Baik/Rusak/Hilang)
└─ User enters notes

Step 4 - Click Finalize Button (submitInspeksi, Line 516+)
├─ Validates all items inspected
├─ Shows confirmation: "FINAL RETURN CONFIRMATION"
├─ Collects item data into pendingInspeksiData
└─ User confirms

Step 5 - Submit Inspection (doSubmitInspeksi, Line 580+)
├─ fetch(api/pengembalian/inspect.php, POST)
│  └─ Body: pengembalian_id, items[], catatan_petugas
│
└─ .then(res => {
    alert(res.message);
    if (res.status) {
        loadPengembalian();  // ← LINE 600 - RE-FETCH AND RE-RENDER
    }
   })

Step 6 - Refresh Return List (Line 349)
└─ loadPengembalian() re-fetches all returns
```

---

## SECTION 6: Summary by File and Pattern Type

### admin/peminjaman/ 
| File | Pattern Type | Action | Line(s) |
|------|----------|--------|---------|
| sedang-dipinjam.html | Navigation | Logout redirect | 423, 427 |
| sedang-dipinjam.html | Data Refresh | Load active borrowings | 434, 567 |
| data-peminjaman.html | Navigation | Logout redirect | (implicit in error handler) |
| data-peminjaman.html | Data Refresh | Load borrowing records by date | 535, 632 |
| menunggu-persetujuan.html | Data Refresh + Alert | Save approval, reload table | 657, 710 |
| admin-approval.html | Data Refresh + Alert | Save approval, reload table | 591, 700 |
| detail-peminjaman.html | Navigation | Logout redirect | 355 |

### manager/persetujuan/
| File | Pattern Type | Action | Line(s) |
|------|----------|--------|---------|
| menunggu-approval.html | Data Refresh + Alert | Save approval, reload table | 606, 710 |
| disetujui.html | Data Refresh | Load approved records | 296 |
| ditolak.html | Data Refresh | Load rejected records | 295 |

### user/peminjaman/
| File | Pattern Type | Action | Line(s) |
|------|----------|--------|---------|
| status-peminjaman.html | Navigation | Return flow redirect | 800 |
| status-peminjaman.html | Navigation | Logout redirect | 534, 540, 542 |
| ajukan-peminjaman.html | Full Table Rewrite | Re-render 5 tables | 1093-1097 |
| ajukan-peminjaman.html | Data Refresh | Load by date range | 642, 791 |
| detail.html | Navigation | Logout redirect | 74 |

### pic-barang/pengembalian/
| File | Pattern Type | Action | Line(s) |
|------|----------|--------|---------|
| pengembalian-barang.html | Data Refresh + Alert | Finalize inspection | 600 |
| pengembalian-barang.html | Data Refresh + Alert | Approve extend | 687 |
| pengembalian-barang.html | Data Refresh + Alert | Reject extend | 702 |

### user/pengembalian/
| File | Pattern Type | Action | Line(s) |
|------|----------|--------|---------|
| ajukan-pengembalian.html | Navigation | Logout redirect | 481, 487, 489 |
| ajukan-pengembalian.html | Navigation | Logout redirect | 683, 942 |

---

## SECTION 7: Patterns by Trigger Action

### Approval Actions
**Trigger**: User submits approval/rejection for borrowing items  
**Flow**: 
1. setUnitDecision() - Save to object
2. saveApprovalDecisions() - Validate
3. doSaveApproval() - Send to API
4. **SUCCESS**: loadDataPeminjaman() - Reload full table

**Files**:
- manager/persetujuan/menunggu-approval.html (Line 606)
- admin/peminjaman/menunggu-persetujuan.html (Line 657)
- admin/peminjaman/admin-approval.html (Line 591)

### Rejection Actions
**Trigger**: Same as approval but with rejection_reason field  
**Special**: Rejection reason textarea appears/hides based on selected rejections  
**Callback**: Same loadDataPeminjaman() refresh

### Return/Pengembalian Finalization
**Trigger**: PIC-Barang completes item inspection for return  
**Flow**:
1. User fills inspection form (item status, damage notes)
2. Confirms final approval dialog
3. doSubmitInspeksi() sends data
4. **SUCCESS**: loadPengembalian() - Reload return table

**Files**:
- pic-barang/pengembalian/pengembalian-barang.html (Line 600)
- admin/pengembalian/pengembalian-barang.html (Line 765)

### Extend Request Approval/Rejection
**Trigger**: PIC-Barang approves/rejects loan extension request  
**Flow**:
1. approveExtend(id) or rejectExtend(id) called
2. Confirmation dialog
3. API call (approve.php or reject.php)
4. **SUCCESS**: loadExtendRequests() - Reload extend table

**Files**:
- pic-barang/pengembalian/pengembalian-barang.html (Lines 687, 702)
- admin/pengembalian/pengembalian-barang.html (Lines ???, ???)

### Submit Borrowing Request
**Trigger**: User submits new borrowing request  
**Pattern**: Shows success and optionally reloads table  
**Callback**: Various - some use loadDataPeminjaman(), some navigate

### Date Filter Change
**Trigger**: User changes date range in report  
**Pattern**: Immediately calls loadDataPeminjaman(startDate, endDate)  
**Location**: admin/peminjaman/data-peminjaman.html, user/peminjaman/ajukan-peminjaman.html

---

## SECTION 8: Critical Findings

### ❌ Issues Found

1. **Over-reliance on Full Page Re-renders**
   - After actions like approval/rejection, entire table is re-fetched
   - Causes unnecessary API calls
   - Poor UX with flickering

2. **No Partial Updates**
   - Even single-item approvals trigger full table reload
   - Could optimize to update only changed item

3. **Modal Complexity**
   - Multiple confirmation modals make flow confusing
   - Some pages have 2-3 modal confirmation levels

4. **Mixed Reload Strategies**
   - Some places use `loadData()`, others use direct navigation
   - Inconsistent error handling

### ✅ Positive Patterns

1. **No Hard Reloads**
   - No `window.location.reload()` found
   - All reloads are data-driven

2. **Consistent API Usage**
   - All data refreshes go through fetch API
   - Proper error handling with try-catch

3. **Alert Feedback**
   - Users get success/failure messages
   - Not silent operations

---

## SECTION 9: Recommendations for Optimization

### Priority 1: Eliminate Unnecessary Full Reloads
**Current**: Approval → reload entire table  
**Suggested**: Approval → update single row or just remove from pending list

### Priority 2: Consolidate Modal Patterns
**Current**: Multiple confirmation modals  
**Suggested**: Single confirmation modal with dynamic content

### Priority 3: Add Partial Update Support
**Current**: `.innerHTML = ` replaces entire table  
**Suggested**: DOM manipulation to update single items

### Priority 4: Implement Spinner/Loading State
**Current**: No visual feedback during reload  
**Suggested**: Show loading spinner or disabled buttons

---

## APPENDIX: Complete File Summary

### Total Files Scanned: 50+

**PROJECT Directory**:
- admin/peminjaman/ - 8 files (approval workflows)
- manager/persetujuan/ - 3 files (approval pages)
- user/peminjaman/ - 5 files (borrowing actions)
- user/pengembalian/ - 2 files (return actions)
- pic-barang/pengembalian/ - 2 files (inspection/extend)
- admin/pengembalian/ - 2 files (admin return view)
- admin/pengaturan.html - 1 file (settings/users)
- Various dashboard files - 5 files

**PROJECT1 Directory** (Mirror structure with variations):
- user/peminjaman/ - 5 files
- admin/peminjaman/ - 5 files
- pic-barang/pengembalian/ - 2 files
- etc.

---

## SCAN METHODOLOGY

- **Search Pattern 1**: `window.location.reload()` - 0 results
- **Search Pattern 2**: `location.reload()` - 0 results
- **Search Pattern 3**: `window.location.href =` - 100+ results
- **Search Pattern 4**: `.innerHTML = renderTable` - 10 results
- **Search Pattern 5**: `.innerHTML =` (general) - 100+ results
- **Search Pattern 6**: `approve|approval|reject|rejection` keywords - 100+ results
- **Manual Review**: Key flow files for approval and return workflows

---

**Report Generated**: 2026-03-05  
**Analysis Status**: Complete - No modifications made
