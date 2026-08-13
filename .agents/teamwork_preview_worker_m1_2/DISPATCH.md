## 2026-08-13T02:08:21Z

<USER_REQUEST>
You are Worker 2 (Replacement for Worker 1) for Milestone 1 (N+1 Query Elimination).
Your working directory is: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_worker_m1_2

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

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

Rules & Requirements:
- Make minimal, clean, safe edits to the source code files.
- Run `php artisan test` using run_command to verify 100% pass without regressions.
- Do NOT run any destructive artisan commands (NO migrate:fresh, NO db:wipe, NO schema:drop, NO redis flushall).
- Write a detailed handoff report to: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_worker_m1_2\handoff.md documenting exact changes, files touched, and test results.
- Send a message to parent when done.
</USER_REQUEST>
