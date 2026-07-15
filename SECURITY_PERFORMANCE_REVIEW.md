# Backend Security & Performance Review
**Project:** NEP System - Backend API  
**Review Date:** 2026-07-15  
**Reviewer:** Automated Security & Performance Analysis  
**Scope:** Laravel Backend API - Pre-Production Review

---

## Executive Summary

This comprehensive review identified **10 security vulnerabilities** (2 Critical, 3 High, 5 Medium) and **8 performance issues** (2 High, 4 Medium, 2 Low) in the Laravel backend API. The most critical issues involve token security, rate limiting, and database query optimization. All findings include prioritized recommendations for remediation before production deployment.

**Risk Level:** HIGH - Immediate action required for critical and high-severity issues.

---

## 1. Security Findings

### 🔴 CRITICAL SEVERITY

#### 1.1 No Token Expiration Configured
**File:** `config/sanctum.php` (Line 20)  
**Risk:** Stolen tokens remain valid indefinitely, increasing the window for unauthorized access  
**Impact:** High - Persistent session hijacking risk

**Current Code:**
```php
'expiration' => null,
```

**Recommendation:**
```php
'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 60), // minutes
```

**Priority:** P0 - Fix immediately  
**Effort:** Low

---

#### 1.2 No Rate Limiting on Authentication Endpoints
**File:** `routes/api.php` (Line 15)  
**Risk:** Brute force attacks on login endpoint  
**Impact:** High - Credential stuffing and password guessing attacks

**Current Code:**
```php
Route::post('/login', [AuthController::class, 'login'])->name('login');
```

**Recommendation:**
```php
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1') // 5 attempts per minute
    ->name('login');
```

**Priority:** P0 - Fix immediately  
**Effort:** Low

---

### 🟠 HIGH SEVERITY

#### 1.3 Missing Authorization in LocationController
**File:** `app/Http/Controllers/Api/LocationController.php` (Lines 56-97)  
**Risk:** Any authenticated user can access location data without role verification  
**Impact:** Medium-High - Information disclosure

**Current Code:**
```php
public function index()
{
    return response()->json(['data' => Province::all()]);
}
```

**Recommendation:** Add role-based access control or document why public access is acceptable:
```php
public function index(Request $request)
{
    // If locations should be restricted to authenticated users only
    // Current implementation is acceptable if locations are public data
    // Consider adding cache for better performance
    return response()->json(['data' => Province::all()]);
}
```

**Priority:** P1 - Review and document access control decision  
**Effort:** Low

---

#### 1.4 Mass Assignment Vulnerability - last_updated_by
**File:** `app/Models/ProgrammeEntry.php` (Lines 13-27)  
**Risk:** `last_updated_by` is fillable and could be manipulated in requests  
**Impact:** Medium - Audit trail tampering

**Current Code:**
```php
protected $fillable = [
    'organisation_id',
    'budget_band_id',
    'programme_name',
    'start_year',
    'end_year',
    'ongoing',
    'fte_staff',
    'indirect_beneficiaries',
    'direct_beneficiaries',
    'method',
    'verified_date',
    'last_updated_at',
    'last_updated_by', // ⚠️ Should not be fillable
];
```

**Recommendation:**
```php
protected $fillable = [
    'organisation_id',
    'budget_band_id',
    'programme_name',
    'start_year',
    'end_year',
    'ongoing',
    'fte_staff',
    'indirect_beneficiaries',
    'direct_beneficiaries',
    'method',
    'verified_date',
    // Remove 'last_updated_at' and 'last_updated_by' from fillable
    // They are set automatically in the booted() method
];
```

**Priority:** P1 - Fix before production  
**Effort:** Low

---

#### 1.5 No Input Sanitization for Rich Text Fields
**File:** Multiple controllers (ProgrammeEntryController, ProgrammeActivityController)  
**Risk:** XSS attacks if frontend doesn't sanitize HTML in description/method fields  
**Impact:** Medium - Cross-site scripting vulnerability

**Affected Fields:**
- `programme_name`
- `method`
- `description` (if added)
- `other_text` in TaxonomyOtherQueue

**Recommendation:**
1. Add HTMLPurifier or similar library for sanitization
2. Or enforce plain text only with stricter validation:
```php
'programme_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s\-_.,!?()]+$/'],
```

**Priority:** P1 - Implement sanitization strategy  
**Effort:** Medium

---

### 🟡 MEDIUM SEVERITY

