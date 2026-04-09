<?php

function aiAgentGetMemoryConfig(array $config = []): array
{
    $storageDir = trim((string) ($config['memory_storage_dir'] ?? 'hermes/data/memory'));
    if ($storageDir === '') {
        $storageDir = 'hermes/data/memory';
    }

    $storageDir = aiAgentResolveHermesStoragePath($storageDir);

    return [
        'enabled' => !isset($config['memory_enabled']) ? true : (bool) $config['memory_enabled'],
        'storage_dir' => $storageDir,
        'profiles_dir' => $storageDir . DIRECTORY_SEPARATOR . 'profiles',
        'conversations_dir' => $storageDir . DIRECTORY_SEPARATOR . 'conversations',
        'lessons_dir' => $storageDir . DIRECTORY_SEPARATOR . 'lessons',
        'reflections_dir' => $storageDir . DIRECTORY_SEPARATOR . 'reflections',
        'database_enabled' => !isset($config['memory_database_enabled']) ? true : (bool) $config['memory_database_enabled'],
        'database_fallback_to_files' => !isset($config['memory_db_fallback_to_files'])
            ? false
            : (bool) $config['memory_db_fallback_to_files'],
        'max_messages_per_conversation' => max(8, (int) ($config['memory_max_messages_per_conversation'] ?? 40)),
        'max_notes_per_user' => max(5, (int) ($config['memory_max_notes_per_user'] ?? 30)),
        'max_search_results' => max(2, (int) ($config['memory_max_search_results'] ?? 5)),
        'max_search_conversations' => max(2, (int) ($config['memory_max_search_conversations'] ?? 10)),
    ];
}

function aiAgentResolveHermesStoragePath(string $path): string
{
    $path = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    if ($path === '') {
        $path = 'hermes/data/memory';
    }

    if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1 || strpos($path, DIRECTORY_SEPARATOR) === 0) {
        return $path;
    }

    $projectRoot = function_exists('aiAgentGetProjectRootPath') ? aiAgentGetProjectRootPath() : dirname(__DIR__, 2);
    return $projectRoot . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function aiAgentEnsureMemoryDirectories(array $memoryConfig = []): void
{
    if (!aiAgentMemoryFileFallbackIsAllowed($memoryConfig)) {
        return;
    }

    foreach (
        [
            (string) ($memoryConfig['storage_dir'] ?? ''),
            (string) ($memoryConfig['profiles_dir'] ?? ''),
            (string) ($memoryConfig['conversations_dir'] ?? ''),
            (string) ($memoryConfig['lessons_dir'] ?? ''),
            (string) ($memoryConfig['reflections_dir'] ?? ''),
        ] as $directory
    ) {
        if ($directory !== '' && !is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }
}

function aiAgentBuildMemoryUserKey(string $role, int $userId): string
{
    $role = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim($role)));
    $role = trim((string) $role, '-');
    if ($role === '') {
        $role = 'user';
    }

    return $role . '-' . max(0, $userId);
}

function aiAgentNormalizeMemoryConversationId(string $conversationId, string $fallback = 'default'): string
{
    $conversationId = preg_replace('/[^a-z0-9._:-]+/i', '-', strtolower(trim($conversationId)));
    $conversationId = trim((string) $conversationId, '-');
    if ($conversationId === '') {
        $conversationId = $fallback;
    }

    return $conversationId;
}

function aiAgentBuildMemoryConversationKey(string $role, int $userId, string $conversationId): string
{
    $conversationId = aiAgentNormalizeMemoryConversationId($conversationId, 'default');
    return aiAgentBuildMemoryUserKey($role, $userId) . '-' . $conversationId;
}

