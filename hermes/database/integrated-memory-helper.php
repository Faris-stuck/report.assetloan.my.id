<?php

/**
 * Integrated Memory Helper
 * Uses the existing "peminjaman" MySQL database and stores Hermes memory in
 * lightweight ai_memory_* tables linked by user_id.
 */

function aiAgentInitializeIntegratedMemoryTables($conn): bool
{
    if (!$conn instanceof mysqli) {
        return false;
    }

    try {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS ai_memory_conversations (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            conversation_id VARCHAR(255) NOT NULL DEFAULT 'default',
            messages LONGTEXT NOT NULL COMMENT 'JSON array of messages',
            updated_at BIGINT NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT 0,
            UNIQUE KEY unique_conv (user_id, conversation_id),
            INDEX idx_user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS ai_memory_profiles (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL UNIQUE,
            profile_data LONGTEXT NOT NULL COMMENT 'JSON object with user preferences and behavioral analysis',
            updated_at BIGINT NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS ai_memory_lessons (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL UNIQUE,
            lessons_data LONGTEXT NOT NULL COMMENT 'JSON array of learned patterns',
            updated_at BIGINT NOT NULL DEFAULT 0,
            created_at BIGINT NOT NULL DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->query(
            "CREATE TABLE IF NOT EXISTS ai_memory_reflections (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            conversation_id VARCHAR(255) NOT NULL DEFAULT 'default',
            reflection_type VARCHAR(100) NOT NULL DEFAULT 'chat_turn',
            reflection_data LONGTEXT NOT NULL COMMENT 'JSON object with reflection details',
            created_at BIGINT NOT NULL DEFAULT 0,
            INDEX idx_user_id (user_id),
            INDEX idx_conversation_id (conversation_id),
            INDEX idx_reflection_type (reflection_type),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        return true;
    } catch (Throwable $e) {
        error_log('Failed to initialize integrated memory tables: ' . $e->getMessage());
        return false;
    }
}

function aiAgentGetIntegratedMemoryConnection(): ?mysqli
{
    static $cachedConnection = null;

    if ($cachedConnection instanceof mysqli && @$cachedConnection->ping()) {
        return $cachedConnection;
    }

    global $conn;
    if (isset($conn) && $conn instanceof mysqli && @$conn->ping()) {
        $cachedConnection = $conn;
        return $cachedConnection;
    }

    try {
        $dbHost = getenv('DB_HOST') ?: 'localhost';
        $dbName = getenv('DB_NAME') ?: 'peminjaman';
        $dbUser = getenv('DB_USER') ?: 'root';
        $dbPass = getenv('DB_PASSWORD') ?: '';

        $createdConnection = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($createdConnection->connect_error) {
            error_log('Integrated memory connection failed: ' . $createdConnection->connect_error);
            return null;
        }

        $createdConnection->set_charset('utf8mb4');
        $cachedConnection = $createdConnection;

        return $cachedConnection;
    } catch (Throwable $e) {
        error_log('Failed to create integrated memory connection: ' . $e->getMessage());
        return null;
    }
}

function aiAgentIntegratedSaveConversationMemory(
    $conn,
    int $userId,
    string $conversationId,
    array $conversationData
): bool {
    if (!$conn instanceof mysqli) {
        return false;
    }

    try {
        $messages = json_encode($conversationData['messages'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = time();
        $createdAt = (int) ($conversationData['created_at'] ?? $now);
        $updatedAt = (int) ($conversationData['updated_at'] ?? $now);

        $stmt = $conn->prepare(
            "INSERT INTO ai_memory_conversations (user_id, conversation_id, messages, updated_at, created_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE messages = VALUES(messages), updated_at = VALUES(updated_at)"
        );

        if (!$stmt) {
            error_log('Prepare failed saving integrated conversation memory: ' . $conn->error);
            return false;
        }

        $stmt->bind_param('issii', $userId, $conversationId, $messages, $updatedAt, $createdAt);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Throwable $e) {
        error_log('Failed to save integrated conversation memory: ' . $e->getMessage());
        return false;
    }
}

function aiAgentIntegratedLoadConversationMemory(
    $conn,
    int $userId,
    string $conversationId = 'default'
): array {
    $fallback = [
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'messages' => [],
        'updated_at' => 0,
        'created_at' => 0,
    ];

    if (!$conn instanceof mysqli) {
        return $fallback;
    }

    try {
        $stmt = $conn->prepare(
            "SELECT messages, updated_at, created_at FROM ai_memory_conversations
            WHERE user_id = ? AND conversation_id = ? LIMIT 1"
        );

        if (!$stmt) {
            error_log('Prepare failed loading integrated conversation memory: ' . $conn->error);
            return $fallback;
        }

        $stmt->bind_param('is', $userId, $conversationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return $fallback;
        }

        return [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'messages' => json_decode((string) ($row['messages'] ?? ''), true) ?: [],
            'updated_at' => (int) ($row['updated_at'] ?? 0),
            'created_at' => (int) ($row['created_at'] ?? 0),
        ];
    } catch (Throwable $e) {
        error_log('Failed to load integrated conversation memory: ' . $e->getMessage());
        return $fallback;
    }
}

function aiAgentIntegratedListConversationMemories(
    $conn,
    int $userId,
    string $excludeConversationId = '',
    ?int $limit = null
): array {
    if (!$conn instanceof mysqli) {
        return [];
    }

    $limit = $limit === null ? 20 : max(1, $limit);
    $states = [];

    try {
        if ($excludeConversationId !== '') {
            $stmt = $conn->prepare(
                "SELECT conversation_id, messages, updated_at, created_at
                FROM ai_memory_conversations
                WHERE user_id = ? AND conversation_id <> ?
                ORDER BY updated_at DESC, id DESC
                LIMIT ?"
            );

            if (!$stmt) {
                error_log('Prepare failed listing integrated conversation memories: ' . $conn->error);
                return [];
            }

            $stmt->bind_param('isi', $userId, $excludeConversationId, $limit);
        } else {
            $stmt = $conn->prepare(
                "SELECT conversation_id, messages, updated_at, created_at
                FROM ai_memory_conversations
                WHERE user_id = ?
                ORDER BY updated_at DESC, id DESC
                LIMIT ?"
            );

            if (!$stmt) {
                error_log('Prepare failed listing integrated conversation memories: ' . $conn->error);
                return [];
            }

            $stmt->bind_param('ii', $userId, $limit);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        while ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
            $states[] = [
                'conversation_id' => (string) ($row['conversation_id'] ?? 'default'),
                'user_id' => $userId,
                'messages' => json_decode((string) ($row['messages'] ?? ''), true) ?: [],
                'updated_at' => (int) ($row['updated_at'] ?? 0),
                'created_at' => (int) ($row['created_at'] ?? 0),
            ];
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log('Failed to list integrated conversation memories: ' . $e->getMessage());
        return [];
    }

    return $states;
}

function aiAgentIntegratedDeleteConversationMemory(
    $conn,
    int $userId,
    string $conversationId
): bool {
    $result = aiAgentIntegratedDeleteConversationArtifacts($conn, $userId, $conversationId);
    return !empty($result['success']);
}

function aiAgentIntegratedDeleteConversationArtifacts(
    $conn,
    int $userId,
    string $conversationId
): array {
    $summary = [
        'success' => false,
        'storage' => 'integrated_db',
        'conversation_found' => false,
        'conversation_deleted' => false,
        'conversation_rows_deleted' => 0,
        'reflection_rows_deleted' => 0,
    ];

    if (!$conn instanceof mysqli) {
        return $summary;
    }

    try {
        $checkStmt = $conn->prepare(
            "SELECT id FROM ai_memory_conversations
            WHERE user_id = ? AND conversation_id = ?
            LIMIT 1"
        );

        if (!$checkStmt) {
            error_log('Prepare failed checking integrated conversation memory before delete: ' . $conn->error);
            return $summary;
        }

        $checkStmt->bind_param('is', $userId, $conversationId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $summary['conversation_found'] = $checkResult instanceof mysqli_result && $checkResult->fetch_assoc() !== null;
        $checkStmt->close();

        if (empty($summary['conversation_found'])) {
            return $summary;
        }

        $conn->begin_transaction();

        $reflectionStmt = $conn->prepare(
            "DELETE FROM ai_memory_reflections
            WHERE user_id = ? AND conversation_id = ?"
        );

        if (!$reflectionStmt) {
            throw new RuntimeException('Prepare failed deleting integrated conversation reflections: ' . $conn->error);
        }

        $reflectionStmt->bind_param('is', $userId, $conversationId);
        $reflectionStmt->execute();
        $summary['reflection_rows_deleted'] = max(0, (int) $reflectionStmt->affected_rows);
        $reflectionStmt->close();

        $conversationStmt = $conn->prepare(
            "DELETE FROM ai_memory_conversations
            WHERE user_id = ? AND conversation_id = ?"
        );

        if (!$conversationStmt) {
            throw new RuntimeException('Prepare failed deleting integrated conversation memory: ' . $conn->error);
        }

        $conversationStmt->bind_param('is', $userId, $conversationId);
        $conversationStmt->execute();
        $summary['conversation_rows_deleted'] = max(0, (int) $conversationStmt->affected_rows);
        $conversationStmt->close();

        if ($summary['conversation_rows_deleted'] <= 0) {
            $conn->rollback();
            return $summary;
        }

        $conn->commit();
        $summary['conversation_deleted'] = true;
        $summary['success'] = true;

        return $summary;
    } catch (Throwable $e) {
        if ($conn instanceof mysqli && $conn->errno === 0) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                error_log('Failed to rollback integrated conversation delete: ' . $rollbackError->getMessage());
            }
        } else {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                error_log('Failed to rollback integrated conversation delete: ' . $rollbackError->getMessage());
            }
        }
        error_log('Failed to delete integrated conversation artifacts: ' . $e->getMessage());
        return $summary;
    }
}

function aiAgentIntegratedSaveUserProfile(
    $conn,
    int $userId,
    array $profileData
): bool {
    if (!$conn instanceof mysqli) {
        return false;
    }

    try {
        $profileJson = json_encode($profileData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = time();
        $createdAt = (int) ($profileData['created_at'] ?? $now);
        $updatedAt = (int) ($profileData['updated_at'] ?? $now);

        $stmt = $conn->prepare(
            "INSERT INTO ai_memory_profiles (user_id, profile_data, updated_at, created_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE profile_data = VALUES(profile_data), updated_at = VALUES(updated_at)"
        );

        if (!$stmt) {
            error_log('Prepare failed saving integrated profile memory: ' . $conn->error);
            return false;
        }

        $stmt->bind_param('isii', $userId, $profileJson, $updatedAt, $createdAt);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Throwable $e) {
        error_log('Failed to save integrated profile memory: ' . $e->getMessage());
        return false;
    }
}

function aiAgentIntegratedLoadUserProfile(
    $conn,
    int $userId
): array {
    $fallback = [
        'user_id' => $userId,
        'notes' => [],
        'behavioral_data' => [],
        'updated_at' => 0,
        'created_at' => 0,
    ];

    if (!$conn instanceof mysqli) {
        return $fallback;
    }

    try {
        $stmt = $conn->prepare(
            "SELECT profile_data, updated_at, created_at FROM ai_memory_profiles
            WHERE user_id = ? LIMIT 1"
        );

        if (!$stmt) {
            error_log('Prepare failed loading integrated profile memory: ' . $conn->error);
            return $fallback;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return $fallback;
        }

        $decoded = json_decode((string) ($row['profile_data'] ?? ''), true) ?: [];
        return [
            'user_id' => $userId,
            'notes' => isset($decoded['notes']) && is_array($decoded['notes']) ? $decoded['notes'] : [],
            'behavioral_data' => isset($decoded['behavioral_data']) && is_array($decoded['behavioral_data'])
                ? $decoded['behavioral_data']
                : [],
            'updated_at' => (int) ($row['updated_at'] ?? 0),
            'created_at' => (int) ($row['created_at'] ?? 0),
        ];
    } catch (Throwable $e) {
        error_log('Failed to load integrated profile memory: ' . $e->getMessage());
        return $fallback;
    }
}

function aiAgentIntegratedSaveUserLessons(
    $conn,
    int $userId,
    array $lessonsData
): bool {
    if (!$conn instanceof mysqli) {
        return false;
    }

    try {
        $lessonsJson = json_encode($lessonsData['lessons'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = time();
        $createdAt = (int) ($lessonsData['created_at'] ?? $now);
        $updatedAt = (int) ($lessonsData['updated_at'] ?? $now);

        $stmt = $conn->prepare(
            "INSERT INTO ai_memory_lessons (user_id, lessons_data, updated_at, created_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE lessons_data = VALUES(lessons_data), updated_at = VALUES(updated_at)"
        );

        if (!$stmt) {
            error_log('Prepare failed saving integrated lessons memory: ' . $conn->error);
            return false;
        }

        $stmt->bind_param('isii', $userId, $lessonsJson, $updatedAt, $createdAt);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Throwable $e) {
        error_log('Failed to save integrated lessons memory: ' . $e->getMessage());
        return false;
    }
}

function aiAgentIntegratedLoadUserLessons(
    $conn,
    int $userId
): array {
    $fallback = [
        'user_id' => $userId,
        'lessons' => [],
        'updated_at' => 0,
        'created_at' => 0,
    ];

    if (!$conn instanceof mysqli) {
        return $fallback;
    }

    try {
        $stmt = $conn->prepare(
            "SELECT lessons_data, updated_at, created_at FROM ai_memory_lessons
            WHERE user_id = ? LIMIT 1"
        );

        if (!$stmt) {
            error_log('Prepare failed loading integrated lessons memory: ' . $conn->error);
            return $fallback;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return $fallback;
        }

        return [
            'user_id' => $userId,
            'lessons' => json_decode((string) ($row['lessons_data'] ?? ''), true) ?: [],
            'updated_at' => (int) ($row['updated_at'] ?? 0),
            'created_at' => (int) ($row['created_at'] ?? 0),
        ];
    } catch (Throwable $e) {
        error_log('Failed to load integrated lessons memory: ' . $e->getMessage());
        return $fallback;
    }
}

function aiAgentIntegratedSaveReflectionLog($conn, array $payload): bool
{
    if (!$conn instanceof mysqli) {
        return false;
    }

    try {
        $reflectionData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($reflectionData) || $reflectionData === '') {
            $reflectionData = '{}';
        }

        $userId = max(0, (int) ($payload['user_id'] ?? 0));
        $conversationId = trim((string) ($payload['conversation_id'] ?? 'default'));
        if ($conversationId === '') {
            $conversationId = 'default';
        }

        $reflectionType = trim((string) ($payload['reflection_type'] ?? 'chat_turn'));
        if ($reflectionType === '') {
            $reflectionType = 'chat_turn';
        }

        $createdAt = (int) ($payload['timestamp'] ?? time());

        $stmt = $conn->prepare(
            "INSERT INTO ai_memory_reflections (user_id, conversation_id, reflection_type, reflection_data, created_at)
            VALUES (?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            error_log('Prepare failed saving integrated reflection log: ' . $conn->error);
            return false;
        }

        $stmt->bind_param('isssi', $userId, $conversationId, $reflectionType, $reflectionData, $createdAt);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Throwable $e) {
        error_log('Failed to save integrated reflection log: ' . $e->getMessage());
        return false;
    }
}

function aiAgentIntegratedGetMemoryTablesStatus($conn): array
{
    $tableNames = [
        'ai_memory_conversations',
        'ai_memory_profiles',
        'ai_memory_lessons',
        'ai_memory_reflections',
    ];

    $status = [];
    foreach ($tableNames as $tableName) {
        $status[$tableName] = [
            'exists' => false,
            'rows' => null,
        ];
    }

    if (!$conn instanceof mysqli) {
        return $status;
    }

    foreach ($tableNames as $tableName) {
        try {
            $exists = false;
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS total
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
            );
            if ($stmt) {
                $stmt->bind_param('s', $tableName);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
                $exists = isset($row['total']) && (int) $row['total'] > 0;
                $stmt->close();
            }

            $status[$tableName]['exists'] = $exists;
            if ($exists) {
                $countResult = $conn->query('SELECT COUNT(*) AS total FROM ' . $tableName);
                if ($countResult instanceof mysqli_result) {
                    $row = $countResult->fetch_assoc();
                    $status[$tableName]['rows'] = isset($row['total']) ? (int) $row['total'] : 0;
                    $countResult->free();
                }
            }
        } catch (Throwable $e) {
            error_log('Failed checking integrated memory table status for ' . $tableName . ': ' . $e->getMessage());
        }
    }

    return $status;
}