#### 1.6 Weak Password Requirements
**File:** `app/Models/User.php` (Line 80)  
**Risk:** Minimum 8 characters with no complexity requirements  
**Impact:** Medium - Weak credentials vulnerable to guessing

**Current Code:**
```php
'password' => [$update ? 'sometimes' : 'nullable', 'string', 'min:8'],
```

**Recommendation:**
```php
'password' => [
    $update ? 'sometimes' : 'nullable', 
    'string', 
    'min:8',
    'regex:/[A-Z]/', // At least one uppercase
    'regex:/[a-z]/', // At least one lowercase
    'regex:/[0-9]/', // At least one number
    'regex:/[^A-Za-z0-9]/', // At least one special character
],
```

**Priority:** P2 - Strengthen before production  
**Effort:** Low

---

#### 1.7 No Account Lockout After Failed Login Attempts
**File:** `app/Http/Controllers/Api/AuthController.php` (Lines 82-104)  
**Risk:** Unlimited login attempts enable brute force attacks  
**Impact:** Medium - Credential stuffing vulnerability

**Current Code:**
```php
if (! $guard->attempt($credentials)) {
    return response()->json([
        'message' => 'Invalid credentials.',
    ], 401);
}
```

**Recommendation:** Implement login attempt tracking:
```php
// Add to User model
protected $fillable[] = 'failed_login_attempts';
protected $fillable[] = 'locked_until;

// In AuthController
$user = User::where('email', $credentials['email'])->first();

if ($user && $user->locked_until?->isFuture()) {
    return response()->json(['message' => 'Account temporarily locked.'], 423);
}

if (! $guard->attempt($credentials)) {
    if ($user) {
        $user->increment('failed_login_attempts');
        if ($user->failed_login_attempts >= 5) {
            $user->update(['locked_until' => now()->addMinutes(15)]);
        }
    }
    return response()->json(['message' => 'Invalid credentials.'], 401);
}

if ($user) {
    $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
}
```

**Priority:** P2 - Implement for production  
**Effort:** Medium

---

#### 1.8 Sensitive Data Exposure in API Responses
**File:** Multiple controllers  
**Risk:** Internal IDs, timestamps, and audit fields exposed unnecessarily  
**Impact:** Low-Medium - Information disclosure

**Example:** `ProgrammeEntry` model exposes `last_updated_at` and `last_updated_by` in JSON responses

**Recommendation:** Use API Resources to control response data:
```php
// app/Http/Resources/ProgrammeEntryResource.php
public function toArray($request)
{
    return [
        'id' => $this->id,
        'programme_name' => $this->programme_name,
        // Expose only necessary fields
        'organisation' => new OrganisationResource($this->organisation),
        // Hide internal audit fields from API consumers
    ];
}
```

**Priority:** P2 - Implement API Resources  
**Effort:** Medium

---

#### 1.9 Missing CORS Configuration
**File:** No CORS configuration detected  
**Risk:** Uncontrolled cross-origin requests  
**Impact:** Medium - CSRF and data exfiltration risk

**Recommendation:** Install and configure Laravel CORS package:
```bash
composer require fruitcake/laravel-cors
```

**Priority:** P2 - Configure before production  
**Effort:** Low

---

#### 1.10 No HTTPS Enforcement
**File:** `.env.example` and application config  
**Risk:** API can be accessed over unencrypted HTTP  
**Impact:** High - Man-in-the-middle attacks

**Recommendation:**
1. Set `APP_URL=https://` in production
2. Add middleware to force HTTPS:
```php
// app/Http/Middleware/ForceHttps.php
public function handle($request, Closure $next)
{
    if (! $request->secure() && app()->environment('production')) {
        return redirect()->secure($request->getRequestUri());
    }
    return $next($request);
}
```

**Priority:** P1 - Enforce in production  
**Effort:** Low

---

## 2. Performance Findings

### 🔴 HIGH SEVERITY

#### 2.1 N+1 Query Problem in CSV Export
**File:** `app/Http/Controllers/Api/MapEntryController.php` (Lines 243-351)  
**Risk:** Exponential database queries when generating CSV exports  
**Impact:** High - Slow response times, potential timeouts

**Current Code:**
```php
foreach ($entries as $entry) {
    $keywords = $entry->keywords->pluck('keyword')->implode('; '); // N+1
    $locations = $entry->locations->map(...); // N+1
    $activities = $entry->activities->map(...); // N+1
    // ... more N+1 queries
}
```

