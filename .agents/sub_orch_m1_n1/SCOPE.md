# Scope: Milestone 1 — N+1 Query Elimination

## Architecture
- Target files: `AdminService.php`, `KesiswaanService.php`, `SarprasService.php`, `ReportController.php`, `DashboardController.php`.
- Objective: Add Eager Loading (`with(...)` / `load(...)`) to eliminate all N+1 queries when fetching model relations in list queries and views.

## Feature Inventory
| # | Feature | Description | File |
|---|---------|-------------|------|
| 1 | Master Data Locations Eager Loading | Add `$query->with('class')` when `$resource === 'locations'` | `app/Services/Role/Superadmin/AdminService.php` |
| 2 | Kesiswaan Violation List Eager Loading | Add `.with(['bullyingDetail.allegedActorClass', 'relatedClass', 'location', 'attachments'])` | `app/Services/Role/Kesiswaan/KesiswaanService.php` |
| 3 | Sarpras Damage List Eager Loading | Add `.with(['damageDetail', 'location', 'damageCategory', 'attachments'])` | `app/Services/Role/Sarpras/SarprasService.php` |
| 4 | Report Detail Nested Relation Eager Loading | Add `'bullyingDetail.allegedActorClass'` to `load(...)` | `app/Http/Controllers/ReportController.php` |
| 5 | Dashboard Report List Eager Loading | Add `['relatedClass', 'location', 'bullyingDetail', 'damageDetail']` to `with(...)` | `app/Http/Controllers/DashboardController.php` |

## Verification Criteria
- `php artisan test` passes 100% (236+ tests).
- Zero N+1 query warnings / fallbacks.
- Strictly adhere to AGENTS.md policy (NO destructive database/Redis operations).
