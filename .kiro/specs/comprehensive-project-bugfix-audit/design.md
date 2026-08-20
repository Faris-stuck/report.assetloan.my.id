# Comprehensive Bugfix Design Document

## Design Overview

This design implements fixes for 5 major bug categories affecting the LAPORIN application:
1. **Data Persistence & Form Issues** - Fix priority coupling, form data loss, layout instability
2. **Session Management** - Implement sessions table, configure Redis session driver
3. **Query Caching & Performance** - Reduce N+1 queries, implement cache invalidation
4. **UI/UX Improvements** - Enhanced error display, touch-friendly navigation, desktop optimization
5. **Component Consistency** - Standardize button, form, and modal styling

---

## Technical Context

### Stack Summary
- **Backend**: Laravel 12 on PHP 8.3
- **Frontend**: Blade templates with Bootstrap 5, Alpine.js, Vite, Tailwind tokens
- **Database**: MariaDB/MySQL (production), SQLite (tests)
- **Cache**: Redis (to be implemented)
- **Runtime**: Docker (image: laporin-app:*)

### Current State
- **Existing Bugs**: 4 partial specs (session-race-condition, form-data-persistence, database-performance, consistency-audit)
- **Partially Implemented**: Session refactoring started, form validation partially addressed
- **Performance Baseline**: Database queries ~300-400ms for list pages, no caching, no session persistence

### Deployment Target
- Production: report.assetloan.my.id
- Environment: Docker network (cf-network), Redis service available
- Constraints: Zero downtime requirement, backward compatibility with existing reports

---

## Design Decisions

### 1. Cache Strategy: Redis + Database Fallback

**Decision**: Use Redis as primary cache with graceful database fallback

**Rationale**:
- Redis provides <50ms cache hits vs <100ms database misses
- Predis/phpredis client works with or without PHP extension
- Graceful fallback maintains uptime if Redis unavailable
- Existing cached data automatically invalidated via Model Observers

**Implementation**:
- Cache driver: `redis` (primary), fallback to `database` if Redis down
- Session driver: `redis` for production, `database` for fallback
- TTL strategy: Master data (24h), Reports (1-6h), User data (30min)
- Cache key format: `laporin:entity:action:params_hash`

---

### 2. Form Data Persistence: Client-Side Alpine.js Store

**Decision**: Store form state in Alpine.js component (not server session)

**Rationale**:
- Preserves all field values during multi-step navigation
- Survives validation errors without server round-trip
- Clear separation of concerns (client vs server)
- Simpler than session management, faster than database

**Implementation**:
- Alpine.js `x-data` stores current step data and all previous steps
- Form fields bound to `x-model="formData[step][fieldName]"`
- On validation error: display alert, keep data, allow retry
- On submit: POST all steps' data to backend as single request
- On success: clear form state, show confirmation

---

### 3. Priority Field Initialization: NULL by Default

**Decision**: Initialize `damage_detail.priority = NULL` on creation, set independently by Sarpras

**Rationale**:
- Urgency (from public form) ≠ Priority (Sarpras assessment)
- NULL indicates priority not yet assessed (safe default)
- Sarpras staff explicitly sets priority during process modal
- Backward compatible with existing data (existing priorities preserved)

**Implementation**:
- `PublicReportService.create()`: Remove priority fallback logic
- Migration: Ensure priority column allows NULL (default NULL if not set)
- `SarprasProcessor.process()`: Update priority independently from urgency
- Tracking page: Display both urgency and priority separately

---

### 4. Session Infrastructure: Database Table + Redis Driver

**Decision**: Create sessions table (Laravel standard), use Redis driver for performance

**Rationale**:
- Sessions table migration already defined in Laravel 12 breeze
- Redis persistence better for high-concurrency scenarios
- Database fallback available if Redis fails
- No custom session handlers needed

**Implementation**:
- Run existing migration: `php artisan migrate --force` (creates sessions table)
- Configure `.env`: `SESSION_DRIVER=redis`
- Configure Redis: `REDIS_HOST=redis`, `REDIS_PORT=6379`, `REDIS_DB=0`
- Session TTL: 120 minutes (7200 seconds)
- Session security: HTTP_ONLY=true, SECURE=true, SAME_SITE=strict

