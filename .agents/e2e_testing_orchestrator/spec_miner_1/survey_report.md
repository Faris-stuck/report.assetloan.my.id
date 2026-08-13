# Comprehensive E2E Test Architecture & Codebase Survey Report

## Executive Summary
This survey establishes the complete technical specification and architectural foundation for the **LAPORIN High-Performance Optimization E2E Test Suite** (Tiers 1-4 + Performance Assertions). The survey covers all 8 optimization targets, security role authorization rules, database isolation mechanisms, existing test suite baseline failures, and test tier design.

---

## 1. Test Setup & Safety Compliance (AGENTS.md)

### Database & Cache Isolation
- **PHPUnit Config (`phpunit.xml`)**:
  - `DB_CONNECTION`: `sqlite`
  - `DB_DATABASE`: `:memory:`
  - `CACHE_STORE`: `array`
  - `SESSION_DRIVER`: `array`
  - `QUEUE_CONNECTION`: `sync`
- **Base Test Case Safety Check (`tests/TestCase.php`)**:
  - `setUp()` explicitly verifies `$app->environment() === 'testing'`.
  - Asserts `config('database.default') === 'sqlite'` and `config('database.connections.sqlite.database') === ':memory:'`.
  - Throws `RuntimeException` if any attempt is made to run tests against non-in-memory databases.
  - Automatically executes `Artisan::call('migrate')` in-memory.
- **AGENTS.md Safety Guarantee**:
  - Zero risk to production MySQL or Redis data.
  - All automated test runs (`php artisan test`) execute entirely in SQLite RAM.

### Baseline Test Execution Results
- Executed full `php artisan test` run (246 tests total across Unit and Feature suites).
- Result: **238 Passed, 8 Failed** (2010 assertions, 102.41s).
- **Key Pre-existing Performance Test Failures Identified**:
  1. `Milestone1EmpiricalNPlusOneTest > sarpras service damage report`: NOT NULL violation on `damage_details.item_name`.
  2. `Milestone1EmpiricalNPlusOneTest > report controller show eager loads`: Class `App\Models\ReportHistory` not found (correct model is `ReportStatusHistory`).
  3. `Milestone1EmpiricalNPlusOneTest > dashboard controller report list`: CHECK constraint violation on `users.role` (attempted setting role `admin`, allowed roles are `superadmin`, `kesiswaan`, `sarpras`, `wali_kelas`).
  4. `NPlusOneEliminationVerificationTest > admin service locations eager loads class`: `class_id` missing on location fixture.
  5. `NPlusOneEliminationVerificationTest > sarpras service index`: NOT NULL constraint on `damage_details.damage_condition`.
  6. `NPlusOneEliminationVerificationTest > dashboard controller`: Accessing dashboard report item relations triggered N+1 queries (5 queries logged).

---

## 2. Key Target Specifications & Survey Breakdown

| # | Target | Source Location | Route / Endpoint | Auth & Role Middleware | Required Eager Loading / Caching Contract | Verification Strategy |
|---|--------|-----------------|------------------|------------------------|-------------------------------------------|-----------------------|
| 1 | Master Data Locations | `AdminService::master()` | `GET /admin/master/locations` | `['auth', 'active', 'role:superadmin']` | `with('class')` | Query count log `DB::enableQueryLog()`, verify constant query count for N locations |
| 2 | Kesiswaan Violation List | `KesiswaanService::index()` | `GET /kesiswaan` | `['auth', 'active', 'role:kesiswaan']` | `with(['bullyingDetail.allegedActorClass', 'relatedClass', 'location', 'attachments'])` | Query count log with 0 vs 25+ violation items |
| 3 | Sarpras Damage List | `SarprasService::index()` | `GET /sarpras` | `['auth', 'active', 'role:sarpras']` | `with(['damageDetail', 'location', 'damageCategory', 'attachments'])` | Query count log with 0 vs 25+ damage items |
| 4 | Report Detail Nested Relation | `ReportController::show()` | `GET /reports/{report}` | `['auth', 'active']` + Policy Authorization | `load(['reporterClass', 'relatedClass', 'location', 'bullyingDetail.allegedActorClass', 'damageDetail', 'attachments', 'notes.user', 'histories'])` | Assert `bullyingDetail.allegedActorClass` loaded without secondary queries |
| 5 | Dashboard Report Stream | `DashboardController::__invoke()` | `GET /dashboard` | `['auth', 'active']` | `with(['relatedClass', 'location', 'bullyingDetail', 'damageDetail'])` | Query count constant across user roles (`superadmin`, `kesiswaan`, `sarpras`, `wali_kelas`) |
| 6 | Dashboard Summary Stats | `DashboardController::__invoke()` | `GET /dashboard` | `['auth', 'active']` | `CacheHelper::remember("laporin:dashboard:stats:{$userKey}", 300, ...)` + Grouped `COUNT` | Assert 0 DB queries on warm cache hit; verify invalidation on report creation |
| 7 | Dashboard Monthly Chart | `DashboardController::__invoke()` | `GET /dashboard` | `['auth', 'active']` | `CacheHelper::remember("laporin:dashboard:chart:{$userKey}", 300, ...)` + Grouped `COUNT BY ym` | Assert single grouped query on cold cache, 0 DB queries on warm cache |
| 8 | Public Reference Data | `PublicReportController::create()` | `GET /` / `GET /lapor/{qr?}` | None (Public) | `CacheHelper::remember` for `SchoolClass`, `Subject`, `StaffUnit`, `Location`, `DamageCategory` (TTL 3600s) | Assert 0 DB queries for reference data on warm cache hit |