**Issue:** While the `export()` method uses eager loading (Line 370-381), the `generateCsv()` method iterates and accesses relationships that may not all be loaded.

**Recommendation:** Ensure all relationships are eager loaded before passing to `generateCsv()`:
```php
$entries = $query->get()->load([
    'keywords',
    'locations.province',
    'locations.district',
    'activities.activityItem.subcategory.category',
    'activities.activityItem.subcategory',
    'activities.activityItem',
    'activities.activityLevels.educationLevel',
    'governmentAgreements',
]);
```

**Priority:** P0 - Fix before production  
**Effort:** Low

---

#### 2.2 Missing Pagination on Map Entries Query
**File:** `app/Http/Controllers/Api/MapEntryController.php` (Line 202)  
**Risk:** Unbounded result sets can crash the application  
**Impact:** High - Memory exhaustion, slow response times

**Current Code:**
```php
return response()->json(['data' => $query->get()]);
```

**Recommendation:**
```php
$perPage = $request->integer('per_page', 25);
return response()->json(['data' => $query->paginate($perPage)]);
```

**Priority:** P0 - Implement pagination immediately  
**Effort:** Low

---

### 🟠 MEDIUM SEVERITY

#### 2.3 Duplicate Query Logic in MapEntryController
**File:** `app/Http/Controllers/Api/MapEntryController.php`  
**Risk:** Code duplication, maintenance burden, inconsistent behavior  
**Impact:** Medium - Developer productivity, bug risk

**Current Issue:** `index()`, `export()`, and `exportPdf()` methods contain nearly identical query-building logic (200+ lines each).

**Recommendation:** Extract to a private method or query scope:
```php
private function buildMapQuery(Request $request, User $user): Builder
{
    $query = ProgrammeEntry::query()
        ->with([...])
        ->distinct();
    
    // Apply all filters once
    // ...
    
    return $query;
}

public function index(Request $request)
{
    // ...
    $query = $this->buildMapQuery($request, $user);
    return response()->json(['data' => $query->paginate(25)]);
}
```

**Priority:** P1 - Refactor for maintainability  
**Effort:** Medium

---

#### 2.4 No Database Query Logging in Production
**File:** `config/database.php`  
**Risk:** Cannot diagnose slow queries in production  
**Impact:** Medium - Debugging difficulty

**Recommendation:** Enable query logging in production with caution:
```php
// AppServiceProvider boot method
if (app()->environment('production')) {
    DB::listen(function ($query) {
        if ($query->time > 1000) { // Log queries > 1 second
            Log::channel('slack')->warning('Slow query detected', [
                'sql' => $query->sql,
                'time' => $query->time,
            ]);
        }
    });
}
```

**Priority:** P2 - Implement monitoring  
**Effort:** Medium

---

#### 2.5 Missing Database Indexes
**File:** Database migrations  
**Risk:** Slow queries on filtered columns  
**Impact:** Medium - Degraded performance as data grows

**Missing Indexes Identified:**
- `programme_entries.organisation_id` (frequently filtered)
- `programme_entries.budget_band_id` (filtered in map queries)
- `programme_entries.fte_staff` (range queries)
- `programme_entries.direct_beneficiaries` (range queries)
- `programme_entries.is_unverified` (filtered in verify workflow)
- `entry_keywords.keyword` (LIKE queries)
- `programme_activities.programme_entry_id` (foreign key)
- `government_agreements.programme_entry_id` (foreign key)

**Recommendation:** Add indexes in new migration:
```php
Schema::table('programme_entries', function (Blueprint $table) {
    $table->index('organisation_id');
    $table->index('budget_band_id');
    $table->index('fte_staff');
    $table->index('direct_beneficiaries');
    $table->index('is_unverified');
});

Schema::table('entry_keywords', function (Blueprint $table) {
    $table->index('keyword');
});
```

**Priority:** P1 - Add before production data grows  
**Effort:** Low

---

#### 2.6 No Caching for Static Data
**File:** `app/Http/Controllers/Api/LocationController.php`, `TaxonomyController.php`  
**Risk:** Repeated database queries for rarely changing data  
**Impact:** Medium - Unnecessary database load

**Current Code:**
```php
public function index()
{
    return response()->json(['data' => Province::all()]);
}
```

**Recommendation:**
```php
public function index()
{
    $provinces = Cache::remember('provinces.all', 3600, function () {
        return Province::all();
    });
    
    return response()->json(['data' => $provinces]);
}
```

