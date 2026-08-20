# Handoff Report — Aggregate Statistics & Caching Optimization Survey

**Agent**: Explorer 2 (Aggregate Statistics & Caching)  
**Target Project**: LAPORIN (Laravel / MySQL / Redis)  
**Date**: 2026-08-13  
**Working Directory**: `c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_explorer_survey_2`

---

## 1. Observation

Direct observations and evidence gathered from the LAPORIN codebase across controllers, services, helpers, traits, and observers:

### A. Repetitive & Un-grouped Aggregate Queries in `DashboardController.php`

1. **Summary Stats Queries (5 Separate `COUNT(*)` Calls)**
   - **File**: `app/Http/Controllers/DashboardController.php` (Lines 24–38)
   - **Code Snippet**:
     ```php
     $stats = [
         'total' => (clone $scope)->count(),
         'violation' => (clone $scope)
             ->where('report_type', 'violation')
             ->count(),
         'damage' => (clone $scope)
             ->where('report_type', 'damage')
             ->count(),
         'pending' => (clone $scope)
             ->where('status', 'menunggu_verifikasi')
             ->count(),
         'done' => (clone $scope)
             ->where('status', 'selesai')
             ->count(),
     ];
     ```
   - **Verbatim Error / Overhead**: Executes **5 distinct SQL queries** (`SELECT COUNT(*) FROM reports WHERE ...`) per page load on the dashboard.

2. **Monthly Chart Data Query Loop (6 Separate `COUNT(*)` Calls)**
   - **File**: `app/Http/Controllers/DashboardController.php` (Lines 118–131)
   - **Code Snippet**:
     ```php
     for ($offset = 5; $offset >= 0; $offset--) {
         $start = $currentMonth->subMonths($offset);
         $end = $start->addMonth();

         $labels[] =
             $monthNames[$start->month]
             .' '
             .$start->format('Y');

         $counts[] = (clone $scope)
             ->where('created_at', '>=', $start)
             ->where('created_at', '<', $end)
             ->count();
     }
     ```
   - **Verbatim Overhead**: Executes **6 distinct SQL queries** in a PHP `for` loop, each selecting `COUNT(*)` for a single month.

### B. Uncached Reference & Administrative Queries Across Application

1. **Public Reporting Form Reference Data (`PublicReportController.php`)**
   - **File**: `app/Http/Controllers/PublicReportController.php` (Lines 162–188)
   - **Code Snippet**:
     ```php
     $classes = SchoolClass::where('is_active', true)->get()->...
     'subjects' => Subject::where('is_active', true)->orderBy('subject_name')->get(),
     'staffUnits' => StaffUnit::where('is_active', true)->orderBy('unit_name')->get(),
     'locations' => Location::where('is_active', true)->orderBy('location_name')->get(),
     'damageCategories' => DamageCategory::where('is_active', true)->orderBy('category_name')->get(),
     ```
   - **Verbatim Overhead**: Every high-traffic public visit to `/lapor` issues **5 uncached database queries** for static reference tables (`school_classes`, `subjects`, `staff_units`, `locations`, `damage_categories`).

2. **Superadmin Active Superadmin Count & Audit Log Actions (`AdminService.php`)**
   - **File**: `app/Services/Role/Superadmin/AdminService.php` (Lines 131–133, Line 229)
   - **Code Snippet**:
     ```php
     // Line 131-133:
     'activeSuperadminCount' => User::where('role', 'superadmin')
         ->where('is_active', true)
         ->count(),

     // Line 229:
     'actions' => AuditLog::distinct()->pluck('action'),
     ```
   - **Verbatim Overhead**: Uncached count query on user management page; full table distinct query on audit log page.

3. **Kesiswaan Reference Data (`KesiswaanService.php`)**
   - **File**: `app/Services/Role/Kesiswaan/KesiswaanService.php` (Lines 50–51)
   - **Code Snippet**:
     ```php
     'students' => Student::with('class')->orderBy('name')->get(),
     'types' => ViolationType::where('is_active', true)->get(),
     ```
   - **Verbatim Overhead**: Fetches full student list and all active violation types uncached on every Kesiswaan index view.

### C. Existing Caching Infrastructure

1. **`CacheHelper` Implementation (`app/Helpers/CacheHelper.php`)**
   - Methods available: `remember()`, `get()`, `put()`, `has()`, `forget()`, `invalidate()`, `invalidateTag()`, `invalidateTags()`, `putWithTags()`, `flush()`.
   - `invalidate()` uses Redis `SCAN` (non-blocking) when Redis driver is active.
   - `flush()` safe for Redis production (only flushes application prefix `laporin:*`).

2. **`CacheableQuery` Trait (`app/Traits/CacheableQuery.php`)**
   - Attached to `Report` model (`app/Models/Report.php`, Line 16).
   - Generates formatted cache keys (`laporin:{entity}:{action}:{params_hash}`).