---

## 3. Role-Based Access & Security Rules

| Role | Dashboard Scope | Access Rights | Forbidden Routes |
|------|-----------------|---------------|------------------|
| `superadmin` | All reports | Full admin management (`/admin/*`), user CRUD, audit logs, QR codes, master data | None |
| `kesiswaan` | Violation (`report_type = 'violation'`) | `/kesiswaan`, report process/reject/complete, report detail | `/admin/*`, `/sarpras` (403 Forbidden) |
| `sarpras` | Damage (`report_type = 'damage'`) | `/sarpras`, report process/reject, report detail | `/admin/*`, `/kesiswaan` (403 Forbidden) |
| `wali_kelas` | Violation reports belonging to assigned homeroom class (`related_class_id` in `homeroomClasses`) | `/dashboard`, report detail for class students, add notes | `/admin/*`, `/kesiswaan`, `/sarpras` (403 Forbidden) |
| Guest / Public | N/A | Submit reports (`/lapor`), track status (`/lacak`), view SEO guides | `/dashboard`, `/admin/*`, `/kesiswaan`, `/sarpras`, `/reports/*` (302 Redirect to `/login`) |

---

## 4. Model Relationships & Factories Matrix

### Models & Key Relations
- `Report`:
  - `reporterClass` (`belongsTo SchoolClass`)
  - `relatedClass` (`belongsTo SchoolClass`)
  - `location` (`belongsTo Location`)
  - `violationType` (`belongsTo ViolationType`)
  - `damageCategory` (`belongsTo DamageCategory`)
  - `bullyingDetail` (`hasOne BullyingDetail`)
  - `damageDetail` (`hasOne DamageDetail`)
  - `attachments` (`hasMany ReportAttachment`)
  - `notes` (`hasMany ReportNote`)
  - `histories` (`hasMany ReportStatusHistory`)
- `BullyingDetail`:
  - `report` (`belongsTo Report`)
  - `allegedActorClass` (`belongsTo SchoolClass`)
- `DamageDetail`:
  - `report` (`belongsTo Report`)
- `Location`:
  - `class` (`belongsTo SchoolClass`)

### Existing Seeders & Factories
- **Seeders**:
  - `DatabaseSeeder`: Creates standard test users (`admin@laporin.local`, `kesiswaan@laporin.local`, `sarpras@laporin.local`, `wali@laporin.local`), calls `TarunaBangsaClassSeeder`, creates default location, subject, staff unit, violation types, and damage categories.
  - `TarunaBangsaClassSeeder`: Generates 120 classes (Grades 10-12 across RPL, TKR, TITL, TAV).
- **Factories**:
  - `UserFactory`: Creates active/unverified users with roles.
  - `ReportFactory`: Generates random violation/damage reports with UUID tokens and access code hashes.
  - `DamageDetailFactory`: Generates damage details attached to reports.

---

## 5. E2E Test Suite Architecture & 4-Tier Test Coverage Plan

```
tests/Feature/E2E/
├── Tier1_FeatureCoverageTest.php
├── Tier2_BoundaryAndCornerCasesTest.php
├── Tier3_CrossFeatureLifecycleTest.php
├── Tier4_RealWorldWorkloadTest.php
└── Performance_QueryCountAssertionTest.php
```

### Tier 1: Feature Coverage (≥5 Test Cases per Feature)
- **Coverage**: End-to-end happy path validation for all 8 target endpoints.
- **Assertions**: HTTP status 200/302, view rendering, correct data structure, session states.

