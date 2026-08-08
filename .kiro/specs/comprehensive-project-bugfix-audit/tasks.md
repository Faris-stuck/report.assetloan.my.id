# Implementation Tasks: Comprehensive Bugfix

## Overview

This task list implements all fixes for data persistence, session management, query caching, and UI/UX improvements identified in the bugfix requirements and design documents.

**Total Estimated Effort**: 30-40 hours  
**Dependencies**: PHP 8.3, Laravel 12, Redis (or fallback to database), Docker (for testing)  
**Testing**: Property-based tests, feature tests, integration tests, load tests

---

## Phase 1: Session Infrastructure (2-3 hours)

### 1.1 Verify Sessions Table Migration
- [x] Open `database/migrations/0001_01_01_000000_create_users_table.php`
- [x] Confirm migration exists with sessions table schema
- [x] Check columns: id, user_id, ip_address, user_agent, payload, last_activity
- [x] Verify indexes on user_id and last_activity
- [x] Mark complete when migration verified

### 1.2 Run Database Migration
- [~] Execute: `php artisan migrate --force`
- [~] Verify no errors during execution
- [~] Confirm sessions table created: `php artisan tinker` → `Schema::hasTable('sessions')`
- [~] Verify table structure: `Schema::getColumns('sessions')`

### 1.3 Configure Redis Connection in .env
- [~] Open `.env` file
- [~] Add/update: `REDIS_HOST=redis`, `REDIS_PORT=6379`, `REDIS_PASSWORD=null`, `REDIS_DB=0`
- [~] Add: `REDIS_CACHE_DB=1`
- [~] Verify values match your environment (localhost for dev, redis for Docker)

### 1.4 Configure Session Driver
- [ ] Open `.env` file
- [~] Set: `SESSION_DRIVER=redis`
- [~] Set: `SESSION_LIFETIME=120`
- [~] Set: `SESSION_SECURE_COOKIE=true`
- [~] Set: `SESSION_HTTP_ONLY=true`
- [~] Set: `SESSION_SAME_SITE=strict`

### 1.5 Test Redis Connection
- [~] Execute: `php artisan tinker`
- [~] Run: `Redis::ping()` → Should return "PONG"
- [~] Run: `Redis::set('test_key', 'test_value')`
- [~] Run: `Redis::get('test_key')` → Should return 'test_value'

### 1.6 Test Session Creation on Login
- [~] Create test user: `User::factory()->create(['email' => 'test@test.com'])`
- [~] POST to `/login` with valid credentials
- [~] Verify session created in Redis: `Redis::keys('*')` should show session key
- [~] Verify accessible on next request

### 1.7 Test Session Lifecycle
- [~] Login → verify authenticated
- [~] Access protected route → verify session persists
- [~] Logout → verify session destroyed
- [~] Session should auto-expire after 120 minutes (test with reduced TTL)

**Status**: [ ] Complete | [ ] Blocked

---

## Phase 2: Query Caching Architecture (3-4 hours)

### 2.1 Create CacheableQuery Trait
- [~] Create file: `app/Traits/CacheableQuery.php`
- [~] Implement: `cacheKey()` static method for consistent key generation
- [~] Format: `laporin:entity:action:params_hash`
- [~] Implement: `cacheTag()` static method for cache tag generation

### 2.2 Create Cache Wrapper Functions
- [~] Verify `app/Helpers/CacheHelper.php` exists
- [~] Add methods: `remember()`, `get()`, `invalidate()`, `invalidateTag()`
- [~] Implement graceful Redis fallback (try Redis, fallback to database)
- [~] Add error logging for cache failures

### 2.3 Define TTL Strategy
- [~] Update or create `config/cache.php` with TTL settings
- [~] Master data (locations, categories, etc): 86400 seconds (24 hours)
- [~] Reports list: 3600 seconds (1 hour)
- [~] Report details: 1800 seconds (30 minutes)
- [~] User profiles: 1800 seconds (30 minutes)

### 2.4 Test Cache Operations
- [~] Execute: `Cache::put('test', 'value', 3600)`
- [~] Execute: `Cache::get('test')` → Should return 'value'
- [~] Execute: `Cache::forget('test')`
- [~] Verify cache working via Redis: `redis-cli keys "*"`

