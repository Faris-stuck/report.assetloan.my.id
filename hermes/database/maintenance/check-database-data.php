<?php

require_once __DIR__ . '/../../../config/database.php';

echo "\n=== DATA IN PEMINJAMAN DATABASE ===\n\n";

// Check ai_memory_conversations
echo "[1] Conversations:\n";
$result = $conn->query('SELECT user_id, conversation_id, SUBSTR(messages, 1, 80) as messages_preview, FROM_UNIXTIME(updated_at) as updated FROM ai_memory_conversations');
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  user_id=" . $row['user_id'] . " | conversation=\"" . $row['conversation_id'] . "\" | preview: " . $row['messages_preview'] . "...\n";
    }
} else {
    echo "  (no data)\n";
}

// Check ai_memory_profiles
echo "\n[2] Profiles:\n";
$result = $conn->query('SELECT user_id, SUBSTR(profile_data, 1, 80) as profile_preview, FROM_UNIXTIME(updated_at) as updated FROM ai_memory_profiles');
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  user_id=" . $row['user_id'] . " | preview: " . $row['profile_preview'] . "...\n";
    }
} else {
    echo "  (no data)\n";
}

// Check ai_memory_lessons
echo "\n[3] Lessons:\n";
$result = $conn->query('SELECT user_id, SUBSTR(lessons_data, 1, 80) as lessons_preview, FROM_UNIXTIME(updated_at) as updated FROM ai_memory_lessons');
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  user_id=" . $row['user_id'] . " | preview: " . $row['lessons_preview'] . "...\n";
    }
} else {
    echo "  (no data)\n";
}

// Show count summary
echo "\n[4] Summary:\n";
$convCount = $conn->query('SELECT COUNT(*) FROM ai_memory_conversations')->fetch_row()[0];
$profileCount = $conn->query('SELECT COUNT(*) FROM ai_memory_profiles')->fetch_row()[0];
$lessonCount = $conn->query('SELECT COUNT(*) FROM ai_memory_lessons')->fetch_row()[0];
echo "  Conversations: " . $convCount . " rows\n";
echo "  Profiles: " . $profileCount . " rows\n";
echo "  Lessons: " . $lessonCount . " rows\n";

// Show table sizes
echo "\n[5] Database space usage:\n";
$result = $conn->query("
    SELECT 
        table_name,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
    FROM information_schema.TABLES 
    WHERE table_schema = DATABASE() 
    AND table_name LIKE 'ai_memory%'
    ORDER BY table_name
");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  " . $row['table_name'] . ": " . $row['size_mb'] . " MB\n";
    }
} else {
    echo "  (no data)\n";
}

echo "\n";
