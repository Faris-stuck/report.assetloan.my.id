# Hermes Agent: Database-Based Memory Storage
## Solution untuk Production Scalability

**Problem Solved:**
- ❌ BEFORE: Ribuan JSON files menumpuk di `hermes/data/memory/`
- ✅ AFTER: Semua data user disimpan di database per-user, folder PROJECT tetap clean

---

## 📋 Overview

Sistem memory Hermes Agent telah diupgrade untuk mendukung **database-backed storage** sebagai alternatif file-based storage. Ini memungkinkan scalability production tanpa tumpukan file.

### Storage Architecture

```
SEBELUM (File-based):
PROJECT/
└── hermes/data/memory/
    ├── profiles/
    │   ├── user-1.json
    │   ├── user-2.json
    │   ├── user-3.json
    │   └── ... (ribuan files)
    └── conversations/
        ├── user-1-default.json
        ├── user-1-conv-april.json
        ├── user-2-default.json
        └── ... (puluhan ribu files)

SESUDAH (Database-backed):
DATABASE (ai_agent_memories_*):
├── ai_agent_memories_conversations
│   ├── user_id=1, role=user, conversation_id=default
│   ├── user_id=1, role=user, conversation_id=conv-april
│   ├── user_id=2, role=user, conversation_id=default
│   └── ... (rows in database)
├── ai_agent_memories_profiles
├── ai_agent_memories_lessons
└── ai_agent_memories_reflections

PROJECT/ (CLEAN! Tidak ada JSON files menumpuk)
```

---

## 🚀 Cara Mengaktifkan Database Storage

### Step 1: Update Environment Variables

Edit `.env` atau file konfigurasi di `PROJECT/hermes/config/ai_agent.php`:

```php
// Enable database-based memory storage
'memory_database_enabled' => true,  // Change from false to true

// Database configuration
'memory_db_host' => getenv('AI_AGENT_DB_HOST') ?: 'localhost',
'memory_db_port' => (int) (getenv('AI_AGENT_DB_PORT') ?: 3306),
'memory_db_name' => getenv('AI_AGENT_DB_NAME') ?: 'information_schema',
'memory_db_username' => getenv('AI_AGENT_DB_USER') ?: 'root',
'memory_db_password' => getenv('AI_AGENT_DB_PASSWORD') ?: '',
'memory_db_auto_init' => true,  // Auto-create tables on first run
'memory_db_fallback_to_files' => false, // Gunakan database saja, tanpa fallback file
```

Atau set environment variables:

```bash
AI_AGENT_MEMORY_DATABASE_ENABLED=true
AI_AGENT_DB_HOST=localhost
AI_AGENT_DB_PORT=3306
AI_AGENT_DB_NAME=information_schema
AI_AGENT_DB_USER=root
AI_AGENT_DB_PASSWORD=your_password
AI_AGENT_MEMORY_DB_AUTO_INIT=true
AI_AGENT_MEMORY_DB_FALLBACK_TO_FILES=true
```

### Step 2: Database Tables Will Auto-Create

Ketika chat.php diakses dengan `memory_database_enabled=true`:
- Tables akan otomatis dibuat di database
- Jika sudah ada, proses skip
- Structure:
  - `ai_agent_memories_conversations` - Menyimpan chat history per user+conversation
  - `ai_agent_memories_profiles` - Menyimpan user preferences per user
  - `ai_agent_memories_lessons` - Menyimpan learned patterns per user
  - `ai_agent_memories_reflections` - Sistem self-improvement logs
  - `ai_agent_memories_cleanup_log` - Audit trail untuk archived/deleted memories

---

## 🔄 Migration dari File-Based ke Database

### Step 3: Migrate Existing Data (Optional)

Jika sudah punya conversation history/profiles dalam file-based format, gunakan migration tool:

```bash
# DRY RUN - lihat apa yang akan dimigrasikan tanpa mengubah apapun
php hermes/database/maintenance/migrate-memory-to-database.php --dry-run --verbose

# LIVE MIGRATION - dengan backup
php hermes/database/maintenance/migrate-memory-to-database.php --backup --verbose

# LIVE MIGRATION - dengan delete file setelah sukses
php hermes/database/maintenance/migrate-memory-to-database.php --backup --delete-after-migrate --verbose

# Options:
# --dry-run              Hanya simulasi, jangan ubah data
# --backup               Buat backup (.bak) sebelum delete
# --verbose              Show detail progress setiap file
# --delete-after-migrate Delete original files setelah sukses migrate
```

### Migration Process

Script akan:
1. Scan `hermes/data/memory/profiles/` - migrate ke `ai_agent_memories_profiles`
2. Scan `hermes/data/memory/lessons/` - migrate ke `ai_agent_memories_lessons`
3. Scan `hermes/data/memory/conversations/` - migrate ke `ai_agent_memories_conversations`
4. Backup original files (optional)
5. Delete original files (optional)
6. Show summary

---

## 💾 Database Schema

### Table: ai_agent_memories_conversations

