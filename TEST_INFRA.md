# E2E Test Infra: LAPORIN High-Performance Optimization

## Test Philosophy
- Opaque-box, requirement-driven end-to-end testing for LAPORIN application.
- Test Methodology: 4-Tier Testing (Category-Partition, Boundary Value Analysis, Pairwise Combinatorial, Real-World Workload Scenarios) plus explicit Performance Query Count Assertions.
- Strict compliance with `AGENTS.md` safety policies: zero destructive database/redis operations (no `migrate:fresh`, `db:wipe`, `FLUSHALL`, etc.).

## Feature Inventory & Test Matrix

| # | Feature | Source | Tier 1 (Feature Coverage) | Tier 2 (Boundary & Corner) | Tier 3 (Cross-Feature) | Tier 4 (Real-World) | Performance Assertions |
|---|---------|--------|:-------------------------:|:--------------------------:|:----------------------:|:-------------------:|:----------------------:|
| 1 | Master Data Locations Eager Loading | PROJECT.md #1 | 5 | 5 | ✓ | ✓ | Query Count Assertions |
| 2 | Kesiswaan Violation List Eager Loading | PROJECT.md #2 | 5 | 5 | ✓ | ✓ | Query Count Assertions |
| 3 | Sarpras Damage List Eager Loading | PROJECT.md #3 | 5 | 5 | ✓ | ✓ | Query Count Assertions |
| 4 | Report Detail Nested Relation Eager Loading | PROJECT.md #4 | 5 | 5 | ✓ | ✓ | Query Count Assertions |
| 5 | Dashboard Report List Eager Loading | PROJECT.md #5 | 5 | 5 | ✓ | ✓ | Query Count Assertions |
| 6 | Dashboard Summary Stats Grouping & Caching | PROJECT.md #6 | 5 | 5 | ✓ | ✓ | Query Count Assertions & Cache Hit Assertions |
| 7 | Dashboard Monthly Chart Grouping & Caching | PROJECT.md #7 | 5 | 5 | ✓ | ✓ | Query Count Assertions & Cache Hit Assertions |
| 8 | Public Reporting Reference Data Caching | PROJECT.md #8 | 5 | 5 | ✓ | ✓ | Query Count Assertions & Cache Hit Assertions |
| 9 | Administrative & Kesiswaan Reference Caching | PROJECT.md #9 | 5 | 5 | ✓ | ✓ | Query Count Assertions & Cache Hit Assertions |
| 10 | Security & Role Authorization Isolation | ORIGINAL_REQUEST R3 | 5 | 5 | ✓ | ✓ | Security Access & Data Isolation Assertions |
| 11 | Overall E2E Regression Suite | PROJECT.md #11 | 5 | 5 | ✓ | ✓ | Full `php artisan test` Suite Verification |

## Test Architecture & Tiers Breakdown

### Tier 1: Feature Coverage (≥5 test cases per feature)
- Direct functional tests for every endpoint and service method (Admin master, Kesiswaan index, Sarpras index, Report show, Dashboard invoke, Public reference data).
- Verifies HTTP status codes, correct data payloads, and rendered views under normal happy-path operation.

### Tier 2: Boundary & Corner Cases (≥5 test cases per feature)
- Edge cases: empty dataset list rendering, zero reports, max pagination limits, unauthenticated access, unauthorized role access, missing optional relationships (e.g. report without bullying detail or attachments).

### Tier 3: Cross-Feature Combinations (Pairwise Coverage)
- Interactions across features: report creation by public user -> observer cache invalidation -> immediate update reflected in Dashboard stats, Kesiswaan list, and Sarpras list.
- Role switching: Admin viewing Kesiswaan vs Sarpras reports, verifying role isolation and cache scoping.

### Tier 4: Real-World Application Workload Scenarios
- High-volume simulated workload: batch report creation across multiple locations, classes, categories, and dates.
- Concurrent read/write simulation: verifying cache consistency when multiple reports are created/updated while dashboard statistics and lists are queried repeatedly.

### Tier 5 / Performance: Query Count & Cache Hit Assertions
- Automated query counting using `DB::enableQueryLog()`:
  - List views (Kesiswaan, Sarpras, Admin, Dashboard) MUST execute a constant O(1) number of SQL queries regardless of record count (e.g. 50+ items loaded with constant 3-5 queries instead of N+1 queries).
  - Dashboard stats & chart queries MUST execute grouped queries (`GROUP BY` / conditional `COUNT`) and achieve 0 database queries on warm cache hit.
  - Public report reference data queries MUST execute 0 database queries on warm cache hit.

## Test Runner Invocation & Pass/Fail Criteria
- Runner Command: `php artisan test`
- Pass Criteria:
  1. 100% of test cases pass (0 failures, 0 errors).
  2. All performance query count assertions pass (no N+1 regressions detected).
  3. No destructive database or Redis commands are invoked during test execution.