3. **Observers (`app/Observers/`)**
   - `ReportObserver.php` (Lines 37–43): Invalidates `laporin:report:*` and tags `report`, `location`.
   - `BullyingDetailObserver.php` & `DamageDetailObserver.php`: Invalidate related tags and pattern prefixes (`laporin:bullyingdetail:*`, `laporin:damagedetail:*`, `laporin:report:*`).

---

## 2. Logic Chain

1. **Observation 1A (Dashboard queries)**: The dashboard currently fires 11 separate SQL queries per page load (5 aggregate stats + 6 monthly chart queries).
   - *Deduction*: By applying SQL **conditional aggregation** (`COUNT(CASE WHEN ... THEN 1 END)`), the 5 summary stats queries can be collapsed into **1 single query**.
   - *Deduction*: By applying SQL `GROUP BY DATE_FORMAT(created_at, '%Y-%m')`, the 6 monthly chart queries can be collapsed into **1 single query**.
   - *Deduction*: Total dashboard aggregate queries reduce from 11 down to 2.

2. **Observation 1A + 1C (Dashboard Caching)**: The dashboard statistics and chart results do not change on every second, yet are calculated dynamically on every page request.
   - *Deduction*: Wrapping the combined dashboard calculations in `CacheHelper::remember()` with a 5-minute (300s) TTL eliminates database queries entirely for cached dashboard views.
   - *Deduction*: Since `ReportObserver` already invalidates `laporin:report:*` on report mutation, cached dashboard data will automatically purge immediately whenever a new report is created or updated.

3. **Observation 1B + 1C (Public Form Reference Caching)**: `/lapor` is the primary public entry point. It loads 5 static reference tables on every hit.
   - *Deduction*: Reference lists like `SchoolClass`, `Subject`, `StaffUnit`, `Location`, and `DamageCategory` change rarely (administrative updates only).
   - *Deduction*: Caching these 5 reference queries using `CacheHelper::remember()` with a 1-hour (3600s) TTL will reduce the SQL query burden of the public reporting page from 6+ queries down to 1 (or 0 for reference lookup).

4. **Observation 1B (Kesiswaan & Admin Reference Caching)**:
   - *Deduction*: Caching `ViolationType::where('is_active', true)->get()` and `AuditLog::distinct()->pluck('action')` will eliminate redundant database scans on administrative panels.

---

## 3. Caveats

1. **Role & Homeroom Class Scoping**:
   - `DashboardController` scopes reports based on user role (`superadmin`, `kesiswaan`, `sarpras`, `wali_kelas`).
   - For `wali_kelas`, scope depends on `$user->homeroomClasses()->pluck('class_id')`.
   - *Constraint*: Dashboard cache keys MUST incorporate the user ID or role ID for `wali_kelas` (e.g. `laporin:dashboard:stats:user_{id}_role_{role}`).

2. **Redis Tag Support**:
   - `CacheHelper` supports both tagged invalidation (`invalidateTag`) and pattern scanning (`invalidate`).
   - If Redis cache store is used without tags, pattern scan (`laporin:report:*`) serves as fallback and is fully supported by `CacheHelper::invalidate()`.

3. **Read-Only Scope**:
   - Explorer 2 is restricted to read-only investigation and proposal. Implementation of proposals will be executed by the implementation agents.

---

## 4. Conclusion & Concrete Optimization Proposals

### Summary of Proposals

| Target File | Current Impact | Proposed Optimization | Expected Benefit |
|---|---|---|---|
| `DashboardController.php` (lines 24-38) | 5 `COUNT(*)` queries | Group into 1 `selectRaw` conditional `COUNT(CASE WHEN ...)` | 80% reduction in stats query count (5 → 1) |
| `DashboardController.php` (lines 118-131) | 6 `COUNT(*)` loop queries | Group into 1 `selectRaw` query with `GROUP BY ym` | 83% reduction in chart query count (6 → 1) |
| `DashboardController.php` (lines 14-44) | 0 caching | Wrap stats & chart in `CacheHelper::remember` (TTL 300s) | 100% database query bypass on cache hit |
| `PublicReportController.php` (lines 162-188) | 5 uncached reference queries | Cache `classes`, `subjects`, `staffUnits`, `locations`, `damageCategories` using `CacheHelper::remember` (TTL 3600s) | 5 SQL queries eliminated per public form request |
| `AdminService.php` (lines 131, 229) | Uncached `User::count()` & `AuditLog::distinct()` | Cache active superadmin count (TTL 600s) & audit log actions (TTL 3600s) | Eliminates DB scans on admin user and audit pages |
| `KesiswaanService.php` (lines 50-51) | Uncached `Student` & `ViolationType` | Cache `ViolationType::where('is_active', true)` (TTL 3600s) | Reduced query load on Kesiswaan panel |

---

### Code Proposals (Diff / Replacement Snippets)

#### Proposal 1: Grouping & Caching in `DashboardController.php`

**Target File**: `app/Http/Controllers/DashboardController.php`

