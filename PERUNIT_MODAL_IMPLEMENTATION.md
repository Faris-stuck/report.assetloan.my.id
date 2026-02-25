# Per-Unit Modal Display Implementation - Complete Summary

## Overview
The extend perpanjangan modal has been completely redesigned to display barang on a **per-unit basis** instead of qty summary. Each row now represents 1 individual unit with its own expected return date based on actual extend status in the database.

## Key Changes

### 1. Database Structure Change
**New Table: `extend_peminjaman_items`**
- **Old Structure** (Legacy): `barang_id`, `qty_extend` (qty-based tracking)
- **New Structure** (Per-Unit): 
  - `id` - Primary key
  - `extend_peminjaman_id` - Link to extend request
  - `detail_peminjaman_id` - Link to specific detail peminjaman record
  - `unit_number` - Which unit from qty (1, 2, 3...)
  - `tanggal_perpanjang` - Extended return date for THIS unit
  - `created_at` - Timestamp

**Why This Matters:**
- Each unit can have its own extended return date
- Supports partial extends (some units extended, some not)
- Accurate per-unit return tracking in the future

### 2. Backend API Endpoints

**GET `/api/peminjaman/get_extend_units.php`** (NEW)
- Input: `peminjaman_id` (query param)
- Output: Per-unit array with expected return calculated per unit
- Response structure:
```json
{
  "status": true,
  "data": {
    "peminjaman_id": 83,
    "peminjaman_rencana_kembali": "2026-03-02",
    "peminjaman_status": "Sedang Dipinjam",
    "units": [
      {
        "unit_id": "detail_114_unit_1",
        "detail_peminjaman_id": 114,
        "barang_id": 96,
        "kode_barang": "LPT-001",
        "nama_barang": "Laptop Lenovo Thinkpad",
        "unit_number": 1,
        "qty_dipinjam": 1,
        "kondisi_pinjam": "Baik",
        "expected_return": "2026-03-02",
        "sudah_dikembalikan": false,
        "is_extended": false,
        "extend_date": null,
        "can_extend": true,
        "unit_display": "1/4"
      },
      // ... more units
    ]
  }
}
```

**POST `/api/extend/request.php`** (UPDATED)
- Now accepts both formats:
  - **NEW per-unit format**: `units` JSON array like `["detail_114_unit_1", "detail_114_unit_2"]`
  - **LEGACY format**: `items` JSON array like `[{"barang_id": 96, "qty_extend": 2}]`
- Inserts data into `extend_peminjaman_items` with per-unit tracking
- Maintains backward compatibility

### 3. Frontend Modal Display

**Modal Table Structure - CHANGED**
- **Old**: No | Barang | Qty Dipinjam | Qty Extend | Pilih (5 columns)
- **New**: No | Barang | Unit | Qty | Expected Return / Status | Pilih (6 columns)

**Key Features:**
- Each row = 1 unit (not grouped)
- Unit display shows "1/4" (unit 1 of 4 total)
- Expected Return calculated per unit
- Status badge shows if unit already returned or extended
- Checkbox selection per individual unit
- Can only extend units not yet returned

**HTML Modal** (Updated in `ajukan-pengembalian.html`)
- Changed table header from 5 columns to 6 columns
- Updated `<tbody id="extendItemsTableBody">` loading structure

### 4. Frontend JavaScript Functions

**`openExtendModal(peminjamanId, currentReturnDate)`** (REWRITTEN)
- Calls `GET /api/peminjaman/get_extend_units.php` instead of `/api/peminjaman/get_detail.php`
- Generates table rows with per-unit structure
- Each row has `data-unit-id="detail_X_unit_Y"` attribute
- Shows expected return and unit status per individual unit
- Checkboxes for unit selection (not qty input)

**`submitExtendRequest()`** (UPDATED)
- Collects checked unit IDs: `["detail_114_unit_1", "detail_114_unit_3"]`
- Sends in `units` parameter as JSON array
- API converts unit_ids to `{detail_peminjaman_id, unit_number}` pairs
- Inserts individual records into `extend_peminjaman_items`

### 5. Database Migrations Applied

1. **`migrate-extend-items-recreate.sql`**
   - Backed up old table to `extend_peminjaman_items_backup`
   - Recreated `extend_peminjaman_items` with new per-unit schema
   - Created proper foreign keys and unique constraints