---

### 5. Layout Stability: CSS Transitions + Display Logic

**Decision**: Use CSS `display: contents` or careful `display: none` with transitions

**Rationale**:
- `display: contents` removes element from layout without hiding
- Prevents layout shift when hiding conditional fields
- Smooth transitions via Alpine.js `x-transition`
- Minimal CSS changes, maximum compatibility

**Implementation**:
- Conditional fields wrapped in `<div x-show="condition" x-transition>`
- CSS: `[x-show] { display: block; } [x-show="false"] { display: none; }`
- Animations: 200ms fade in/out transition
- Prevents "jump" effect when fields appear/disappear

---

### 6. Error Display: Bootstrap Alert Component

**Decision**: Use Bootstrap `.alert-danger` with icon and auto-scroll

**Rationale**:
- High contrast (red background, white text) meets WCAG AA
- Icon (⚠️) draws attention visually
- Auto-scroll ensures error visible without manual navigation
- Bootstrap-native, no external dependencies

**Implementation**:
- Error container: `<div class="alert alert-danger">` with icon
- Error message includes field name/label for clarity
- jQuery/Alpine `scrollIntoView()` after error rendered
- Dismissible via close button or form resubmit

---

### 7. Touch-Friendly Targets: 44px Minimum

**Decision**: Enforce 44px × 44px minimum for all interactive elements

**Rationale**:
- WCAG AA accessibility requirement
- Reduces accidental mis-taps on mobile
- Industry standard (Apple iOS, Android Material)

**Implementation**:
- Step dots: `min-width: 44px; min-height: 44px;`
- Buttons: `min-height: 44px; height: 44px;`
- Form fields: Default Bootstrap height adequate (≥40px)
- Tab targets: Enforce 44px padding for keyboard/touch

---

### 8. Desktop Optimization: Responsive Media Query

**Decision**: Add 1024px+ media query for desktop-specific padding/spacing

**Rationale**:
- Mobile-first approach: existing mobile style is baseline
- Desktop users benefit from increased whitespace
- Doesn't affect mobile or tablet layouts
- Simple CSS addition, no JavaScript needed

**Implementation**:
- Media query: `@media (min-width: 1024px) { ... }`
- Form padding: 16px (mobile) → 32px (desktop)
- Modal body padding: 16px (mobile) → 32px (desktop)
- Table cell padding: 8px 12px (mobile) → 12px 16px (desktop)

---

### 9. Cache Invalidation: Model Observers + Listeners

**Decision**: Use Laravel Model Observers to trigger cache clearing on CRUD events

**Rationale**:
- Automatic invalidation on model changes (no manual cache clearing)
- Observer pattern decouples cache logic from business logic
- Scales well as model count increases
- Existing Laravel 12 feature, no external packages

**Implementation**:
- Create Observers: `ReportObserver`, `DamageDetailObserver`, `BullyingDetailObserver`
- Observer methods: `created()`, `updated()`, `deleted()`, `forceDeleted()`
- Each observer flushes relevant cache tags: `Cache::tags('reports', 'locations')->flush()`
- Register in `AppServiceProvider::boot()`

---

### 10. Performance Monitoring: CacheMetricsService

**Decision**: Implement lightweight metrics tracking without external APM tools

**Rationale**:
- Minimal dependencies (uses Redis INFO command)
- Real-time cache hit rate visibility
- Early warning system for performance degradation
- Can integrate with future monitoring stack

**Implementation**:
- Service class: `CacheMetricsService` with static methods
- Methods: `getHitRate()`, `getMemoryUsage()`, `getConnectedClients()`, `logMetrics()`
- Data source: Redis INFO stats (keyspace_hits, keyspace_misses)
- Integration: Call from middleware or scheduled command

---

## Architecture Changes

### New Files to Create