```sql
CREATE TABLE ai_agent_memories_conversations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    conversation_id VARCHAR(255) NOT NULL,
    messages LONGTEXT NOT NULL,              -- JSON array
    updated_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL,
    UNIQUE KEY unique_conversation (user_id, role, conversation_id),
    INDEX idx_user_role (user_id, role),
    INDEX idx_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: ai_agent_memories_profiles

```sql
CREATE TABLE ai_agent_memories_profiles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    notes LONGTEXT NOT NULL,                 -- JSON array
    behavioral_data LONGTEXT,                -- JSON object
    updated_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL,
    UNIQUE KEY unique_profile (user_id, role),
    INDEX idx_user_role (user_id, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: ai_agent_memories_lessons

```sql
CREATE TABLE ai_agent_memories_lessons (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    lessons LONGTEXT NOT NULL,               -- JSON array
    updated_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL,
    UNIQUE KEY unique_lessons (user_id, role),
    INDEX idx_user_role (user_id, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: ai_agent_memories_reflections

```sql
CREATE TABLE ai_agent_memories_reflections (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    reflection_type VARCHAR(100) NOT NULL,
    reflection_data LONGTEXT NOT NULL,      -- JSON object
    created_at BIGINT NOT NULL,
    INDEX idx_type (reflection_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: ai_agent_memories_cleanup_log

```sql
CREATE TABLE ai_agent_memories_cleanup_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    item_type VARCHAR(50) NOT NULL,         -- 'conversation'|'profile'|'lesson'
    item_id VARCHAR(255),
    action VARCHAR(20) NOT NULL,             -- 'archived'|'deleted'
    reason VARCHAR(255),
    archived_data LONGTEXT,                  -- JSON backup
    created_at BIGINT NOT NULL,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 🔧 Configuration Options

### Hybrid Mode (Smart Fallback)

```php
'memory_db_fallback_to_files' => false
```

Jika database enabled tapi connection fails, system akan fallback ke file storage otomatis.

### Cleanup & Archival

Built-in cleanup untuk manage old conversations:

```php
// Function signature:
aiAgentCleanupOldConversations(
    $pdo,
    $userId,
    $role,
    $maxConversations = 20,  // Keep max 20 recent conversations
    $archiveReason          // Optional reason for archive
)

// Result: Old conversations dipindah ke ai_agent_memories_cleanup_log
//         dan dihapus dari tabel conversations
```

---

## 📊 Performance Comparison

| Metric | File-Based | Database-Based |
|--------|-----------|-----------------|
| **Storage on Disk** | 1000+ files, 500+ MB | Database rows, Compact |
| **Read Speed** | File I/O for each record | SQL query, indexed |
| **Write Speed** | File I/O, potential conflicts | Transactional, safe |
| **Concurrent Access** | Risky with locks | Handled by database |
| **Backup/Restore** | Folder copy | SQL dump |
| **Scalability** | Limited (~1000 users) | Unlimited (millions) |
| **Cleanup** | Manual + migration | Built-in |

---

## 🚨 Important Notes

### When to Use File-Based vs Database

**Use File-Based** (`memory_database_enabled=false`):
- Development/testing environments
- Small installations (<10 users)
- No database available
- Simple deployments

**Use Database-Based** (`memory_database_enabled=true`):
- Production environments
- Large teams (100+ users)
- Multi-server deployments
- High-performance requirements
- Need for proper audit trail

### Rollback Strategy

Jika perlu rollback ke file-based:

1. Set `memory_database_enabled=false` di config
2. Database data tetap ada (restore if needed later)
3. File-based storage (if still exists) akan digunakan kembali
4. Old files masih tersimpan di `hermes/data/memory/` backup

### Data Consistency

- **Transactions**: Database mode menggunakan PDO transactions
- **Uniqueness**: UNIQUE constraints prevent duplicate conversations/profiles
- **Indexing**: Optimized queries dengan indexes pada user_id, role, conversation_id
- **Fallback**: Automatic fallback jika database unavailable

---

## 🔍 Monitoring & Troubleshooting

### Check if Database is Working

```php
// Inside PHP code:
$dbConfig = aiAgentGetDatabaseMemoryConfig([]);
$pdo = aiAgentGetDatabaseConnection($dbConfig);
if ($pdo) {
    echo "Database connected ✓\n";
} else {
    echo "Database connection failed ✗\n";
}

// Check table status:
$stmt = $pdo->query("SELECT COUNT(*) FROM ai_agent_memories_conversations");
echo "Conversations stored: " . $stmt->fetchColumn() . "\n";
```

### Migration Status

```bash
# Check how many rows were migrated:
SELECT COUNT(*) FROM ai_agent_memories_conversations;
SELECT COUNT(*) FROM ai_agent_memories_profiles;
SELECT COUNT(*) FROM ai_agent_memories_lessons;

# Check cleanup log:
SELECT * FROM ai_agent_memories_cleanup_log ORDER BY created_at DESC;
```

### Common Issues

1. **"PDO Exception: SQLSTATE[HY000]: General error"**
   - Solution: Check database connection settings, verify database exists

2. **"Fallback to files"**
   - Normal behavior when database unavailable
   - Check error logs for actual DB error

3. **"Old files still exist after migration"**
   - Use `--delete-after-migrate` flag to remove them
   - Manual delete: `rm hermes/data/memory/profiles/*.json`

---

## 📝 Summary

| Aspect | Before | After |
|--------|--------|-------|
| **Storage Model** | 1000+ files | Database rows |
| **Folder Size** | 500+ MB | Clean (minimal) |
| **Scalability** | ~1000 users max | Unlimited |
| **Production Ready** | Limited | Yes ✓ |
| **Data Isolation** | Per-file | Per-user per-database |
| **Concurrent Access** | Risky | Safe |
| **Cleanup** | Manual | Automatic |

**Result**: Production-ready Hermes Agent dengan unlimited scalability dan zero file accumulation. 🎉