## Unit Generation Logic

For each `detail_peminjaman` record with `jumlah: 4`:
```
Unit 1/4: expected_return = rencana_kembali (or extend if exists for unit 1)
Unit 2/4: expected_return = rencana_kembali (or extend if exists for unit 2)
Unit 3/4: expected_return = rencana_kembali (or extend if exists for unit 3)
Unit 4/4: expected_return = rencana_kembali (or extend if exists for unit 4)
```

## Expected Return Determination (Per Unit)

For each generated unit:
1. Default: `peminjaman.rencana_kembali`
2. If approved extend exists for THIS specific unit: use `extend_peminjaman_items.tanggal_perpanjang`
3. If blanket extend exists (no unit-specific): use `extend_peminjaman.tanggal_perpanjang`
4. Display status: "Extended to DATE" if is_extended=true

## Return Status Determination

Unit is marked `sudah_dikembalikan = true` if:
```
total_units_returned >= unit_number
```

For example, if 2 laptops returned out of 4:
- Unit 1: sudah_dikembalikan = true
- Unit 2: sudah_dikembalikan = true
- Unit 3: sudah_dikembalikan = false
- Unit 4: sudah_dikembalikan = false

## Extend Eligibility (`can_extend` flag)

Unit can be extended if:
- NOT already returned (`sudah_dikembalikan = false`)
- AND peminjaman status is active (not Completed/Rejected/Cancelled)
- AND (optionally) not already extended (can re-extend)

## Testing Results

✓ Table structure correct (6 columns with proper fields)
✓ Unit generation working (generates N rows for qty N)
✓ Expected return calculation working
✓ API returns proper JSON structure
✓ Per-unit unit_id format correct: `detail_X_unit_Y`
✓ Modal table header updated to 6 columns
✓ Frontend functions rewritten for per-unit handling
✓ API request handler accepts per-unit format

## Backward Compatibility

- Old `items` JSON format still supported in request.php
- Legacy data converted to per-unit on submission
- System works with or without per-unit data

## Files Modified

### Backend Files:
1. `/api/peminjaman/get_extend_units.php` (NEW) - Per-unit API endpoint
2. `/api/extend/request.php` (UPDATED) - Now handles per-unit format
3. `/database/migrate-extend-items-recreate.sql` (NEW) - Schema migration

### Frontend Files:
1. `/user/pengembalian/ajukan-pengembalian.html` (UPDATED):
   - Modal table header (6 columns)
   - `openExtendModal()` function (rewritten)
   - `submitExtendRequest()` function (updated)

### Database Tables:
1. `extend_peminjaman_items` (RECREATED with new schema)

## Example Flow

**User extends 2 out of 4 laptops to 2026-03-10:**

Frontend sends:
```json
{
  "peminjaman_id": 83,
  "tanggal_perpanjang": "2026-03-10",
  "alasan": "Pekerjaan belum selesai",
  "units": ["detail_114_unit_1", "detail_114_unit_3"]
}
```

Backend processes:
1. Creates `extend_peminjaman` record (extends whole loan)
2. Creates 2 records in `extend_peminjaman_items`:
   - `{extend_id, detail_id: 114, unit_number: 1, tanggal_perpanjang: 2026-03-10}`
   - `{extend_id, detail_id: 114, unit_number: 3, tanggal_perpanjang: 2026-03-10}`

Next time modal loads for this peminjaman:
- Unit 1: expected_return = 2026-03-10, is_extended = true, badge shows "Extended to 2026-03-10"
- Unit 2: expected_return = 2026-03-02 (original), is_extended = false
- Unit 3: expected_return = 2026-03-10, is_extended = true, badge shows "Extended to 2026-03-10"
- Unit 4: expected_return = 2026-03-02 (original), is_extended = false

## Error Handling

- Session validation on API endpoints
- User authorization (peminjaman must belong to requesting user)
- Unit validation (unit_number must be ≤ qty_total)
- Detail item validation (must exist in peminjaman)
- Extend date validation (must be after current return date)

## No Hardcoding, No Assumptions

✓ All expected_return dates come from actual database records
✓ Unit generation based on actual qty_dipinjam values
✓ Extend status determined from actual extend_peminjaman_items records
✓ Return status calculated from actual detail_pengembalian records
✓ No hardcoded defaults - all from database relations
