# Milestone 2 Explorer Handoff Report: Aggregate Stats Grouping & Caching

**Agent**: Explorer Agent (Milestone 2 — Aggregate Stats Grouping & Caching)  
**Target Project**: LAPORIN (Laravel / MySQL / Redis)  
**Date**: 2026-08-13  
**Working Directory**: `c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_explorer_m2_1`

---

## 1. Observation

Direct code examination of target files yielded the following findings:

### Task 1: Dashboard Summary Stats (`app/Http/Controllers/DashboardController.php`)
- **Location**: `app/Http/Controllers/DashboardController.php` (Lines 24–43)
- **Current Code**:
  ```php
  $userKey = $user->id . '_' . $user->role;
  $stats = \App\Helpers\CacheHelper::remember(
      "laporin:dashboard:stats:{$userKey}",
      300,
      fn () => [
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
      ]
  );
  ```
- **Analysis**: When a cache miss occurs, **5 separate `COUNT(*)` SQL queries** are dispatched sequentially to the database to build `$stats`. Key format is `$userKey` (`user_{id}_role_{role}`).

### Task 2: Dashboard Monthly Chart (`app/Http/Controllers/DashboardController.php`)
- **Location**: `app/Http/Controllers/DashboardController.php` (Lines 45–49 & Lines 104–160)
- **Current Code**:
  ```php
  // Lines 45-49:
  $chart = \App\Helpers\CacheHelper::remember(
      "laporin:dashboard:chart:{$userKey}",
      300,
      fn () => $this->monthlyChart($scope, $user)
  );

  // Lines 129-142 in monthlyChart():
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
- **Analysis**: `monthlyChart()` runs a 6-iteration loop executing **6 separate `COUNT(*)` queries** to get monthly counts for the last 6 months.

### Task 3: Public Reporting Reference Data (`app/Http/Controllers/PublicReportController.php`)
- **Location**: `app/Http/Controllers/PublicReportController.php` (Lines 161–188)
- **Current Code**:
  ```php
  $majorOrder = array_flip(array_keys(self::CLASS_MAJOR_LABELS));
  $classes = SchoolClass::where('is_active', true)->get()->sort(...);
  $classesByMajor = $classes->groupBy(...);

  return view('public.report-form', [
      'qrCode' => $qrCode,
      'reportSubmitToken' => $submitToken,
      'captchaQuestion' => "$a + $b",
      'classesByMajor' => $classesByMajor,
      'classMajorLabels' => self::CLASS_MAJOR_LABELS,
      'subjects' => Subject::where('is_active', true)->orderBy('subject_name')->get(),
      'staffUnits' => StaffUnit::where('is_active', true)->orderBy('unit_name')->get(),
      'locations' => Location::where('is_active', true)->orderBy('location_name')->get(),
      'damageCategories' => DamageCategory::where('is_active', true)->orderBy('category_name')->get(),
  ]);
  ```
- **Analysis**: Every visit to the public form (`/lapor`) triggers **5 uncached reference queries** (`SchoolClass`, `Subject`, `StaffUnit`, `Location`, `DamageCategory`).

### Task 4: Administrative & Kesiswaan Reference Caching (`AdminService.php` & `KesiswaanService.php`)
- **Locations**:
  - `app/Services/Role/Superadmin/AdminService.php` (Lines 135–137 & Line 233)
  - `app/Services/Role/Kesiswaan/KesiswaanService.php` (Line 52)
- **Current Code**:
  - `AdminService.php` (Line 135): `'activeSuperadminCount' => User::where('role', 'superadmin')->where('is_active', true)->count()`
  - `AdminService.php` (Line 233): `'actions' => AuditLog::distinct()->pluck('action')`
  - `KesiswaanService.php` (Line 52): `'types' => ViolationType::where('is_active', true)->get()`
- **Analysis**: These reference and summary count queries are currently uncached.

### Helper & Observer Capabilities
- `CacheHelper` (`app/Helpers/CacheHelper.php`) supports `remember()`, `invalidate()`, and `invalidateTags()`. `invalidate()` relies on SCAN for Redis.
- `ReportObserver` (`app/Observers/ReportObserver.php`) executes `CacheHelper::invalidate('laporin:report:*')`. Adding `CacheHelper::invalidate('laporin:dashboard:*')` ensures dashboard stats and chart caches clear immediately when reports are mutated.

---

## 2. Logic Chain

1. **Task 1 (Dashboard Stats Grouping)**:
   - *Observation*: 5 separate `COUNT(*)` queries run on `$scope`.
   - *Reasoning*: Using SQL conditional aggregation (`COUNT(CASE WHEN ... THEN 1 END)`), all 5 metrics (`total`, `violation`, `damage`, `pending`, `done`) can be calculated in a single database query.
   - *Conclusion*: Replace 5 queries with 1 `selectRaw` query and wrap in `CacheHelper::remember` with key `laporin:dashboard:stats:user_{id}_role_{role}` (TTL 300s). Query reduction: 5 → 1 on cache miss, 0 on hit.

2. **Task 2 (Dashboard Monthly Chart Grouping)**:
   - *Observation*: 6 loop queries run for the last 6 months.
   - *Reasoning*: Querying reports from 5 months ago with `selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as aggregate_count")` and `groupBy('ym')` collects all monthly counts in 1 query. Map results in PHP loop by key `'Y-m'`.
   - *Conclusion*: Replace 6 loop queries with 1 `GROUP BY` query and wrap in `CacheHelper::remember` with key `laporin:dashboard:chart:user_{id}_role_{role}` (TTL 300s). Query reduction: 6 → 1 on cache miss, 0 on hit.

