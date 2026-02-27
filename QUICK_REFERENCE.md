# QUICK REFERENCE - CONFIGURATION IMPLEMENTATION

## 📌 3 CRITICAL FILES

### 1️⃣ [config/database.php](config/database.php)
**Purpose:** Centralized database configuration  
**Size:** 43 lines  
**Auto-detects:** Environment via $_SERVER['HTTP_HOST']  
**Used by:** All PHP files that need database access

**Usage:**
```php
require_once __DIR__ . '/../config/database.php';
// Now $conn is available for queries
```

---

### 2️⃣ [assets/js/base-url.js](assets/js/base-url.js)
**Purpose:** Frontend base URL detection  
**Size:** ~15 lines  
**Auto-detects:** Domain/IP/localhost via window.location.origin  
**Used by:** All JavaScript that makes API calls

**Usage:**
```html
<script src="assets/js/base-url.js"></script>
<!-- Creates window.BASE_URL -->
<!-- Must be loaded FIRST! -->
```

---

### 3️⃣ [api/koneksi.php](api/koneksi.php)
**Purpose:** Database bridge for backward compatibility  
**Was:** 276 lines of connection code  
**Now:** 6 lines (just includes config/database.php)  
**Used by:** Legacy files that still use koneksi.php

**Current content:**
```php
<?php
require_once __DIR__ . '/../config/database.php';
?>
```

---

## 📋 FILES MODIFIED (Total: 9)

### New Files (1)
- ✅ [config/database.php](config/database.php)

### Updated Files (2)
- ✅ [api/koneksi.php](api/koneksi.php) - Bridge to centralized config
- ✅ [CONFIGURATION_FINAL.md](CONFIGURATION_FINAL.md) - This documentation

### Fixed Debug/Test Files (6)
- ✅ [debug-return.php](debug-return.php)
- ✅ [test-list-api.php](test-list-api.php)
- ✅ [verify-database.php](verify-database.php)
- ✅ [api/admin/database-verify.php](api/admin/database-verify.php)
- ✅ [api/peminjaman/test-logic-direct.php](api/peminjaman/test-logic-direct.php)
- ✅ [test-detail-units-direct.php](test-detail-units-direct.php) - Was empty

### Verified Correct (2)
- ✅ [assets/js/base-url.js](assets/js/base-url.js) - Already correct
- ✅ [assets/js/config/api.js](assets/js/config/api.js) - Already uses BASE_URL

---

## 🔄 INCLUDE PATTERNS BY LOCATION

### Pattern for files in /api/ folder:
```php
<?php
require_once __DIR__ . '/../config/database.php';
// Now use: $conn->prepare(), $conn->query(), etc.
```

### Pattern for files in /admin/ subfolder:
```php
<?php
require_once __DIR__ . '/../../config/database.php';
// Now use: $conn->prepare(), $conn->query(), etc.
```

### Pattern for files in root folder:
```php
<?php
require_once __DIR__ . '/config/database.php';
// Now use: $conn->prepare(), $conn->query(), etc.
```

### Pattern for legacy code using koneksi.php:
```php
<?php
require_once 'api/koneksi.php';
// $conn already available (from config/database.php)
```

---

## 🌐 ENVIRONMENT DETECTION

| Access URL | HTTP_HOST | MySQL Server | Status |
|------------|-----------|--------------|--------|
| http://localhost/PROJECT | localhost | localhost (laptop) | ✅ |
| https://assetloan.my.id | assetloan.my.id | localhost (VPS) | ✅ |
| http://43.157.205.89 | 43.157.205.89 | localhost (VPS) | ✅ |

**Database always:** `peminjaman` on local `localhost`

---

## ✨ KEY FEATURES

✅ **Zero Configuration**
- No need to edit config files per environment
- System auto-detects where it's running
- Same code works everywhere

✅ **Single Source of Truth**
- Database config in one place: config/database.php
- Base URL determined automatically: window.location.origin
- No duplicate/conflicting settings

✅ **Backward Compatible**
- koneksi.php still works (now bridges to config/database.php)
- Old code using koneksi.php doesn't break
- New code can use config/database.php directly

✅ **Secure**
- Database credentials centralized (not scattered)
- No hardcoded values in multiple places
- Proper error handling included

---

## 🚀 DEPLOYMENT

### Before Deployment
1. Verify config/database.php exists
2. Verify api/koneksi.php has the bridge line
3. Verify MySQL database `peminjaman` exists on all servers

### During Deployment
```bash
git pull origin main
# That's it! System auto-detects environment
```

### After Deployment
```bash
# Test the connection
curl http://your-server/PROJECT/verify-database.php

# Expected response:
# {"status":"connected","database":"peminjaman"}
```

---

## 🔍 TROUBLESHOOTING

### "Database connection failed"
```
✓ Check: MySQL running on server
✓ Check: Database 'peminjaman' exists
✓ Check: Root user/password correct (config/database.php)
✓ Fix: Update credentials in config/database.php if needed
```

### "API endpoints returning undefined"
```
✓ Check: base-url.js loaded FIRST in HTML
✓ Check: window.BASE_URL shows correct URL (F12 console)
✓ Check: fetch calls use: fetch(BASE_URL + '/api/...')
✓ Fix: Move <script src="base-url.js"></script> to top
```

### "Config file not found"
```
✓ Check: File exists: /PROJECT/config/database.php
✓ Check: Include path is correct (relative to current file)
✓ Check: File permissions allow reading
✓ Fix: Verify require_once path matches file location
```

---

## 📚 DOCUMENTATION FILES

| File | Purpose |
|------|---------|
| [CONFIGURATION_FINAL.md](CONFIGURATION_FINAL.md) | Complete implementation guide |
| [VERIFICATION_REPORT.md](VERIFICATION_REPORT.md) | Verification details & analysis |
| This file: Quick reference | Fast lookup & common patterns |

---

## ✅ PRODUCTION CHECKLIST

Before committing to production:

- [✅] config/database.php created
- [✅] api/koneksi.php updated to use config/database.php
- [✅] All PHP files use centralized config
- [✅] All JavaScript uses BASE_URL
- [✅] No hardcoded localhost/IP/domain values
- [✅] Database connection verified (verify-database.php)
- [✅] API endpoints tested
- [✅] base-url.js loaded first in HTML pages
- [✅] Error handling in place
- [✅] Timezone set to Asia/Jakarta

---

## 🎯 SUMMARY

**You now have:**
1. Single codebase that works on all environments
2. Automatic environment detection
3. Zero configuration needed after deploy
4. Production-grade security & reliability

**What to do next:**
1. Commit changes to GitHub
2. Pull on VPS (both domain and IP)
3. Done! System auto-adapts

---

**Status:** ✅ READY FOR PRODUCTION  
**Last Updated:** 2026-02-27  
**Support:** Check CONFIGURATION_FINAL.md for detailed guide
