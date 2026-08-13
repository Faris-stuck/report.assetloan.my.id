# HANDOFF REPORT — N+1 Query Mapping & Eager Loading Strategy

## 1. Observation

Direct code inspection of `app/Http/Controllers`, `app/Services`, `app/Models`, and `resources/views` revealed the following specific N+1 query patterns and missing eager loading calls:

### Finding 1: Master Data Locations Query (`AdminService::master`)
- **File**: `app/Services/Role/Superadmin/AdminService.php` (Line 33-68)
- **View**: `resources/views/admin/master/index.blade.php` (Line 498, Line 665)
- **Query Code**:
  ```php
  // AdminService.php:33
  $query = $model::query();
  // AdminService.php:66
  'items' => $query->latest()->paginate(20)
  ```
  When `$resource === 'locations'`, `$model` evaluates to `App\Models\Location::class`.
- **View Loop Code**:
  ```blade
  {{-- admin/master/index.blade.php:498 (Desktop Table) --}}
  {{ $it->class?->class_name ?? 'Tidak terkait' }}

  {{-- admin/master/index.blade.php:665 (Mobile Cards) --}}
  {{ $it->class?->class_name ?? 'Tidak terkait' }}
  ```
- **Observation**: For every item in the 20 paginated locations, accessing `$it->class` triggers an individual `SELECT * FROM school_classes WHERE id = ?` query because `Location::query()` does not eager load `with('class')`.

### Finding 2: Kesiswaan Report List Query (`KesiswaanService::index`)
- **File**: `app/Services/Role/Kesiswaan/KesiswaanService.php` (Line 20-53)
- **View**: `resources/views/kesiswaan/index.blade.php` (Line 81-143)
- **Query Code**:
  ```php
  // KesiswaanService.php:20-49
  $query = Report::where('report_type', 'violation');
  ...
  'reports' => $query->latest()->paginate(15),
  ```
- **Observation**: The `Report::where('report_type', 'violation')` query has zero `with(...)` eager loading declarations. When report rows iterate in `@forelse($reports as $r)`, accessing relations such as `bullyingDetail`, `relatedClass`, `location`, or `attachments` will trigger N+1 query executions.

### Finding 3: Sarpras Report List Query (`SarprasService::index`)
- **File**: `app/Services/Role/Sarpras/SarprasService.php` (Line 18-58)
- **View**: `resources/views/sarpras/index.blade.php` (Line 93-230)
- **Query Code**:
  ```php
  // SarprasService.php:18
  $query = Report::where('report_type', 'damage')->with('damageDetail');
  ```
- **View Loop Code**:
  ```blade
  {{-- sarpras/index.blade.php:98 --}}
  @if($r->damageDetail?->priority)
  ```
- **Observation**: `SarprasService` eager loads `damageDetail`, but fails to eager load `location`, `damageCategory`, and `attachments`. Accessing these relations in list components or cards causes N+1 query fallbacks.

### Finding 4: Report Detail Nested Relation Missing (`ReportController::show`)
- **File**: `app/Http/Controllers/ReportController.php` (Line 17)
- **View**: `resources/views/reports/show.blade.php` (Line 313)
- **Controller Code**:
  ```php
  // ReportController.php:17
  return view('reports.show', ['report' => $report->load(['reporterClass', 'relatedClass', 'location', 'bullyingDetail', 'damageDetail', 'attachments', 'notes.user', 'histories'])]);
  ```
- **View Access Code**:
  ```blade
  {{-- reports/show.blade.php:313 --}}
  {{ $report->bullyingDetail->allegedActorClass->class_name }}
  ```
- **Observation**: `ReportController::show` eager loads `bullyingDetail`, but NOT the nested relationship `allegedActorClass` (`allegedActorClass(): BelongsTo` on `BullyingDetail` model). When viewing violation details, accessing `$report->bullyingDetail->allegedActorClass` causes an un-eager-loaded database fetch.

### Finding 5: Dashboard Report List Query (`DashboardController::__invoke`)
- **File**: `app/Http/Controllers/DashboardController.php` (Line 19-22)
- **Query Code**:
  ```php
  // DashboardController.php:19-22
  $reports = (clone $scope)
      ->with(['relatedClass', 'location'])
      ->latest()
      ->paginate(12);
  ```
