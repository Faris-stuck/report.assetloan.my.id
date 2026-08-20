# Database Performance & Sessions Management Bugfix Design

## Overview

LAPORIN aplikasi mengalami dua masalah terkait yang saling memperkuat:

1. **Session Management Crash**: Tabel `sessions` ada di migration tetapi session handler fail karena Rails tidak menemukan tabel saat runtime. User login gagal dengan error "Table 'laporin.sessions' doesn't exist".

2. **Performance Degradation**: Query response time mencapai 151-651ms karena database overloaded dan no caching layer. Multiple concurrent users cause exponential degradation.

Solusi ini menggunakan bug condition methodology:
- **C(X)**: Condition ketika tabel sessions tidak terdapat atau query tidak di-cache
- **P(result)**: Expected behavior: session stored properly, cached queries <50ms, database load reduced
- **¬C(X)**: Non-buggy inputs: existing security, auth flow, data integrity tetap unchanged

Hasil akhir: aplikasi stabil dengan session management berfungsi + response time <50ms untuk 80% cached queries.

---

## Glossary

- **Bug_Condition (C)**: Condition yang trigger bug - session table missing atau query tanpa caching
- **Property (P)**: Desired behavior untuk buggy inputs - session tersimpan, query cached, response <50ms
- **Preservation (¬C)**: Existing behavior yang harus unchanged - auth security, data integrity, audit logging
- **Sessions Table**: Database table untuk menyimpan session data (Laravel default: `sessions`)
- **Redis Cache**: In-memory store untuk cache query results dan session data
- **Cache Invalidation**: Strategy untuk clear cache ketika data berubah (CREATE/UPDATE/DELETE)
- **Predis Package**: PHP client untuk Redis connection
- **Cache Prefix**: Strategy untuk avoid key collisions - format: `laporin:entity:action`
- **Cache TTL**: Time To Live - berapa lama data tetap cached sebelum auto-expire
- **Cache Hit Rate**: Percentage query yang served dari cache vs database

---

## Bug Details

### Bug Condition

Bug terjadi di dua skenario yang saling terkait:

**Skenario 1: Session Table Missing**
- WHEN Laravel session handler dipicu (login, session access)
- AND database migration tidak complete atau session table belum created
- THEN DatabaseSessionHandler gagal query sessions table
- AND aplikasi crash dengan "Base table or view not found: 1146 Table 'laporin.sessions' doesn't exist"

**Skenario 2: Query Performance Degradation**
- WHEN user melakukan read operations (fetch reports, master data, user profiles)
- AND query langsung hit database tanpa caching layer
- THEN response time mencapai 151-651ms
- AND multiple concurrent users cause database overload + exponential latency increase
- AND stale data display sampai query cache expire (atau not cached at all)

**Formal Specification:**

```
FUNCTION isBugCondition(input)
  INPUT: input of type DatabaseOperation or SessionOperation
  OUTPUT: boolean
  
  CONDITION_1: SessionTableMissing
    RETURN input.isSessionOperation 
           AND NOT tableExists('sessions')
  
  CONDITION_2: QueryNotCached
    RETURN input.isReadOperation 
           AND NOT isCached(input.queryKey)
           AND input.queryType IN ['report_list', 'user_profile', 'master_data']
           AND NOT usesRedisCache()
  
  RETURN CONDITION_1 OR CONDITION_2
END FUNCTION
```

### Examples

**Example 1: Session Creation Fails**
```
User Action: Click "Login" button
Expected: Session created, stored di database, user authenticated
Actual: Error "Table 'laporin.sessions' doesn't exist" - login fails, user stuck at login page
```

**Example 2: Report List Query Slow**
```
User Action: Navigate to "Laporan" page
Expected: Report list load dalam <50ms (from cache)
Actual: Database query takes 300-400ms, page feels slow
Concurrent Users (50): Request 50x slower karena database bottleneck
```

**Example 3: Updated Report Shows Stale Data**
```
User A: Submit laporan baru (CREATE)
Expected: Laporan langsung visible dalam list untuk User B (cache invalidated)
Actual: User B masih lihat old list sampai cache expire (1-24 jam nanti)
```

