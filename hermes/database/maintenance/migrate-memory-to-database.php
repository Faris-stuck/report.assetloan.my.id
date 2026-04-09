<?php

/**
 * Migration Script: Migrate Hermes Agent Memory from File-Based to Database Storage
 * 
 * Usage: php hermes/database/maintenance/migrate-memory-to-database.php [options]
 * Options:
 *   --dry-run           Show what would be migrated without actually migrating
 *   --backup            Create backup of original file before migration
 *   --verbose           Show detailed migration progress
 *   --delete-after-migrate  Delete files after successful migration
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../model/config-helper.php';
require_once __DIR__ . '/../../engine/runtime-helper.php';
require_once __DIR__ . '/../../memory/memory-helper.php';
require_once __DIR__ . '/database-schema-helper.php';

// Parse command line arguments
$isDryRun = in_array('--dry-run', $argv, true);
$shouldBackup = in_array('--backup', $argv, true);
$isVerbose = in_array('--verbose', $argv, true);
$deleteAfter = in_array('--delete-after-migrate', $argv, true);

echo "\n=== Hermes Agent Memory Migration Tool ===\n";
echo "Mode: " . ($isDryRun ? "DRY RUN" : "LIVE MIGRATION") . "\n";
echo "Backup: " . ($shouldBackup ? "ENABLED" : "DISABLED") . "\n";
echo "Delete after: " . ($deleteAfter ? "YES" : "NO") . "\n";
echo "\n";

// Load config
$config = aiAgentLoadConfig([
    __DIR__ . '/../../config/ai_agent.php',
]);

$memoryConfig = aiAgentGetMemoryConfig($config);
$dbMemoryConfig = aiAgentGetDatabaseMemoryConfig([]);

// Verify file storage path exists
$fileStorageDir = $memoryConfig['storage_dir'];
if (!is_dir($fileStorageDir)) {
    echo "[ERROR] File storage directory not found: {$fileStorageDir}\n";
    exit(1);
}

// Initialize database
if (!$isDryRun) {
    echo "[*] Initializing database tables...\n";
    if (!aiAgentInitializeMemoryDatabase($dbMemoryConfig)) {
        echo "[ERROR] Failed to initialize database. Check your database configuration.\n";
        exit(1);
    }
    echo "[✓] Database initialized\n\n";
}

// Get database connection
$pdo = aiAgentGetDatabaseConnection($dbMemoryConfig);
if (!$pdo && !$isDryRun) {
    echo "[ERROR] Cannot connect to database\n";
    exit(1);
}

// Migration statistics
$stats = [
    'conversations_migrated' => 0,
    'profiles_migrated' => 0,
    'lessons_migrated' => 0,
    'reflections_migrated' => 0,
    'errors' => 0,
    'total_files' => 0,
];

// Migrate profiles
echo "[*] Scanning profiles directory...\n";
$profilesDir = $memoryConfig['profiles_dir'];
if (is_dir($profilesDir)) {
    $profileFiles = glob("{$profilesDir}/*.json");
    $stats['total_files'] += count($profileFiles);

    foreach ($profileFiles as $filePath) {
        $fileName = basename($filePath);
        if ($isVerbose) {
            echo "  Processing: {$fileName}";
        }

        // Parse filename: {role}-{userId}.json
        if (preg_match('/^(.+?)-(\d+)\.json$/', $fileName, $matches)) {
            $role = $matches[1];
            $userId = (int) $matches[2];

            $profileData = json_decode(file_get_contents($filePath), true);
            if (!is_array($profileData)) {
                $profileData = ['notes' => []];
            }

            if (!$isDryRun && $pdo) {
                $success = aiAgentWriteProfileToDatabase($pdo, $userId, $role, $profileData);
                if ($success) {
                    $stats['profiles_migrated']++;
                    if ($isVerbose) {
                        echo " ✓\n";
                    }

                    // Backup or delete
                    if ($shouldBackup && !$isDryRun) {
                        $backupPath = $filePath . '.bak';
                        copy($filePath, $backupPath);
                    }
                    if ($deleteAfter && !$isDryRun) {
                        unlink($filePath);
                    }
                } else {
                    $stats['errors']++;
                    if ($isVerbose) {
                        echo " ✗ (database write failed)\n";
                    }
                }
            } else {
                $stats['profiles_migrated']++;
                if ($isVerbose) {
                    echo " [DRY RUN]\n";
                }
            }
        } else {
            $stats['errors']++;
            if ($isVerbose) {
                echo "  SKIP: Invalid filename format: {$fileName}\n";
            }
        }
    }
}
echo "[✓] Profiles: " . $stats['profiles_migrated'] . " migrated\n\n";

// Migrate lessons
echo "[*] Scanning lessons directory...\n";
$lessonsDir = $memoryConfig['lessons_dir'];
if (is_dir($lessonsDir)) {
    $lessonFiles = glob("{$lessonsDir}/*.json");
    $stats['total_files'] += count($lessonFiles);

    foreach ($lessonFiles as $filePath) {
        $fileName = basename($filePath);
        if ($isVerbose) {
            echo "  Processing: {$fileName}";
        }

        // Parse filename: {role}-{userId}.json
        if (preg_match('/^(.+?)-(\d+)\.json$/', $fileName, $matches)) {
            $role = $matches[1];
            $userId = (int) $matches[2];

            $lessonsData = json_decode(file_get_contents($filePath), true);
            if (!is_array($lessonsData)) {
                $lessonsData = ['lessons' => []];
            }

            if (!$isDryRun && $pdo) {
                $success = aiAgentWriteLessonsToDatabase($pdo, $userId, $role, $lessonsData);
                if ($success) {
                    $stats['lessons_migrated']++;
                    if ($isVerbose) {
                        echo " ✓\n";
                    }

                    if ($shouldBackup && !$isDryRun) {
                        $backupPath = $filePath . '.bak';
                        copy($filePath, $backupPath);
                    }
                    if ($deleteAfter && !$isDryRun) {
                        unlink($filePath);
                    }
                } else {
                    $stats['errors']++;
                    if ($isVerbose) {
                        echo " ✗ (database write failed)\n";
                    }
                }
            } else {
                $stats['lessons_migrated']++;
                if ($isVerbose) {
                    echo " [DRY RUN]\n";
                }
            }
        } else {
            $stats['errors']++;
            if ($isVerbose) {
                echo "  SKIP: Invalid filename format: {$fileName}\n";
            }
        }
    }
}
echo "[✓] Lessons: " . $stats['lessons_migrated'] . " migrated\n\n";

// Migrate conversations
echo "[*] Scanning conversations directory...\n";
$conversationsDir = $memoryConfig['conversations_dir'];
if (is_dir($conversationsDir)) {
    $conversationFiles = glob("{$conversationsDir}/*.json");
    $stats['total_files'] += count($conversationFiles);

    foreach ($conversationFiles as $filePath) {
        $fileName = basename($filePath);
        if ($isVerbose) {
            echo "  Processing: {$fileName}";
        }

        // Parse filename: {role}-{userId}-{conversationId}.json
        if (preg_match('/^(.+?)-(\d+)-(.+)\.json$/', $fileName, $matches)) {
            $role = $matches[1];
            $userId = (int) $matches[2];
            $conversationId = $matches[3];

            $conversationData = json_decode(file_get_contents($filePath), true);
            if (!is_array($conversationData)) {
                $conversationData = ['messages' => []];
            }

            if (!$isDryRun && $pdo) {
                $success = aiAgentWriteConversationToDatabase($pdo, $userId, $role, $conversationId, $conversationData);
                if ($success) {
                    $stats['conversations_migrated']++;
                    if ($isVerbose) {
                        echo " ✓\n";
                    }

                    if ($shouldBackup && !$isDryRun) {
                        $backupPath = $filePath . '.bak';
                        copy($filePath, $backupPath);
                    }
                    if ($deleteAfter && !$isDryRun) {
                        unlink($filePath);
                    }
                } else {
                    $stats['errors']++;
                    if ($isVerbose) {
                        echo " ✗ (database write failed)\n";
                    }
                }
            } else {
                $stats['conversations_migrated']++;
                if ($isVerbose) {
                    echo " [DRY RUN]\n";
                }
            }
        } else {
            $stats['errors']++;
            if ($isVerbose) {
                echo "  SKIP: Invalid filename format: {$fileName}\n";
            }
        }
    }
}
echo "[✓] Conversations: " . $stats['conversations_migrated'] . " migrated\n\n";

// Summary
echo "=== Migration Summary ===\n";
echo "Total files scanned: " . $stats['total_files'] . "\n";
echo "Profiles migrated: " . $stats['profiles_migrated'] . "\n";
echo "Lessons migrated: " . $stats['lessons_migrated'] . "\n";
echo "Conversations migrated: " . $stats['conversations_migrated'] . "\n";
echo "Errors: " . $stats['errors'] . "\n";
echo "Total migrated: " . ($stats['profiles_migrated'] + $stats['lessons_migrated'] + $stats['conversations_migrated']) . "\n";

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
} else {
    echo "[!] Migration completed with " . $stats['errors'] . " error(s).\n";
    exit(1);
}
