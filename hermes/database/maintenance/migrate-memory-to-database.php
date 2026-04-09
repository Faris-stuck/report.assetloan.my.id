<?php

/**
 * Migration Script: Migrate Hermes Agent Memory from file-based storage into
 * the same integrated MySQL tables used by the Hermes chat runtime.
 *
 * Usage: php hermes/database/maintenance/migrate-memory-to-database.php [options]
 * Options:
 *   --dry-run                Show what would be migrated without writing to DB
 *   --backup                 Create a .bak copy before deleting original files
 *   --verbose                Show detailed migration progress
 *   --delete-after-migrate   Delete files after successful migration
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../model/config-helper.php';
require_once __DIR__ . '/../../engine/runtime-helper.php';
require_once __DIR__ . '/../../engine/summarization-helper.php';
require_once __DIR__ . '/../../memory/memory-helper.php';
require_once __DIR__ . '/../../database/integrated-memory-helper.php';

function aiAgentFinalizeMigratedSourceFile(string $filePath, bool $shouldBackup, bool $deleteAfter): void
{
    if ($shouldBackup) {
        @copy($filePath, $filePath . '.bak');
    }

    if ($deleteAfter) {
        @unlink($filePath);
    }
}

// Parse command line arguments
$isDryRun = in_array('--dry-run', $argv, true);
$shouldBackup = in_array('--backup', $argv, true);
$isVerbose = in_array('--verbose', $argv, true);
$deleteAfter = in_array('--delete-after-migrate', $argv, true);

echo "\n=== Hermes Agent Memory Migration Tool ===\n";
echo "Mode: " . ($isDryRun ? "DRY RUN" : "LIVE MIGRATION") . "\n";
echo "Backup: " . ($shouldBackup ? "ENABLED" : "DISABLED") . "\n";
echo "Delete after: " . ($deleteAfter ? "YES" : "NO") . "\n\n";

// Load config
$config = aiAgentLoadConfig([
    __DIR__ . '/../../config/ai_agent.php',
]);

$memoryConfig = aiAgentGetMemoryConfig($config);

// Verify file storage path exists
$fileStorageDir = $memoryConfig['storage_dir'];
if (!is_dir($fileStorageDir)) {
    echo "[ERROR] File storage directory not found: {$fileStorageDir}\n";
    exit(1);
}

$memoryConn = null;
if (!$isDryRun) {
    require_once __DIR__ . '/../../../config/database.php';

    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno && @$conn->ping()) {
        $memoryConn = $conn;
    } else {
        $memoryConn = aiAgentGetIntegratedMemoryConnection();
    }

    if (!$memoryConn instanceof mysqli || !@$memoryConn->ping()) {
        echo "[ERROR] Cannot connect to integrated Hermes memory database.\n";
        exit(1);
    }

    echo "[*] Initializing integrated memory tables...\n";
    if (!aiAgentInitializeIntegratedMemoryTables($memoryConn)) {
        echo "[ERROR] Failed to initialize integrated memory tables.\n";
        exit(1);
    }
    echo "[✓] Integrated database initialized\n\n";
}

// Migration statistics
$stats = [
    'conversations_migrated' => 0,
    'profiles_migrated' => 0,
    'lessons_migrated' => 0,
    'memory_snapshots_migrated' => 0,
    'reflections_migrated' => 0,
    'errors' => 0,
    'total_files' => 0,
];

// Migrate profiles (*.json)
echo "[*] Scanning profiles directory...\n";
$profilesDir = $memoryConfig['profiles_dir'];
if (is_dir($profilesDir)) {
    $profileFiles = glob($profilesDir . DIRECTORY_SEPARATOR . '*.json');
    $stats['total_files'] += is_array($profileFiles) ? count($profileFiles) : 0;

    foreach ($profileFiles ?: [] as $filePath) {
        $fileName = basename($filePath);
        if ($isVerbose) {
            echo "  Processing profile: {$fileName}";
        }

        if (preg_match('/^(.+?)-(\d+)\.json$/', $fileName, $matches) !== 1) {
            $stats['errors']++;
            if ($isVerbose) {
                echo "  SKIP: Invalid filename format\n";
            }
            continue;
        }

        $role = $matches[1];
        $userId = (int) $matches[2];
        $profileData = json_decode((string) file_get_contents($filePath), true);
        if (!is_array($profileData)) {
            $profileData = ['notes' => []];
        }

        $profileData['role'] = $role;
        $profileData['user_id'] = $userId;

        if ($isDryRun) {
            $stats['profiles_migrated']++;
            if ($isVerbose) {
                echo " [DRY RUN]\n";
            }
            continue;
        }

        $success = aiAgentIntegratedSaveUserProfile($memoryConn, $userId, $profileData);
        if ($success) {
            $stats['profiles_migrated']++;
            aiAgentFinalizeMigratedSourceFile($filePath, $shouldBackup, $deleteAfter);
            if ($isVerbose) {
                echo " ✓\n";
            }
            continue;
        }

        $stats['errors']++;
        if ($isVerbose) {
            echo " ✗ (database write failed)\n";
        }
    }

    $memoryMarkdownFiles = glob($profilesDir . DIRECTORY_SEPARATOR . '*-MEMORY.md');
    $stats['total_files'] += is_array($memoryMarkdownFiles) ? count($memoryMarkdownFiles) : 0;

    foreach ($memoryMarkdownFiles ?: [] as $filePath) {
        $fileName = basename($filePath);
        if ($isVerbose) {
            echo "  Processing curated memory: {$fileName}";
        }

        if (preg_match('/^(.+?)-(\d+)-MEMORY\.md$/', $fileName, $matches) !== 1) {
            $stats['errors']++;
            if ($isVerbose) {
                echo "  SKIP: Invalid filename format\n";
            }
            continue;
        }

        $role = $matches[1];
        $userId = (int) $matches[2];
        $markdown = (string) file_get_contents($filePath);
        $parsedMemory = aiAgentNormalizeStructuredMemorySections(aiAgentParseMemoryMarkdown($markdown));

        if (implode('', $parsedMemory) === '') {
            if ($isVerbose) {
                echo " SKIP: Empty curated memory\n";
            }
            continue;
        }

        if ($isDryRun) {
            $stats['memory_snapshots_migrated']++;
            if ($isVerbose) {
                echo " [DRY RUN]\n";
            }
            continue;
        }

        $profileState = aiAgentIntegratedLoadUserProfile($memoryConn, $userId);
        $behavioralData = isset($profileState['behavioral_data']) && is_array($profileState['behavioral_data'])
            ? $profileState['behavioral_data']
            : [];
        $behavioralData['curated_memory'] = $parsedMemory;
        $behavioralData['curated_memory_updated_at'] = time();

        $profileState['role'] = $role;
        $profileState['user_id'] = $userId;
        $profileState['behavioral_data'] = $behavioralData;
        $profileState['updated_at'] = time();

        $success = aiAgentIntegratedSaveUserProfile($memoryConn, $userId, $profileState);
        if ($success) {
            $stats['memory_snapshots_migrated']++;
            aiAgentFinalizeMigratedSourceFile($filePath, $shouldBackup, $deleteAfter);
            if ($isVerbose) {
                echo " ✓\n";
            }
            continue;
        }

        $stats['errors']++;
        if ($isVerbose) {
            echo " ✗ (database write failed)\n";
        }
    }
}
echo "[✓] Profiles: " . $stats['profiles_migrated'] . " migrated\n";
echo "[✓] Memory snapshots: " . $stats['memory_snapshots_migrated'] . " migrated\n\n";

// Migrate lessons
echo "[*] Scanning lessons directory...\n";
$lessonsDir = $memoryConfig['lessons_dir'];
if (is_dir($lessonsDir)) {
    $lessonFiles = glob($lessonsDir . DIRECTORY_SEPARATOR . '*.json');
    $stats['total_files'] += is_array($lessonFiles) ? count($lessonFiles) : 0;

    foreach ($lessonFiles ?: [] as $filePath) {
        $fileName = basename($filePath);
        if ($isVerbose) {
            echo "  Processing lesson: {$fileName}";
        }

        if (preg_match('/^(.+?)-(\d+)\.json$/', $fileName, $matches) !== 1) {
            $stats['errors']++;
            if ($isVerbose) {
                echo "  SKIP: Invalid filename format\n";
            }
            continue;
        }

        $role = $matches[1];
        $userId = (int) $matches[2];
        $lessonsData = json_decode((string) file_get_contents($filePath), true);
        if (!is_array($lessonsData)) {
            $lessonsData = ['lessons' => []];
        }

        $lessonsData['role'] = $role;
        $lessonsData['user_id'] = $userId;

        if ($isDryRun) {
            $stats['lessons_migrated']++;
            if ($isVerbose) {
                echo " [DRY RUN]\n";
            }
            continue;
        }

        $success = aiAgentIntegratedSaveUserLessons($memoryConn, $userId, $lessonsData);
        if ($success) {
            $stats['lessons_migrated']++;
            aiAgentFinalizeMigratedSourceFile($filePath, $shouldBackup, $deleteAfter);
            if ($isVerbose) {
                echo " ✓\n";
            }
            continue;
        }

        $stats['errors']++;
        if ($isVerbose) {
            echo " ✗ (database write failed)\n";
        }
    }
}
echo "[✓] Lessons: " . $stats['lessons_migrated'] . " migrated\n\n";

// Migrate conversations
echo "[*] Scanning conversations directory...\n";
$conversationsDir = $memoryConfig['conversations_dir'];
if (is_dir($conversationsDir)) {
    $conversationFiles = glob($conversationsDir . DIRECTORY_SEPARATOR . '*.json');
    $stats['total_files'] += is_array($conversationFiles) ? count($conversationFiles) : 0;

    foreach ($conversationFiles ?: [] as $filePath) {
        $fileName = basename($filePath);
        if ($isVerbose) {
            echo "  Processing conversation: {$fileName}";
        }

        if (preg_match('/^(.+?)-(\d+)-(.+)\.json$/', $fileName, $matches) !== 1) {
            $stats['errors']++;
            if ($isVerbose) {
                echo "  SKIP: Invalid filename format\n";
            }
            continue;
        }

        $role = $matches[1];
        $userId = (int) $matches[2];
        $conversationId = $matches[3];
        $conversationData = json_decode((string) file_get_contents($filePath), true);
        if (!is_array($conversationData)) {
            $conversationData = ['messages' => []];
        }

        $conversationData['role'] = $role;
        $conversationData['user_id'] = $userId;
        $conversationData['conversation_id'] = $conversationId;

        if ($isDryRun) {
            $stats['conversations_migrated']++;
            if ($isVerbose) {
                echo " [DRY RUN]\n";
            }
            continue;
        }

        $success = aiAgentIntegratedSaveConversationMemory($memoryConn, $userId, $conversationId, $conversationData);
        if ($success) {
            $stats['conversations_migrated']++;
            aiAgentFinalizeMigratedSourceFile($filePath, $shouldBackup, $deleteAfter);
            if ($isVerbose) {
                echo " ✓\n";
            }
            continue;
        }

        $stats['errors']++;
        if ($isVerbose) {
            echo " ✗ (database write failed)\n";
        }
    }
}
echo "[✓] Conversations: " . $stats['conversations_migrated'] . " migrated\n\n";

// Migrate reflection logs
echo "[*] Scanning reflections directory...\n";
$reflectionsDir = $memoryConfig['reflections_dir'];
if (is_dir($reflectionsDir)) {
    $reflectionFiles = glob($reflectionsDir . DIRECTORY_SEPARATOR . '*.jsonl');
    $stats['total_files'] += is_array($reflectionFiles) ? count($reflectionFiles) : 0;

    foreach ($reflectionFiles ?: [] as $filePath) {
        $fileName = basename($filePath);
        if ($isVerbose) {
            echo "  Processing reflection log: {$fileName}\n";
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            $stats['errors']++;
            continue;
        }

        $fileHasError = false;
        $fileMigratedEntries = 0;

        foreach ($lines as $index => $line) {
            $payload = json_decode((string) $line, true);
            if (!is_array($payload) || (int) ($payload['user_id'] ?? 0) <= 0) {
                $stats['errors']++;
                $fileHasError = true;
                if ($isVerbose) {
                    echo "    SKIP line " . ($index + 1) . ": invalid JSON payload\n";
                }
                continue;
            }

            if ($isDryRun) {
                $stats['reflections_migrated']++;
                $fileMigratedEntries++;
                continue;
            }

            if (aiAgentIntegratedSaveReflectionLog($memoryConn, $payload)) {
                $stats['reflections_migrated']++;
                $fileMigratedEntries++;
                continue;
            }

            $stats['errors']++;
            $fileHasError = true;
            if ($isVerbose) {
                echo "    ✗ line " . ($index + 1) . ": database write failed\n";
            }
        }

        if (!$isDryRun && !$fileHasError && $fileMigratedEntries > 0) {
            aiAgentFinalizeMigratedSourceFile($filePath, $shouldBackup, $deleteAfter);
        }
    }
}
echo "[✓] Reflections: " . $stats['reflections_migrated'] . " migrated\n\n";

// Summary
echo "=== Migration Summary ===\n";
echo "Total files scanned: " . $stats['total_files'] . "\n";
echo "Profiles migrated: " . $stats['profiles_migrated'] . "\n";
echo "Memory snapshots migrated: " . $stats['memory_snapshots_migrated'] . "\n";
echo "Lessons migrated: " . $stats['lessons_migrated'] . "\n";
echo "Conversations migrated: " . $stats['conversations_migrated'] . "\n";
echo "Reflections migrated: " . $stats['reflections_migrated'] . "\n";
echo "Errors: " . $stats['errors'] . "\n";
echo "Total migrated: "
    . ($stats['profiles_migrated']
        + $stats['memory_snapshots_migrated']
        + $stats['lessons_migrated']
        + $stats['conversations_migrated']
        + $stats['reflections_migrated'])
    . "\n";

if ($isDryRun) {
    echo "\n[!] DRY RUN MODE - No changes made. Remove --dry-run to perform actual migration.\n";
}

echo "\n";

if ($stats['errors'] === 0) {
    echo "[✓] Migration completed successfully!\n";
    if ($deleteAfter && !$isDryRun) {
        echo "[✓] Original files have been deleted.\n";
    } elseif (!$isDryRun) {
        echo "[!] Original files still exist. Use --delete-after-migrate to remove them.\n";
    }
    exit(0);
}

echo "[!] Migration completed with " . $stats['errors'] . " error(s).\n";
exit(1);