**Example 4: Edge Case - Session Exists But No Cache**
```
User Action: Already logged in, navigate between pages
Expected: Each page load <50ms (session cached, page data cached)
Actual: Each page load 200-400ms karena no cache layer, even session query hits database
```

---

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors** (MUST NOT change oleh fix):

1. **Authentication Security**
   - Session cookie tetap HTTP_ONLY, SAME_SITE=strict, SECURE=true
   - Session lifetime tetap 120 minutes
   - Session data tetap encrypted sebelum storage (jika SESSION_ENCRYPT=true)

2. **Session Lifecycle**
   - Session created on login
   - Session destroyed on logout (clear cache + database)
   - Session auto-invalidate on timeout
   - Session regeneration on login (security best practice)

3. **Authorization & Access Control**
   - Role-based access control tetap work (superadmin, kesiswaan, sarpras, wali_kelas)
   - Protected routes tetap require authentication
   - 403/401 errors tetap return untuk unauthorized users

4. **Data Integrity & Consistency**
   - All CREATE/READ/UPDATE/DELETE operations tetap maintain foreign key constraints
   - Audit logging tetap record semua user actions dengan correct timestamps
   - Report data tetap immutable (soft deletes tetap work)

5. **File Attachments & Uploads**
   - Attachment upload/download tetap work independently dari caching layer
   - File storage tetap reliable
   - Attachment queries MAY cached (safe karena attachments immutable post-upload)

6. **Database Fallback**
   - Jika Redis unavailable, aplikasi fallback ke database cache (graceful degradation)
   - Session tetap work (database session driver as fallback)
   - No data loss atau corruption

7. **Audit & Compliance**
   - Audit logs tetap record dengan accurate timestamps
   - User action tracing tetap work
   - Privacy of encrypted fields tetap maintained

**Scope**: All inputs that do NOT involve session creation/access atau query caching should be completely unaffected:
- Mouse clicks, form submissions - unaffected
- Database writes (INSERT/UPDATE/DELETE) - unaffected except cache invalidation
- File I/O operations - unaffected
- Email notifications - unaffected
- Non-read operations - unaffected

---

## Hypothesized Root Cause

Based pada bug description, likely root causes:

### Root Cause 1: Incomplete Database Setup
**Most Likely**

Issue: Session table defined di migration tetapi:
- Migration tidak run di production
- atau Database migration runner gagal silently
- atau Manual table creation missed sessions table

Evidence: 
- Session table schema already in 0001_01_01_000000_create_users_table.php
- But runtime error suggest table tidak exist
- Likely: migration incomplete atau production database tidak sync

Fix: Ensure migration complete + add explicit check

### Root Cause 2: Laravel Session Driver Configuration Mismatch
**Likely**

Issue: SESSION_DRIVER set ke `database` tetapi:
- Database connection configuration salah
- Session table name di config tidak match actual table
- Missing database connection untuk session driver

Evidence:
- .env.example menunjukkan SESSION_DRIVER=database
- config/session.php properly configured
- Likely: production .env different dari .env.example

Fix: Verify .env configuration + add validation

### Root Cause 3: No Query Caching Layer
**Certain**

Issue: Queries directly hit database karena:
- No Redis configured
- No cache middleware untuk read operations
- Master data (damage categories, locations, etc) queried setiap request

Evidence:
- Response times 151-651ms (too slow untuk simple queries)
- Multiple users cause exponential latency (database bottleneck)
- CACHE_STORE=database (file-based cache, not ideal untuk concurrent load)

Fix: Implement Redis layer + query caching strategy

### Root Cause 4: No Cache Invalidation Strategy
**Certain**

Issue: Stale data displayed karena:
- Cache created tanpa invalidation hooks
- When data changes (create/update/delete), cache not cleared
- Users see old data sampai TTL expire

Evidence:
- Requirement 1.5 shows stale data problem
- No event listeners atau observers untuk cache invalidation

Fix: Add Model observers untuk automatic cache invalidation