3. **Task 3 (Public Reference Caching)**:
   - *Observation*: Static dropdown lists (`SchoolClass`, `Subject`, `StaffUnit`, `Location`, `DamageCategory`) are fetched uncached on every public report page load.
   - *Reasoning*: Master data changes infrequently. Wrapping each lookup in `CacheHelper::remember` (TTL 3600s) eliminates 5 queries per public request.
   - *Conclusion*: Cache `SchoolClass` grouped list (`laporin:school_class:public_grouped`), `Subject` (`laporin:subject:active_list`), `StaffUnit` (`laporin:staff_unit:active_list`), `Location` (`laporin:location:active_list`), and `DamageCategory` (`laporin:damage_category:active_list`).

4. **Task 4 (Admin & Kesiswaan Reference Caching)**:
   - *Observation*: `activeSuperadminCount`, `audit log actions`, and `violation types` are fetched uncached.
   - *Reasoning*: Static/semi-static administrative data can be safely cached with appropriate TTLs (600s for superadmin count, 3600s for audit log actions and violation types).
   - *Conclusion*: Wrap queries using `CacheHelper::remember`.

5. **Observer Synchronization**:
   - *Observation*: `ReportObserver::clearCache()` currently invalidates `laporin:report:*`.
   - *Reasoning*: Dashboard cache keys start with `laporin:dashboard:*`. Adding `CacheHelper::invalidate('laporin:dashboard:*')` to `ReportObserver` guarantees instant invalidation when reports are added, modified, or deleted.

---

## 3. Caveats

- **Scoped Key Isolation**: Dashboard stats and charts are user-role and user-ID scoped. Keys MUST strictly use `user_{id}_role_{role}` format (e.g. `laporin:dashboard:stats:user_{id}_role_{role}`) to prevent cross-user data leaking, especially for `wali_kelas` users who only see reports for their assigned classes.
- **Null Safety in Aggregate Query**: `selectRaw` with conditional `COUNT` returns 0 for empty counts, but array casting `(int) ($rawStats->total ?? 0)` ensures strict integer types expected by views.
- **Non-Destructive Invalidation**: `CacheHelper::invalidate()` uses SCAN pattern matching on Redis and does NOT execute `FLUSHALL` or `FLUSHDB`, adhering 100% to `AGENTS.md`.

---

## 4. Conclusion & Copy-Pasteable Code Proposals

### Summary of Changes

| Task | Target File | Line(s) | Proposed Solution | TTL | Cache Key |
|---|---|---|---|---|---|
| 1 | `DashboardController.php` | 24–43 | 1 conditional `COUNT(CASE WHEN ...)` query | 300s | `laporin:dashboard:stats:user_{$user->id}_role_{$user->role}` |
| 2 | `DashboardController.php` | 45–49, 104–160 | 1 `selectRaw` `GROUP BY ym` query | 300s | `laporin:dashboard:chart:user_{$user->id}_role_{$user->role}` |
| 3 | `PublicReportController.php` | 161–188 | `CacheHelper::remember` for 5 reference data collections | 3600s | `laporin:school_class:public_grouped`, `laporin:subject:active_list`, etc. |
| 4 | `AdminService.php` | 135–137, 233 | `CacheHelper::remember` for superadmin count & audit actions | 600s / 3600s | `laporin:admin:active_superadmin_count`, `laporin:audit_log:actions` |
| 4 | `KesiswaanService.php` | 52 | `CacheHelper::remember` for active violation types | 3600s | `laporin:violation_type:active_list` |
| Hook | `ReportObserver.php` | 36–43 | Add `CacheHelper::invalidate('laporin:dashboard:*')` | N/A | Invalidation hook |