**Status**: [ ] Complete | [ ] Blocked

---

## Phase 3: Cache Invalidation via Model Observers (2-3 hours)

### 3.1 Create ReportObserver
- [~] Create file: `app/Observers/ReportObserver.php`
- [~] Implement `created()`, `updated()`, `deleted()`, `forceDeleted()` methods
- [~] Each method flushes tags: `Cache::tags('reports', 'locations')->flush()`
- [~] Register observer in AppServiceProvider

### 3.2 Create DamageDetailObserver
- [~] Create file: `app/Observers/DamageDetailObserver.php`
- [~] Implement CRUD observer methods
- [~] Flush tags: `Cache::tags('damagedetails', 'reports')->flush()`

### 3.3 Create BullyingDetailObserver
- [~] Create file: `app/Observers/BullyingDetailObserver.php`
- [ ] Implement CRUD observer methods
- [~] Flush tags: `Cache::tags('bullyingdetails', 'reports')->flush()`

### 3.4 Register Observers in AppServiceProvider
- [~] Open `app/Providers/AppServiceProvider.php`
- [~] Add to `boot()` method: Observer registrations
- [~] Verify all three observers registered

### 3.5 Test Cache Invalidation
- [~] Create damage report → verify cache cleared
- [~] Update report → verify cache cleared
- [~] Delete report → verify cache cleared
- [~] Next query should show new data immediately

**Status**: [ ] Complete | [ ] Blocked


---

## Phase 4: Controller-Level Query Caching (2 hours)

### 4.1 Implement Caching in ReportController
- [~] Open `app/Http/Controllers/ReportController.php`
- [~] Update `index()`: Cache report list with 3600s TTL
- [~] Update `show()`: Cache individual report with 1800s TTL
- [~] Use eager loading: `->with('location', 'violationType', 'damageDetails')`
- [~] Verify no cache on create/update/delete endpoints

### 4.2 Implement Caching in PublicReportController
- [~] Open `app/Http/Controllers/PublicReportController.php`
- [~] Cache public reports list (longer TTL: 86400s)
- [~] Verify form submission endpoints NOT cached

### 4.3 Implement Caching for Master Data
- [~] Create `app/Services/MasterDataService.php` (if not exists)
- [~] Cache locations, damage categories, violation types (24h TTL)
- [~] Use in controllers/views via `MasterDataService::getLocations()`

### 4.4 Test Controller Caching
- [~] Load reports list page → measure first load (cache miss <100ms)
- [~] Reload same page → measure second load (cache hit <50ms)
- [~] Verify cache hit rate >80% with repeated loads

**Status**: [ ] Complete | [ ] Blocked

---

## Phase 5: Priority Field Fix (1-2 hours)

### 5.1 Modify PublicReportService
- [x] Open `app/Services/PublicReport/PublicReportService.php`
- [~] Find priority initialization logic (line ~56)
- [~] Replace: `'priority' => $validated['priority'] ?? $validated['urgency']`
- [~] With: `'priority' => null,  // Initially NULL - Sarpras sets independently`
- [~] Add comment explaining the change

### 5.2 Verify SarprasProcessor
- [~] Open `app/Services/Role/Sarpras/SarprasProcessor.php`
- [~] Verify priority updates work independently
- [~] Confirm no logic ties priority to urgency

### 5.3 Test Priority Independence
- [~] Create damage report with urgency='darurat'
- [~] Query database: verify `damage_detail.priority = NULL`
- [~] Login as Sarpras user
- [~] Process report: set priority='tinggi'
- [~] Verify priority and urgency display independently

**Status**: [ ] Complete | [ ] Blocked

---

## Phase 6: Form Data Persistence (3-4 hours)

### 6.1 Implement Alpine.js Form State
- [~] Open `resources/views/public/report-form.blade.php`
- [~] Add `x-data` with formData object storing all steps' data
- [~] Structure: `{ step1: {...}, step2: {...}, step3: {...}, step4: {...} }`
- [~] Persist form state in localStorage for session recovery

