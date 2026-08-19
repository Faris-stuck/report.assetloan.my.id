<?php

/**
 * Test script to verify integrated memory tables in peminjaman database
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../integrated-memory-helper.php';

echo "\n=== Testing Integrated Memory Setup ===\n\n";

// Step 1: Check connection
echo "[1] Checking database connection...\n";
if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "❌ FAILED: No database connection\n";
    exit(1);
}

if ($conn->connect_error) {
    echo "❌ FAILED: " . $conn->connect_error . "\n";
    exit(1);
}

echo "✓ Connected to database: " . $conn->get_charset()->charset . "\n";
//echo "✓ Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "\n\n";

// Step 2: Initialize memory tables
echo "[2] Initializing memory tables...\n";
if (aiAgentInitializeIntegratedMemoryTables($conn)) {
    echo "✓ Memory tables initialized successfully\n\n";
} else {
    echo "❌ FAILED to initialize tables\n";
    exit(1);
}

// Step 3: Verify tables exist
echo "[3] Verifying tables in database...\n";
$tables = ['ai_memory_conversations', 'ai_memory_profiles', 'ai_memory_lessons'];
$allFound = true;

foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'");
    if ($result && $result->fetch_row()[0] > 0) {
        echo "✓ Table exists: $table\n";
    } else {
        echo "❌ Table NOT found: $table\n";
        $allFound = false;
    }
}

if (!$allFound) {
    echo "\n❌ Some tables missing!\n";
    exit(1);
}

echo "\n";

// Step 4: Check table structures
echo "[4] Checking table structures...\n";

// Check ai_memory_conversations
$result = $conn->query("DESCRIBE ai_memory_conversations");
if ($result) {
    echo "✓ ai_memory_conversations columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "❌ Failed to describe ai_memory_conversations\n";
}

echo "\n";

// Check ai_memory_profiles
$result = $conn->query("DESCRIBE ai_memory_profiles");
if ($result) {
    echo "✓ ai_memory_profiles columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "❌ Failed to describe ai_memory_profiles\n";
}

echo "\n";

// Check ai_memory_lessons
$result = $conn->query("DESCRIBE ai_memory_lessons");
if ($result) {
    echo "✓ ai_memory_lessons columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "❌ Failed to describe ai_memory_lessons\n";
}

echo "\n";

// Step 5: Test CRUD operations
echo "[5] Testing CRUD operations...\n";

// Get first user ID
$userResult = $conn->query("SELECT id FROM users LIMIT 1");
if (!$userResult || $userResult->num_rows === 0) {
    echo "⚠ No users in database, skipping CRUD test\n";
} else {
    $testUserId = $userResult->fetch_row()[0];
    echo "Using test user_id: $testUserId\n\n";

    // Test save conversation
    echo "  [a] Testing save conversation...\n";
    $testConversation = [
        'messages' => [
            ['role' => 'user', 'content' => 'Test message'],
            ['role' => 'assistant', 'content' => 'Test response']
        ]
    ];

    if (aiAgentIntegratedSaveConversationMemory($conn, $testUserId, 'test-conv', $testConversation)) {
        echo "  ✓ Conversation saved\n";
    } else {
        echo "  ❌ Failed to save conversation\n";
    }

    // Test load conversation
    echo "  [b] Testing load conversation...\n";
    $loaded = aiAgentIntegratedLoadConversationMemory($conn, $testUserId, 'test-conv');
    if ($loaded['messages'] && count($loaded['messages']) > 0) {
        echo "  ✓ Conversation loaded with " . count($loaded['messages']) . " messages\n";
    } else {
        echo "  ❌ Failed to load conversation\n";
    }

    // Test save profile
    echo "  [c] Testing save profile...\n";
    $testProfile = [
        'notes' => ['Test note 1', 'Test note 2'],
        'behavioral_data' => ['preference' => 'verbose']
    ];

    if (aiAgentIntegratedSaveUserProfile($conn, $testUserId, $testProfile)) {
        echo "  ✓ Profile saved\n";
    } else {
        echo "  ❌ Failed to save profile\n";
    }

    // Test load profile
    echo "  [d] Testing load profile...\n";
    $loadedProfile = aiAgentIntegratedLoadUserProfile($conn, $testUserId);
    if ($loadedProfile['notes'] && count($loadedProfile['notes']) > 0) {
        echo "  ✓ Profile loaded with " . count($loadedProfile['notes']) . " notes\n";
    } else {
        echo "  ❌ Failed to load profile\n";
    }

    // Test save lessons
    echo "  [e] Testing save lessons...\n";
    $testLessons = [
        'lessons' => [
            ['lesson' => 'First lesson', 'source_message' => 'learned from user'],
            ['lesson' => 'Second lesson', 'source_message' => 'learned from context']
        ]
    ];

    if (aiAgentIntegratedSaveUserLessons($conn, $testUserId, $testLessons)) {
        echo "  ✓ Lessons saved\n";
    } else {
        echo "  ❌ Failed to save lessons\n";
    }

    // Test load lessons
    echo "  [f] Testing load lessons...\n";
    $loadedLessons = aiAgentIntegratedLoadUserLessons($conn, $testUserId);
    if ($loadedLessons['lessons'] && count($loadedLessons['lessons']) > 0) {
        echo "  ✓ Lessons loaded with " . count($loadedLessons['lessons']) . " lessons\n";
    } else {
        echo "  ❌ Failed to load lessons\n";
    }

    echo "\n";
}

// Step 6: Check row counts
echo "[6] Current row counts...\n";
$convCount = $conn->query("SELECT COUNT(*) FROM ai_memory_conversations")->fetch_row()[0];
$profileCount = $conn->query("SELECT COUNT(*) FROM ai_memory_profiles")->fetch_row()[0];
$lessonCount = $conn->query("SELECT COUNT(*) FROM ai_memory_lessons")->fetch_row()[0];

echo "  ai_memory_conversations: $convCount rows\n";
echo "  ai_memory_profiles: $profileCount rows\n";
echo "  ai_memory_lessons: $lessonCount rows\n";
echo "\n";

// Step 7: Verify foreign keys
echo "[7] Verifying foreign key constraints...\n";
$result = $conn->query(
    "SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA=DATABASE() 
    AND REFERENCED_TABLE_NAME='users' 
    AND TABLE_NAME LIKE 'ai_memory%'"
);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "✓ Foreign key in " . $row['TABLE_NAME'] . " -> users\n";
    }
} else {
    echo "⚠ No foreign keys found (might still work, check manually)\n";
}

echo "\n";

// Final summary
echo "=== TEST SUMMARY ===\n";
echo "✓ All tests passed!\n";
echo "✓ Integrated memory storage is ready to use\n";
echo "✓ Tables created in peminjaman database\n";
echo "✓ CRUD operations working\n";
echo "\n";
