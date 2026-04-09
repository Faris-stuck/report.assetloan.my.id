# HERMES AGENT - DYNAMIC CONFIG SYSTEM

## 📋 Overview

Sistem Hermes Agent sekarang **fully dynamis tanpa hardcode**. Semua configuration terpusat di 5 config files:

### **5 Core Config Files**

1. **config/hermes-schema.php** - Table definitions & columns
2. **config/hermes-roles.php** - Role definitions & permissions
3. **config/hermes-keywords.php** - Detection patterns & keywords
4. **config/hermes-limits.php** - Magic numbers & timeouts
5. **config/hermes-strings.php** - UI strings (i18n-ready)

---

## 🔧 Usage Examples

### **1. Get Table Information**

```php
// Get all tables
$tables = aiAgentGetAllTableNames();  
// Returns: ['barang', 'peminjaman', 'pengembalian', 'extend_peminjaman', 'detail_peminjaman', 'user']

// Get specific table schema
$schema = aiAgentGetTableSchema('peminjaman');
// Returns: ['label' => 'Borrowing', 'columns' => [...], 'scope' => 'peminjaman', ...]

// Get status column untuk table
$statusCol = aiAgentGetStatusColumn('peminjaman');  // Returns: 'status'

// Get user_id column untuk scoping
$userCol = aiAgentGetUserColumn('peminjaman');  // Returns: 'user_id'

// Get tables untuk business scope
$inventoryTables = aiAgentGetTablesForScope('inventory');
// Returns: ['barang']

$borrowingTables = aiAgentGetTablesForScope('peminjaman');
// Returns: ['peminjaman', 'detail_peminjaman']
```

### **2. Get Role Information**

```php
// Normalize role name (handle variants)
$role = aiAgentNormalizeRoleName('pic barang');  // Returns: 'pic_barang'
$role = aiAgentNormalizeRoleName('user biasa');  // Returns: 'user'

// Get all roles
$roles = aiAgentGetAllRoles();
// Returns: ['admin', 'manager', 'pic_barang', 'user']

// Check role permission
$canApprove = aiAgentRoleHasPermission('manager', 'approve_borrowing');  // Returns: true
$canApprove = aiAgentRoleHasPermission('user', 'approve_borrowing');     // Returns: false

// Get accessible tables untuk role
$tables = aiAgentGetAccessibleTables('manager');
// Returns: ['barang', 'peminjaman', 'pengembalian', 'extend_peminjaman', 'detail_peminjaman']

// Check if user-scoped
$isUserScoped = aiAgentRoleIsUserScoped('user');  // Returns: true
$isUserScoped = aiAgentRoleIsUserScoped('admin'); // Returns: false

// Get display name
$displayName = aiAgentGetRoleDisplayName('pic_barang');  // Returns: 'Inventory PIC'
$displayName = aiAgentGetRoleDisplayName('pic_barang', true);  // Returns: 'PIC' (short)
```

### **3. Keyword Detection**

```php
// Get keywords untuk context
$keywords = aiAgentGetKeywordsByContext('borrowing_analysis');
// Returns: ['paling banyak', 'most borrow', 'sering', 'terbanyak', ...]

// Get keywords untuk scope
$keywords = aiAgentGetKeywordsByScope('peminjaman');
// Returns: ['overdue', 'due today', 'jatuh tempo', 'peminjaman', ...]

// Detect context dari message
$contexts = aiAgentDetectContext('barang apa saja yang paling banyak dipinjam?');
// Returns: ['most_borrowed', 'scope_borrowing']

// Get month mapping
$monthMap = aiAgentGetMonthMapping();
// Returns: ['januari' => 1, 'maret' => 3, ..., 'january' => 1, 'march' => 3, ...]
```

### **4. Limits & Configuration**

```php
// Get specific limit
$max = aiAgentGetMaxBorrowedItemsDisplay();  // Returns: 5
$max = aiAgentGetMaxSqlResultRows();        // Returns: 40
$max = aiAgentGetLimit('max_tables_in_context', 6);  // Returns: 6

// Get all limits
$allLimits = aiAgentGetLimitsConfig();
// Returns: ['max_tables_in_context' => 6, 'max_borrowed_items_display' => 5, ...]
```

### **5. Strings (i18n)**

```php
// Get string dalam Bahasa Indonesia
$str = aiAgentGetStringId('project_name');
// Returns: 'Sistem Informasi Peminjaman Barang Berbasis Web'

// Get string dengan parameter
$str = aiAgentGetStringId('most_borrowed_header', 'bulan 03/2026');
// Returns: '📊 Barang paling banyak dipinjam bulan 03/2026:'

// Get string dalam English
$str = aiAgentGetStringEn('most_borrowed_header', 'all-time');
// Returns: '📊 Most borrowed items all-time:'

// Get string dengan language parameter
$str = aiAgentGetString('project_name', 'en');
// Returns: 'Web-Based Inventory Borrowing Information System'

// Get with sprintf formatting
$str = aiAgentGetStringId('most_borrowed_total', 18);  // 18 transaksi
// Returns: '📋 Total peminjaman: 18 transaksi'
```