```php
// BEFORE (Lines 24-38 & 118-131):
$stats = [
    'total' => (clone $scope)->count(),
    'violation' => (clone $scope)->where('report_type', 'violation')->count(),
    'damage' => (clone $scope)->where('report_type', 'damage')->count(),
    'pending' => (clone $scope)->where('status', 'menunggu_verifikasi')->count(),
    'done' => (clone $scope)->where('status', 'selesai')->count(),
];

// AFTER (Proposed Grouped Query):
$cacheKey = "laporin:dashboard:user_{$user->id}_role_{$user->role}";

return CacheHelper::remember($cacheKey, 300, function () use ($scope, $user, $reports) {
    // Single query for stats via conditional aggregation
    $rawStats = (clone $scope)
        ->selectRaw("
            COUNT(*) as total,
            COUNT(CASE WHEN report_type = 'violation' THEN 1 END) as violation,
            COUNT(CASE WHEN report_type = 'damage' THEN 1 END) as damage,
            COUNT(CASE WHEN status = 'menunggu_verifikasi' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'selesai' THEN 1 END) as done
        ")
        ->first();

    $stats = [
        'total' => (int) ($rawStats->total ?? 0),
        'violation' => (int) ($rawStats->violation ?? 0),
        'damage' => (int) ($rawStats->damage ?? 0),
        'pending' => (int) ($rawStats->pending ?? 0),
        'done' => (int) ($rawStats->done ?? 0),
    ];

    return view('dashboard.index', [
        'reports' => $reports,
        'stats' => $stats,
        'chart' => $this->monthlyChart($scope, $user),
    ]);
});
```

#### Proposal 2: Grouping Monthly Chart in `DashboardController.php`

```php
// BEFORE (Lines 118-131):
for ($offset = 5; $offset >= 0; $offset--) {
    $start = $currentMonth->subMonths($offset);
    $end = $start->addMonth();
    $counts[] = (clone $scope)
        ->where('created_at', '>=', $start)
        ->where('created_at', '<', $end)
        ->count();
}

// AFTER (Proposed Grouped Query):
$sixMonthsAgo = $currentMonth->subMonths(5);

$monthlyData = (clone $scope)
    ->where('created_at', '>=', $sixMonthsAgo)
    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as count")
    ->groupBy('ym')
    ->pluck('count', 'ym')
    ->all();

for ($offset = 5; $offset >= 0; $offset--) {
    $start = $currentMonth->subMonths($offset);
    $key = $start->format('Y-m');

    $labels[] = $monthNames[$start->month] . ' ' . $start->format('Y');
    $counts[] = (int) ($monthlyData[$key] ?? 0);
}
```

#### Proposal 3: Caching Public Reference Data in `PublicReportController.php`

**Target File**: `app/Http/Controllers/PublicReportController.php`

```php
// AFTER (Proposed Cache Wrapping):
$classesByMajor = CacheHelper::remember('laporin:school_class:public_grouped', 3600, function () use ($majorOrder) {
    $classes = SchoolClass::where('is_active', true)->get()->sort(...);
    return $classes->groupBy(fn (SchoolClass $class) => strtoupper(trim((string) ($class->major ?: 'LAINNYA'))));
});

$subjects = CacheHelper::remember('laporin:subject:active_list', 3600, function () {
    return Subject::where('is_active', true)->orderBy('subject_name')->get();
});

$staffUnits = CacheHelper::remember('laporin:staff_unit:active_list', 3600, function () {
    return StaffUnit::where('is_active', true)->orderBy('unit_name')->get();
});

$locations = CacheHelper::remember('laporin:location:active_list', 3600, function () {
    return Location::where('is_active', true)->orderBy('location_name')->get();
});

$damageCategories = CacheHelper::remember('laporin:damage_category:active_list', 3600, function () {
    return DamageCategory::where('is_active', true)->orderBy('category_name')->get();
});
```

---

## 5. Verification Method

To independently verify these findings and future optimizations:

1. **Automated Test Suite**:
   ```bash
   php artisan test
   ```
   Must execute 100% PASS without regressions.

2. **Query Counter / Profiling Verification**:
   Inspect SQL query counts during request lifecycle (e.g. via `DB::enableQueryLog()` or Laravel Telescope/Debugbar):
   - **Dashboard Page (`/dashboard`)**:
     - *Baseline Query Count*: ~12+ queries (5 stats + 6 chart + 1 pagination).
     - *Target Query Count*: 3 queries on cache miss (1 stats + 1 chart + 1 pagination), 1 query on cache hit.
   - **Public Report Page (`/lapor`)**:
     - *Baseline Query Count*: ~6+ queries.
     - *Target Query Count*: 1 query on cache hit.

3. **Invalidation Verification**:
   - Create/update a report via public form or admin panel.
   - Verify `ReportObserver::clearCache()` triggers `CacheHelper::invalidate('laporin:report:*')`.
   - Verify dashboard statistics reflect the newly created report on next refresh.