```
app/
├── Traits/
│   └── CacheableQuery.php              [Cache key generation]
├── Helpers/
│   └── CacheHelper.php                 [Existing, enhance with Redis]
├── Services/
│   ├── CacheMetricsService.php         [Metrics tracking]
│   └── PublicReport/
│       ├── PublicReportService.php     [Modify: Priority NULL fix]
│       └── ...existing...
├── Observers/
│   ├── ReportObserver.php              [Cache invalidation]
│   ├── DamageDetailObserver.php
│   └── BullyingDetailObserver.php
└── Http/
    └── Middleware/
        └── CheckRedisHealth.php        [Health check]

config/
├── cache.php                           [Modify: TTL strategy]
└── database.php                        [Verify: Redis config]

database/migrations/
└── 0001_01_01_000000_create_users_table.php [Verify: sessions table]

resources/views/
└── public/
    └── report-form.blade.php           [Modify: Layout, error display]
```

### Files to Modify

```
app/
├── Http/Controllers/
│   ├── PublicReportController.php      [Form data handling]
│   ├── ReportController.php            [Query caching]
│   └── ...others with caching...
├── Models/
│   ├── Report.php                      [Observers, relationships]
│   ├── DamageDetail.php
│   ├── BullyingDetail.php
│   └── User.php
└── Providers/
    └── AppServiceProvider.php          [Register observers]

resources/views/
├── public/
│   ├── report-form.blade.php           [Form state, error display]
│   └── success.blade.php               [No changes expected]
├── admin/
│   └── users/index.blade.php           [Desktop padding]
└── ...modal styling...                 [Consistency updates]

.env.example                            [Add Redis config]
```

---

## Implementation Phases

### Phase 1: Session Infrastructure (2-3 hours)
1. Verify sessions table migration exists
2. Run database migration (`php artisan migrate --force`)
3. Configure Redis connection (`.env`, `config/database.php`)
4. Configure session driver (`SESSION_DRIVER=redis`)
5. Test session creation/retrieval/destruction

**Files**: Database migrations, `.env`, `config/database.php`

### Phase 2: Query Caching Architecture (3-4 hours)
1. Create CacheableQuery trait
2. Create CacheHelper functions
3. Define TTL strategy in config
4. Create cache wrapper functions
5. Test cache hit/miss performance

**Files**: `app/Traits/CacheableQuery.php`, `app/Helpers/CacheHelper.php`, `config/cache.php`

### Phase 3: Cache Invalidation Setup (2-3 hours)
1. Create Model Observers (Report, DamageDetail, BullyingDetail)
2. Register observers in AppServiceProvider
3. Test automatic cache invalidation on create/update/delete
4. Verify no race conditions

**Files**: `app/Observers/*.php`, `app/Providers/AppServiceProvider.php`

### Phase 4: Controller-Level Caching (2 hours)
1. Apply caching to read endpoints
2. Verify no cache on write endpoints
3. Test cache hit rate >80%
4. Performance testing

**Files**: Controller files, service files

### Phase 5: Priority Field Fix (1-2 hours)
1. Modify PublicReportService: Priority NULL initialization
2. Verify SarprasProcessor handles independent updates
3. Test migration: existing priorities unchanged
4. Verify no regression in tracking/processing

**Files**: `app/Services/PublicReport/PublicReportService.php`

### Phase 6: Form Data Persistence (3-4 hours)
1. Implement Alpine.js form state store
2. Bind form fields to x-model
3. Preserve data across step navigation
4. Restore data after validation error
5. Test mobile and desktop

**Files**: `resources/views/public/report-form.blade.php`

### Phase 7: Form Layout Stability & Error Display (2-3 hours)
1. Fix conditional field layout with CSS
2. Update error display to alert-danger
3. Implement auto-scroll to error
4. Test layout stability during field toggling
5. Test on mobile (375px) and desktop (1024px+)

**Files**: `resources/views/public/report-form.blade.php`, CSS files

### Phase 8: UI/UX Desktop Optimization (2 hours)
1. Add media query for 1024px+ breakpoint
2. Update form padding (16px → 32px)
3. Update modal padding
4. Update table cell padding
5. Test desktop layout (1024px, 1366px)

**Files**: CSS files, `resources/views/` various