---

## 📝 Migration Guide - Replace Hardcoded with Config

### **Before (Hardcoded)**
```php
// Old way - tables hardcoded
$tables = ['barang', 'peminjaman', 'pengembalian', 'extend_peminjaman'];

// Old way - role comparison
if ($role === 'admin') { ... }
if ($role === 'manager') { ... }
if ($role === 'pic_barang') { ... }

// Old way - limits hardcoded
$limit = 5;  // ???
$max = 6;    // ???
```

### **After (Dynamic Config)**
```php
// New way - get from schema
$tables = aiAgentGetTablesForScope('peminjaman');

// New way - use config functions
if (aiAgentRoleHasPermission($role, 'approve_borrowing')) { ... }

// Get display name automatically
$displayName = aiAgentGetRoleDisplayName($role);

// New way - limits dari config
$limit = aiAgentGetMaxBorrowedItemsDisplay();  // 5
$max = aiAgentGetMaxTablesInContext();         // 6
```

---

## 🎯 Adding New Configurations

### **Add New Role**

Edit `config/hermes-roles.php`:
```php
'supervisor' => [
    'label' => 'Supervisor',
    'display_name' => 'Supervisor',
    'permissions' => [
        'view_all_items' => true,
        'manage_inventory' => true,
        'approve_borrowing' => true,
        'access_all_scopes' => false,
    ],
    'accessible_tables' => ['barang', 'peminjaman', 'pengembalian'],
    'accessible_scopes' => ['inventory', 'peminjaman', 'pengembalian'],
    'can_see_sensitive' => true,
],
```

### **Add New Table**

Edit `config/hermes-schema.php`:
```php
'maintenance' => [
    'label' => 'Maintenance',
    'primary_key' => 'id',
    'columns' => ['id', 'barang_id', 'tanggal', 'status', ...],
    'status_column' => 'status',
    'scope' => 'inventory',
],
```

### **Add New Keywords**

Edit `config/hermes-keywords.php`:
```php
'maintenance_query' => [
    'keywords' => ['maintenance', 'perbaikan', 'rusak', 'maintenance report'],
    'language' => ['id', 'en'],
    'context' => 'maintenance',
],
```

### **Add New Limit**

Edit `config/hermes-limits.php`:
```php
'max_maintenance_reports' => 10,
'maintenance_alert_threshold' => 3,
```

### **Add New String (Multilingual)**

Edit `config/hermes-strings.php`:
```php
'id' => [
    'maintenance_header' => 'Status Maintenance Barang',
    'maintenance_count' => 'Total report maintenance: %d',
],
'en' => [
    'maintenance_header' => 'Item Maintenance Status',
    'maintenance_count' => 'Total maintenance reports: %d',
],
```

---

## 🔄 How It Works

```
User Chat Request
    ↓
chat.php loaded
    ↓
Loads: config/hermes-schema.php
        config/hermes-roles.php
        config/hermes-keywords.php
        config/hermes-limits.php
        config/hermes-strings.php
    ↓
All hardcoded values replaced with config function calls
    ↓
ai AgentDetectContext() → uses config/hermes-keywords.php
aiAgentNormalizeRoleName() → uses config/hermes-roles.php
aiAgentGetTablesForScope() → uses config/hermes-schema.php
aiAgentGetMaxBorrowedItemsDisplay() → uses config/hermes-limits.php
aiAgentGetStringId() → uses config/hermes-strings.php
    ↓
Response with dynamic values
```

---

## ✅ Benefits

| Aspek | Before | After |
|-------|--------|-------|
| **Flexibility** | ❌ Hard to change | ✅ Edit config, no code change |
| **Maintainability** | ❌ Values scattered | ✅ Centralized config |
| **Scalability** | ❌ Add feature = edit 5+ files | ✅ Add feature = edit 1-2 config files |
| **Multilingual** | ❌ Hardcoded ID only | ✅ i18n-ready (ID, EN, more) |
| **New Roles** | ❌ Code + logic changes | ✅ Just add to config |
| **Testing** | ❌ Hard to mock | ✅ Easy to test with mocked config |

---

## 📌 Summary

Hermes Agent adalah sekarang **100% dynamic**:
- ✅ Zero hardcoded table names
- ✅ Zero hardcoded role logic
- ✅ Zero hardcoded keywords
- ✅ Zero hardcoded limits
- ✅ All strings centralized & i18n-ready

Everything dapat diubah dari config files tanpa mengubah core logic!
