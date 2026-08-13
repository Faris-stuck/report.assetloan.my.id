## 2026-08-13T09:22:05Z
You are the Tier 3, 4 & Performance Test Writer (Gen 2) for LAPORIN High-Performance Optimization.
Working directory: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\e2e_testing_orchestrator\test_writer_2_gen2

Mandatory Inputs:
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\ORIGINAL_REQUEST.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\PROJECT.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\AGENTS.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\TEST_INFRA.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\e2e_testing_orchestrator\spec_miner_1\survey_report.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Task Objective:
Create authentic end-to-end tests for Tier 3 (Cross-Feature Lifecycle), Tier 4 (Real-World Workload Scenarios), and Performance Query Count Assertions:
1. Create `tests/Feature/E2E/Tier3_CrossFeatureLifecycleTest.php`:
   - Test pairwise cross-feature integration: public report submission -> observer cache invalidation -> immediate update in Dashboard stats & list views; Kesiswaan report processing -> student point reduction -> status history logging; Wali Kelas homeroom class data isolation vs Superadmin.
2. Create `tests/Feature/E2E/Tier4_RealWorldWorkloadTest.php`:
   - Test real-world application scenarios: high-volume multi-major datasets with 50+ reports across RPL, TKR, TITL, TAV majors and 10 locations; paginated browsing; concurrent read/write cache consistency.
3. Create `tests/Feature/E2E/Performance_QueryCountAssertionTest.php`:
   - Performance query count & cache hit assertion tests using `DB::enableQueryLog()`:
     * Assert list views (Admin locations, Kesiswaan index, Sarpras index, Report show, Dashboard list) execute constant O(1) SQL queries (same query count for 50+ items vs 0 items).
     * Assert Dashboard stats & chart execute grouped queries (GROUP BY / conditional COUNT) and 0 DB queries on warm cache hit.
     * Assert Public report form reference data executes 0 DB queries on warm cache hit.
     * Assert ReportObserver invalidates dashboard cache keys upon report creation.

Verification:
- Run `php artisan test --filter=Tier3_CrossFeatureLifecycleTest`, `php artisan test --filter=Tier4_RealWorldWorkloadTest`, and `php artisan test --filter=Performance_QueryCountAssertionTest`.
- Ensure 100% PASS with zero errors or regressions.
- Deliver your handoff report in `c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\e2e_testing_orchestrator\test_writer_2_gen2\handoff.md`.