### 6.2 Bind Form Fields to State
- [~] Update all form inputs with `x-model="formData[currentStep][fieldName]"`
- [~] Update all textareas with `x-model`
- [~] Update all selects with `x-model`
- [~] Verify initial values load from formData

### 6.3 Preserve Data Across Step Navigation
- [~] Next button: save current step data before navigation
- [~] Back button: restore previous step's data from formData
- [~] Validate only current step (not all steps)

### 6.4 Restore Data After Validation Error
- [~] On validation error: display error alert but KEEP data
- [~] User can click "Lanjut" again to retry
- [~] formData should preserve all entries during retry

### 6.5 Submit All Steps' Data
- [~] Form submission POST entire formData as array
- [~] Backend validates complete request (not step-by-step)
- [~] Backend creates single report with all data

### 6.6 Test Form Data Persistence
- [~] Fill form steps 1-3 correctly
- [~] Leave step 4 field empty
- [~] Click "Lanjut" → validation error appears
- [~] Verify all steps' data still visible/preserved
- [~] Fill missing field, retry submit → should succeed
- [~] Test on mobile (375px) and desktop (1024px)

**Status**: [ ] Complete | [ ] Blocked

---

## Phase 7: Form Layout Stability & Error Display (2-3 hours)

### 7.1 Fix Conditional Field Layout
- [ ] Open `resources/views/public/report-form.blade.php`
- [~] Find conditional fields (x-show directives)
- [~] Wrap in div: `<div x-show="condition" x-transition>`
- [~] Add CSS: Prevent layout shift with `display: contents` or `display: none`

### 7.2 Update Error Display to Alert-Danger
- [~] Find `.invalid-step-hint` or error container
- [~] Replace with Bootstrap `.alert alert-danger`
- [~] Add icon: `<i class="fas fa-exclamation-circle"></i>`
- [~] Include field label in error message

### 7.3 Implement Auto-Scroll to Error
- [~] On validation error: scroll error into view
- [~] Use `scrollIntoView({ behavior: 'smooth', block: 'center' })`
- [~] Delay 100ms to allow DOM render

### 7.4 Test Layout Stability
- [~] Fill step 2: select reporter_type='siswa'
- [~] Kelas field appears (no layout jump)
- [~] Change to reporter_type='pegawai'
- [~] Kelas field disappears smoothly
- [~] Content below doesn't jump position
- [~] Test on mobile: scroll position preserved

### 7.5 Test Error Display
- [~] Leave required field empty
- [~] Click "Lanjut"
- [~] Error displays in red alert box with icon
- [~] Error message clear and visible
- [~] Error scrolls into viewport center
- [~] Test on mobile and desktop

**Status**: [ ] Complete | [ ] Blocked

---

## Phase 8: UI/UX Desktop Optimization (2 hours)

### 8.1 Add Desktop Media Query
- [~] Create or update `public/css/custom.css`
- [~] Add media query: `@media (min-width: 1024px) { ... }`
- [~] Set form padding: 32px (2rem)
- [~] Set modal body padding: 32px
- [~] Set table cell padding: 12px vertical × 16px horizontal

### 8.2 Update Form Container Padding
- [~] Find `.wizard-panel` or main form container
- [~] Update classes: `p-3 p-md-4 p-lg-5` (or similar responsive)
- [~] Or add CSS rule in media query

### 8.3 Update Modal Padding
- [~] Find `.modal-body` in admin modals
- [~] Update classes or add CSS rule for 32px padding on desktop

### 8.4 Update Button Alignment
- [~] Buttons at bottom: ensure 44px minimum height
- [~] Vertically center with input fields
- [~] Use flexbox alignment if needed

### 8.5 Test Desktop Layout
- [~] View form on 1024px viewport → verify 32px padding
- [~] View form on 1366px viewport → padding still 32px
- [~] View on 768px tablet → should use smaller padding
- [~] View on 375px mobile → should use 16px (unchanged)

**Status**: [ ] Complete | [ ] Blocked

---

## Phase 9: Component Consistency (2-3 hours)

