## 2026-08-13T09:05:00Z
You are a teamwork_preview_spec_miner assigned to survey the test infrastructure and feature specifications for LAPORIN High-Performance Optimization.
Your working directory is: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_spec_miner_e2e_survey

Mandatory Inputs:
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\ORIGINAL_REQUEST.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\PROJECT.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\AGENTS.md

Task:
1. Examine the test runner setup (`phpunit.xml`, `tests/TestCase.php`, factories, seeders, test environment configuration).
2. List all existing test files in `tests/Unit` and `tests/Feature`.
3. Analyze the routes, controllers, services, and views related to the 10 features in `PROJECT.md § Feature Inventory`:
   - AdminService::master() ($resource === 'locations')
   - KesiswaanService::index()
   - SarprasService::index()
   - ReportController::show()
   - DashboardController::__invoke()
   - PublicReportController reference data
   - Administrative & Kesiswaan reference data
   - Aggregates/Stats/Charts caching & grouping
4. Check available Model Factories (`database/factories/`) and Seeders (`database/seeders/`) for creating test data without touching production DB.
5. Check how authentication/roles are handled in tests (e.g., Sanctum/session/User roles).
6. Write a comprehensive handoff report to `c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_spec_miner_e2e_survey\handoff.md`.

Do NOT write or modify application source code or run destructive database commands! Respect AGENTS.md policy.