### Root Cause 5: Session Driver Not Using Redis
**Likely for Performance**

Issue: SESSION_DRIVER=database tetapi:
- Database session queries compete with application queries
- Session creation/destroy during high load cause slowdown
- Redis can handle sessions better (atomic operations, faster)

Evidence:
- Session operation need <50ms response (database miss this target)
- Redis can serve <10ms per operation

Fix: Migrate SESSION_DRIVER dari database ke redis

---

## Correctness Properties

Property 1: Session Table Creation - Bug Condition

_For any_ Laravel application start, login attempt, atau session access operation where the sessions table does NOT exist, the fixed solution SHALL:
- Ensure sessions table exists dengan proper schema (id, user_id, ip_address, user_agent, payload, last_activity)
- Create sessions successfully on login
- Retrieve sessions on subsequent requests
- Return session data within <50ms

**Validates: Requirements 2.1, 2.2**

Property 2: Query Response Time - Bug Condition

_For any_ read operation (fetch reports, user profiles, master data) where caching NOT applied, the fixed solution SHALL:
- Cache query results di Redis dengan TTL 1-24 hours (configurable)
- Return cached data within <50ms untuk cache HIT
- Return fresh data within <100ms untuk cache MISS (database query + cache storage)
- Achieve >80% cache hit rate untuk read-heavy queries

**Validates: Requirements 2.3, 2.4**

Property 3: Cache Invalidation - Bug Condition

_For any_ CREATE, UPDATE, atau DELETE operation on reports atau master data, the fixed solution SHALL:
- Immediately invalidate related cache entries
- Clear cache keys matching pattern: `laporin:entity:*` (entity affected)
- Ensure next read query fetch fresh data dari database
- Complete cache invalidation within <10ms

**Validates: Requirements 2.5**

Property 4: Session Security Preservation - Preservation

_For any_ login, authentication, atau session lifecycle operation where the bug condition does NOT hold (sessions table exists, caching works), the fixed solution SHALL:
- Produce exactly same behavior as original: session created, user authenticated
- Maintain HTTP_ONLY, SAME_SITE=strict, SECURE cookie attributes
- Maintain 120-minute session lifetime
- Maintain session timeout behavior
- Preserve same session regeneration logic

**Validates: Requirements 3.1, 3.2, 3.3**

Property 5: Authorization & Access Control Preservation - Preservation

_For any_ protected route access attempt where user logged in, the fixed solution SHALL:
- Continue to enforce role-based access control (superadmin, kesiswaan, sarpras, wali_kelas)
- Return 403/401 errors untuk unauthorized attempts (same as before)
- Not introduce performance difference untuk authorization checks
- Maintain audit logging dengan same accuracy

**Validates: Requirements 3.4, 3.7**

Property 6: Data Integrity Preservation - Preservation

_For any_ CREATE/UPDATE/DELETE database operation, the fixed solution SHALL:
- Continue to maintain foreign key constraints
- Continue to enforce data validation rules
- Not cause data loss atau corruption
- Soft deletes continue to work correctly
- Attachment upload/download continue independently

**Validates: Requirements 3.6, 3.8**

Property 7: Database Fallback Preservation - Preservation

_For any_ operation where Redis connection fails atau unavailable, the fixed solution SHALL:
- Gracefully fallback ke database cache (or file cache)
- Maintain application availability (no crash)
- Session continue to work using database driver
- Performance degrade gracefully (not instant fail)

**Validates: Requirements 3.5**

---

## Fix Implementation

### Changes Required

Assuming root cause analysis correct, implementing fix requires:

#### **Change 1: Verify & Create Sessions Table Migration**

**File**: `database/migrations/0001_01_01_000000_create_users_table.php`

**Current State**: Sessions table already defined dalam create_users_table migration

**Specific Change**:
- Migration already correct - sessions table has all required columns
- Add database index untuk `user_id` dan `last_activity` (performance optimization)
- Current migration sufficient, just ensure it's been run

**Validation**: Run `php artisan migrate --force` to ensure sessions table exists