### Phase 9: Component Consistency (2-3 hours)
1. Standardize button styling across pages
2. Standardize form component styling
3. Standardize modal styling
4. Create consistency checklist

**Files**: Various view files

### Phase 10: Monitoring & Performance Verification (2 hours)
1. Create CacheMetricsService
2. Implement health check middleware
3. Test metrics collection
4. Verify cache hit rate >80%
5. Verify response times <50ms (cache), <100ms (database)

**Files**: `app/Services/CacheMetricsService.php`, middleware

### Phase 11: Testing & Validation (4-6 hours)
1. Write property-based tests for bug conditions
2. Write preservation tests
3. Run full test suite
4. Docker build and CI/CD verification
5. Performance benchmarking

**Files**: `tests/Feature/` test files

### Phase 12: Documentation & Deployment (2 hours)
1. Update `.env.example` with Redis config
2. Create consistency checklist
3. Document changes for team
4. Prepare deployment steps

**Files**: `.env.example`, `docs/`

**Total Estimated Effort**: 30-40 hours

---

## Correctness Properties

### Property Group 1: Session Management

**Property 1.1: Sessions Persist Across Requests**
```pascal
FUNCTION isBugCondition_SessionPersistence(X)
  INPUT: X of type LoginCredential
  OUTPUT: boolean
  RETURN X.is_valid AND X.browser != "incognito"
END FUNCTION

FOR ALL X WHERE isBugCondition_SessionPersistence(X) DO
  // User logs in
  session₁ ← login(X)
  
  // Session persists on next request
  session₂ ← getSessionData(session₁.id)
  ASSERT session₂.user_id = X.user_id
  ASSERT session₂.last_activity > now() - 5_minutes
END FOR
```

**Property 1.2: Sessions Timeout After 120 Minutes**
```pascal
FOR ALL X WHERE isBugCondition_SessionPersistence(X) DO
  session ← login(X)
  
  // Wait 120 minutes
  wait(7200_seconds)
  
  // Session should be expired
  auth ← checkAuth()
  ASSERT auth.isGuest = true
END FOR
```

### Property Group 2: Data Persistence

**Property 2.1: Priority Remains NULL After Creation**
```pascal
FUNCTION isBugCondition_PriorityIndependence(X)
  INPUT: X of type DamageReportInput
  OUTPUT: boolean
  RETURN X.urgency IN ['darurat', 'tinggi', 'sedang', 'rendah']
END FUNCTION

FOR ALL X WHERE isBugCondition_PriorityIndependence(X) DO
  report ← createReport(X.location, X.urgency)
  detail ← getDamageDetail(report.id)
  
  // Priority should be NULL, not urgency
  ASSERT detail.priority = NULL
  ASSERT detail.urgency = X.urgency
  ASSERT detail.priority ≠ detail.urgency
END FOR
```

**Property 2.2: Form Data Persists Across Steps**
```pascal
FOR ALL X WHERE isMultiStepForm(X) DO
  // Fill step 1
  step1_data ← {type: X.type, location_id: X.location_id, ...}
  
  // Navigate to step 2
  formState ← getFormState()
  ASSERT formState.step1 = step1_data
  
  // Fill step 2, navigate to step 3
  step2_data ← {category_id: X.category_id, urgency: X.urgency, ...}
  formState ← getFormState()
  ASSERT formState.step1 = step1_data
  ASSERT formState.step2 = step2_data
  
  // Validation error on step 4
  error ← validateStep4()
  ASSERT error.exists = true
  
  // Data should still exist after error
  formState ← getFormState()
  ASSERT formState.step1 = step1_data
  ASSERT formState.step2 = step2_data
END FOR
```

### Property Group 3: Query Performance

**Property 3.1: Cached Queries Complete in <50ms**
```pascal
FUNCTION isBugCondition_QueryCaching(X)
  INPUT: X of type QueryOperation
  OUTPUT: boolean
  RETURN X.isCacheable AND Cache.hasEntry(X.key)
END FUNCTION

FOR ALL X WHERE isBugCondition_QueryCaching(X) DO
  result ← query(X)
  response_time ← measure(result)
  
  // Cache hit should be <50ms
  ASSERT response_time < 50_milliseconds
END FOR
```

