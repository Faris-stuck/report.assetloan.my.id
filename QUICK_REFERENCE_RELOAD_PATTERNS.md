# Quick Reference: Page Reload Patterns

## Summary Table: All Reload/Re-render Patterns Found

### PATTERN 1: Direct Navigation (window.location.href)
```
Pattern: window.location.href = "path"
Count: 100+ instances
Purpose: Login redirect, logout, role-based navigation
Reload Type: Full page change (not same-page reload)
Performance Impact: HIGH - loads new page
```

**Key Locations**:
| File | Line | Trigger | Target |
|------|------|---------|--------|
| admin/peminjaman/sedang-dipinjam.html | 423 | Logout/Auth fail | index.html |
| user/peminjaman/status-peminjaman.html | 800 | Return request | pengembalian form |
| admin/pengaturan.html | 2871, 2943 | 403 Error | index.html |
| admin/dashboard.html | 403 | Auth fail | index.html |
| register.html | 302 | Registration done | login.html |

---

### PATTERN 2: Full Table HTML Rewrite (renderTableRows)
```
Pattern: document.getElementById("id").innerHTML = renderTableRows(data)
Count: 10 instances
Purpose: Filter-based table display (all statuses)
Reload Type: DOM replacement (same page)
Performance Impact: MEDIUM - processes data locally
```

**Files**:
- user/peminjaman/ajukan-peminjaman.html
  - Line 1093: tableSemua
  - Line 1094: tableMenunggu
  - Line 1095: tableDipinjam
  - Line 1096: tableDikembalikan (different structure)
  - Line 1097: tableDitolak

- PROJECT1/user/peminjaman/ajukan-peminjaman.html
  - Lines 1228-1232 (identical pattern)

---

### PATTERN 3: Data Refresh via Fetch (Most Common)
```
Pattern:
fetch(API_URL)
  .then(res => res.json())
  .then(data => {
    elem.innerHTML = buildHTML(data)  // RE-RENDER
  })

Count: 60+ instances
Purpose: Reload data after actions (approval, rejection, inspection)
Reload Type: DOM update via fetch + re-render
Performance Impact: LOW-MEDIUM - API latency dependent
```

**Refresh Functions**:

#### loadDataPeminjaman()
```
Files: 
  - admin/peminjaman/data-peminjaman.html (L535)
  - admin/peminjaman/sedang-dipinjam.html (L434)
  - user/peminjaman/ajukan-peminjaman.html (L642)
  - manager/persetujuan/menunggu-approval.html (L397)
  - admin/peminjaman/menunggu-persetujuan.html (L463)
  - admin/peminjaman/admin-approval.html (L397)

Called After: Approval save, rejection, date filter change, manual refresh
API: api/approver/list-by-status.php
Updates: Full table tbody via innerHTML
```

#### loadPengembalian()
```
Files:
  - pic-barang/pengembalian/pengembalian-barang.html (L349)
  - admin/pengembalian/pengembalian-barang.html (L506)

Called After: Inspection finalization, return status update
API: api/pengembalian/list.php
Updates: Return table tbody via innerHTML
```

#### loadExtendRequests()
```
Files:
  - pic-barang/pengembalian/pengembalian-barang.html (L616)
  - admin/pengembalian/pengembalian-barang.html (L???)

Called After: Extend approval, extend rejection
API: api/extend/list.php (with optional status filter)
Updates: Extend requests table tbody via innerHTML
```

---

## Key Action → Reload Pattern Mapping

### BORROWING APPROVAL WORKFLOW
```
User Action: Click Approve/Reject button on borrow request
├─ setUnitDecision(key, status)  [no reload]
├─ Click Save button
├─ saveApprovalDecisions()        [validation only]
├─ Show confirmation modal
├─ Click Confirm in modal
├─ doSaveApproval()
│  ├─ fetch('/api/approver/approve-items.php', {POST})
│  ├─ [API processes]
│  └─ .then(res => {
│       if (res.status) {
│         showSuccess('...');
│         loadDataPeminjaman()   ← ⚡ RELOAD HERE ⚡
│       }
│     })
└─ loadDataPeminjaman()
   ├─ fetch('/api/approver/list-by-status.php')
   ├─ [Parse JSON response]
   └─ tbody.innerHTML = [rebuild full table]

Files:
  - manager/persetujuan/menunggu-approval.html (L606)
  - admin/peminjaman/menunggu-persetujuan.html (L657)
  - admin/peminjaman/admin-approval.html (L591)
```

