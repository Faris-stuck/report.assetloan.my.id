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

    $projectRoot = function_exists('aiAgentGetProjectRootPath') ? aiAgentGetProjectRootPath() : dirname(__DIR__);
    return $projectRoot . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function aiAgentEnsureMemoryDirectories(array $memoryConfig = []): void
{
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

function aiAgentBuildMemoryConversationKey(string $role, int $userId, string $conversationId): string
{
    $conversationId = preg_replace('/[^a-z0-9._:-]+/i', '-', strtolower(trim($conversationId)));
    $conversationId = trim((string) $conversationId, '-');
    if ($conversationId === '') {
        $conversationId = 'default';
    }

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

function aiAgentLoadConversationMemory(array $memoryConfig, string $role, int $userId, string $conversationId): array
{
    $path = aiAgentGetConversationMemoryPath($memoryConfig, $role, $userId, $conversationId);
    return aiAgentReadJsonFile($path, [
        'conversation_id' => $conversationId,
        'role' => $role,
        'user_id' => $userId,
        'messages' => [],
        'updated_at' => 0,
    ]);
}

function aiAgentLoadUserProfileMemory(array $memoryConfig, string $role, int $userId): array
{
    $path = aiAgentGetProfileMemoryPath($memoryConfig, $role, $userId);
    return aiAgentReadJsonFile($path, [
        'role' => $role,
        'user_id' => $userId,
        'notes' => [],
        'updated_at' => 0,
    ]);
}

function aiAgentLoadUserLessonsMemory(array $memoryConfig, string $role, int $userId): array
{
    $path = aiAgentGetLessonsMemoryPath($memoryConfig, $role, $userId);
    return aiAgentReadJsonFile($path, [
        'role' => $role,
        'user_id' => $userId,
        'lessons' => [],
        'updated_at' => 0,
    ]);
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

    $conversationPaths = array_slice(
        aiAgentListUserConversationMemoryPaths($memoryConfig, $role, $userId, $conversationId),
        0,
        max(1, (int) ($memoryConfig['max_search_conversations'] ?? 10))
    );
    foreach ($conversationPaths as $path) {
        $state = aiAgentReadJsonFile($path, []);
        foreach (aiAgentNormalizeMemoryMessages($state['messages'] ?? [], 12) as $message) {
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $entries[] = [
                'source' => 'past_conversation',
                'content' => $content,
                'timestamp' => (int) ($message['timestamp'] ?? (int) @filemtime($path)),
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

    aiAgentWriteJsonFile(aiAgentGetConversationMemoryPath($memoryConfig, $role, $userId, $conversationId), [
        'conversation_id' => $conversationId,
        'role' => $role,
        'user_id' => $userId,
        'updated_at' => time(),
        'messages' => $messages,
    ]);
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

    aiAgentWriteJsonFile(aiAgentGetProfileMemoryPath($memoryConfig, $role, $userId), $profile);
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

    aiAgentWriteJsonFile(aiAgentGetLessonsMemoryPath($memoryConfig, $role, $userId), $lessonsState);
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
