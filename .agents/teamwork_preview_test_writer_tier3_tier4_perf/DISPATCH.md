## 2026-08-13T02:10:31Z
You are a teamwork_preview_test_writer assigned to write Tier 3 (Cross-Feature Combinations), Tier 4 (Real-World Application Workloads), and Performance Query Count Assertion test suites for LAPORIN High-Performance Optimization.
Your working directory is: c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_test_writer_tier3_tier4_perf

Mandatory Inputs:
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\ORIGINAL_REQUEST.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\PROJECT.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\AGENTS.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\TEST_INFRA.md
- Read c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_spec_miner_e2e_survey\handoff.md

Tasks:
1. Create `tests/Feature/E2E/Tier3_CrossFeatureInteractionTest.php`:
   - Tests cross-feature workflows: public report submission -> observer cache invalidation -> immediate reflection in Kesiswaan, Sarpras, and Dashboard.
   - Admin master updates -> reference cache invalidation -> public form dropdown updates.
   - Role switching & cache isolation across superadmin, kesiswaan, sarpras, and wali_kelas.
2. Create `tests/Feature/E2E/Tier4_RealWorldScenarioTest.php`:
   - End-to-end multi-role lifecycle (Public submit -> Kesiswaan process -> Sarpras process -> Wali kelas view -> Admin audit).
   - High-volume simulated workload with 50+ batch created reports, verifying pagination, stats counts, and charts without errors.
3. Create `tests/Feature/Performance/PerformanceQueryCountAssertionTest.php`:
   - Uses `DB::enableQueryLog()` to assert query counts:
     - Constant O(1) query count for list views regardless of item count (assert query log count <= threshold).
     - Warm cache hit assertions: stats, chart, and public reference data execute 0 database queries on second call.
4. Run `php artisan test` to verify 100% test pass rate across the full test suite.
5. Write handoff report to `c:\Users\azmia\Downloads\report.assetloan.my.id\.agents\teamwork_preview_test_writer_tier3_tier4_perf\handoff.md`.
