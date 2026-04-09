# Hermes Agent: Integrated Memory Storage (Single Database)

## ✅ SOLUTION: No Separate Database Needed

**Previous Approach:**
- ❌ Create separate database for memory tables
- ❌ Separate config for AI_AGENT_DB_*
- ❌ Complex setup

**NEW Approach (This One):**
- ✅ Use existing `peminjaman` database
- ✅ Add only 3 lightweight tables
- ✅ Link everything via `user_id` only
- ✅ Zero new configuration needed
- ✅ Simple & clean

---

## 📊 Architecture

### Database Structure

```
peminjaman (existing database)
├── users (existing)
│   ├── id
│   ├── nama
│   ├── email
│   ├── role
│   └── ...
├── peminjaman (existing)
├── barang (existing)
├── ai_memory_conversations (NEW - minimal)
│   ├── id
│   ├── user_id → FK to users.id
│   ├── conversation_id
│   ├── messages (JSON)
│   └── timestamps
├── ai_memory_profiles (NEW - minimal)
│   ├── id
│   ├── user_id → FK to users.id (UNIQUE)
│   ├── profile_data (JSON)
│   └── timestamps
└── ai_memory_lessons (NEW - minimal)
    ├── id
    ├── user_id → FK to users.id (UNIQUE)
    ├── lessons_data (JSON)
    └── timestamps
```

### How It Works

```
Hermes Chat Request
    ↓
Load $conn from database.php (existing peminjaman connection)
    ↓
Initialize memory tables (if not exist) in peminjaman DB
    ↓
Read/Write memory using $conn + user_id
    ├── aiAgentLoadConversationMemory($conn, $userId, 'default')
    ├── aiAgentSaveConversationMemory($conn, $userId, $conversationId, $data)
    ├── aiAgentLoadUserProfile($conn, $userId)
    └── aiAgentSaveUserProfile($conn, $userId, $data)
    ↓
Everything linked via user_id only ✓
```

---

## 🚀 Setup (Very Simple)

### Step 1: NOTHING - already configured!

Since database/integrated-memory-helper.php automatically:
- Uses existing `$conn` from `config/database.php`
- Creates tables in existing `peminjaman` database
- Links via `user_id` from existing `users` table

### Step 2: First Request = Auto-Initialize

When user sends first chat message:
```
1. chat.php loads database.php
2. Gets existing $conn (peminjaman database)
3. Calls aiAgentInitializeMemoryTables($conn)
4. Tables auto-created if not exist
5. All memory operations use this connection
```

### Step 3: Done!

No environment variables, no config changes, no migration scripts needed.

---

## 💾 Table Schemas

### ai_memory_conversations
```sql
CREATE TABLE ai_memory_conversations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,                          -- Link to users.id
    conversation_id VARCHAR(255) NOT NULL,         -- default, conv-april, etc
    messages LONGTEXT NOT NULL,                    -- JSON array of messages
    updated_at BIGINT NOT NULL,                    -- Last update timestamp
    created_at BIGINT NOT NULL,                    -- Creation timestamp
    UNIQUE KEY unique_conv (user_id, conversation_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### ai_memory_profiles
```sql
CREATE TABLE ai_memory_profiles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,                   -- One profile per user
    profile_data LONGTEXT NOT NULL,                -- JSON object with preferences
    updated_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### ai_memory_lessons
```sql
CREATE TABLE ai_memory_lessons (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,                   -- One lesson set per user
    lessons_data LONGTEXT NOT NULL,                -- JSON array of lessons
    updated_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Key Features:**
- ✅ Foreign keys to users table (automatic cleanup on user delete)
- ✅ UNIQUE constraints on user_id for profiles/lessons (one per user)
- ✅ Multiple conversations per user (no UNIQUE on conversation_id)
- ✅ Indexes for fast queries
- ✅ JSON storage for flexibility

---

## 🔧 Available Functions

### Load Functions
```php
// Load specific conversation
$conversation = aiAgentLoadConversationMemory($conn, $userId, $conversationId);
// Returns: ['conversation_id', 'user_id', 'messages', 'updated_at', 'created_at']

// Load user profile
$profile = aiAgentLoadUserProfile($conn, $userId);
// Returns: ['user_id', 'notes', 'behavioral_data', 'updated_at', 'created_at']