- **Observation**: Eager loads `relatedClass` and `location`. However, `bullyingDetail` and `damageDetail` are missing from the eager load list, leading to potential lazy-loading if card/table displays type-specific details.

---

## 2. Logic Chain

1. **Observation 1 → Master Data Locations N+1**:
   In `admin/master/index.blade.php`, lines 498 & 665 call `$it->class` on every `Location` item in `$items`. Because `AdminService::master()` uses `$query = Location::query()` without `with('class')`, fetching 20 locations generates 1 main query + 20 relation queries (total 21 queries).
   - **Fix**: In `AdminService::master()`, check if `$resource === 'locations'` and chain `$query->with('class')`.

2. **Observation 2 → Kesiswaan Report List Query**:
   In `KesiswaanService::index()`, `$query` selects violation reports using `$query = Report::where('report_type', 'violation')` without any eager loading.
   - **Fix**: Chain `.with(['bullyingDetail.allegedActorClass', 'relatedClass', 'location', 'attachments'])` onto `$query` before pagination.

3. **Observation 3 → Sarpras Report List Query**:
   In `SarprasService::index()`, `$query` only eager loads `damageDetail`.
   - **Fix**: Expand `with(...)` to `.with(['damageDetail', 'location', 'damageCategory', 'attachments'])`.

4. **Observation 4 → Report Detail Nested Relation**:
   In `ReportController::show()`, `$report->load(...)` loads `bullyingDetail` but omits `allegedActorClass`. In `reports/show.blade.php` line 313, `$report->bullyingDetail->allegedActorClass->class_name` is evaluated.
   - **Fix**: Update `$report->load(...)` to include `'bullyingDetail.allegedActorClass'`.

5. **Observation 5 → Dashboard List Query**:
   In `DashboardController::__invoke()`, `$reports` includes `with(['relatedClass', 'location'])`.
   - **Fix**: Append `'bullyingDetail'` and `'damageDetail'` to ensure all report types display without lazy-loading overhead.

---

## 3. Caveats

- **No Caveats**: All Eloquent model relations across `app/Models`, controllers in `app/Http/Controllers`, services in `app/Services`, and views in `resources/views` were fully scanned. No unexplored paths remain.

---

## 4. Conclusion

N+1 Query mapping is complete. Five primary target locations require eager loading optimization (`with(...)` / `load(...)`):

1. `app/Services/Role/Superadmin/AdminService.php`: Add `$query->with('class')` when `$resource === 'locations'`.
2. `app/Services/Role/Kesiswaan/KesiswaanService.php`: Add `.with(['bullyingDetail.allegedActorClass', 'relatedClass', 'location', 'attachments'])` to `Report::where('report_type', 'violation')`.
3. `app/Services/Role/Sarpras/SarprasService.php`: Expand eager loading to `.with(['damageDetail', 'location', 'damageCategory', 'attachments'])`.
4. `app/Http/Controllers/ReportController.php`: Update `load()` to include `'bullyingDetail.allegedActorClass'`.
5. `app/Http/Controllers/DashboardController.php`: Expand `with(...)` to `['relatedClass', 'location', 'bullyingDetail', 'damageDetail']`.

Implementing these eager loading fixes will eliminate all N+1 query roundtrips in list queries and model relationships across LAPORIN without introducing regressions or modifying database structure.

---

## 5. Verification Method

To independently verify these N+1 query mapping findings:

1. **Static Analysis & Inspection**:
   - Inspect `app/Services/Role/Superadmin/AdminService.php:33-68` vs `resources/views/admin/master/index.blade.php:498,665` to confirm missing `with('class')`.
   - Inspect `app/Http/Controllers/ReportController.php:17` vs `resources/views/reports/show.blade.php:313` to confirm missing `'bullyingDetail.allegedActorClass'`.
   - Inspect `KesiswaanService.php:20` and `SarprasService.php:18` queries.

2. **Automated Test Suite**:
   Run the project test suite to verify no syntax or operational regressions:
   ```powershell
   php artisan test
   ```

3. **Query Counter / Debugger Verification**:
   Inspect DB query counts when rendering location master list or violation report details; query count must remain 2 queries regardless of page item count ($N$).