**Priority:** P2 - Implement caching strategy  
**Effort:** Low

---

### 🟡 LOW SEVERITY

#### 2.7 Inefficient PDF Export Chunking
**File:** `app/Http/Controllers/Api/MapEntryController.php` (Lines 696-699)  
**Risk:** Loading all chunks into memory defeats the purpose of chunking  
**Impact:** Low-Medium - Memory usage

**Current Code:**
```php
$entries = collect();
$query->chunk(50, function ($chunk) use ($entries) {
    $entries->push(...$chunk); // Still loads everything into memory
});
```

**Recommendation:** Stream PDF generation or use a queue job for large exports:
```php
// For large exports, use a queued job
ExportPdfJob::dispatch($filters, $user->id);
return response()->json(['message' => 'Export queued', 'job_id' => $job_id]);
```

**Priority:** P3 - Consider for large datasets  
**Effort:** High

---

#### 2.8 No Response Compression
**File:** Not configured  
**Risk:** Large JSON responses transmitted uncompressed  
**Impact:** Low - Increased bandwidth costs and slower responses

**Recommendation:** Enable response compression in production:
```php
// In .htaccess or nginx config
# Apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE application/json
</IfModule>

// Or use Laravel middleware
```

**Priority:** P3 - Configure at server level  
**Effort:** Low

---

## 3. Additional Security Recommendations

### 3.1 Implement API Versioning
**Current:** No versioning strategy  
**Recommendation:** Add version prefix to routes:
```php
Route::prefix('api/v1')->group(function () {
    // All routes
});
```

**Priority:** P2 - Plan for future API versions  
**Effort:** Medium

---

### 3.2 Add Request Logging and Monitoring
**Recommendation:** Log all API requests with:
- User ID
- Endpoint accessed
- Request parameters
- Response status
- Execution time

Use Laravel Telescope for development and a logging service (e.g., Sentry, Bugsnag) for production.

**Priority:** P1 - Implement monitoring  
**Effort:** Medium

---

### 3.3 Implement Content Security Policy
**Recommendation:** Add CSP headers to prevent XSS:
```php
// In middleware
return $next($request)
    ->header('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';");
```

**Priority:** P2 - Add security headers  
**Effort:** Low

---

## 4. Performance Optimization Recommendations

### 4.1 Implement API Response Caching
**Cache these endpoints:**
- `/taxonomy/categories` - Cache for 1 hour
- `/provinces` - Cache for 24 hours
- `/provinces/{id}/districts` - Cache for 24 hours

**Priority:** P1 - Significant performance improvement  
**Effort:** Low

---

### 4.2 Use Database Query Optimization
**Recommendations:**
1. Add covering indexes for common query patterns
2. Use `select()` to limit columns in large result sets
3. Consider read replicas for reporting/export queries

**Priority:** P1 - Critical for scalability  
**Effort:** Medium

---

### 4.3 Implement Queue Jobs for Heavy Operations
**Operations to queue:**
- PDF export generation
- CSV export for large datasets
- Email notifications

**Priority:** P1 - Prevent request timeouts  
**Effort:** Medium

---

## 5. Priority Action Plan

### Immediate (Before Production) - P0
1. ✅ **Configure token expiration** in `config/sanctum.php`
2. ✅ **Add rate limiting** to login endpoint
3. ✅ **Implement pagination** on `/map/entries` endpoint
4. ✅ **Fix N+1 queries** in CSV export

### High Priority - P1 (Within 1 Week)
1. ✅ **Add database indexes** for frequently queried columns
2. ✅ **Remove `last_updated_by` from fillable** array
3. ✅ **Enforce HTTPS** in production
4. ✅ **Implement caching** for static data (provinces, taxonomy)
5. ✅ **Refactor duplicate query logic** in MapEntryController

### Medium Priority - P2 (Within 1 Month)
1. ✅ **Strengthen password requirements**
2. ✅ **Implement account lockout** after failed login attempts
3. ✅ **Add CORS configuration**
4. ✅ **Create API Resources** for response control
5. ✅ **Add request logging and monitoring**
6. ✅ **Implement queued jobs** for exports

### Low Priority - P3 (Ongoing)
1. ✅ **Add input sanitization** for rich text fields
2. ✅ **Implement response compression**
3. ✅ **Add CSP headers**
4. ✅ **Plan API versioning strategy**