**Property 3.2: Cache Invalidated Immediately After Update**
```pascal
FOR ALL X WHERE isDataModel(X) DO
  // Prime cache
  data₁ ← query(X)
  ASSERT Cache.hasEntry(getCacheKey(X)) = true
  
  // Update data
  updated_X ← update(X.id, {new_value})
  
  // Cache should be cleared
  ASSERT Cache.hasEntry(getCacheKey(X)) = false
  
  // Next query should show new data
  data₂ ← query(X)
  ASSERT data₂.new_value = updated_X.new_value
END FOR
```

### Property Group 4: UI/UX Accessibility

**Property 4.1: Touch Targets Minimum 44px**
```pascal
FUNCTION isBugCondition_TouchTargets(element)
  INPUT: element of type HTMLElement
  OUTPUT: boolean
  RETURN isInteractive(element)
END FUNCTION

FOR ALL e WHERE isBugCondition_TouchTargets(e) DO
  computed_width ← getComputedStyle(e).width
  computed_height ← getComputedStyle(e).height
  
  ASSERT computed_width ≥ 44_pixels
  ASSERT computed_height ≥ 44_pixels
END FOR
```

**Property 4.2: Error Messages Visible and Clear**
```pascal
FOR ALL validation_error IN form_validations DO
  // Error should be visible
  error_element ← getErrorElement(validation_error)
  ASSERT isVisible(error_element) = true
  
  // Error should be in alert-danger (red, high contrast)
  background ← getComputedStyle(error_element).backgroundColor
  ASSERT isHighContrast(background, white) = true
  
  // Error should be scrolled into view
  scroll_position ← window.scrollY
  error_position ← error_element.getBoundingClientRect().top
  ASSERT abs(error_position - view_center) < 100_pixels
END FOR
```

---

## Backward Compatibility & Regression Prevention

### Migrations & Rollback

- **Sessions Table**: Already defined in Laravel 12, idempotent migration
- **Priority Field**: No schema change needed (already nullable in most cases)
- **Rollback Plan**: All changes are additive (new cache, new observers), no breaking changes

### Data Safety

- **Existing Reports**: Priority values unchanged (NULL stays NULL, existing priorities preserved)
- **Existing Sessions**: Migrated to Redis/database, users stay logged in
- **Existing Caches**: TTL automatically invalidates old entries
- **File Attachments**: No changes to storage layer, downloads unaffected

### Performance Impact

- **Positive**: Query performance +60-70% improvement (cache hits), reduced database load
- **Neutral**: Form submission path unchanged, same round-trip time
- **Monitoring**: CacheMetricsService provides visibility into impact

---

## Deployment Checklist

- [ ] Database migrations completed (sessions table exists)
- [ ] Redis service running and accessible
- [ ] `.env` updated with `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `REDIS_*` values
- [ ] `config/database.php` includes Redis configuration
- [ ] `config/cache.php` includes TTL strategy
- [ ] Model Observers registered in AppServiceProvider
- [ ] Cache warming command available (optional)
- [ ] Health check middleware active
- [ ] Performance metrics accessible
- [ ] All tests passing (unit, feature, integration)
- [ ] Load testing completed (50+ concurrent users stable)
- [ ] Documentation updated
- [ ] Team trained on new patterns

---

## Success Metrics

| Metric | Target | Current | Expected |
|--------|--------|---------|----------|
| Cache hit rate | >80% | 0% | 85-90% |
| Query response (cached) | <50ms | N/A | 10-30ms |
| Query response (db miss) | <100ms | 300-400ms | 80-100ms |
| Page load time | -60% | 300-400ms | 100-150ms |
| Database CPU (50 users) | <20% | ~100% | 5-15% |
| Session creation time | <50ms | Varies | 20-40ms |
| Error visibility | 100% clear | Small/muted | Bootstrap alert |
| Mobile touch accuracy | >95% hits | ~80% | >97% |
| Desktop layout spacing | 32px padding | 16px | 32px |

