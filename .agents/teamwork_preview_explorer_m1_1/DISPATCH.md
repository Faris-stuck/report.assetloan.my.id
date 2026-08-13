## 2026-08-13T02:03:54Z
You are Explorer 1 for Milestone 1 (N+1 Query Elimination).
Your working directory is: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_explorer_m1_1

Mandatory Inputs:
- Read ORIGINAL_REQUEST.md at: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\ORIGINAL_REQUEST.md
- Read PROJECT.md at: c:\Users\azmia\Downloads\report.assetloan.my.id\PROJECT.md
- Read SCOPE.md at: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\sub_orch_m1_n1\SCOPE.md
- Read Survey Explorer Handoff at: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_explorer_survey_1\handoff.md

Objective:
Investigate and produce an exact, line-by-line implementation blueprint for the 5 target changes of Milestone 1:

1. AdminService::master() - Add with('class') when $resource === 'locations' in app/Services/Role/Superadmin/AdminService.php.
2. KesiswaanService::index() - Add eager loading with(['bullyingDetail.allegedActorClass', 'relatedClass', 'location', 'attachments']) in app/Services/Role/Kesiswaan/KesiswaanService.php.
3. SarprasService::index() - Expand eager loading with(['damageDetail', 'location', 'damageCategory', 'attachments']) in app/Services/Role/Sarpras/SarprasService.php.
4. ReportController::show() - Add 'bullyingDetail.allegedActorClass' to load() in app/Http/Controllers/ReportController.php.
5. DashboardController::__invoke() - Expand eager loading with(['relatedClass', 'location', 'bullyingDetail', 'damageDetail']) in app/Http/Controllers/DashboardController.php.

Instructions:
- Read the 5 files in detail.
- Examine line numbers, existing queries, and relations.
- Write your handoff report to: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_explorer_m1_1\handoff.md.
- Send a message to parent when done.
- DO NOT modify any source code. You are read-only.
