# VERIFICATION GUIDE - Return Status System

**Purpose:** Quick checklist to verify all fixes are working correctly  
**Last Updated:** February 20, 2026  

---

## PRE-VERIFICATION CHECKLIST

### Database State
- [ ] Ensure database has test data with multiple pengembalian records
- [ ] Check that `peminjaman` table has correct status enum values
- [ ] Verify `detail_pengembalian` has sample rows with `jumlah_kembali` and `jumlah_rusak`

### Backup Recommendation
- [ ] Backup database before testing
- [ ] Take note of test user IDs for verification

---

## USER VERIFICATION (Login as Regular User)

### 1. List View - "Ajukan Peminjaman" Page
**File:** http://localhost/PROJECT/user/peminjaman/ajukan-peminjaman.html

#### Check Tab: "Semua" (All)
- [ ] List loads without errors
- [ ] Display shows: Kode, Nama Peminjam, Tanggal, Status, Detail button

#### Check Tab: "Sedang Dipinjam" (Currently Borrowed)
- [ ] Only shows items with status "Sedang Dipinjam"
- [ ] Items with returned items not shown here

#### Check Tab: "Dikembalikan" (Returned)
- [ ] Shows items with status "Dikembalikan"
- [ ] **IMPORTANT:** Also shows items with status "Sebagian Rusak" and "Semua Rusak"
- [ ] Items with status "Sebagian Dikembalikan" NOT shown (filtered out correctly)

#### Check Tab: "Menunggu" (Pending Approval)
- [ ] Shows only pending approval items
- [ ] Correct badge color (yellow/warning)

#### Check Tab: "Ditolak" (Rejected)
- [ ] Shows only rejected items
- [ ] Correct badge color (red/danger)

### 2. Modal Detail - Borrowing Details
**Click "DETAIL" button on any returned borrowing**

#### Header Information
- [ ] Shows: Kode, Nama, NRP, Status, Tanggal Pinjam, Rencana Kembali
- [ ] Status badge shows: "Dikembalikan", "Sebagian Rusak", or "Semua Rusak"

#### Item List Table
- [ ] Columns: No., Item Name, Qty, Return Status
- [ ] **CRITICAL:** Return Status shows "Dikembalikan" for ALL returned items
- [ ] Item 1, 2, 3... all show "Dikembalikan" (NOT "Dikembalikan - Rusak")
- [ ] Good condition items show green badge [✓]
- [ ] Damaged items show orange/yellow badge [!] (but text still says "Dikembalikan")
- [ ] Badge colors are DIFFERENT, status text is SAME

### 3. Partial Return Scenario
**Open a borrowing with status "Sebagian Dikembalikan"**

#### Modal Display
- [ ] Header shows: "Sebagian Dikembalikan"
- [ ] Some items show: "Dikembalikan" [✓]
- [ ] Some items show: "Belum Dikembalikan" [?]
- [ ] Mix of returned and pending items visible

#### List Placement
- [ ] NOT in "Dikembalikan" tab
- [ ] Only appears in "Semua" tab (filtered correctly)

### 4. Return Submission Page
**File:** http://localhost/PROJECT/user/pengembalian/ajukan-pengembalian.html

#### Items Available for Return
- [ ] Page loads list of items available to return
- [ ] Only shows: "Sedang Dipinjam" or "Sebagian Dikembalikan" status items
- [ ] Does NOT show items with "Dikembalikan" status (already returned)
- [ ] Message shows "No borrowings found. All items have been returned." for completed returns

#### Submit Return
- [ ] Can select items to return
- [ ] Can indicate damage status
- [ ] Form submission works correctly
- [ ] After submission, page reloads with updated list

---

## PIC/ADMIN VERIFICATION (Login as PIC or Admin)

### 1. Pengembalian Inspection
**File:** Any PIC inspection interface

#### Inspection Form
- [ ] Loads return items correctly
- [ ] Can mark items as damaged
- [ ] Can set damage quantity