#### **Change 2: Configure Redis Connection**

**File**: `config/database.php` (already configured)

**Current State**: Redis configuration exists dengan cache dan default connections

**Specific Change**:
- Already properly configured (database.php has redis section)
- Just need to add environment variables ke .env

**Action**: Add environment variables:
```
REDIS_CLIENT=phpredis          # or predis (need composer package)
REDIS_HOST=redis               # Docker service name
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
REDIS_CACHE_DB=1               # Separate database untuk cache
```

#### **Change 3: Install Redis PHP Client (Predis Package)**

**File**: `composer.json`

**Specific Change**:
- Add `predis/predis` package ke require
- Run `composer require predis/predis` (recommended over php-redis untuk flexibility)

**Alternative**: Use native php-redis extension (faster tetapi needs system setup)

#### **Change 4: Configure Laravel Cache Driver untuk Redis**

**File**: `config/cache.php` (already configured)

**Current State**: Redis store already defined

**Specific Change**:
- Already configured properly
- Just need to set environment variable CACHE_STORE=redis

**Action**: Update .env:
```
CACHE_STORE=redis              # Change dari 'database' ke 'redis'
CACHE_PREFIX=laporin_          # Prefix untuk avoid key collisions
```

#### **Change 5: Configure Laravel Session Driver untuk Redis**

**File**: `config/session.php` (already configured)

**Current State**: Configuration supports redis

**Specific Change**:
- Update environment variable untuk use Redis

**Action**: Update .env:
```
SESSION_DRIVER=redis           # Change dari 'database' ke 'redis'
SESSION_STORE=default          # Use default Redis connection untuk sessions
SESSION_LIFETIME=120           # Keep 120 minutes
SESSION_ENCRYPT=false          # Predis tidak support encrypted sessions natively
SESSION_SECURE_COOKIE=true     # HTTPS only
SESSION_HTTP_ONLY=true         # JavaScript cannot access
SESSION_SAME_SITE=strict       # CSRF protection
```

#### **Change 6: Create Query Cache Keys Architecture**

**File**: `app/Traits/CacheableQuery.php` (NEW)

**Specific Implementation**:
- Create trait dengan static cache key generator
- Format: `laporin:entity:action:params`
- Example: `laporin:reports:list:all`, `laporin:users:profile:123`

```php
trait CacheableQuery {
    public static function cacheKey($action, ...$params): string {
        $entityName = strtolower(class_basename(static::class));
        $paramStr = implode(':', array_map('md5', $params));
        return "laporin:{$entityName}:{$action}:{$paramStr}";
    }
}
```

#### **Change 7: Implement Query Caching di Controllers**

**File**: `app/Http/Controllers/ReportController.php` (dan similar controllers)

**Specific Changes**:
- Wrap read queries dengan Cache::remember()
- TTL: 24 hours untuk report list, 1 hour untuk individual reports
- Example pattern:
```php
$reports = Cache::remember(
    CacheableQuery::cacheKey('list', 'all'),
    3600,  // 1 hour TTL
    fn() => Report::with('location', 'violationType')->get()
);
```

#### **Change 8: Implement Model Observers untuk Cache Invalidation**

**File**: `app/Observers/ReportObserver.php` (NEW)

**Specific Implementation**:
- Hook ke Report::created(), updated(), deleted() events
- Clear related cache keys on each event
- Use cache tags untuk grouped invalidation

```php
class ReportObserver {
    public function created(Report $report) {
        Cache::tags('reports')->flush();
    }
    public function updated(Report $report) {
        Cache::tags('reports', 'locations')->flush();
    }
    public function deleted(Report $report) {
        Cache::tags('reports')->flush();
    }
}
```

#### **Change 9: Register Model Observers dalam Boot**

**File**: `app/Providers/AppServiceProvider.php`

**Specific Change**:
- Register observers di boot() method
- Ensure event listeners attached sebelum any request processing

```php
public function boot() {
    Report::observe(ReportObserver::class);
    DamageDetail::observe(DamageDetailObserver::class);
    // ... other observers
}
```