### 9.1 Standardize Button Styling
- [~] Audit all pages: admin users, master data, kesiswaan, sarpras
- [~] Primary action buttons: ALL use `.btn-laporin` (green)
- [~] Secondary action buttons: ALL use `.btn-outline-secondary` (grey)
- [~] Danger action buttons: ALL use `.btn-outline-danger` (red)
- [~] Row action buttons: `.btn-sm` for consistent sizing

### 9.2 Standardize Form Component Styling
- [~] All form inputs: `.form-control` class
- [~] All labels: `.form-label` with `for` attribute
- [~] All required fields: include `<span class="text-danger">*</span>`
- [~] All form grids: use `row g-3` for spacing
- [~] All errors: display in `.invalid-feedback` div

### 9.3 Standardize Modal Styling
- [~] All modals: use same header styling (title + helper text)
- [~] All modals: button order Batal (left) → Simpan (right)
- [~] All modals: same padding and spacing
- [~] All modals: focus trap on Tab key, close on Escape

### 9.4 Create Consistency Documentation
- [~] Document all button patterns and classes
- [~] Document form component patterns
- [~] Document modal patterns
- [~] Create `.md` checklist for future development

**Status**: [ ] Complete | [ ] Blocked

---

## Phase 10: Monitoring & Performance Verification (2 hours)

### 10.1 Create CacheMetricsService
- [~] Create file: `app/Services/CacheMetricsService.php`
- [~] Implement: `getHitRate()`, `getMemoryUsage()`, `getConnectedClients()`
- [~] Implement: `logMetrics()` for logging to file

### 10.2 Create Health Check Middleware
- [~] Create file: `app/Http/Middleware/CheckRedisHealth.php`
- [~] Test Redis connection: `Redis::ping()`
- [~] Log warnings if Redis unavailable
- [~] Allow application to continue (graceful fallback)

### 10.3 Verify Cache Hit Rate
- [~] Run load test: 100+ repeated requests to reports page
- [~] Measure cache hit rate: `CacheMetricsService::getHitRate()`
- [~] Target: >80% hit rate
- [~] Document baseline metrics

### 10.4 Verify Query Performance
- [~] Measure cached query response: should be <50ms
- [~] Measure database query response (cache miss): should be <100ms
- [~] Measure for: reports list, reports detail, master data
- [~] Document performance improvements

**Status**: [ ] Complete | [ ] Blocked

---

## Phase 11: Testing & Validation (4-6 hours)

### 11.1 Write Property-Based Tests
- [~] Test session persistence across requests
- [~] Test session timeout after 120 minutes
- [~] Test priority field NULL initialization
- [~] Test form data persistence across steps
- [~] Test cache hit rate >80%
- [~] Test cache invalidation on create/update/delete

### 11.2 Write Feature Tests
- [~] Test login → authentication works
- [~] Test report creation → all data persists
- [~] Test priority update → independent from urgency
- [~] Test form submission with errors → data preserved
- [~] Test form navigation → conditional fields stable

### 11.3 Write Integration Tests
- [~] Test full report workflow: create → process → track
- [~] Test concurrent users (50+): load stable
- [~] Test cache invalidation doesn't break queries
- [~] Test Redis failover: graceful database fallback

### 11.4 Run Test Suite
- [~] Execute: `php artisan test`
- [~] Verify: 0 failures, 0 errors
- [~] Verify: no console warnings (except expected deprecations)

### 11.5 Docker Build & CI/CD
- [~] Execute: `npm run test:docker`
- [~] Verify: Docker image builds successfully
- [~] Verify: All tests pass in Docker environment
- [~] Verify: CI/CD pipeline passes (if GitHub Actions configured)

### 11.6 Performance Benchmarking
- [~] Load test: 50 concurrent users for 5 minutes
- [~] Measure: Average response time, error rate, cache hit rate
- [~] Target: <150ms average response, <1% errors, >80% cache hits
- [~] Compare: Before/after performance improvement

**Status**: [ ] Complete | [ ] Blocked

---

## Phase 12: Documentation & Deployment (2 hours)