#### After Submission
- [ ] Pengembalian status changes to "Selesai"
- [ ] peminjaman.status updates to:
  - "Dikembalikan" if no damage
  - "Sebagian Rusak" if some damaged  
  - "Semua Rusak" if all damaged
- [ ] Not always "Dikembalikan" regardless of damage

---

## DATABASE VERIFICATION

### Check Status Calculations
```sql
-- Verify all items returned shows correct status
SELECT p.id, p.kode_peminjaman, p.status,
       SUM(dp.jumlah) as total_items,
       SUM(dpg.jumlah_kembali) as total_returned,
       SUM(dpg.jumlah_rusak) as total_damaged
FROM peminjaman p
JOIN detail_peminjaman dp ON p.id = dp.peminjaman_id
LEFT JOIN detail_pengembalian dpg ON dp.id = dpg.detail_peminjaman_id
WHERE p.user_id = [USER_ID]
GROUP BY p.id
ORDER BY p.id DESC;

-- Expected for fully returned with damage:
-- total_items = 5, total_returned = 5, total_damaged = 1
-- peminjaman.status should be "Sebagian Rusak"
```

### Check No Hybrid Status
```sql
-- Verify no "Dikembalikan - Rusak" status exists
SELECT DISTINCT status FROM peminjaman 
WHERE status LIKE '%Dikembalikan-%';

-- Should return: EMPTY (no results)
```

### Verify Aggregate Calculation
```sql
-- For a specific peminjaman with multiple pengembalian records
SELECT p.kode_pengembalian, p.status,
       SUM(dp.jumlah_kembali) as total_kembali
FROM pengembalian p
JOIN detail_pengembalian dp ON p.id = dp.pengembalian_id
WHERE p.peminjaman_id = [PEMINJAMAN_ID]
GROUP BY p.id;

-- Should sum across ALL pengembalian records
```

---

## API RESPONSE VERIFICATION

### Get All Peminjaman
**API:** `/api/peminjaman/get_all.php?user_id=X`

```json
{
  "status": true,
  "data": [
    {
      "id": 123,
      "status": "Dikembalikan",  // ✓ Should be from database
      "status_en": "Returned",     // ✓ Should match status
      "barang": "...description.."
    }
  ]
}
```

#### Verification Points
- [ ] Status values are from database enum (not hardcoded)
- [ ] Aggregate calculation used (not just latest pengembalian)
- [ ] "Sebagian Rusak" appears when applicable
- [ ] "Dikembalikan" appears when all returned, no damage
- [ ] No hybrid statuses like "Dikembalikan - Rusak"

### Get Detail Peminjaman
**API:** `/api/user/get-detail.php?peminjaman_id=X`

```json
{
  "status": true,
  "data": {
    "status": "Sebagian Rusak",           // ✓ From database/aggregate
    "status_en": "Partially Damaged",
    "detail_barang": [
      {
        "nama_barang": "Projector",
        "jumlah": 2,
        "jumlah_kembali": 2,             // ✓ Aggregated from all pengembalian
        "jumlah_rusak": 1,               // ✓ Aggregated from all pengembalian
        "kondisi_kembali": "Rusak"
      }
    ]
  }
}
```

#### Verification Points
- [ ] Status correctly calculated from aggregate
- [ ] detail_barang shows aggregated jumlah_kembali/jumlah_rusak
- [ ] All pengembalian records included (not just latest)

---

## VISUAL VERIFICATION

### Badge Colors - Correct Display
```
Modal showing returned items:
  ✓ Item 1 (good)      → Badge: GREEN [✓]     - Return Status: "Dikembalikan"
  ✓ Item 2 (damaged)   → Badge: ORANGE [!]    - Return Status: "Dikembalikan"
  ✓ Item 3 (good)      → Badge: GREEN [✓]     - Return Status: "Dikembalikan"

NOT showing:
  ✗ "Dikembalikan - Rusak" as status text
  ✗ RED [✗] badge for returned items
```

