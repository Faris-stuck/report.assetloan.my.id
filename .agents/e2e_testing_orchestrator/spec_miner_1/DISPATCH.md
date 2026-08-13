## 2026-08-13T09:10:17Z
You are the E2E Test Architecture Explorer (spec miner) for LAPORIN High-Performance Optimization.
Working directory: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\e2e_testing_orchestrator\spec_miner_1

Mandatory Inputs:
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\ORIGINAL_REQUEST.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\PROJECT.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\AGENTS.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\TEST_INFRA.md

Task Objective:
Inspect the project test setup and codebase structure to prepare for writing the E2E Test Suite (Tiers 1-4 + Performance assertions):
1. Inspect `phpunit.xml`, `.env.testing` (if present), `tests/`, `routes/`, `app/Models/`, `database/factories/`, `database/seeders/`.
2. Check existing test files in `tests/Feature/` and `tests/Unit/`.
3. Check test database configuration in `phpunit.xml` to ensure test execution is safe and compliant with `AGENTS.md` (e.g. SQLite in-memory or DatabaseTransactions to avoid modifying/wiping production DB).
4. Survey exact route URLs, controller methods, roles, middleware, model relations, factories, and seeders needed for E2E tests across:
   - AdminService / master data locations (`with('class')`)
   - KesiswaanService / violation list (`with(['bullyingDetail.allegedActorClass', 'relatedClass', 'location', 'attachments'])`)
   - SarprasService / damage list (`with(['damageDetail', 'location', 'damageCategory', 'attachments'])`)
   - ReportController / show (`with('bullyingDetail.allegedActorClass')`)
   - DashboardController / invoke (`with(['relatedClass', 'location', 'bullyingDetail', 'damageDetail'])`)
   - DashboardController summary stats (grouped `COUNT` & `CacheHelper::remember`)
   - DashboardController monthly chart (grouped `COUNT BY ym` & `CacheHelper::remember`)
   - PublicReportController reference data (`SchoolClass`, `Subject`, `StaffUnit`, `Location`, `DamageCategory` cached)
5. Save your comprehensive survey report in `c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\e2e_testing_orchestrator\spec_miner_1\survey_report.md` and deliver your handoff report.