// Load user lessons
$lessons = aiAgentLoadUserLessons($conn, $userId);
// Returns: ['user_id', 'lessons', 'updated_at', 'created_at']
```

### Save Functions
```php
// Save conversation
aiAgentSaveConversationMemory($conn, $userId, $conversationId, [
    'messages' => [/* messages */]
]);

// Save profile
aiAgentSaveUserProfile($conn, $userId, [
    'notes' => [],
    'behavioral_data' => []
]);

// Save lessons
aiAgentSaveUserLessons($conn, $userId, [
    'lessons' => []
]);
```

### Initialization
```php
// Auto-called on first request, but can be called manually:
aiAgentInitializeMemoryTables($conn);

// Get connection (uses existing peminjaman connection)
$conn = aiAgentGetDatabaseConnection();
```

---

## 📋 Flow Example

### User 1 sends message "Berapa stok barang?"

```php
// 1. chat.php loads existing database
require_once 'config/database.php';  // $conn = peminjaman DB

// 2. Initialize memory tables (if first request)
aiAgentInitializeMemoryTables($conn);
// Creates: ai_memory_conversations, ai_memory_profiles, ai_memory_lessons
// If already exist, skips

// 3. Load user's conversation history
$conversation = aiAgentLoadConversationMemory($conn, $userId = 1, 'default');
// SELECT messages FROM ai_memory_conversations WHERE user_id=1 AND conversation_id='default'

// 4. Process message through Hermes Agent

// 5. Save updated conversation
aiAgentSaveConversationMemory($conn, 1, 'default', [
    'messages' => [
        ['role' => 'user', 'content' => 'Berapa stok barang?'],
        ['role' => 'assistant', 'content' => 'Stok ada 50 unit']
    ]
]);
// INSERT/UPDATE into ai_memory_conversations WHERE user_id=1 AND conversation_id='default'
```

### Result
```
peminjaman database after:
ai_memory_conversations:
├── id=1, user_id=1, conversation_id='default', messages=[...], updated_at=NOW

ai_memory_profiles:
├── id=1, user_id=1, profile_data={...}, updated_at=NOW

(All linked by user_id = 1)
```

---

## 🎯 Benefits vs Old Approach

| Feature | Old (Separate DB) | NEW (Integrated) |
|---------|------------------|------------------|
| **Setup** | Complex: Separate DB + config | Simple: Auto ✓ |
| **Database** | `ai_agent_memories` | Existing `peminjaman` |
| **Tables** | 5 tables (conversations, profiles, lessons, reflections, cleanup_log) | 3 tables (minimal) |
| **Config** | Needs AI_AGENT_DB_* env vars | Zero config needed |
| **Connection** | Separate Connection object | Reuse existing $conn |
| **Linking** | user_id, role, conversation_id | user_id only ✓ |
| **Migration** | Complex script needed | Auto on first request |
| **Cleanup** | Manual or script | FK cascade delete |
| **File accumulation** | Solved | Solved ✓ |
| **Production Ready** | Yes | Yes ✓ |

---

## 📂 Files Changed

```
hermes/
├── database/integrated-memory-helper.php (NEW) - DB operations
├── chat.php (UPDATED) - Use integrated helper
├── docs/DATABASE-MEMORY-GUIDE.md (old - still useful reference)
├── database/maintenance/database-schema-helper.php (DEPRECATED - not used now)
└── database/maintenance/migrate-memory-to-database.php (DEPRECATED - not used now)
```

---

## ✅ Verification

To verify memory is working:

```sql
-- Check if tables exist
SELECT TABLE_NAME FROM information_schema.TABLES 
WHERE TABLE_SCHEMA='peminjaman' AND TABLE_NAME LIKE 'ai_memory%';
-- Should show: ai_memory_conversations, ai_memory_profiles, ai_memory_lessons

-- Check conversation history for user 1
SELECT conversation_id, messages FROM ai_memory_conversations WHERE user_id=1;

-- Check user 1 profile
SELECT profile_data FROM ai_memory_profiles WHERE user_id=1;

-- Check user 1 lessons
SELECT lessons_data FROM ai_memory_lessons WHERE user_id=1;
```

---

## 🎉 Summary

**Problem:** Too many files accumulating in `hermes/data/memory/`

**Solution:** Store everything in existing `peminjaman` database, linked via `user_id` only

**Result:**
- ✅ No file accumulation
- ✅ No separate database
- ✅ No new config needed
- ✅ Simple 3-table schema
- ✅ Foreign keys + auto-cleanup
- ✅ Production ready
- ✅ Integrated with existing system
