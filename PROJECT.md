# Project: LAPORIN High-Performance Optimization

## Architecture
- Framework: Laravel (PHP 8.2+), DB: MySQL, Cache/Session: Redis.
- Architecture: MVC with Role-Based Service Layer (`app/Services/Role/*`) and Observers (`app/Observers/*`).
- Key Controllers: `DashboardController`, `PublicReportController`, `ReportController`, `AdminService`, `KesiswaanService`, `SarprasService`.
- Key Models & Relations: `Report`, `BullyingDetail`, `DamageDetail`, `SchoolClass`, `Subject`, `StaffUnit`, `Location`, `DamageCategory`, `User`.
- Caching System: `CacheHelper` (Redis SCAN invalidation) + `CacheableQuery` trait + Observers.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | Master Data Locations Eager Loading | Add `with('class')` to `AdminService::master()` when `$resource === 'locations'` | M1 | survey |
| 2 | Kesiswaan Violation List Eager Loading | Add `.with(['bullyingDetail.allegedActorClass', 'relatedClass', 'location', 'attachments'])` to `KesiswaanService::index()` | M1 | survey |
| 3 | Sarpras Damage List Eager Loading | Add `.with(['damageDetail', 'location', 'damageCategory', 'attachments'])` to `SarprasService::index()` | M1 | survey |
| 4 | Report Detail Nested Relation Eager Loading | Add `'bullyingDetail.allegedActorClass'` to `ReportController::show()` eager loads | M1 | survey |
| 5 | Dashboard Report List Eager Loading | Add `['relatedClass', 'location', 'bullyingDetail', 'damageDetail']` to `DashboardController::__invoke()` | M1 | survey |
| 6 | Dashboard Summary Stats Grouping & Caching | Replace 5 `COUNT(*)` queries with 1 conditional `COUNT(CASE WHEN ...)` and wrap in `CacheHelper::remember` (TTL 300s) | M2 | survey |
| 7 | Dashboard Monthly Chart Grouping & Caching | Replace 6 monthly loop `COUNT(*)` queries with 1 `GROUP BY ym` query and wrap in `CacheHelper::remember` (TTL 300s) | M2 | survey |
| 8 | Public Reporting Reference Data Caching | Wrap `SchoolClass`, `Subject`, `StaffUnit`, `Location`, `DamageCategory` in `CacheHelper::remember` (TTL 3600s) in `PublicReportController` | M2 | survey |
| 9 | Administrative & Kesiswaan Reference Caching | Cache active superadmin count, audit log actions, and active violation types | M2 | survey |
| 10 | Query Count Assertion & Regression Tests | Create automated query count assertion tests (`DB::enableQueryLog()`) for list views & dashboard cache hits | M3 | survey |
| 11 | Complete E2E Suite & Security Verification | Verify 100% test pass rate (`php artisan test`), role security, and AGENTS.md compliance | M4 | survey |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | N+1 Query Elimination | Add Eager Loading (`with(...)`) across Admin, Kesiswaan, Sarpras, Report detail, and Dashboard list queries | none | DONE |
| 2 | Aggregate Stats Grouping & Caching | Refactor Dashboard stats/chart queries into grouped queries (`GROUP BY` / conditional `COUNT`) and wrap in `CacheHelper::remember` | M1 | PLANNED |
| 3 | Performance & Query Count Test Suite | Add query count assertion tests and cache invalidation verification tests | M2 | PLANNED |
| 4 | Final E2E Pass & Hardening | Run 100% E2E test verification, security role verification, and forensic audit | M3 | PLANNED |

## Interface Contracts
### Controllers ↔ Models / Cache
- `CacheHelper::remember(string $key, int $ttl, Closure $callback)`: Must be used for caching query results.
- `CacheHelper::invalidate(string $pattern)`: Must be triggered on report mutations (`laporin:report:*`).
- `ReportObserver`: Automatically invalidates report cache keys on create/update/delete.
- Eager Loading (`with(...)` / `load(...)`): Must match exact blade view property access chains.

## Code Layout
- `app/Services/Role/Superadmin/AdminService.php`: Master data queries.
- `app/Services/Role/Kesiswaan/KesiswaanService.php`: Violation report list queries.
- `app/Services/Role/Sarpras/SarprasService.php`: Damage report list queries.
- `app/Http/Controllers/ReportController.php`: Single report view queries.
- `app/Http/Controllers/DashboardController.php`: Dashboard list, stats, and monthly chart queries.
- `app/Http/Controllers/PublicReportController.php`: Public form reference data.
- `tests/Feature/Performance/`: Query count & performance assertion test files.
