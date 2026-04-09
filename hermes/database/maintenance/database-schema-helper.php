<?php

/**
 * Database Schema Helper for Hermes Agent Memory Storage
 * Handles creation and management of database tables for persistent memory
 * instead of file-based storage
 */

function aiAgentInitializeMemoryDatabase(array $dbConfig = []): bool
{
    $pdo = aiAgentGetDatabaseConnection($dbConfig);
    if (!$pdo) {
        return false;
    }

    try {
        // Create conversations table
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ai_agent_memories_conversations (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'user',
            conversation_id VARCHAR(255) NOT NULL DEFAULT 'default',
            messages LONGTEXT NOT NULL COMMENT 'JSON array of messages',
            updated_at BIGINT NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT 0,
            UNIQUE KEY unique_conversation (user_id, role, conversation_id),
            INDEX idx_user_role (user_id, role),
            INDEX idx_conversation (conversation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Create profiles table
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ai_agent_memories_profiles (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'user',
            notes LONGTEXT NOT NULL COMMENT 'JSON array of profile notes',
            behavioral_data LONGTEXT COMMENT 'JSON object with behavioral analysis',
            updated_at BIGINT NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT 0,
            UNIQUE KEY unique_profile (user_id, role),
            INDEX idx_user_role (user_id, role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Create lessons table
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ai_agent_memories_lessons (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'user',
            lessons LONGTEXT NOT NULL COMMENT 'JSON array of learned patterns',
            updated_at BIGINT NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT 0,
            UNIQUE KEY unique_lessons (user_id, role),
            INDEX idx_user_role (user_id, role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Create reflections table (system-wide self-improvement)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ai_agent_memories_reflections (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            reflection_type VARCHAR(100) NOT NULL,
            reflection_data LONGTEXT NOT NULL COMMENT 'JSON object with reflection details',
            created_at BIGINT NOT NULL DEFAULT 0,
            INDEX idx_type (reflection_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Create cleanup log table for tracking archived/deleted memories
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ai_agent_memories_cleanup_log (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            role VARCHAR(50) NOT NULL,
            item_type VARCHAR(50) NOT NULL COMMENT 'conversation|profile|lesson',
            item_id VARCHAR(255),
            action VARCHAR(20) NOT NULL COMMENT 'archived|deleted',
            reason VARCHAR(255),
            archived_data LONGTEXT COMMENT 'JSON backup of archived data',
            created_at BIGINT NOT NULL DEFAULT 0,
            INDEX idx_user_action (user_id, action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        return true;
    } catch (PDOException $e) {
        error_log('Failed to initialize memory database: ' . $e->getMessage());
        return false;
    }
}

function aiAgentGetDatabaseConnection(array $dbConfig = []): ?PDO
{
    static $connections = [];

    $host = $dbConfig['host'] ?? getenv('AI_AGENT_DB_HOST') ?: 'localhost';
    $port = $dbConfig['port'] ?? getenv('AI_AGENT_DB_PORT') ?: 3306;
    $database = $dbConfig['database'] ?? getenv('AI_AGENT_DB_NAME') ?: 'information_schema';
    $username = $dbConfig['username'] ?? getenv('AI_AGENT_DB_USER') ?: 'root';
    $password = $dbConfig['password'] ?? getenv('AI_AGENT_DB_PASSWORD') ?: '';
    $charset = $dbConfig['charset'] ?? 'utf8mb4';

    $cacheKey = md5("{$host}:{$port}:{$database}:{$username}");

    if (isset($connections[$cacheKey])) {
        return $connections[$cacheKey];
    }

    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false,
        ]);

        // Set aggressive connection timeout
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);

        $connections[$cacheKey] = $pdo;
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        return null;
    }
}

function aiAgentGetDatabaseMemoryConfig(array $config = []): array
{
    return [
        'enabled' => getenv('AI_AGENT_MEMORY_DATABASE_ENABLED') !== false
            ? filter_var(getenv('AI_AGENT_MEMORY_DATABASE_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : false,
        'host' => trim((string) (getenv('AI_AGENT_DB_HOST') ?: 'localhost')),
        'port' => (int) (getenv('AI_AGENT_DB_PORT') ?: 3306),
        'database' => trim((string) (getenv('AI_AGENT_DB_NAME') ?: 'information_schema')),
        'username' => trim((string) (getenv('AI_AGENT_DB_USER') ?: 'root')),
        'password' => (string) (getenv('AI_AGENT_DB_PASSWORD') ?: ''),
        'charset' => 'utf8mb4',
        'auto_initialize' => getenv('AI_AGENT_MEMORY_DB_AUTO_INIT') !== false
            ? filter_var(getenv('AI_AGENT_MEMORY_DB_AUTO_INIT'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'fallback_to_files' => getenv('AI_AGENT_MEMORY_DB_FALLBACK_TO_FILES') !== false
            ? filter_var(getenv('AI_AGENT_MEMORY_DB_FALLBACK_TO_FILES'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'max_connections' => max(1, (int) (getenv('AI_AGENT_MEMORY_DB_MAX_CONNECTIONS') ?: 10)),
        'connection_timeout' => max(1, (int) (getenv('AI_AGENT_MEMORY_DB_CONNECTION_TIMEOUT') ?: 5)),
    ];
}

function aiAgentWriteConversationToDatabase(
    PDO $pdo,
    int $userId,
    string $role,
    string $conversationId,
    array $conversationData
): bool {
    try {
        $messages = json_encode($conversationData['messages'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = time();

        $stmt = $pdo->prepare(
            "INSERT INTO ai_agent_memories_conversations 
            (user_id, role, conversation_id, messages, updated_at, created_at)
            VALUES (:user_id, :role, :conversation_id, :messages, :updated_at, :created_at)
            ON DUPLICATE KEY UPDATE 
                messages = VALUES(messages),
                updated_at = VALUES(updated_at)"
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':role' => substr($role, 0, 50),
            ':conversation_id' => substr($conversationId, 0, 255),
            ':messages' => $messages,
            ':updated_at' => $now,
            ':created_at' => $conversationData['created_at'] ?? $now,
        ]);
    } catch (PDOException $e) {
        error_log('Failed to write conversation to database: ' . $e->getMessage());
        return false;
    }
}

function aiAgentReadConversationFromDatabase(
    PDO $pdo,
    int $userId,
    string $role,
    string $conversationId
): array {
    try {
        $stmt = $pdo->prepare(
            "SELECT messages, updated_at, created_at FROM ai_agent_memories_conversations
            WHERE user_id = :user_id AND role = :role AND conversation_id = :conversation_id
            LIMIT 1"
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':role' => $role,
            ':conversation_id' => $conversationId,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return [
                'conversation_id' => $conversationId,
                'role' => $role,
                'user_id' => $userId,
                'messages' => [],
                'updated_at' => 0,
            ];
        }

        return [
            'conversation_id' => $conversationId,
            'role' => $role,
            'user_id' => $userId,
            'messages' => json_decode($row['messages'], true) ?: [],
            'updated_at' => (int) $row['updated_at'],
            'created_at' => (int) $row['created_at'],
        ];
    } catch (PDOException $e) {
        error_log('Failed to read conversation from database: ' . $e->getMessage());
        return [
            'conversation_id' => $conversationId,
            'role' => $role,
            'user_id' => $userId,
            'messages' => [],
            'updated_at' => 0,
        ];
    }
}

function aiAgentWriteProfileToDatabase(
    PDO $pdo,
    int $userId,
    string $role,
    array $profileData
): bool {
    try {
        $notes = json_encode($profileData['notes'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $behavioralData = json_encode($profileData['behavioral_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = time();

        $stmt = $pdo->prepare(
            "INSERT INTO ai_agent_memories_profiles
            (user_id, role, notes, behavioral_data, updated_at, created_at)
            VALUES (:user_id, :role, :notes, :behavioral_data, :updated_at, :created_at)
            ON DUPLICATE KEY UPDATE
                notes = VALUES(notes),
                behavioral_data = VALUES(behavioral_data),
                updated_at = VALUES(updated_at)"
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':role' => substr($role, 0, 50),
            ':notes' => $notes,
            ':behavioral_data' => $behavioralData,
            ':updated_at' => $now,
            ':created_at' => $profileData['created_at'] ?? $now,
        ]);
    } catch (PDOException $e) {
        error_log('Failed to write profile to database: ' . $e->getMessage());
        return false;
    }
}

function aiAgentReadProfileFromDatabase(PDO $pdo, int $userId, string $role): array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT notes, behavioral_data, updated_at, created_at FROM ai_agent_memories_profiles
            WHERE user_id = :user_id AND role = :role
            LIMIT 1"
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':role' => $role,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return [
                'role' => $role,
                'user_id' => $userId,
                'notes' => [],
                'updated_at' => 0,
            ];
        }

        return [
            'role' => $role,
            'user_id' => $userId,
            'notes' => json_decode($row['notes'], true) ?: [],
            'behavioral_data' => json_decode($row['behavioral_data'], true) ?: [],
            'updated_at' => (int) $row['updated_at'],
            'created_at' => (int) $row['created_at'],
        ];
    } catch (PDOException $e) {
        error_log('Failed to read profile from database: ' . $e->getMessage());
        return [
            'role' => $role,
            'user_id' => $userId,
            'notes' => [],
            'updated_at' => 0,
        ];
    }
}

function aiAgentWriteLessonsToDatabase(
    PDO $pdo,
    int $userId,
    string $role,
    array $lessonsData
): bool {
    try {
        $lessons = json_encode($lessonsData['lessons'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = time();

        $stmt = $pdo->prepare(
            "INSERT INTO ai_agent_memories_lessons
            (user_id, role, lessons, updated_at, created_at)
            VALUES (:user_id, :role, :lessons, :updated_at, :created_at)
            ON DUPLICATE KEY UPDATE
                lessons = VALUES(lessons),
                updated_at = VALUES(updated_at)"
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':role' => substr($role, 0, 50),
            ':lessons' => $lessons,
            ':updated_at' => $now,
            ':created_at' => $lessonsData['created_at'] ?? $now,
        ]);
    } catch (PDOException $e) {
        error_log('Failed to write lessons to database: ' . $e->getMessage());
        return false;
    }
}

function aiAgentReadLessonsFromDatabase(PDO $pdo, int $userId, string $role): array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT lessons, updated_at, created_at FROM ai_agent_memories_lessons
            WHERE user_id = :user_id AND role = :role
            LIMIT 1"
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':role' => $role,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return [
                'role' => $role,
                'user_id' => $userId,
                'lessons' => [],
                'updated_at' => 0,
            ];
        }

        return [
            'role' => $role,
            'user_id' => $userId,
            'lessons' => json_decode($row['lessons'], true) ?: [],
            'updated_at' => (int) $row['updated_at'],
            'created_at' => (int) $row['created_at'],
        ];
    } catch (PDOException $e) {
        error_log('Failed to read lessons from database: ' . $e->getMessage());
        return [
            'role' => $role,
            'user_id' => $userId,
            'lessons' => [],
            'updated_at' => 0,
        ];
    }
}

function aiAgentWriteReflectionToDatabase(
    PDO $pdo,
    string $reflectionType,
    array $reflectionData
): bool {
    try {
        $data = json_encode($reflectionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $pdo->prepare(
            "INSERT INTO ai_agent_memories_reflections
            (reflection_type, reflection_data, created_at)
            VALUES (:reflection_type, :reflection_data, :created_at)"
        );

        return $stmt->execute([
            ':reflection_type' => substr($reflectionType, 0, 100),
            ':reflection_data' => $data,
            ':created_at' => time(),
        ]);
    } catch (PDOException $e) {
        error_log('Failed to write reflection to database: ' . $e->getMessage());
        return false;
    }
}

function aiAgentCleanupOldConversations(
    PDO $pdo,
    int $userId,
    string $role,
    int $maxConversations = 20,
    int $archiveReason = 0
): int {
    try {
        // Get conversations count
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) as cnt FROM ai_agent_memories_conversations
            WHERE user_id = :user_id AND role = :role"
        );
        $countStmt->execute([':user_id' => $userId, ':role' => $role]);
        $count = (int) $countStmt->fetch()['cnt'];

        if ($count <= $maxConversations) {
            return 0;
        }

        $toDelete = $count - $maxConversations;

        // Get old conversations
        $getStmt = $pdo->prepare(
            "SELECT id, conversation_id, messages FROM ai_agent_memories_conversations
            WHERE user_id = :user_id AND role = :role
            ORDER BY updated_at ASC
            LIMIT :limit"
        );
        $getStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $getStmt->bindValue(':role', $role, PDO::PARAM_STR);
        $getStmt->bindValue(':limit', $toDelete, PDO::PARAM_INT);
        $getStmt->execute();

        $oldRows = $getStmt->fetchAll();

        // Archive each old conversation
        $archiveStmt = $pdo->prepare(
            "INSERT INTO ai_agent_memories_cleanup_log
            (user_id, role, item_type, item_id, action, archived_data, created_at)
            VALUES (:user_id, :role, :item_type, :item_id, :action, :archived_data, :created_at)"
        );

        $deleteIds = [];
        foreach ($oldRows as $row) {
            $archiveStmt->execute([
                ':user_id' => $userId,
                ':role' => $role,
                ':item_type' => 'conversation',
                ':item_id' => $row['conversation_id'],
                ':action' => 'archived',
                ':archived_data' => $row['messages'],
                ':created_at' => time(),
            ]);
            $deleteIds[] = $row['id'];
        }

        // Delete old rows
        if (!empty($deleteIds)) {
            $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
            $delStmt = $pdo->prepare(
                "DELETE FROM ai_agent_memories_conversations WHERE id IN ({$placeholders})"
            );
            $delStmt->execute($deleteIds);
        }

        return count($oldRows);
    } catch (PDOException $e) {
        error_log('Failed to cleanup old conversations: ' . $e->getMessage());
        return 0;
    }
}
