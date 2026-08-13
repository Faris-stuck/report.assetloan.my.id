# Scope: Milestone 2 — Aggregate Stats Grouping & Caching

## Architecture
- Target files: `DashboardController.php`, `PublicReportController.php`, `AdminService.php`, `KesiswaanService.php`.
- Objective: Collapse repetitive aggregate queries using conditional `COUNT(CASE WHEN ...)` and `GROUP BY`, and wrap aggregate/reference queries in `CacheHelper::remember(...)` with appropriate TTLs.

## Feature Inventory
| # | Feature | Description | File |
|---|---------|-------------|------|
| 6 | Dashboard Summary Stats Grouping & Caching | Replace 5 `COUNT(*)` queries with 1 conditional `COUNT(CASE WHEN ...)` query and wrap in `CacheHelper::remember` (TTL 300s, key formatted with user ID and role) | `app/Http/Controllers/DashboardController.php` |
| 7 | Dashboard Monthly Chart Grouping & Caching | Replace 6 monthly loop `COUNT(*)` queries with 1 `selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as count")->groupBy('ym')` query and wrap in `CacheHelper::remember` (TTL 300s) | `app/Http/Controllers/DashboardController.php` |
| 8 | Public Reporting Reference Data Caching | Cache `SchoolClass`, `Subject`, `StaffUnit`, `Location`, `DamageCategory` in `PublicReportController` using `CacheHelper::remember` (TTL 3600s) | `app/Http/Controllers/PublicReportController.php` |
| 9 | Administrative & Kesiswaan Reference Caching | Cache active superadmin count (TTL 600s), audit log actions (TTL 3600s), and active violation types (TTL 3600s) | `app/Services/Role/Superadmin/AdminService.php`, `app/Services/Role/Kesiswaan/KesiswaanService.php` |

## Caching & Invalidation Rules
- All caching must use `CacheHelper::remember(string $key, int $ttl, Closure $callback)`.
- Dashboard cache keys must incorporate user ID and role (e.g. `laporin:dashboard:stats:user_{id}_role_{role}`).
- Verify that existing `ReportObserver` invalidates `laporin:report:*` on report mutations so dashboard cache stays synchronized.
- Strictly adhere to `AGENTS.md` policy: NO `FLUSHALL` / `FLUSHDB`, only SCAN-based `CacheHelper::invalidate()`.

## Verification Criteria
- `php artisan test` passes 100% (236+ tests).
- Dashboard aggregate queries reduced from 11 down to 2 on cache miss, 0 DB queries on cache hit.
- Public report reference queries cached on hit.
- Role authorization and data isolation intact.