#### **Change 10: Add Cache Warming Strategy**

**File**: `app/Console/Commands/WarmApplicationCache.php` (NEW - optional)

**Specific Implementation**:
- Artisan command untuk pre-populate cache
- Run on deployment atau scheduled
- Cache master data, frequently accessed reports

#### **Change 11: Add Redis Health Check Middleware**

**File**: `app/Http/Middleware/CheckRedisConnection.php` (NEW)

**Specific Implementation**:
- Verify Redis connection available
- Graceful fallback jika Redis down
- Log Redis errors untuk monitoring

#### **Change 12: Update .env.example**

**File**: `.env.example`

**Changes**:
- Add Redis configuration variables
- Update CACHE_STORE ke redis
- Update SESSION_DRIVER ke redis
- Add documentation comments

---

## Testing Strategy

### Validation Approach

Testing strategy uses bug condition methodology:

**Phase 1: Exploratory Bug Condition Checking**
- Reproduce bugs pada UNFIXED code
- Confirm root causes
- Document current defective behavior

**Phase 2: Fix Checking**
- Verify for all inputs WHERE isBugCondition(input)=true
- Fixed function produces expected behavior
- Session created successfully, queries cached

**Phase 3: Preservation Checking**
- Verify for all inputs WHERE isBugCondition(input)=false
- Fixed function produces same result as original
- No regressions di auth, data integrity, audit logging

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples yang demonstrate bugs BEFORE fix. Confirm/refute root cause hypotheses.

**Test Plan**: Write tests untuk:
1. Session table access (will fail if table missing)
2. Query performance (will show 150-600ms response)
3. Concurrent load test (will show degradation)
4. Cache invalidation (will show stale data)

**Test Cases**:

1. **Session Creation Test** (will fail on unfixed code)
   ```
   WHEN user login
   THEN verify session table exists
   THEN verify session data stored
   THEN verify session retrieved on next request
   ```
   Expected Failure: "Table 'laporin.sessions' doesn't exist"

2. **Query Performance Test** (will show slow response on unfixed code)
   ```
   WHEN fetch reports list (first time)
   THEN measure query response time
   EXPECT: <100ms
   ACTUAL: 150-400ms (unfixed)
   ```

3. **Concurrent Load Test** (will show degradation on unfixed code)
   ```
   WHEN 50 concurrent users fetch reports
   THEN measure response times
   EXPECT: sustained <100ms per request
   ACTUAL: 200-600ms+ (unfixed due to database bottleneck)
   ```

4. **Cache Invalidation Test** (will show stale data on unfixed code)
   ```
   GIVEN report list cached
   WHEN new report created
   THEN fetch report list again
   EXPECT: new report visible
   ACTUAL: not visible until cache expire (unfixed)
   ```

5. **Session Timeout Test** (will show inconsistent behavior on unfixed code)
   ```
   WHEN session expire (120 minutes)
   THEN verify session destroyed
   EXPECT: re-authentication required
   ACTUAL: may fail if session table missing
   ```

### Fix Checking

**Goal**: Verify untuk semua inputs WHERE isBugCondition=true, fixed function produces expected behavior.

**Pseudocode**:
```
FOR ALL input WHERE isBugCondition(input) DO
  result := fixedFunction(input)
  ASSERT expectedBehavior(result)
END FOR
```

**Specific Tests**:

1. **Unit Test: Session Table Exists**
   ```php
   public function test_sessions_table_exists() {
       $this->assertTrue(Schema::hasTable('sessions'));
   }
   ```

2. **Unit Test: Session Created On Login**
   ```php
   public function test_session_created_on_login() {
       $response = $this->post('/login', [
           'email' => 'user@test.com',
           'password' => 'password'
       ]);
       
       $this->assertAuthenticated();
       $this->assertTrue(session()->has('user_id'));
   }
   ```

3. **Unit Test: Query Response Time <50ms (Cache Hit)**
   ```php
   public function test_cached_query_response_under_50ms() {
       // Prime cache
       Report::all();
       
       $start = microtime(true);
       $reports = Report::all();
       $elapsed = (microtime(true) - $start) * 1000;
       
       $this->assertLessThan(50, $elapsed);
   }
   ```