### 12.1 Update .env.example
- [~] Add: `REDIS_HOST=redis`, `REDIS_PORT=6379`, etc.
- [~] Add: `SESSION_DRIVER=redis`, `SESSION_LIFETIME=120`, etc.
- [~] Add: `CACHE_STORE=redis`, `CACHE_PREFIX=laporin_`
- [~] Include placeholders: `[REDACTED]` for sensitive values

### 12.2 Update Documentation
- [~] Create: `docs/CONSISTENCY_CHECKLIST.md`
- [~] Document button patterns, form patterns, modal patterns
- [~] Create: `docs/FUTURE_PAGES_IMPLEMENTATION_GUIDE.md`
- [~] Include code snippets and examples

### 12.3 Update AGENTS.md (if needed)
- [~] Document new commands: `php artisan cache:warm` (if added)
- [~] Document new tests: `php artisan test --filter=Cache*`
- [~] Document deployment steps

### 12.4 Create Deployment Checklist
- [~] Migrations completed
- [~] Redis service running
- [~] .env configured
- [~] All tests passing
- [~] Performance metrics acceptable
- [~] Documentation updated
- [~] Team trained

**Status**: [ ] Complete | [ ] Blocked

---

## Verification Checkpoint

### ✅ All Phase 1-7 Complete
- [~] Sessions table created and working
- [~] Redis configured and connected
- [~] Query caching implemented and hit rate >80%
- [~] Cache invalidation on CRUD working
- [~] Priority field NULL on creation
- [~] Form data persists across steps
- [~] Layout stable and errors prominent
- [~] Desktop UI optimized

### ✅ All Tests Passing
- [~] Unit tests: 0 failures
- [~] Feature tests: 0 failures
- [~] Integration tests: 0 failures
- [~] Property-based tests: 0 failures
- [~] Docker build: Successful
- [~] CI/CD pipeline: Passing
- [~] Load test: <150ms average, >80% cache hits

### ✅ Performance Improved
- [~] Query response time: <50ms (cache), <100ms (db)
- [~] Page load time: 60-70% improvement
- [~] Database CPU: <20% with 50 concurrent users
- [~] Cache hit rate: >80%

### ✅ No Regressions
- [~] Authentication workflow unchanged
- [~] Authorization checks unchanged
- [~] Report submission unchanged
- [~] Mobile experience unchanged
- [~] Public pages unchanged
- [~] API responses unchanged

### ✅ Deployment Ready
- [~] Backup created
- [~] Rollback plan documented
- [~] Team aware of changes
- [~] Monitoring configured
- [~] Performance baseline established

---

## Success Criteria

This comprehensive bugfix is successful when:

✅ **Data Persistence Bugs Fixed**
- Priority field is NULL on creation, set independently by Sarpras
- Form data persists across all steps and validation attempts
- Layout stable when conditional fields toggle

✅ **Session Management Working**
- Sessions table created and indexes configured
- Users stay logged in across requests and server restarts
- Session timeout works correctly (120 minutes)

✅ **Query Caching Implemented**
- Cache hit rate >80%
- Cached queries complete in <50ms
- Database queries complete in <100ms (cache miss)

✅ **Cache Invalidation Working**
- Cache cleared immediately on create/update/delete
- Users always see fresh data
- No stale data issues

✅ **UI/UX Improved**
- Validation errors prominent and clearly visible
- Step tracker touch-friendly (44px+ targets)
- Desktop layout properly spaced (32px padding)
- Form layout stable during field toggling

✅ **Component Consistency Established**
- All buttons styled consistently
- All forms styled consistently
- All modals styled consistently

✅ **Performance Optimized**
- Page load time improved 60-70%
- Database load reduced 60-70%
- System handles 50+ concurrent users

✅ **No Regressions**
- All existing workflows continue working
- All existing tests pass
- Mobile experience unchanged
- Public interface unchanged

✅ **Tests Passing**
- 0 test failures
- >80% code coverage
- Performance benchmarks met
- Load test successful

---

**Last Updated**: 2025-01-01  
**Status**: Ready for Implementation  
**Co-Authored-By**: Hermes Agent <noreply@nousresearch.com>