function aiAgentGetConversationMemoryPath(array $memoryConfig, string $role, int $userId, string $conversationId): string
{
    return rtrim((string) ($memoryConfig['conversations_dir'] ?? ''), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . aiAgentBuildMemoryConversationKey($role, $userId, $conversationId)
        . '.json';
}

function aiAgentGetProfileMemoryPath(array $memoryConfig, string $role, int $userId): string
{
    return rtrim((string) ($memoryConfig['profiles_dir'] ?? ''), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . aiAgentBuildMemoryUserKey($role, $userId)
        . '.json';
}

function aiAgentGetLessonsMemoryPath(array $memoryConfig, string $role, int $userId): string
{
    return rtrim((string) ($memoryConfig['lessons_dir'] ?? ''), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . aiAgentBuildMemoryUserKey($role, $userId)
        . '.json';
}

function aiAgentGetReflectionLogPath(array $memoryConfig): string
{
    return rtrim((string) ($memoryConfig['reflections_dir'] ?? ''), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'reflection-log.jsonl';
}

function aiAgentReadJsonFile(string $path, array $fallback = []): array
{
    if (!is_file($path) || !is_readable($path)) {
        return $fallback;
    }

    $decoded = json_decode((string) @file_get_contents($path), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function aiAgentWriteJsonFile(string $path, array $data): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function aiAgentBuildEmptyConversationMemoryState(string $role, int $userId, string $conversationId): array
{
    return [
        'conversation_id' => $conversationId,
        'role' => $role,
        'user_id' => $userId,
        'messages' => [],
        'updated_at' => 0,
        'created_at' => 0,
    ];
}

function aiAgentBuildEmptyProfileMemoryState(string $role, int $userId): array
{
    return [
        'role' => $role,
        'user_id' => $userId,
        'notes' => [],
        'behavioral_data' => [],
        'updated_at' => 0,
        'created_at' => 0,
    ];
}

function aiAgentBuildEmptyLessonsMemoryState(string $role, int $userId): array
{
    return [
        'role' => $role,
        'user_id' => $userId,
        'lessons' => [],
        'updated_at' => 0,
        'created_at' => 0,
    ];
}

function aiAgentLoadConversationMemoryFromFile(array $memoryConfig, string $role, int $userId, string $conversationId): array
{
    if (!aiAgentMemoryLegacyFileReadIsAllowed($memoryConfig)) {
        return aiAgentBuildEmptyConversationMemoryState($role, $userId, $conversationId);
    }

    $path = aiAgentGetConversationMemoryPath($memoryConfig, $role, $userId, $conversationId);
    $state = aiAgentReadJsonFile($path, aiAgentBuildEmptyConversationMemoryState($role, $userId, $conversationId));
    return array_merge(aiAgentBuildEmptyConversationMemoryState($role, $userId, $conversationId), $state);
}

function aiAgentLoadUserProfileMemoryFromFile(array $memoryConfig, string $role, int $userId): array
{
    if (!aiAgentMemoryLegacyFileReadIsAllowed($memoryConfig)) {
        return aiAgentBuildEmptyProfileMemoryState($role, $userId);
    }

    $path = aiAgentGetProfileMemoryPath($memoryConfig, $role, $userId);
    $state = aiAgentReadJsonFile($path, aiAgentBuildEmptyProfileMemoryState($role, $userId));
    return array_merge(aiAgentBuildEmptyProfileMemoryState($role, $userId), $state);
}

function aiAgentLoadUserLessonsMemoryFromFile(array $memoryConfig, string $role, int $userId): array
{
    if (!aiAgentMemoryLegacyFileReadIsAllowed($memoryConfig)) {
        return aiAgentBuildEmptyLessonsMemoryState($role, $userId);
    }

    $path = aiAgentGetLessonsMemoryPath($memoryConfig, $role, $userId);
    $state = aiAgentReadJsonFile($path, aiAgentBuildEmptyLessonsMemoryState($role, $userId));
    return array_merge(aiAgentBuildEmptyLessonsMemoryState($role, $userId), $state);
}

function aiAgentGetUserActiveConversationId(array $memoryConfig, string $role, int $userId): string
{
    if (empty($memoryConfig['enabled']) || $userId <= 0) {
        return '';
    }

    $profile = aiAgentLoadUserProfileMemory($memoryConfig, $role, $userId);
    $behavioralData = isset($profile['behavioral_data']) && is_array($profile['behavioral_data'])
        ? $profile['behavioral_data']
        : [];

    return aiAgentNormalizeMemoryConversationId((string) ($behavioralData['active_conversation_id'] ?? ''), '');
}

function aiAgentSetUserActiveConversationId(array $memoryConfig, string $role, int $userId, string $conversationId): bool
{
    if (empty($memoryConfig['enabled']) || $userId <= 0) {
        return false;
    }

    $normalizedConversationId = aiAgentNormalizeMemoryConversationId($conversationId, '');
    $profile = aiAgentLoadUserProfileMemory($memoryConfig, $role, $userId);
    $behavioralData = isset($profile['behavioral_data']) && is_array($profile['behavioral_data'])
        ? $profile['behavioral_data']
        : [];

    if ($normalizedConversationId === '') {
        unset($behavioralData['active_conversation_id']);
    } else {
        $behavioralData['active_conversation_id'] = $normalizedConversationId;
    }

    $profile['role'] = $role;
    $profile['user_id'] = $userId;
    $profile['behavioral_data'] = $behavioralData;
    $profile['updated_at'] = time();

    return aiAgentPersistUserProfileMemoryState($memoryConfig, $role, $userId, $profile);
}

function aiAgentMemoryDatabaseIsEnabled(array $memoryConfig): bool
{
    return !empty($memoryConfig['enabled']) && !empty($memoryConfig['database_enabled']);
}

function aiAgentMemoryFileFallbackIsAllowed(array $memoryConfig): bool
{
    if (empty($memoryConfig['enabled'])) {
        return false;
    }

    if (empty($memoryConfig['database_enabled'])) {
        return true;
    }

    return !empty($memoryConfig['database_fallback_to_files']);
}

function aiAgentMemoryLegacyFileReadIsAllowed(array $memoryConfig): bool
{
    if (empty($memoryConfig['enabled'])) {
        return false;
    }

    return aiAgentMemoryFileFallbackIsAllowed($memoryConfig) || !empty($memoryConfig['database_enabled']);
}

function aiAgentMemoryShouldPruneLegacyFiles(array $memoryConfig): bool
{
    return !empty($memoryConfig['enabled']) && !empty($memoryConfig['database_enabled']);
}

function aiAgentDeleteMemoryFile(string $path): void
{
    $path = trim($path);
    if ($path === '' || !is_file($path)) {
        return;
    }

    @unlink($path);
}

function aiAgentDeleteLegacyConversationMemoryFile(
    array $memoryConfig,
    string $role,
    int $userId,
    string $conversationId
): void {
    if (!aiAgentMemoryShouldPruneLegacyFiles($memoryConfig)) {
        return;
    }

    aiAgentDeleteMemoryFile(aiAgentGetConversationMemoryPath($memoryConfig, $role, $userId, $conversationId));
}

function aiAgentDeleteLegacyProfileMemoryFile(array $memoryConfig, string $role, int $userId): void
{
    if (!aiAgentMemoryShouldPruneLegacyFiles($memoryConfig)) {
        return;
    }

    aiAgentDeleteMemoryFile(aiAgentGetProfileMemoryPath($memoryConfig, $role, $userId));
}

function aiAgentDeleteLegacyLessonsMemoryFile(array $memoryConfig, string $role, int $userId): void
{
    if (!aiAgentMemoryShouldPruneLegacyFiles($memoryConfig)) {
        return;
    }

    aiAgentDeleteMemoryFile(aiAgentGetLessonsMemoryPath($memoryConfig, $role, $userId));
}

function aiAgentDeleteLegacyReflectionLogFile(array $memoryConfig): void
{
    if (!aiAgentMemoryShouldPruneLegacyFiles($memoryConfig)) {
        return;
    }

    aiAgentDeleteMemoryFile(aiAgentGetReflectionLogPath($memoryConfig));
}

function aiAgentGetIntegratedMemoryConnectionForRuntime(array $memoryConfig, bool $initializeTables = true): ?mysqli
{
    static $initialized = false;

    if (!aiAgentMemoryDatabaseIsEnabled($memoryConfig)) {
        return null;
    }

    if (!function_exists('aiAgentGetIntegratedMemoryConnection')) {
        return null;
    }

    $conn = aiAgentGetIntegratedMemoryConnection();
    if (!$conn instanceof mysqli || !@$conn->ping()) {
        return null;
    }

    if ($initializeTables && !$initialized && function_exists('aiAgentInitializeIntegratedMemoryTables')) {
        aiAgentInitializeIntegratedMemoryTables($conn);
        $initialized = true;
    }

    return $conn;
}

function aiAgentConversationMemoryStateHasContent(array $state): bool
{
    return !empty($state['messages'])
        || (int) ($state['updated_at'] ?? 0) > 0
        || (int) ($state['created_at'] ?? 0) > 0;
}

function aiAgentProfileMemoryStateHasContent(array $state): bool
{
    return !empty($state['notes'])
        || !empty($state['behavioral_data'])
        || (int) ($state['updated_at'] ?? 0) > 0
        || (int) ($state['created_at'] ?? 0) > 0;
}

function aiAgentLessonsMemoryStateHasContent(array $state): bool
{
    return !empty($state['lessons'])
        || (int) ($state['updated_at'] ?? 0) > 0
        || (int) ($state['created_at'] ?? 0) > 0;
}

function aiAgentPersistConversationMemoryState(
    array $memoryConfig,
    string $role,
    int $userId,
    string $conversationId,
    array $conversationData
): bool {
    $state = array_merge(
        aiAgentBuildEmptyConversationMemoryState($role, $userId, $conversationId),
        $conversationData
    );
    $state['conversation_id'] = $conversationId;
    $state['role'] = $role;
    $state['user_id'] = $userId;
    $state['messages'] = aiAgentNormalizeMemoryMessages(
        isset($state['messages']) && is_array($state['messages']) ? $state['messages'] : [],
        (int) ($memoryConfig['max_messages_per_conversation'] ?? 40)
    );
    $state['updated_at'] = (int) ($state['updated_at'] ?? time());
    if ((int) ($state['created_at'] ?? 0) <= 0) {
        $state['created_at'] = $state['updated_at'];
    }

    $persisted = false;
    if ($conn = aiAgentGetIntegratedMemoryConnectionForRuntime($memoryConfig)) {
        $persisted = aiAgentIntegratedSaveConversationMemory($conn, $userId, $conversationId, $state);
    }

    if ($persisted) {
        aiAgentDeleteLegacyConversationMemoryFile($memoryConfig, $role, $userId, $conversationId);
        return true;
    }

    if (!aiAgentMemoryFileFallbackIsAllowed($memoryConfig)) {
        return false;
    }

    aiAgentWriteJsonFile(aiAgentGetConversationMemoryPath($memoryConfig, $role, $userId, $conversationId), $state);
    return true;
}

function aiAgentPersistUserProfileMemoryState(array $memoryConfig, string $role, int $userId, array $profileData): bool
{
    $state = array_merge(aiAgentBuildEmptyProfileMemoryState($role, $userId), $profileData);
    $state['role'] = $role;
    $state['user_id'] = $userId;
    $state['notes'] = isset($state['notes']) && is_array($state['notes']) ? array_values($state['notes']) : [];
    $state['behavioral_data'] = isset($state['behavioral_data']) && is_array($state['behavioral_data'])
        ? $state['behavioral_data']
        : [];
    $state['updated_at'] = (int) ($state['updated_at'] ?? time());
    if ((int) ($state['created_at'] ?? 0) <= 0) {
        $state['created_at'] = $state['updated_at'];
    }

    $persisted = false;
    if ($conn = aiAgentGetIntegratedMemoryConnectionForRuntime($memoryConfig)) {
        $persisted = aiAgentIntegratedSaveUserProfile($conn, $userId, $state);
    }

    if ($persisted) {
        aiAgentDeleteLegacyProfileMemoryFile($memoryConfig, $role, $userId);
        return true;
    }

    if (!aiAgentMemoryFileFallbackIsAllowed($memoryConfig)) {
        return false;
    }

    aiAgentWriteJsonFile(aiAgentGetProfileMemoryPath($memoryConfig, $role, $userId), $state);
    return true;
}

function aiAgentPersistUserLessonsMemoryState(array $memoryConfig, string $role, int $userId, array $lessonsData): bool
{
    $state = array_merge(aiAgentBuildEmptyLessonsMemoryState($role, $userId), $lessonsData);
    $state['role'] = $role;
    $state['user_id'] = $userId;
    $state['lessons'] = isset($state['lessons']) && is_array($state['lessons']) ? array_values($state['lessons']) : [];
    $state['updated_at'] = (int) ($state['updated_at'] ?? time());
    if ((int) ($state['created_at'] ?? 0) <= 0) {
        $state['created_at'] = $state['updated_at'];
    }

    $persisted = false;
    if ($conn = aiAgentGetIntegratedMemoryConnectionForRuntime($memoryConfig)) {
        $persisted = aiAgentIntegratedSaveUserLessons($conn, $userId, $state);
    }

    if ($persisted) {
        aiAgentDeleteLegacyLessonsMemoryFile($memoryConfig, $role, $userId);
        return true;
    }

    if (!aiAgentMemoryFileFallbackIsAllowed($memoryConfig)) {
        return false;
    }

    aiAgentWriteJsonFile(aiAgentGetLessonsMemoryPath($memoryConfig, $role, $userId), $state);
    return true;
}

function aiAgentLoadConversationMemory(array $memoryConfig, string $role, int $userId, string $conversationId): array
{
    $conversationId = aiAgentNormalizeMemoryConversationId($conversationId, 'default');
    $fallback = aiAgentBuildEmptyConversationMemoryState($role, $userId, $conversationId);
    if (empty($memoryConfig['enabled']) || $userId <= 0) {
        return $fallback;
    }

    if ($conn = aiAgentGetIntegratedMemoryConnectionForRuntime($memoryConfig)) {
        $state = array_merge($fallback, aiAgentIntegratedLoadConversationMemory($conn, $userId, $conversationId));
        if (aiAgentConversationMemoryStateHasContent($state)) {
            aiAgentDeleteLegacyConversationMemoryFile($memoryConfig, $role, $userId, $conversationId);
            return $state;
        }

        $legacyState = aiAgentLoadConversationMemoryFromFile($memoryConfig, $role, $userId, $conversationId);
        if (aiAgentConversationMemoryStateHasContent($legacyState)) {
            aiAgentPersistConversationMemoryState($memoryConfig, $role, $userId, $conversationId, $legacyState);
            return $legacyState;
        }

        return $fallback;
    }

    if (!aiAgentMemoryFileFallbackIsAllowed($memoryConfig)) {
        return $fallback;
    }

    return aiAgentLoadConversationMemoryFromFile($memoryConfig, $role, $userId, $conversationId);
}

function aiAgentLoadUserProfileMemory(array $memoryConfig, string $role, int $userId): array
{
    $fallback = aiAgentBuildEmptyProfileMemoryState($role, $userId);
    if (empty($memoryConfig['enabled']) || $userId <= 0) {
        return $fallback;
    }

    if ($conn = aiAgentGetIntegratedMemoryConnectionForRuntime($memoryConfig)) {
        $state = array_merge($fallback, aiAgentIntegratedLoadUserProfile($conn, $userId));
        if (aiAgentProfileMemoryStateHasContent($state)) {
            aiAgentDeleteLegacyProfileMemoryFile($memoryConfig, $role, $userId);
            return $state;
        }

        $legacyState = aiAgentLoadUserProfileMemoryFromFile($memoryConfig, $role, $userId);
        if (aiAgentProfileMemoryStateHasContent($legacyState)) {
            aiAgentPersistUserProfileMemoryState($memoryConfig, $role, $userId, $legacyState);
            return $legacyState;
        }

        return $fallback;
    }

    if (!aiAgentMemoryFileFallbackIsAllowed($memoryConfig)) {
        return $fallback;
    }

    return aiAgentLoadUserProfileMemoryFromFile($memoryConfig, $role, $userId);
}

function aiAgentLoadUserLessonsMemory(array $memoryConfig, string $role, int $userId): array
{
    $fallback = aiAgentBuildEmptyLessonsMemoryState($role, $userId);
    if (empty($memoryConfig['enabled']) || $userId <= 0) {
        return $fallback;
    }

    if ($conn = aiAgentGetIntegratedMemoryConnectionForRuntime($memoryConfig)) {
        $state = array_merge($fallback, aiAgentIntegratedLoadUserLessons($conn, $userId));
        if (aiAgentLessonsMemoryStateHasContent($state)) {
            aiAgentDeleteLegacyLessonsMemoryFile($memoryConfig, $role, $userId);
            return $state;
        }

        $legacyState = aiAgentLoadUserLessonsMemoryFromFile($memoryConfig, $role, $userId);
        if (aiAgentLessonsMemoryStateHasContent($legacyState)) {
            aiAgentPersistUserLessonsMemoryState($memoryConfig, $role, $userId, $legacyState);
            return $legacyState;
        }

        return $fallback;
    }

    if (!aiAgentMemoryFileFallbackIsAllowed($memoryConfig)) {
        return $fallback;
    }

    return aiAgentLoadUserLessonsMemoryFromFile($memoryConfig, $role, $userId);
}

function aiAgentTokenizeMemorySearchText(string $text): array
{
    $normalized = strtolower(trim((string) preg_replace('/[^\p{L}\p{N}_\s-]+/u', ' ', $text)));
    if ($normalized === '') {
        return [];
    }

    $stopwords = [
        'yang',
        'dan',
        'atau',
        'untuk',
        'dengan',
        'dari',
        'pada',
        'ke',
        'di',
        'ini',
        'itu',
        'saya',
        'aku',
        'anda',
        'kamu',
        'kami',
        'mereka',
        'tidak',
        'bukan',
        'sudah',
        'belum',
        'agar',
        'supaya',
        'tentang',
        'halaman',
        'sistem',
        'project',
        'hermes',
        'chat',
        'the',
        'and',
        'for',
        'with',
        'from',
        'this',
        'that',
    ];

    $tokens = preg_split('/\s+/', $normalized) ?: [];
    $selected = [];
    foreach ($tokens as $token) {
        $token = trim($token, "-_ ");
        if ($token === '' || aiAgentStringLength($token) < 3) {
            continue;
        }
        if (in_array($token, $stopwords, true)) {
            continue;
        }
        $selected[] = $token;
    }

    return array_values(array_unique($selected));
}

function aiAgentListUserConversationMemoryPaths(array $memoryConfig, string $role, int $userId, string $conversationId = ''): array
{
    if (!aiAgentMemoryLegacyFileReadIsAllowed($memoryConfig)) {
        return [];
    }

    $conversationKeyPrefix = aiAgentBuildMemoryUserKey($role, $userId) . '-';
    $pattern = rtrim((string) ($memoryConfig['conversations_dir'] ?? ''), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . $conversationKeyPrefix
        . '*.json';

    $paths = glob($pattern);
    if (!is_array($paths)) {
        return [];
    }

    $currentPath = $conversationId !== ''
        ? aiAgentGetConversationMemoryPath($memoryConfig, $role, $userId, $conversationId)
        : '';

    $filtered = [];
    foreach ($paths as $path) {
        if (!is_string($path) || !is_file($path)) {
            continue;
        }
        if ($currentPath !== '' && strcasecmp($path, $currentPath) === 0) {
            continue;
        }
        $filtered[] = $path;
    }

    usort($filtered, static function (string $left, string $right): int {
        return ((int) @filemtime($right)) <=> ((int) @filemtime($left));
    });

    return $filtered;
}

function aiAgentLoadConversationMemoryStatesFromFile(
    array $memoryConfig,
    string $role,
    int $userId,
    string $conversationId = ''
): array {
    $states = [];
    foreach (aiAgentListUserConversationMemoryPaths($memoryConfig, $role, $userId, $conversationId) as $path) {
        $state = aiAgentReadJsonFile($path, []);
        $derivedConversationId = (string) ($state['conversation_id'] ?? '');
        if ($derivedConversationId === '') {
            $derivedConversationId = basename($path, '.json');
        }

        $states[] = array_merge(
            aiAgentBuildEmptyConversationMemoryState($role, $userId, $derivedConversationId),
            $state,
            [
                'conversation_id' => $derivedConversationId,
                'role' => $role,
                'user_id' => $userId,
                'updated_at' => (int) ($state['updated_at'] ?? (int) @filemtime($path)),
                'created_at' => (int) ($state['created_at'] ?? (int) @filectime($path)),
            ]
        );
    }

    return $states;
}

function aiAgentListUserConversationMemoryStates(
    array $memoryConfig,
    string $role,
    int $userId,
    string $conversationId = '',
    ?int $limitOverride = null
): array {
    $limit = $limitOverride === null
        ? max(1, (int) ($memoryConfig['max_search_conversations'] ?? 10))
        : max(1, $limitOverride);
    $states = [];
    $seenConversationIds = [];
    $databaseActive = false;

    if ($conn = aiAgentGetIntegratedMemoryConnectionForRuntime($memoryConfig)) {
        $databaseActive = true;
        foreach (aiAgentIntegratedListConversationMemories($conn, $userId, $conversationId, $limit) as $state) {
            $normalizedConversationId = trim((string) ($state['conversation_id'] ?? ''));
            if ($normalizedConversationId === '') {
                continue;
            }

            $states[] = array_merge(
                aiAgentBuildEmptyConversationMemoryState($role, $userId, $normalizedConversationId),
                $state,
                [
                    'conversation_id' => $normalizedConversationId,
                    'role' => $role,
                    'user_id' => $userId,
                ]
            );
            aiAgentDeleteLegacyConversationMemoryFile($memoryConfig, $role, $userId, $normalizedConversationId);
            $seenConversationIds[$normalizedConversationId] = true;
        }
    }

    if (!$databaseActive && !aiAgentMemoryFileFallbackIsAllowed($memoryConfig)) {
        return [];
    }

    foreach (aiAgentLoadConversationMemoryStatesFromFile($memoryConfig, $role, $userId, $conversationId) as $state) {
        $normalizedConversationId = trim((string) ($state['conversation_id'] ?? ''));
        if ($normalizedConversationId === '') {
            continue;
        }

        if ($databaseActive && !isset($seenConversationIds[$normalizedConversationId])) {
            aiAgentPersistConversationMemoryState($memoryConfig, $role, $userId, $normalizedConversationId, $state);
            $states[] = $state;
            $seenConversationIds[$normalizedConversationId] = true;
            continue;
        }

        if (!$databaseActive && aiAgentMemoryFileFallbackIsAllowed($memoryConfig)) {
            $states[] = $state;
        }
    }

    usort($states, static function (array $left, array $right): int {
        $updatedDiff = ((int) ($right['updated_at'] ?? 0)) <=> ((int) ($left['updated_at'] ?? 0));
        if ($updatedDiff !== 0) {
            return $updatedDiff;
        }

        return ((int) ($right['created_at'] ?? 0)) <=> ((int) ($left['created_at'] ?? 0));
    });

    return array_slice($states, 0, $limit);
}

function aiAgentBuildMemorySearchEntries(array $memoryConfig, string $role, int $userId, string $conversationId): array
{
    $entries = [];

    $profile = aiAgentLoadUserProfileMemory($memoryConfig, $role, $userId);
    foreach (($profile['notes'] ?? []) as $note) {
        $note = trim((string) $note);
        if ($note === '') {
            continue;
        }
        $entries[] = [
            'source' => 'profile_note',
            'content' => $note,
            'timestamp' => (int) ($profile['updated_at'] ?? 0),
        ];
    }

    $lessons = aiAgentLoadUserLessonsMemory($memoryConfig, $role, $userId);
    foreach (($lessons['lessons'] ?? []) as $lesson) {
        $note = trim((string) ($lesson['note'] ?? ''));
        if ($note === '') {
            continue;
        }
        $entries[] = [
            'source' => 'lesson',
            'content' => $note . ' | sumber: ' . trim((string) ($lesson['source_message'] ?? '')),
            'timestamp' => (int) ($lesson['created_at'] ?? 0),
        ];
    }

    $currentConversation = aiAgentLoadConversationMemory($memoryConfig, $role, $userId, $conversationId);
    foreach (aiAgentNormalizeMemoryMessages($currentConversation['messages'] ?? [], 20) as $message) {
        $content = trim((string) ($message['content'] ?? ''));
        if ($content === '') {
            continue;
        }
        $entries[] = [
            'source' => ((string) ($message['role'] ?? 'assistant')) === 'user' ? 'current_user' : 'current_assistant',
            'content' => $content,
            'timestamp' => (int) ($message['timestamp'] ?? 0),
        ];
    }

    foreach (aiAgentListUserConversationMemoryStates($memoryConfig, $role, $userId, $conversationId) as $state) {
        foreach (aiAgentNormalizeMemoryMessages($state['messages'] ?? [], 12) as $message) {
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $entries[] = [
                'source' => 'past_conversation',
                'content' => $content,
                'timestamp' => (int) ($message['timestamp'] ?? (int) ($state['updated_at'] ?? 0)),
            ];
        }
    }

    return $entries;
}

function aiAgentScoreMemorySearchEntry(array $entry, array $queryTokens): int
{
    $content = strtolower(trim((string) ($entry['content'] ?? '')));
    if ($content === '' || empty($queryTokens)) {
        return 0;
    }

    $score = 0;
    foreach ($queryTokens as $token) {
        if ($token === '') {
            continue;
        }
        if (strpos($content, $token) !== false) {
            $score += 12;
            $score += min(18, substr_count($content, $token) * 4);
        }
    }

    $source = (string) ($entry['source'] ?? '');
    if ($source === 'lesson' || $source === 'profile_note') {
        $score += 8;
    }
    if ($source === 'current_user' || $source === 'current_assistant') {
        $score += 4;
    }

    $timestamp = (int) ($entry['timestamp'] ?? 0);
    if ($timestamp > 0) {
        $ageDays = max(0, (time() - $timestamp) / 86400);
        $score += max(0, 10 - (int) floor($ageDays));
    }

    return $score;
}

function aiAgentSearchMemory(array $memoryConfig, string $role, int $userId, string $conversationId, string $query, int $limit = 5): array
{
    if (empty($memoryConfig['enabled']) || $userId <= 0) {
        return [];
    }

    $queryTokens = aiAgentTokenizeMemorySearchText($query);
    if (empty($queryTokens)) {
        return [];
    }

    $results = [];
    $seen = [];
    foreach (aiAgentBuildMemorySearchEntries($memoryConfig, $role, $userId, $conversationId) as $entry) {
        $content = trim((string) ($entry['content'] ?? ''));
        if ($content === '') {
            continue;
        }
        $fingerprint = md5(strtolower($content));
        if (isset($seen[$fingerprint])) {
            continue;
        }

        $score = aiAgentScoreMemorySearchEntry($entry, $queryTokens);
        if ($score <= 0) {
            continue;
        }

        $seen[$fingerprint] = true;
        $entry['score'] = $score;
        $results[] = $entry;
    }

    usort($results, static function (array $left, array $right): int {
        $scoreDiff = ((int) ($right['score'] ?? 0)) <=> ((int) ($left['score'] ?? 0));
        if ($scoreDiff !== 0) {
            return $scoreDiff;
        }

        return ((int) ($right['timestamp'] ?? 0)) <=> ((int) ($left['timestamp'] ?? 0));
    });

    return array_slice($results, 0, max(1, $limit));
}

function aiAgentNormalizeMemoryMessages(array $messages, int $limit = 12): array
{
    $normalized = [];

    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }

        $role = (string) ($message['role'] ?? '');
        if ($role !== 'user' && $role !== 'assistant') {
            continue;
        }

        $content = trim((string) ($message['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        if (function_exists('aiAgentStringLength') && aiAgentStringLength($content) > 1200) {
            $content = aiAgentStringSubstring($content, 0, 1200);
        } elseif (strlen($content) > 1200) {
            $content = substr($content, 0, 1200);
        }

        $normalized[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => (int) ($message['timestamp'] ?? time()),
        ];
    }

    return array_slice($normalized, -max(1, $limit));
}

function aiAgentBuildConversationArchiveTitle(array $messages): string
{
    foreach ($messages as $message) {
        if (($message['role'] ?? '') !== 'user') {
            continue;
        }

        $content = trim((string) ($message['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        if (aiAgentStringLength($content) > 42) {
            return rtrim(aiAgentStringSubstring($content, 0, 41)) . '...';
        }

        return $content;
    }

    $lastMessage = end($messages);
    $timestamp = (int) ($lastMessage['timestamp'] ?? time());
    return 'Percakapan ' . date('d M Y H:i', $timestamp);
}

function aiAgentBuildConversationArchivePreview(array $messages): string
{
    foreach ($messages as $message) {
        if (($message['role'] ?? '') !== 'user') {
            continue;
        }

        $content = trim((string) ($message['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        if (aiAgentStringLength($content) > 120) {
            return rtrim(aiAgentStringSubstring($content, 0, 119)) . '...';
        }

        return $content;
    }

    foreach ($messages as $message) {
        $content = trim((string) ($message['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        if (aiAgentStringLength($content) > 120) {
            return rtrim(aiAgentStringSubstring($content, 0, 119)) . '...';
        }

        return $content;
    }

    return 'Percakapan tanpa isi.';
}

function aiAgentBuildConversationArchiveRecord(array $state, int $messageLimit = 60): array
{
    $conversationId = aiAgentNormalizeMemoryConversationId((string) ($state['conversation_id'] ?? ''), 'default');
    $messages = aiAgentNormalizeMemoryMessages(
        isset($state['messages']) && is_array($state['messages']) ? $state['messages'] : [],
        max(1, $messageLimit)
    );

    $updatedAt = (int) ($state['updated_at'] ?? 0);
    if ($updatedAt <= 0 && !empty($messages)) {
        $updatedAt = (int) ($messages[count($messages) - 1]['timestamp'] ?? 0);
    }

    $createdAt = (int) ($state['created_at'] ?? 0);
    if ($createdAt <= 0 && !empty($messages)) {
        $createdAt = (int) ($messages[0]['timestamp'] ?? 0);
    }

    return [
        'id' => $conversationId,
        'conversation_id' => $conversationId,
        'server_conversation_id' => $conversationId,
        'title' => aiAgentBuildConversationArchiveTitle($messages),
        'preview' => aiAgentBuildConversationArchivePreview($messages),
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
        'count' => count($messages),
        'messages' => $messages,
    ];
}

function aiAgentMergeConversationHistory(array $clientHistory, array $serverMessages, int $limit = 12): array
{
    $merged = array_merge(
        aiAgentNormalizeMemoryMessages($serverMessages, $limit * 2),
        aiAgentNormalizeMemoryMessages($clientHistory, $limit * 2)
    );

    $deduped = [];
    $seen = [];
    foreach ($merged as $message) {
        $fingerprint = $message['role'] . '|' . md5((string) ($message['content'] ?? ''));
        if (isset($seen[$fingerprint])) {
            continue;
        }

        $seen[$fingerprint] = true;
        $deduped[] = [
            'role' => $message['role'],
            'content' => $message['content'],
        ];
    }

    return array_slice($deduped, -max(1, $limit));
}

function aiAgentAppendConversationMemory(
    array $memoryConfig,
    string $role,
    int $userId,
    string $conversationId,
    string $userMessage,
    string $assistantReply,
    array $pageContext = []
): void {
    if (empty($memoryConfig['enabled'])) {
        return;
    }

    aiAgentEnsureMemoryDirectories($memoryConfig);
    $existing = aiAgentLoadConversationMemory($memoryConfig, $role, $userId, $conversationId);
    $messages = isset($existing['messages']) && is_array($existing['messages']) ? $existing['messages'] : [];

    $messages[] = [
        'role' => 'user',
        'content' => trim($userMessage),
        'timestamp' => time(),
        'page_path' => (string) ($pageContext['path'] ?? ''),
    ];
    $messages[] = [
        'role' => 'assistant',
        'content' => trim($assistantReply),
        'timestamp' => time(),
        'page_path' => (string) ($pageContext['path'] ?? ''),
    ];
    $messages = aiAgentNormalizeMemoryMessages($messages, (int) ($memoryConfig['max_messages_per_conversation'] ?? 40));
    aiAgentPersistConversationMemoryState($memoryConfig, $role, $userId, $conversationId, [
        'conversation_id' => $conversationId,
        'role' => $role,
        'user_id' => $userId,
        'created_at' => (int) ($existing['created_at'] ?? time()),
        'updated_at' => time(),
        'messages' => $messages,
    ]);
}

function aiAgentDeleteConversationMemory(
    array $memoryConfig,
    string $role,
    int $userId,
    string $conversationId
): bool {
    if (empty($memoryConfig['enabled']) || $userId <= 0) {
        return false;
    }

    $conversationId = aiAgentNormalizeMemoryConversationId($conversationId, '');
    if ($conversationId === '') {
        return false;
    }

    $handled = false;
    if ($conn = aiAgentGetIntegratedMemoryConnectionForRuntime($memoryConfig)) {
        if (function_exists('aiAgentIntegratedDeleteConversationMemory')) {
            $handled = aiAgentIntegratedDeleteConversationMemory($conn, $userId, $conversationId);
        }
        aiAgentDeleteLegacyConversationMemoryFile($memoryConfig, $role, $userId, $conversationId);
        return $handled;
    }

    if (!aiAgentMemoryFileFallbackIsAllowed($memoryConfig)) {
        return false;
    }

    $path = aiAgentGetConversationMemoryPath($memoryConfig, $role, $userId, $conversationId);
    $exists = is_file($path);
    aiAgentDeleteMemoryFile($path);

    return $exists && !is_file($path);
}

function aiAgentExtractUserMemoryNotes(string $message): array
{
    $message = trim($message);
    if ($message === '') {
        return [];
    }

    $notes = [];
    $patterns = [
        '/\bingat(?:lah)?\b\s*(?:bahwa|kalau)?\s*(.+)$/iu' => 'Catatan user: %s',
        '/\b(jangan\s+.+)$/iu' => 'Preferensi user: %s',
        '/\b(mulai sekarang.+)$/iu' => 'Instruksi jangka panjang user: %s',
        '/\b(?:prefer|lebih suka)\b\s+(.+)$/iu' => 'Preferensi user: %s',
        '/\b(?:panggil|sebut)\s+saya\s+(.+)$/iu' => 'Panggilan user: %s',
        '/\b(?:bukan)\s+(.+?)\s+\b(?:tapi|melainkan)\b\s+(.+)$/iu' => 'Koreksi user: bukan %s, tetapi %s',
    ];

    foreach ($patterns as $pattern => $template) {
        if (!preg_match($pattern, $message, $matches)) {
            continue;
        }

        if (strpos($template, '%s') !== false && count($matches) >= 3) {
            $note = sprintf($template, trim((string) $matches[1]), trim((string) $matches[2]));
        } else {
            $note = sprintf($template, trim((string) ($matches[1] ?? '')));
        }

        $note = trim((string) preg_replace('/\s+/', ' ', $note));
        if ($note !== '') {
            $notes[] = $note;
        }
    }

    return array_values(array_unique(array_slice($notes, 0, 6)));
}

function aiAgentStoreUserMemoryNotes(array $memoryConfig, string $role, int $userId, array $notes): void
{
    if (empty($memoryConfig['enabled']) || $userId <= 0 || empty($notes)) {
        return;
    }

    aiAgentEnsureMemoryDirectories($memoryConfig);

    $profile = aiAgentLoadUserProfileMemory($memoryConfig, $role, $userId);
    $existingNotes = isset($profile['notes']) && is_array($profile['notes']) ? $profile['notes'] : [];
    foreach ($notes as $note) {
        $note = trim((string) $note);
        if ($note !== '') {
            $existingNotes[] = $note;
        }
    }
    $existingNotes = array_values(array_unique(array_slice($existingNotes, -max(1, (int) ($memoryConfig['max_notes_per_user'] ?? 30)))));
    $profile['notes'] = $existingNotes;
    $profile['updated_at'] = time();
    aiAgentPersistUserProfileMemoryState($memoryConfig, $role, $userId, $profile);
}

function aiAgentStoreLessonMemory(array $memoryConfig, string $role, int $userId, string $message, string $reply): array
{
    $notes = aiAgentExtractUserMemoryNotes($message);
    if (empty($notes) || $userId <= 0) {
        return [];
    }

    aiAgentEnsureMemoryDirectories($memoryConfig);
    $lessonsState = aiAgentLoadUserLessonsMemory($memoryConfig, $role, $userId);
    $lessons = isset($lessonsState['lessons']) && is_array($lessonsState['lessons']) ? $lessonsState['lessons'] : [];

    foreach ($notes as $note) {
        $lessons[] = [
            'note' => $note,
            'source_message' => trim($message),
            'assistant_reply_excerpt' => trim(function_exists('aiAgentStringSubstring') ? aiAgentStringSubstring($reply, 0, 200) : substr($reply, 0, 200)),
            'created_at' => time(),
        ];
    }

    $lessons = array_slice($lessons, -max(1, (int) ($memoryConfig['max_notes_per_user'] ?? 30)));
    $lessonsState['lessons'] = $lessons;
    $lessonsState['updated_at'] = time();
    aiAgentPersistUserLessonsMemoryState($memoryConfig, $role, $userId, $lessonsState);
    aiAgentStoreUserMemoryNotes($memoryConfig, $role, $userId, $notes);

    return $notes;
}

function aiAgentBuildMemoryContext(array $memoryConfig, string $role, int $userId, string $conversationId, string $currentMessage = ''): string
{
    if (empty($memoryConfig['enabled'])) {
        return '';
    }

    aiAgentEnsureMemoryDirectories($memoryConfig);

    $profile = aiAgentLoadUserProfileMemory($memoryConfig, $role, $userId);
    $lessonsState = aiAgentLoadUserLessonsMemory($memoryConfig, $role, $userId);
    $conversationState = aiAgentLoadConversationMemory($memoryConfig, $role, $userId, $conversationId);

    $lines = ['[MEMORY_CONTEXT]'];

    $profileNotes = isset($profile['notes']) && is_array($profile['notes']) ? array_slice($profile['notes'], -8) : [];
    if (!empty($profileNotes)) {
        $lines[] = 'Preferensi dan catatan user yang pernah disimpan:';
        foreach ($profileNotes as $note) {
            $lines[] = '- ' . trim((string) $note);
        }
    }

    $lessons = isset($lessonsState['lessons']) && is_array($lessonsState['lessons']) ? array_slice($lessonsState['lessons'], -6) : [];
    if (!empty($lessons)) {
        $lines[] = 'Pelajaran dari percakapan sebelumnya:';
        foreach ($lessons as $lesson) {
            $note = trim((string) ($lesson['note'] ?? ''));
            if ($note !== '') {
                $lines[] = '- ' . $note;
            }
        }
    }

    $recentMessages = isset($conversationState['messages']) && is_array($conversationState['messages'])
        ? array_slice(aiAgentNormalizeMemoryMessages($conversationState['messages'], 10), -6)
        : [];
    if (!empty($recentMessages)) {
        $lines[] = 'Ringkasan percakapan server-side terbaru:';
        foreach ($recentMessages as $memoryMessage) {
            $content = trim((string) ($memoryMessage['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $prefix = ((string) ($memoryMessage['role'] ?? 'assistant')) === 'user' ? 'User' : 'Assistant';
            $content = function_exists('aiAgentStringSubstring') && aiAgentStringLength($content) > 220
                ? aiAgentStringSubstring($content, 0, 220)
                : substr($content, 0, 220);
            $lines[] = '- ' . $prefix . ': ' . $content;
        }
    }

    $memoryMatches = aiAgentSearchMemory(
        $memoryConfig,
        $role,
        $userId,
        $conversationId,
        $currentMessage,
        (int) ($memoryConfig['max_search_results'] ?? 5)
    );
    if (!empty($memoryMatches)) {
        $lines[] = 'Recall memory yang paling relevan dengan pertanyaan saat ini:';
        foreach ($memoryMatches as $match) {
            $content = trim((string) ($match['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $content = aiAgentStringLength($content) > 220
                ? aiAgentStringSubstring($content, 0, 220)
                : $content;
            $lines[] = '- [' . (string) ($match['source'] ?? 'memory') . '] ' . $content;
        }
    }

    // Include curated MEMORY.md if available (higher priority than JSON memory)
    if (function_exists('aiAgentBuildMemoryContextForPrompt')) {
        $curatedMemoryContext = aiAgentBuildMemoryContextForPrompt($memoryConfig, $role, $userId);
        if ($curatedMemoryContext !== '') {
            $lines[] = '';
            $lines[] = trim($curatedMemoryContext);
        }
    }

    $lines[] = '[/MEMORY_CONTEXT]';

    return count($lines) > 2 ? implode("\n", $lines) : '';
}

function aiAgentAppendReflectionLog(array $memoryConfig, array $payload): void
{
    if (empty($memoryConfig['enabled'])) {
        return;
    }

    $persisted = false;
    if ($conn = aiAgentGetIntegratedMemoryConnectionForRuntime($memoryConfig)) {
        if (function_exists('aiAgentIntegratedSaveReflectionLog')) {
            $persisted = aiAgentIntegratedSaveReflectionLog($conn, $payload);
        }
    }

    if ($persisted) {
        aiAgentDeleteLegacyReflectionLogFile($memoryConfig);
        return;
    }

    if (!aiAgentMemoryFileFallbackIsAllowed($memoryConfig)) {
        return;
    }

    aiAgentEnsureMemoryDirectories($memoryConfig);
    $path = aiAgentGetReflectionLogPath($memoryConfig);
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

/**
 * Infer user goals from recent conversations
 */
function aiAgentInferUserGoals(array $recentMessages): array
{
    $goals = [];
    $goalKeywords = [
        'ingin|mau|bertujuan|tujuan|target|goal|objective|mencari|cari|butuh|perlu|harus' => 'Task',
        'belajar|pelajari|pahami|mengerti|gimana cara|bagaimana|tutorial|step' => 'Learning',
        'error|bug|masalah|problem|tidak bisa|gagal|error|issue' => 'Problem-solving',
        'laporan|ringkasan|summary|statistik|data|total|berapa|berapa banyak' => 'Reporting',
        'buat|buat|tambah|edit|ubah|ganti|delete|hapus|remove' => 'Data-modification',
        'cek|lihat|tampilkan|show|list|daftar|mana|siapa' => 'Inspection',
    ];

    foreach ($recentMessages as $msg) {
        $content = strtolower((string) ($msg['content'] ?? ''));
        if ($content === '' || ($msg['role'] ?? '') !== 'user') {
            continue;
        }

        foreach ($goalKeywords as $pattern => $goalLabel) {
            if (preg_match('/' . $pattern . '/u', $content) === 1) {
                $goals[$goalLabel] = ($goals[$goalLabel] ?? 0) + 1;
            }
        }
    }

    arsort($goals);
    return array_slice(array_keys($goals), 0, 3);
}

/**
 * Infer communication style from recent messages
 */
function aiAgentInferCommunicationStyle(array $recentMessages): array
{
    $styles = [];
    $messageCount = 0;
    $averageLength = 0;
    $usedFormal = 0;
    $usedCasual = 0;
    $usedQuestions = 0;

    foreach ($recentMessages as $msg) {
        if (($msg['role'] ?? '') !== 'user') {
            continue;
        }

        $content = trim((string) ($msg['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        $messageCount += 1;
        $averageLength += strlen($content);

        if (preg_match('/\b(mohon|harap|diminta|silakan)\b/u', $content) === 1) {
            $usedFormal += 1;
        }
        if (preg_match('/\b(please|pls|thanks|thx|sure)\b/i', $content) === 1) {
            $usedCasual += 1;
        }
        if (preg_match('/\?$/', trim($content)) === 1) {
            $usedQuestions += 1;
        }
    }

    if ($messageCount > 0) {
        $averageLength = (int) ($averageLength / $messageCount);
        if ($averageLength < 50) {
            $styles[] = 'Concise';
        } elseif ($averageLength > 200) {
            $styles[] = 'Verbose';
        }

        $formalRatio = $usedFormal / $messageCount;
        if ($formalRatio > 0.3) {
            $styles[] = 'Formal';
        } elseif ($usedCasual / $messageCount > 0.3) {
            $styles[] = 'Casual';
        }

        $questionRatio = $usedQuestions / $messageCount;
        if ($questionRatio > 0.4) {
            $styles[] = 'Inquiry-driven';
        } elseif ($questionRatio < 0.1) {
            $styles[] = 'Statement-driven';
        }
    }

    return array_values(array_unique($styles));
}

/**
 * Infer expertise level from technical depth of questions
 */
function aiAgentInferExpertiseLevel(array $recentMessages): string
{
    $technicalKeywords = 0;
    $basicKeywords = 0;
    $userMessages = 0;

    foreach ($recentMessages as $msg) {
        if (($msg['role'] ?? '') !== 'user') {
            continue;
        }

        $content = strtolower((string) ($msg['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        $userMessages += 1;

        if (preg_match('/\b(schema|endpoint|query|sql|function|api|payload|authentication|query optimization|indexing)\b/', $content) === 1) {
            $technicalKeywords += 1;
        }
        if (preg_match('/\b(button|click|page|menu|halaman|klik|tombol)\b/', $content) === 1) {
            $basicKeywords += 1;
        }
    }

    if ($userMessages === 0) {
        return 'Unknown';
    }

    $technicalRatio = $technicalKeywords / $userMessages;
    $basicRatio = $basicKeywords / $userMessages;

    if ($technicalRatio > 0.4) {
        return 'Advanced (technical depth)';
    } elseif ($basicRatio > 0.5) {
        return 'Beginner (UI-focused)';
    }

    return 'Intermediate';
}

/**
 * Update MEMORY.md dengan automatic behavioral profiling
 */
function aiAgentEnhanceMemoryWithBehavioralProfile(array $parsedMemory, array $recentMessages): array
{
    $goals = aiAgentInferUserGoals($recentMessages);
    $styles = aiAgentInferCommunicationStyle($recentMessages);
    $expertise = aiAgentInferExpertiseLevel($recentMessages);

    if (!empty($goals)) {
        $parsedMemory['goals'] = implode("\n", array_map(function ($goal) {
            return '- ' . trim($goal);
        }, array_filter($goals)));
    }

    if (!empty($styles)) {
        $parsedMemory['preferences'] = "Communication style: " . implode(', ', $styles)
            . "\nExpertise: " . $expertise
            . "\nApproach: Prefer " . (in_array('Concise', $styles) ? 'concise, direct answers' : 'detailed explanations') . ".";
    }

    return $parsedMemory;
}

function aiAgentCountLegacyMemoryFiles(array $memoryConfig, string $key): int
{
    $directory = (string) ($memoryConfig[$key] ?? '');
    if ($directory === '' || !is_dir($directory)) {
        return 0;
    }

    $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json');
    return is_array($files) ? count($files) : 0;
}

function aiAgentGetMemoryBackendStatus(array $memoryConfig): array
{
    $status = [
        'backend' => 'disabled',
        'database_enabled' => !empty($memoryConfig['database_enabled']),
        'db_connection_ok' => false,
        'db_tables' => [],
        'fallback_active' => false,
        'backfill_enabled' => !empty($memoryConfig['database_enabled']),
        'legacy_files_present' => false,
        'legacy_file_counts' => [
            'conversations' => aiAgentCountLegacyMemoryFiles($memoryConfig, 'conversations_dir'),
            'profiles' => aiAgentCountLegacyMemoryFiles($memoryConfig, 'profiles_dir'),
            'lessons' => aiAgentCountLegacyMemoryFiles($memoryConfig, 'lessons_dir'),
        ],
    ];

    $status['legacy_files_present'] = array_sum($status['legacy_file_counts']) > 0;

    if (empty($memoryConfig['enabled'])) {
        return $status;
    }

    $status['backend'] = aiAgentMemoryFileFallbackIsAllowed($memoryConfig) ? 'file_fallback' : 'database_only';

    if (!$status['database_enabled']) {
        return $status;
    }

    $conn = aiAgentGetIntegratedMemoryConnectionForRuntime($memoryConfig, false);
    if (!$conn instanceof mysqli || !@$conn->ping()) {
        $status['backend'] = aiAgentMemoryFileFallbackIsAllowed($memoryConfig)
            ? 'file_fallback'
            : 'database_only_unavailable';
        $status['fallback_active'] = aiAgentMemoryFileFallbackIsAllowed($memoryConfig);
        return $status;
    }

    $status['db_connection_ok'] = true;
    $status['db_tables'] = aiAgentIntegratedGetMemoryTablesStatus($conn);

    $allTablesReady = !empty($status['db_tables']);
    foreach ($status['db_tables'] as $tableStatus) {
        if (empty($tableStatus['exists'])) {
            $allTablesReady = false;
            break;
        }
    }

    if ($allTablesReady) {
        $status['backend'] = 'integrated_db';
        $status['fallback_active'] = false;
        return $status;
    }

    $status['backend'] = aiAgentMemoryFileFallbackIsAllowed($memoryConfig)
        ? 'file_fallback'
        : 'database_only_unavailable';
    $status['fallback_active'] = aiAgentMemoryFileFallbackIsAllowed($memoryConfig);
    return $status;
}

/**
 * Backward-compatible wrappers for older helper names.
 */
function aiAgentLoadConversationMemoryWithDb(array $memoryConfig, string $role, int $userId, string $conversationId): array
{
    return aiAgentLoadConversationMemory($memoryConfig, $role, $userId, $conversationId);
}

function aiAgentSaveConversationMemoryWithDb(
    array $memoryConfig,
    string $role,
    int $userId,
    string $conversationId,
    array $conversationData
): bool {
    return aiAgentPersistConversationMemoryState($memoryConfig, $role, $userId, $conversationId, $conversationData);
}

function aiAgentLoadUserProfileMemoryWithDb(array $memoryConfig, string $role, int $userId): array
{
    return aiAgentLoadUserProfileMemory($memoryConfig, $role, $userId);
}

function aiAgentSaveUserProfileMemoryWithDb(
    array $memoryConfig,
    string $role,
    int $userId,
    array $profileData
): bool {
    return aiAgentPersistUserProfileMemoryState($memoryConfig, $role, $userId, $profileData);
}

function aiAgentLoadUserLessonsMemoryWithDb(array $memoryConfig, string $role, int $userId): array
{
    return aiAgentLoadUserLessonsMemory($memoryConfig, $role, $userId);
}

function aiAgentSaveUserLessonsMemoryWithDb(
    array $memoryConfig,
    string $role,
    int $userId,
    array $lessonsData
): bool {
    return aiAgentPersistUserLessonsMemoryState($memoryConfig, $role, $userId, $lessonsData);
}