4. **Unit Test: Query Response Time <100ms (Cache Miss)**
   ```php
   public function test_uncached_query_response_under_100ms() {
       Cache::flush();
       
       $start = microtime(true);
       $reports = Report::all();
       $elapsed = (microtime(true) - $start) * 1000;
       
       $this->assertLessThan(100, $elapsed);
   }
   ```

5. **Unit Test: Cache Invalidation On Create**
   ```php
   public function test_cache_invalidated_on_report_create() {
       Report::all(); // Prime cache
       
       $cacheKey = CacheableQuery::cacheKey('list', 'all');
       $this->assertTrue(Cache::has($cacheKey));
       
       Report::create([...]);
       
       $this->assertFalse(Cache::has($cacheKey));
   }
   ```

### Preservation Checking

**Goal**: Verify untuk semua inputs WHERE isBugCondition=false, fixed function produces same result sebagai original.

**Pseudocode**:
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT originalFunction(input) = fixedFunction(input)
END FOR
```

**Testing Approach**: Property-based testing recommended karena:
- Generate many test cases automatically across input domain
- Catch edge cases yang manual tests miss
- Provide strong guarantees behavior unchanged

**Specific Tests**:

1. **Property Test: Authentication Behavior Unchanged**
   ```php
   public function test_login_with_valid_credentials_unchanged() {
       // Original behavior: user authenticated
       // Fixed behavior: MUST be same
       
       $user = User::factory()->create(['password' => Hash::make('password')]);
       
       $this->post('/login', [
           'email' => $user->email,
           'password' => 'password'
       ])->assertAuthenticated();
       
       // Verify same session attributes (HTTP_ONLY, SECURE, SAME_SITE)
       // Verify same cookie security
   }
   ```

2. **Property Test: Unauthorized Access Still Denied**
   ```php
   public function test_unauthorized_routes_still_denied() {
       // Original behavior: 403/401 for unauthorized
       // Fixed behavior: MUST be same
       
       $this->get('/admin/dashboard')
           ->assertStatus(401 || 403);
   }
   ```

3. **Property Test: Data Integrity Preserved**
   ```php
   public function test_foreign_key_constraints_preserved() {
       // Original: cannot delete class with students
       // Fixed: MUST be same
       
       $class = SchoolClass::factory()->has(Student::factory())->create();
       
       $this->expectException(Exception::class);
       $class->forceDelete();
   }
   ```

4. **Property Test: Audit Logging Unchanged**
   ```php
   public function test_audit_logging_continues_on_data_changes() {
       AuditLog::query()->delete();
       
       Report::create([...]);
       
       $this->assertDatabaseHas('audit_logs', [
           'action' => 'create',
           'model' => 'Report'
       ]);
   }
   ```

5. **Property Test: File Attachments Unaffected**
   ```php
   public function test_file_attachment_upload_unchanged() {
       $file = UploadedFile::fake()->create('test.pdf');
       
       $response = $this->post('/reports/1/attachments', [
           'file' => $file
       ]);
       
       $this->assertDatabaseHas('report_attachments', [
           'report_id' => 1
       ]);
   }
   ```

6. **Property Test: Redis Fallback To Database**
   ```php
   public function test_graceful_fallback_when_redis_down() {
       // Simulate Redis connection failure
       
       // Should still work (fallback to database)
       $reports = Report::all();
       $this->assertNotEmpty($reports);
   }
   ```

### Unit Tests

- Session table migration tests
- Session creation/retrieval tests
- Cache key generation tests
- Cache invalidation tests
- Redis connection tests
- Query timing tests (<50ms cache, <100ms database)

### Property-Based Tests

- Generate random report data + verify cache behavior
- Generate random user profiles + verify session handling
- Generate random concurrent operations + verify consistency
- Generate random cache key patterns + verify invalidation

### Integration Tests

- Full login flow: authentication, session creation, cookies set
- Full read flow: first query cache misses, cache stored, next query hits
- Full write flow: create report, cache invalidated, next query sees new data
- Full concurrent flow: 50+ users reading/writing simultaneously
- Redis failure scenario: verify graceful fallback
- Session timeout scenario: verify automatic invalidation

### Monitoring & Observability

- Add metrics untuk cache hit/miss rate
- Add metrics untuk query response times (cached vs uncached)
- Add metrics untuk Redis connection health
- Add logs untuk cache invalidation events
- Add health check endpoints untuk monitoring

---

## Deployment Strategy

### Step 1: Install Redis & Predis Package
```bash
# Add Predis to composer
composer require predis/predis

