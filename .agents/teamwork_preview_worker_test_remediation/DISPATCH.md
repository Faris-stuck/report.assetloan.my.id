## DISPATCH
Target Directory: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_worker_test_remediation
Role: teamwork_preview_worker
Task: Fix the 34 test failures across tests/Feature/E2E/ and tests/Feature/Performance/.

Mandatory Inputs:
- c:\Users\azmia\Downloads\report.assetloan.my.id\ORIGINAL_REQUEST.md
- c:\Users\azmia\Downloads\report.assetloan.my.id\PROJECT.md
- c:\Users\azmia\Downloads\report.assetloan.my.id\AGENTS.md
- c:\Users\azmia\Downloads\report.assetloan.my.id\TEST_INFRA.md
- c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_reviewer_e2e_verification_2\handoff.md

Failure Fix Instructions:
1. Route & View Names:
   - Correct view names: 'admin.master.index', 'kesiswaan.index', 'sarpras.index', 'reports.show', 'dashboard.index', 'public.report-form', 'admin.users.index'.
   - Correct route names: 'admin.master.index' (not 'admin.master').
   - Correct view keys: Admin master uses 'items' (not 'locations').
2. Database Field/Enum Constraints in Test Factories/Helpers:
   - `AuditLog::create([...])`: include `actor_type => 'App\\Models\\User'` or similar valid class string.
   - `ViolationType::create([...])`: include `point_reduction => 10`.
   - `Report::create(['reporter_type' => ...])`: use valid reporter_type enum (e.g. 'siswa', 'guru', 'orang_tua', 'masyarakat').
   - User role enums: use 'superadmin' (not 'admin').
   - `DamageDetail::create([...])`: include required `damage_condition => 'rusak_ringan'`.
3. Check and update tests/Feature/E2E/* and tests/Feature/Performance/* so that `php artisan test` runs with 100% PASS rate.
4. Run `php artisan test` and verify 0 failures and 0 errors.
