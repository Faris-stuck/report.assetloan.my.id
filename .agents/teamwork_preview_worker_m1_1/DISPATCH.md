## 2026-08-13T02:06:23Z
Worker 1 for Milestone 1 (N+1 Query Elimination) task received.

Mandatory Inputs:
- Read ORIGINAL_REQUEST.md at: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\ORIGINAL_REQUEST.md
- Read PROJECT.md at: c:\Users\azmia\Downloads\report.assetloan.my.id\PROJECT.md
- Read SCOPE.md at: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\sub_orch_m1_n1\SCOPE.md
- Read Explorer Handoff at: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_explorer_m1_1\handoff.md

Task:
Implement the 5 eager loading changes specified in the Explorer handoff:
1. app/Services/Role/Superadmin/AdminService.php:
   In AdminService::master(), add `$query->with('class');` when `$resource === 'locations'`.
2. app/Services/Role/Kesiswaan/KesiswaanService.php:
   In KesiswaanService::index(), add eager loading:
   `->with(['bullyingDetail.allegedActorClass', 'relatedClass', 'location', 'attachments'])` to `Report::where('report_type', 'violation')`.
3. app/Services/Role/Sarpras/SarprasService.php:
   In SarprasService::index(), expand eager loading to:
   `->with(['damageDetail', 'location', 'damageCategory', 'attachments'])` to `Report::where('report_type', 'damage')`.
4. app/Http/Controllers/ReportController.php:
   In ReportController::show(), add `'bullyingDetail.allegedActorClass'` to `$report->load(...)`.
5. app/Http/Controllers/DashboardController.php:
   In DashboardController::__invoke(), expand eager loading to:
   `->with(['relatedClass', 'location', 'bullyingDetail', 'damageDetail'])`.