# Or ensure Redis PHP extension installed
# For production, Redis server already running
```

### Step 2: Configure Environment Variables
- Update .env dengan Redis connection
- Update CACHE_STORE=redis
- Update SESSION_DRIVER=redis

### Step 3: Create Sessions Table (if not exists)
```bash
php artisan migrate --force
# Verify with: php artisan tinker
# Schema::hasTable('sessions')
```

### Step 4: Test Session Creation
```bash
php artisan tinker
# Test: session storage works
```

### Step 5: Test Query Caching
```bash
# Create test observer
# Create cache wrapper untuk queries
# Verify cache behavior
```

### Step 6: Performance Testing
```bash
# Load test dengan concurrent users
# Verify response times <50ms cached
# Verify response times <100ms uncached
# Verify cache hit rate >80%
```

### Step 7: Production Deployment
- Blue-green deployment (zero downtime)
- Verify Redis running sebelum deploy
- Monitor for errors dalam first 24 hours
- Gradual traffic shift (canary deployment)

---

## Configuration Examples

### .env Configuration
```
# Redis Settings
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
REDIS_CACHE_DB=1

# Cache Configuration
CACHE_STORE=redis
CACHE_PREFIX=laporin_

# Session Configuration
SESSION_DRIVER=redis
SESSION_STORE=default
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict
```

### Cache Key Strategy
```
Master Data:      laporin:master:damage_categories
                  laporin:master:locations
                  laporin:master:violation_types
                  
Report Queries:   laporin:reports:list:all
                  laporin:reports:detail:123
                  laporin:reports:by_student:456
                  
User Profiles:    laporin:users:profile:789
                  laporin:users:by_role:superadmin
                  
Session Data:     PHPSESSID (handled by Redis)
```

### Cache TTL Strategy
```
Master Data:      86400    (24 hours - rarely changes)
Report List:      3600     (1 hour - regular updates)
Report Detail:    1800     (30 minutes)
User Profile:     1800     (30 minutes)
Session Data:     7200     (2 hours - handled by SESSION_LIFETIME)
Rate Limiting:    60       (1 minute)
```

---

## Success Metrics

After fix implemented, measure:

1. **Session Availability**: 100% successful session creation on login (0 errors)
2. **Query Response Time**: 
   - Cache hit: <50ms (target <30ms)
   - Cache miss: <100ms (target <50ms)
3. **Cache Hit Rate**: >80% untuk read-heavy queries (target >90%)
4. **Database Load**: Reduced 60-70% during peak hours
5. **Concurrent User Support**: Stable performance untuk 100+ concurrent users
6. **Error Rate**: <0.1% untuk cache-related errors
7. **Redis Uptime**: >99.9% availability
8. **Graceful Fallback**: 100% success rate ketika Redis unavailable
9. **Data Consistency**: 0 data corruption atau stale data issues
10. **User Experience**: Page load time <1 second (improved from 3-5 seconds)

---

## Risk Mitigation

1. **Redis Not Available**: Graceful fallback to database cache
2. **Cache Invalidation Missed**: Cache warming + periodic refresh
3. **Session Data Loss**: Database backup + Redis persistence (RDB/AOF)
4. **Performance Degradation**: Implement circuit breaker pattern
5. **Security Risk**: Use strong Redis password, enable AUTH, limit network access
6. **Data Privacy**: Ensure encrypted fields still encrypted di cache (or don't cache)

---