---

### Copy-Pasteable Code Snippets

#### 1. `app/Http/Controllers/DashboardController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $scope = $this->scopedReports($user);

        $reports = (clone $scope)
            ->with(['relatedClass', 'location', 'bullyingDetail', 'damageDetail'])
            ->latest()
            ->paginate(12);

        $userKey = "user_{$user->id}_role_{$user->role}";

        $stats = CacheHelper::remember(
            "laporin:dashboard:stats:{$userKey}",
            300,
            function () use ($scope) {
                $rawStats = (clone $scope)
                    ->selectRaw("
                        COUNT(*) as total,
                        COUNT(CASE WHEN report_type = 'violation' THEN 1 END) as violation,
                        COUNT(CASE WHEN report_type = 'damage' THEN 1 END) as damage,
                        COUNT(CASE WHEN status = 'menunggu_verifikasi' THEN 1 END) as pending,
                        COUNT(CASE WHEN status = 'selesai' THEN 1 END) as done
                    ")
                    ->first();

                return [
                    'total' => (int) ($rawStats->total ?? 0),
                    'violation' => (int) ($rawStats->violation ?? 0),
                    'damage' => (int) ($rawStats->damage ?? 0),
                    'pending' => (int) ($rawStats->pending ?? 0),
                    'done' => (int) ($rawStats->done ?? 0),
                ];
            }
        );

        $chart = CacheHelper::remember(
            "laporin:dashboard:chart:{$userKey}",
            300,
            fn () => $this->monthlyChart($scope, $user)
        );

        return view('dashboard.index', [
            'reports' => $reports,
            'stats' => $stats,
            'chart' => $chart,
        ]);
    }

    private function scopedReports(User $user): Builder
    {
        $query = Report::query();

        if ($user->isRole('superadmin')) {
            return $query;
        }

        if ($user->isRole('kesiswaan')) {
            return $query->where(
                'report_type',
                'violation'
            );
        }

        if ($user->isRole('sarpras')) {
            return $query->where(
                'report_type',
                'damage'
            );
        }

        if ($user->isRole('wali_kelas')) {
            $classIds = $user
                ->homeroomClasses()
                ->pluck('class_id');

            return $query
                ->where('report_type', 'violation')
                ->whereIn('related_class_id', $classIds);
        }

        return $query->whereRaw('1 = 0');
    }

    private function monthlyChart(
        Builder $scope,
        User $user
    ): array {
        $monthNames = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        $labels = [];
        $counts = [];

        $currentMonth = CarbonImmutable::now()
            ->startOfMonth();

        $sixMonthsAgo = $currentMonth->subMonths(5);

        $monthlyCounts = (clone $scope)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as aggregate_count")
            ->groupBy('ym')
            ->pluck('aggregate_count', 'ym')
            ->all();

        for ($offset = 5; $offset >= 0; $offset--) {
            $start = $currentMonth->subMonths($offset);
            $ymKey = $start->format('Y-m');

            $labels[] =
                $monthNames[$start->month]
                .' '
                .$start->format('Y');

            $counts[] = (int) ($monthlyCounts[$ymKey] ?? 0);
        }

        $titles = [
            'superadmin' => 'Semua Laporan 6 Bulan Terakhir',
            'kesiswaan' => 'Laporan Perundungan 6 Bulan Terakhir',
            'sarpras' => 'Laporan Kerusakan 6 Bulan Terakhir',
            'wali_kelas' => 'Laporan Kelas Terkait 6 Bulan Terakhir',
        ];

        return [
            'title' =>
                $titles[$user->role]
                ?? 'Laporan 6 Bulan Terakhir',

            'labels' => $labels,
            'counts' => $counts,
            'max' => max(1, ...$counts),
        ];
    }
}
```

---

#### 2. `app/Http/Controllers/PublicReportController.php` (Lines 161–188 Replacement)

```php
        // Replace lines 161-188 in PublicReportController::create():

        $majorOrder = array_flip(array_keys(self::CLASS_MAJOR_LABELS));

        $classesByMajor = CacheHelper::remember('laporin:school_class:public_grouped', 3600, function () use ($majorOrder) {
            $classes = SchoolClass::where('is_active', true)->get()->sort(function (SchoolClass $left, SchoolClass $right) use ($majorOrder): int {
                $leftMajor = strtoupper(trim((string) ($left->major ?: 'LAINNYA')));
                $rightMajor = strtoupper(trim((string) ($right->major ?: 'LAINNYA')));
                $leftRank = $majorOrder[$leftMajor] ?? PHP_INT_MAX;
                $rightRank = $majorOrder[$rightMajor] ?? PHP_INT_MAX;

                return ($leftRank <=> $rightRank)
                    ?: strnatcasecmp($leftMajor, $rightMajor)
                    ?: strnatcasecmp((string) $left->grade_level, (string) $right->grade_level)
                    ?: strnatcasecmp($left->class_name, $right->class_name);
            });

            return $classes->groupBy(
                fn (SchoolClass $class): string => strtoupper(trim((string) ($class->major ?: 'LAINNYA')))
            );
        });

        $subjects = CacheHelper::remember('laporin:subject:active_list', 3600, fn () =>
            Subject::where('is_active', true)->orderBy('subject_name')->get()
        );

        $staffUnits = CacheHelper::remember('laporin:staff_unit:active_list', 3600, fn () =>
            StaffUnit::where('is_active', true)->orderBy('unit_name')->get()
        );

        $locations = CacheHelper::remember('laporin:location:active_list', 3600, fn () =>
            Location::where('is_active', true)->orderBy('location_name')->get()
        );

        $damageCategories = CacheHelper::remember('laporin:damage_category:active_list', 3600, fn () =>
            DamageCategory::where('is_active', true)->orderBy('category_name')->get()
        );

        return view('public.report-form', [
            'qrCode' => $qrCode,
            'reportSubmitToken' => $submitToken,
            'captchaQuestion' => "$a + $b",
            'classesByMajor' => $classesByMajor,
            'classMajorLabels' => self::CLASS_MAJOR_LABELS,
            'subjects' => $subjects,
            'staffUnits' => $staffUnits,
            'locations' => $locations,
            'damageCategories' => $damageCategories,
        ]);