### Tier 2: Boundary & Corner Cases (≥5 Test Cases per Feature)
- **Coverage**: Edge cases such as empty datasets (0 reports/locations), page limits, missing optional relationships (report without bullying detail or attachments), unauthenticated access, role privilege escalation attempts.

### Tier 3: Cross-Feature & System Lifecycle (Pairwise Coverage)
- **Coverage**:
  1. Public report submission -> `ReportObserver` cache clearing -> immediate listing in Kesiswaan / Sarpras views.
  2. Kesiswaan report processing -> automatic student point reduction -> status history logging -> public tracking update.
  3. Role-based data isolation: Wali Kelas only seeing reports for their assigned homeroom class.

### Tier 4: Real-World Workload Scenarios
- **Coverage**: High-volume data sets (50+ reports across 4 majors and 10 locations), paginated listing performance, multi-user role browsing simulation.

### Tier 5 / Performance: Query Count & Cache Hit Assertions
- **Coverage**: Automated query counting via `DB::enableQueryLog()`:
  - List views (Admin, Kesiswaan, Sarpras, Dashboard) execute a constant O(1) number of queries.
  - Dashboard stats & chart execute 0 DB queries on warm cache hit.
  - Public form reference data executes 0 DB queries on warm cache hit.

---

## 6. Discovered Features & Edge Cases

### Features Discovered
| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|----------|---------|-------------|--------|---------|----------------|----------------|
| 1 | Admin | Master Data Locations Eager Loading | `AdminService::master('locations')` eager loads `class` relation | GET `/admin/master/locations` | View `admin.master.index` with `items` | 404 if invalid resource | Source survey |
| 2 | Kesiswaan | Violation List Eager Loading | `KesiswaanService::index()` eager loads `['bullyingDetail.allegedActorClass', 'relatedClass', 'location', 'attachments']` | GET `/kesiswaan` | View `kesiswaan.index` | 403 if non-kesiswaan role | Source survey |
| 3 | Sarpras | Damage List Eager Loading | `SarprasService::index()` eager loads `['damageDetail', 'location', 'damageCategory', 'attachments']` | GET `/sarpras` | View `sarpras.index` | 403 if non-sarpras role | Source survey |
| 4 | Reports | Detail Nested Relation Eager Loading | `ReportController::show()` loads `bullyingDetail.allegedActorClass` | GET `/reports/{id}` | View `reports.show` | 403 if user not authorized for report | Source survey |
| 5 | Dashboard | Report List Eager Loading | `DashboardController` eager loads `['relatedClass', 'location', 'bullyingDetail', 'damageDetail']` | GET `/dashboard` | View `dashboard.index` | 302 to login if guest | Source survey |
| 6 | Dashboard | Summary Stats Grouping & Caching | Grouped `COUNT` cached under `laporin:dashboard:stats:{userKey}` for 300s | GET `/dashboard` | Array of stats counts | Serves cached array if key exists | Source survey |
| 7 | Dashboard | Monthly Chart Grouping & Caching | Grouped `COUNT BY ym` cached under `laporin:dashboard:chart:{userKey}` for 300s | GET `/dashboard` | Array of labels & counts | Serves cached array if key exists | Source survey |
| 8 | Public Form | Reference Data Caching | Cached reference data (`SchoolClass`, `Subject`, `StaffUnit`, `Location`, `DamageCategory`) for 3600s | GET `/` or GET `/lapor/{qr}` | View `public.report-form` | Handles null QR gracefully | Source survey |
| 9 | Observers | Report Cache Invalidation | `ReportObserver`, `BullyingDetailObserver`, `DamageDetailObserver` trigger `CacheHelper::invalidate('laporin:report:*')` | Model create/update/delete | Cache invalidated | N/A | Source survey |
| 10 | Security | Last Superadmin Protection | `AdminService` prevents deactivating/deleting the last active superadmin | DELETE / PUT user | Flash error message | Throws `ValidationException` | Source survey |

### Edge Cases
| # | Feature | Input | Observed Behavior |
|---|---------|-------|-------------------|
| 1 | Kesiswaan Index | Filter by invalid status parameter | Ignored, fallback to all statuses without throwing error |
| 2 | Sarpras Index | Filter by priority when report has no `damageDetail` | Handled via `whereHas('damageDetail')`, returning 0 results safely |
| 3 | Dashboard | Legacy user role (e.g. `guru` or `siswa`) | Scoped query returns `1 = 0` (empty list, fail-closed security) |
| 4 | Public Form | Multiple tabs opened simultaneously | Each tab gets unique submit token & captcha in session `report_submit_forms` |
| 5 | Public Form | Form submission with expired or duplicate token | Throws `ValidationException` with message "Sesi formulir sudah habis..." |