### RETURN INSPECTION FINALIZATION
```
User Action: Complete item inspection for return
├─ [Fill form: item status, damage notes]
├─ Click "Finalize Inspection" button
├─ submitInspeksi()               [validation]
├─ Show "FINAL RETURN CONFIRMATION" dialog
├─ Click Confirm
├─ doSubmitInspeksi()
│  ├─ fetch('/api/pengembalian/inspect.php', {POST})
│  ├─ [API updates database]
│  └─ .then(res => {
│       if (res.status) {
│         alert(res.status);
│         loadPengembalian()      ← ⚡ RELOAD HERE ⚡
│       }
│     })
└─ loadPengembalian()
   ├─ fetch('/api/pengembalian/list.php')
   ├─ [Parse JSON]
   └─ tbody.innerHTML = [rebuild table]

Files:
  - pic-barang/pengembalian/pengembalian-barang.html (L600)
  - admin/pengembalian/pengembalian-barang.html (L765)
```

### EXTEND REQUEST APPROVAL
```
User Action: Approve/reject loan extension
├─ Click Approve/Reject button on extend record
├─ Show confirmation dialog
├─ Click Confirm
├─ approveExtend() OR rejectExtend()
│  ├─ fetch('/api/extend/approve.php' or '/reject.php', {POST})
│  ├─ [API processes]
│  └─ .then(res => {
│       alert(res.message);
│       if (res.status) 
│         loadExtendRequests()    ← ⚡ RELOAD HERE ⚡
│     })
└─ loadExtendRequests()
   ├─ fetch('/api/extend/list.php')
   ├─ [Parse JSON]
   └─ tbody.innerHTML = [rebuild extend table]

Files:
  - pic-barang/pengembalian/pengembalian-barang.html (L687, L702)
  - admin/pengembalian/pengembalian-barang.html (Line ???)
```

### DATE FILTER CHANGE
```
User Action: Select new date range
├─ daterangepicker onChange event
├─ loadDataPeminjaman(startDate, endDate)
│  ├─ fetch('/api/peminjaman/list.php?start=X&end=Y')
│  ├─ [Parse JSON]
│  └─ tbody.innerHTML = [rebuild table with filtered data]

Files:
  - admin/peminjaman/data-peminjaman.html (L632, L744, L929)
  - user/peminjaman/ajukan-peminjaman.html (L638, L791, L914)
```

---

## Reload Patterns Summary

### ❌ NOT FOUND
| Pattern | Count |
|---------|-------|
| `window.location.reload()` | 0 |
| `location.reload()` | 0 |
| `location.href = location.href` | 0 |
| `window.location.href = window.location.href` | 0 |

### ✅ FOUND
| Pattern | Count | Type |
|---------|-------|------|
| `window.location.href = "path"` | 100+ | Navigation |
| `.innerHTML = renderTableRows()` | 10 | Re-render |
| `element.innerHTML = ...` (various) | 100+ | Re-render |
| `loadData*()/loadPengembalian()` | 60+ | Fetch+Render |
| `.then() {...loadData()...}` | 30+ | Callback |

---

## Problem Areas Identified

| Issue | Location | Impact | Severity |
|-------|----------|--------|----------|
| Full table reload after each approval | manager/persetujuan/menunggu-approval.html L606 | Unnecessary API call | MEDIUM |
| No partial update support | pic-barang/pengembalian/pengembalian-barang.html L600 | Entire table re-renders | MEDIUM |
| Multiple confirmation modals | admin/peminjaman/menunggu-persetujuan.html L311-402 | UX confusion | LOW |
| Mixed reload strategies | Various files | Inconsistency | LOW |
| No loading spinner during refresh | All reload locations | Poor UX feedback | LOW |

---

## Files Ranked by Reload Complexity

### TIER 1: Heavy Reload (Multiple patterns)
1. manager/persetujuan/menunggu-approval.html (approval table + reload)
2. pic-barang/pengembalian/pengembalian-barang.html (inspection + extend + reload)
3. admin/peminjaman/data-peminjaman.html (filtered view + reload)

### TIER 2: Moderate Reload
1. user/peminjaman/ajukan-peminjaman.html (multi-table render)
2. admin/peminjaman/menunggu-persetujuan.html (approval workflow)
3. admin/pengembalian/pengembalian-barang.html (inspection + extend)

### TIER 3: Light Reload
1. user/peminjaman/status-peminjaman.html (navigate, not reload)
2. admin/pengaturan.html (simple data refresh)

---

## Performance Impact Assessment

### High Impact Actions (Full Page Reload)
- Login/logout redirects
- Role-based navigation
- Authentication failures

### Medium Impact Actions (Full Table Reload)
- Approval submission
- Inspection finalization
- Extend approval/rejection

### Low Impact Actions (Partial Reload)
- Status text updates
- Single field changes
- Non-table data updates

---

**Last Updated**: 2026-03-05  
**Files Analyzed**: 50+  
**Total Patterns Found**: 270+  
**Analysis Complete**: ✅ YES - No modifications made