```

---

#### 3. `app/Services/Role/Superadmin/AdminService.php` (Target Chunks)

```php
// Add import at top of file:
use App\Helpers\CacheHelper;

// In users() method (Lines 131-139 replacement):
        return view('admin.users.index', [
            'users' => $query->latest()->paginate(20),
            'roles' => User::ROLES,

            'activeSuperadminCount' => CacheHelper::remember(
                'laporin:admin:active_superadmin_count',
                600,
                fn () => User::where('role', 'superadmin')
                    ->where('is_active', true)
                    ->count()
            ),
        ]);

// In audit() method (Lines 231-235 replacement):
        return view('admin.audit', [
            'logs' => $query->latest()->paginate(30),
            'actions' => CacheHelper::remember(
                'laporin:audit_log:actions',
                3600,
                fn () => AuditLog::distinct()->pluck('action')
            ),
        ]);
```

---

#### 4. `app/Services/Role/Kesiswaan/KesiswaanService.php` (Target Chunk)

```php
// Add import at top of file:
use App\Helpers\CacheHelper;

// In index() method (Lines 49-53 replacement):
        return view('kesiswaan.index', [
            'reports' => $query->latest()->paginate(15),
            'students' => Student::with('class')->orderBy('name')->get(),
            'types' => CacheHelper::remember(
                'laporin:violation_type:active_list',
                3600,
                fn () => ViolationType::where('is_active', true)->get()
            ),
        ]);
```

---

#### 5. `app/Observers/ReportObserver.php` (Invalidation Chunk Update)

```php
    private function clearCache(): void
    {
        CacheHelper::invalidateTags([
            'report',
            'location',
        ]);

        CacheHelper::invalidate('laporin:report:*');
        CacheHelper::invalidate('laporin:dashboard:*');
    }
```

---

## 5. Verification Method

1. **Automated Test Verification**:
   Execute the full test suite to verify zero regressions:
   ```bash
   php artisan test
   ```
   Must pass 100% (236+ tests).

2. **Query Profiling Verification**:
   - Open `/dashboard` on cold cache: observe 3 database queries (1 pagination + 1 stats + 1 chart).
   - Refresh `/dashboard` on warm cache: observe 1 database query (pagination only; 0 queries for stats & charts).
   - Refresh `/lapor` on warm cache: observe 0 queries for reference dropdown data.

3. **Observer Invalidation Verification**:
   - Submit a new public report or mutate a report via admin/kesiswaan.
   - Verify `ReportObserver::clearCache()` triggers `CacheHelper::invalidate('laporin:dashboard:*')`.
   - Refresh dashboard: statistics and chart update immediately.