### List View - Correct Filtering
```
"Dikembalikan" tab should contain:
  ✓ Items with status "Dikembalikan"
  ✓ Items with status "Sebagian Rusak"
  ✓ Items with status "Semua Rusak"

"Dikembalikan" tab should NOT contain:
  ✗ Items with status "Sebagian Dikembalikan"
  ✗ Items with status "Sedang Dipinjam"
  ✗ Items with status "Ditolak"
```

---

## EDGE CASE TESTING

### Test Case 1: Multiple Submissions
```
Setup:
  - Borrow 5 items (ID: 100)
  - First return: 3 items (Selesai)
  - Second return: 2 items (Diajukan)

Expected:
  ✓ List shows: "Dikembalikan" (5 items total returned)
  ✓ Modal shows: All 5 items with "Dikembalikan" status
  ✓ Database: peminjaman.status = "Dikembalikan"
  ✓ Tab: Appears in "Dikembalikan" tab
```

### Test Case 2: Damage Across Multiple Submissions
```
Setup:
  - First return: 3 items, 0 damaged (Selesai)
  - Second return: 2 items, 1 damaged (Selesai)

Expected:
  ✓ List shows: "Sebagian Rusak"
  ✓ Modal shows: 4 items "Dikembalikan" [✓], 1 "Dikembalikan" [!]
  ✓ Database: peminjaman.status = "Sebagian Rusak"
```

### Test Case 3: All Damaged
```
Setup:
  - Borrow 3 items
  - Return all 3, all marked damaged

Expected:
  ✓ List shows: "Semua Rusak"
  ✓ Modal shows: All 3 items "Dikembalikan" [!]
  ✓ Database: peminjaman.status = "Semua Rusak"
```

---

## TROUBLESHOOTING

### Problem: Modal shows "Sebagian Dikembalikan" even though all items returned
**Solution:**
1. Check if latest pengembalian has status "Selesai"
2. Verify API response includes all detail_pengembalian rows
3. Check SQL aggregate calculation: SUM(jumlah_kembali) should equal total items
4. Clear browser cache and reload

### Problem: Damage items shown as red badge instead of orange
**Solution:**
1. Check CSS class is `bg-warning` not `bg-danger`
2. Verify HTML renders badge correctly
3. Check for CSS conflicts
4. Reload page with Ctrl+Shift+R (hard refresh)

### Problem: "Sebagian Rusak" not appearing in "Dikembalikan" tab
**Solution:**
1. Check filter condition in ajukan-peminjaman.html line 1245-1248
2. Verify filter includes 'sebagian rusak' status
3. Verify filter excludes only 'sebagian dikembalikan'
4. Debug: Check browser console for errors

### Problem: API returns wrong status
**Solution:**
1. Check aggregate query in get_all.php:
   ```sql
   SELECT SUM(jumlah_kembali) FROM detail_pengembalian
   JOIN pengembalian WHERE peminjaman_id = ?
   ```
2. Manually run query and compare to expected total
3. Check if detail_pengembalian entries are being created correctly
4. Verify peminjaman.status is updated by inspect.php

---

## SIGNOFF CHECKLIST

- [ ] All tab filtering works correctly
- [ ] Modal shows correct status text ("Dikembalikan" for returned items)
- [ ] Badge colors correctly indicate condition (green/orange)
- [ ] Multiple pengembalian submissions handled correctly
- [ ] Damage status calculated correctly (Sebagian Rusak / Semua Rusak)
- [ ] Database stores correct final status
- [ ] No hardcoded status strings found
- [ ] All user-facing screens use APIs
- [ ] All test cases pass
- [ ] Edge cases handled correctly
- [ ] No SQL errors in logs
- [ ] Performance acceptable (query times < 1s)

**Verification Complete:** _______________ Date: _______________