---

## 6. Testing Recommendations

### Security Testing
1. **Penetration Testing:** Test for SQL injection, XSS, CSRF
2. **Authentication Testing:** Verify token expiration, rate limiting, account lockout
3. **Authorization Testing:** Ensure role-based access controls work correctly
4. **Input Validation Testing:** Test boundary conditions and malicious inputs

### Performance Testing
1. **Load Testing:** Simulate 100+ concurrent users
2. **Stress Testing:** Identify breaking points
3. **Database Query Analysis:** Use Laravel Debugbar or Clockwork to identify slow queries
4. **Memory Profiling:** Monitor memory usage during exports

---

## 7. Compliance and Best Practices

### Laravel Security Best Practices
- ✅ Use Laravel Sanctum for API authentication (Implemented)
- ✅ Use Form Requests for validation (Implemented)
- ✅ Use Eloquent ORM to prevent SQL injection (Implemented)
- ⚠️ Enable HTTPS in production (Not configured)
- ⚠️ Implement rate limiting (Partially implemented)
- ⚠️ Use API Resources for response control (Not implemented)

### OWASP Top 10 Coverage
1. **Broken Access Control:** ⚠️ Partial - Some endpoints missing authorization checks
2. **Cryptographic Failures:** ✅ Good - Passwords hashed, tokens used
3. **Injection:** ✅ Good - Eloquent ORM prevents SQL injection
4. **Insecure Design:** ⚠️ Partial - No rate limiting, no token expiration
5. **Security Misconfiguration:** ⚠️ Partial - Debug mode, missing security headers
6. **Vulnerable Components:** ✅ Good - Dependencies managed via Composer
7. **Authentication Failures:** ⚠️ Partial - No account lockout, weak password policy
8. **Data Integrity Failures:** ✅ Good - Mass assignment protection mostly implemented
9. **Logging Failures:** ⚠️ Partial - No comprehensive request logging
10. **SSRF:** ✅ Good - No user-controlled URLs detected

---

## 8. Conclusion

The NEP backend demonstrates solid foundational security practices with Laravel Sanctum authentication, Form Request validation, and Eloquent ORM usage. However, **critical gaps in token management, rate limiting, and query optimization must be addressed before production deployment**.

**Key Takeaways:**
1. **Security:** Focus on token expiration, rate limiting, and access control
2. **Performance:** Address N+1 queries, add pagination, and implement caching
3. **Monitoring:** Add logging and alerting for production visibility
4. **Scalability:** Plan for database optimization and queued jobs

**Estimated Remediation Time:**
- P0 Issues: 1-2 days
- P1 Issues: 1 week
- P2 Issues: 2-3 weeks
- P3 Issues: Ongoing

**Next Steps:**
1. Assign P0 and P1 issues to development team immediately
2. Schedule security testing before production launch
3. Implement monitoring and alerting
4. Conduct follow-up review after fixes are deployed

---

## Appendix A: Files Reviewed

### Controllers
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/Api/ProgrammeEntryController.php`
- `app/Http/Controllers/Api/ProgrammeActivityController.php`
- `app/Http/Controllers/Api/ProgrammeGeographyController.php`
- `app/Http/Controllers/Api/GovernmentAgreementController.php`
- `app/Http/Controllers/Api/MapEntryController.php`
- `app/Http/Controllers/Api/TaxonomyController.php`
- `app/Http/Controllers/Api/LocationController.php`
- `app/Http/Controllers/Api/Admin/UserManagementController.php`

### Middleware
- `app/Http/Middleware/RoleMiddleware.php`

### Models
- `app/Models/User.php`
- `app/Models/ProgrammeEntry.php`
- `app/Models/Organisation.php`
- `app/Models/ProgrammeLocation.php`

### Configuration
- `config/auth.php`
- `config/sanctum.php`
- `config/database.php`
- `.env.example`

### Routes
- `routes/api.php`

### Form Requests
- `app/Http/Requests/StoreProgrammeEntryRequest.php`

---

## Appendix B: References

- [Laravel Security Documentation](https://laravel.com/docs/security)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [Laravel Performance Tips](https://laravel.com/docs/performance)
- [Laravel API Best Practices](https://github.com/gehaxelt/PHP-Laravel-REST-API-Best-Practices)

---

*Report generated: 2026-07-15*  
*Review Status: Complete*  
*Action Required: Yes - P0 and P1 issues must be resolved before production*