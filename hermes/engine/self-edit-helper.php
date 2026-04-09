<?php

function aiAgentGetHermesSelfEditConfig(array $config = []): array
{
    $projectRoot = function_exists('aiAgentGetProjectRootPath') ? aiAgentGetProjectRootPath() : dirname(__DIR__, 2);
    $allowedRoot = trim((string) ($config['hermes_self_edit_root'] ?? 'hermes'));
    $allowedRoot = str_replace('\\', '/', $allowedRoot);
    $allowedRoot = function_exists('aiAgentNormalizeRelativeSegments')
        ? aiAgentNormalizeRelativeSegments($allowedRoot)
        : trim($allowedRoot, '/');

    if ($allowedRoot === '' || ($allowedRoot !== 'hermes' && strpos($allowedRoot, 'hermes/') !== 0)) {
        $allowedRoot = 'hermes';
    }

    $allowedRootAbsolute = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $allowedRoot);
    $resolvedAllowedRoot = realpath($allowedRootAbsolute);
    if ($resolvedAllowedRoot !== false) {
        $allowedRootAbsolute = $resolvedAllowedRoot;
    }

    $extensionsRaw = trim((string) ($config['hermes_self_edit_allowed_extensions'] ?? 'php,js,css,md,json,txt,sql,html,yml,yaml,ini'));
    $allowedExtensions = [];
    foreach (preg_split('/\s*,\s*/', $extensionsRaw) ?: [] as $extension) {
        $extension = strtolower(trim((string) $extension, " ."));
        if ($extension !== '') {
            $allowedExtensions[] = $extension;
        }
    }

    if (empty($allowedExtensions)) {
        $allowedExtensions = ['php', 'js', 'css', 'md', 'json', 'txt', 'sql', 'html', 'yml', 'yaml', 'ini'];
    }

    return [
        'enabled' => !isset($config['hermes_self_edit_enabled']) ? true : (bool) $config['hermes_self_edit_enabled'],
        'allowed_role' => trim((string) ($config['hermes_self_edit_allowed_role'] ?? 'admin')),
        'requires_sensitive_access' => !isset($config['hermes_self_edit_requires_sensitive_access'])
            ? true
            : (bool) $config['hermes_self_edit_requires_sensitive_access'],
        'allowed_root' => $allowedRoot,
        'allowed_root_absolute' => $allowedRootAbsolute,
        'allowed_extensions' => array_values(array_unique($allowedExtensions)),
        'max_files_per_edit' => max(1, (int) ($config['hermes_self_edit_max_files'] ?? 3)),
        'max_context_files' => max(1, (int) ($config['hermes_self_edit_max_context_files'] ?? 4)),
        'max_prompt_file_chars' => max(1200, (int) ($config['hermes_self_edit_max_prompt_file_chars'] ?? 16000)),
        'max_total_prompt_chars' => max(3000, (int) ($config['hermes_self_edit_max_total_prompt_chars'] ?? 28000)),
        'max_total_write_bytes' => max(4000, (int) ($config['hermes_self_edit_max_total_write_bytes'] ?? 240000)),
        'max_tokens' => max(600, (int) ($config['hermes_self_edit_max_tokens'] ?? 2200)),
        'temperature' => (float) ($config['hermes_self_edit_temperature'] ?? 0.05),
        'auto_signal_reindex' => !isset($config['hermes_self_edit_auto_signal_reindex'])
            ? true
            : (bool) $config['hermes_self_edit_auto_signal_reindex'],
        'log_file' => function_exists('aiAgentResolveHermesStoragePath')
            ? aiAgentResolveHermesStoragePath((string) ($config['hermes_self_edit_log_file'] ?? 'hermes/logs/code-edit-log.jsonl'))
            : ($projectRoot . DIRECTORY_SEPARATOR . 'hermes' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'code-edit-log.jsonl'),
        'backup_dir' => function_exists('aiAgentResolveHermesStoragePath')
            ? aiAgentResolveHermesStoragePath((string) ($config['hermes_self_edit_backup_dir'] ?? 'hermes/patches/applied-backups'))
            : ($projectRoot . DIRECTORY_SEPARATOR . 'hermes' . DIRECTORY_SEPARATOR . 'patches' . DIRECTORY_SEPARATOR . 'applied-backups'),
    ];
}

function aiAgentEnsureHermesSelfEditDirectories(array $selfEditConfig = []): void
{
    $directories = [
        dirname((string) ($selfEditConfig['log_file'] ?? '')),
        (string) ($selfEditConfig['backup_dir'] ?? ''),
    ];

    foreach ($directories as $directory) {
        if ($directory !== '' && !is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }
}

function aiAgentAppendHermesSelfEditLog(array $payload, array $selfEditConfig = []): void
{
    $path = (string) ($selfEditConfig['log_file'] ?? '');
    if ($path === '') {
        return;
    }

    aiAgentEnsureHermesSelfEditDirectories($selfEditConfig);
    @file_put_contents(
        $path,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function aiAgentReadHermesSelfEditLogEntries(int $limit = 10, array $selfEditConfig = []): array
{
    $path = (string) ($selfEditConfig['log_file'] ?? '');
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        return [];
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || empty($lines)) {
        return [];
    }

    $entries = [];
    foreach (array_reverse($lines) as $line) {
        $decoded = json_decode((string) $line, true);
        if (!is_array($decoded)) {
            continue;
        }

        $entries[] = $decoded;
        if (count($entries) >= max(1, $limit)) {
            break;
        }
    }

    return $entries;
}

function aiAgentMessageRequestsHermesSelfEdit(string $message): bool
{
    $message = strtolower(trim($message));
    if ($message === '') {
        return false;
    }

    $editVerbs = [
        'ubah',
        'edit',
        'perbaiki',
        'benerin',
        'fix',
        'modifikasi',
        'refactor',
        'patch',
        'tambah',
        'tambahkan',
        'buat file',
        'update code',
        'update coding',
        'ubah coding',
        'ubah codingan',
        'rewrite',
        'implement',
    ];
    $targets = [
        'hermes',
        'kode',
        'coding',
        'source code',
        'file',
        'folder',
        'helper',
        'endpoint',
        'prompt',
        'memory',
        'chat.php',
        'status.php',
        'history.php',
        'reindex.php',
    ];

    $hasVerb = false;
    foreach ($editVerbs as $verb) {
        if (strpos($message, $verb) !== false) {
            $hasVerb = true;
            break;
        }
    }

    if (!$hasVerb) {
        return false;
    }

    $nonEditQuestionMarkers = [
        'file apa',
        'apa yang kamu ubah',
        'apa yang diubah',
        'yang kamu ubah',
        'yang diubah',
        'sudah kamu ubah',
        'baru saja kamu ubah',
        'baru saja diubah',
        'tadi kamu ubah',
        'tadi diubah',
        'sebelumnya kamu ubah',
        'riwayat perubahan',
        'perubahan apa',
    ];
    $looksLikeQuestion = strpos($message, '?') !== false
        || strpos($message, 'apa ') !== false
        || strpos($message, 'siapa ') !== false
        || strpos($message, 'bagaimana ') !== false;
    if ($looksLikeQuestion) {
        foreach ($nonEditQuestionMarkers as $marker) {
            if (strpos($message, $marker) !== false) {
                return false;
            }
        }
    }

    $directRequestMarkers = [
        'tolong ',
        'please ',
        'coba ',
        'mohon ',
        'silakan ',
        'ganti ',
        'buat ',
        'tambahkan ',
        'ubah ',
        'edit ',
        'fix ',
        'perbaiki ',
        'modifikasi ',
        'refactor ',
        'patch ',
        'implement ',
        'rewrite ',
    ];
    $hasDirectRequestMarker = false;
    foreach ($directRequestMarkers as $marker) {
        if (strpos($message, $marker) !== false) {
            $hasDirectRequestMarker = true;
            break;
        }
    }

    if (!$hasDirectRequestMarker) {
        return false;
    }

    foreach ($targets as $target) {
        if (strpos($message, $target) !== false) {
            return true;
        }
    }

    return preg_match('/\b[a-z0-9._-]+\.(php|js|css|md|json|txt|sql|html|yml|yaml|ini)\b/i', $message) === 1;
}

function aiAgentExtractHermesFileMentions(string $message, array $projectIndexState = []): array
{
    $paths = [];

    if (preg_match_all('/\bhermes\/[a-z0-9._\/-]+\.(php|js|css|md|json|txt|sql|html|yml|yaml|ini)\b/i', $message, $pathMatches)) {
        foreach (($pathMatches[0] ?? []) as $match) {
            $path = str_replace('\\', '/', trim((string) $match));
            $paths[] = function_exists('aiAgentNormalizeRelativeSegments')
                ? aiAgentNormalizeRelativeSegments($path)
                : trim($path, '/');
        }
    }

    $basenameToPath = [];
    $files = isset($projectIndexState['project_index']['files']) && is_array($projectIndexState['project_index']['files'])
        ? $projectIndexState['project_index']['files']
        : [];

    foreach ($files as $relativePath => $_unused) {
        $normalizedPath = str_replace('\\', '/', (string) $relativePath);
        if ($normalizedPath === '' || strpos($normalizedPath, 'hermes/') !== 0) {
            continue;
        }

        $basename = strtolower(basename($normalizedPath));
        if (!isset($basenameToPath[$basename])) {
            $basenameToPath[$basename] = [];
        }
        $basenameToPath[$basename][] = $normalizedPath;
    }

    if (preg_match_all('/\b[a-z0-9._-]+\.(php|js|css|md|json|txt|sql|html|yml|yaml|ini)\b/i', $message, $fileMatches)) {
        foreach (($fileMatches[0] ?? []) as $match) {
            $basename = strtolower(trim((string) $match));
            if ($basename === '' || empty($basenameToPath[$basename]) || count($basenameToPath[$basename]) !== 1) {
                continue;
            }

            $paths[] = $basenameToPath[$basename][0];
        }
    }

    return array_values(array_unique(array_filter($paths)));
}

function aiAgentRankHermesProjectIndexPaths(string $message, array $projectIndexState = [], int $limit = 4): array
{
    $files = isset($projectIndexState['project_index']['files']) && is_array($projectIndexState['project_index']['files'])
        ? $projectIndexState['project_index']['files']
        : [];
    if (empty($files)) {
        return [];
    }

    $keywords = function_exists('aiAgentBuildCodeSearchKeywords')
        ? aiAgentBuildCodeSearchKeywords($message, '', '', '', [])
        : (preg_split('/[^a-z0-9_]+/i', strtolower($message)) ?: []);
    $keywords = array_values(array_filter(array_unique(array_map('strval', $keywords)), static function ($keyword) {
        return strlen((string) $keyword) >= 3;
    }));

    $scored = [];
    foreach ($files as $relativePath => $entry) {
        $normalizedPath = str_replace('\\', '/', (string) $relativePath);
        if ($normalizedPath === '' || strpos($normalizedPath, 'hermes/') !== 0) {
            continue;
        }

        $haystack = strtolower(
            $normalizedPath . ' ' .
            basename($normalizedPath) . ' ' .
            implode(' ', array_map('strval', (array) (($entry['functions'] ?? [])))) . ' ' .
            implode(' ', array_map('strval', (array) (($entry['headings'] ?? []))))
        );

        $score = 0;
        foreach ($keywords as $keyword) {
            if (strpos($haystack, $keyword) !== false) {
                $score += 4;
            }
        }

        if ($score <= 0) {
            continue;
        }

        $scored[] = [
            'path' => $normalizedPath,
            'score' => $score,
        ];
    }

    usort($scored, static function (array $left, array $right): int {
        return ($right['score'] ?? 0) <=> ($left['score'] ?? 0);
    });

    return array_map(static function (array $item): string {
        return (string) ($item['path'] ?? '');
    }, array_slice($scored, 0, max(1, $limit)));
}

function aiAgentCollectHermesEditCandidates(
    string $message,
    array $pageContext = [],
    array $sharedState = [],
    array $selfEditConfig = []
): array {
    $projectRoot = function_exists('aiAgentGetProjectRootPath') ? aiAgentGetProjectRootPath() : dirname(__DIR__, 2);
    $projectIndexState = isset($sharedState['project_index_state']) && is_array($sharedState['project_index_state'])
        ? $sharedState['project_index_state']
        : [];
    $explicitPaths = aiAgentExtractHermesFileMentions($message, $projectIndexState);
    $candidatePaths = $explicitPaths;

    $matches = [];
    if (function_exists('aiAgentCollectRelevantCodeMatches')) {
        $matches = aiAgentCollectRelevantCodeMatches([
            'message' => $message,
            'page_path' => (string) ($pageContext['path'] ?? ''),
            'page_title' => (string) ($pageContext['title'] ?? ''),
            'page_heading' => (string) ($pageContext['heading'] ?? ''),
            'page_snapshot' => isset($pageContext['ui_snapshot']) && is_array($pageContext['ui_snapshot'])
                ? $pageContext['ui_snapshot']
                : [],
            'focus_scopes' => ['hermes'],
        ]);
    }

    $matchMap = [];
    foreach ($matches as $match) {
        $path = str_replace('\\', '/', (string) ($match['path'] ?? ''));
        if ($path === '' || strpos($path, 'hermes/') !== 0) {
            continue;
        }

        $matchMap[$path] = $match;
        $candidatePaths[] = $path;
    }

    $manifestEntries = isset($sharedState['relevant_manifest_entries']) && is_array($sharedState['relevant_manifest_entries'])
        ? $sharedState['relevant_manifest_entries']
        : [];
    foreach ($manifestEntries as $entry) {
        $path = str_replace('\\', '/', (string) ($entry['path'] ?? ''));
        if ($path !== '' && strpos($path, 'hermes/') === 0) {
            $candidatePaths[] = $path;
        }
    }

    if (empty($candidatePaths)) {
        $candidatePaths = aiAgentRankHermesProjectIndexPaths($message, $projectIndexState, (int) ($selfEditConfig['max_context_files'] ?? 4));
    }

    $candidatePaths = array_values(array_unique(array_filter($candidatePaths)));
    $candidatePaths = array_slice($candidatePaths, 0, max(1, (int) ($selfEditConfig['max_context_files'] ?? 4)));

    $totalPromptChars = 0;
    $maxPromptChars = max(1200, (int) ($selfEditConfig['max_prompt_file_chars'] ?? 16000));
    $maxTotalPromptChars = max($maxPromptChars, (int) ($selfEditConfig['max_total_prompt_chars'] ?? 28000));
    $candidates = [];

    foreach ($candidatePaths as $path) {
        $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $entry = [
            'path' => $path,
            'exists' => is_file($absolutePath),
            'size' => is_file($absolutePath) ? (int) @filesize($absolutePath) : 0,
            'full_content' => '',
            'snippets' => [],
        ];

        if ($entry['exists']) {
            $content = (string) @file_get_contents($absolutePath);
            if ($content !== '' && aiAgentStringLength($content) <= $maxPromptChars && ($totalPromptChars + aiAgentStringLength($content)) <= $maxTotalPromptChars) {
                $entry['full_content'] = $content;
                $totalPromptChars += aiAgentStringLength($content);
            } elseif (isset($matchMap[$path]['snippets']) && is_array($matchMap[$path]['snippets'])) {
                foreach (array_slice($matchMap[$path]['snippets'], 0, 3) as $snippet) {
                    $entry['snippets'][] = [
                        'start' => (int) ($snippet['start'] ?? 0),
                        'end' => (int) ($snippet['end'] ?? 0),
                        'lines' => isset($snippet['lines']) && is_array($snippet['lines']) ? $snippet['lines'] : [],
                    ];
                }
            }
        }

        $candidates[] = $entry;
    }

    return [
        'explicit_paths' => $explicitPaths,
        'candidates' => $candidates,
    ];
}

function aiAgentBuildHermesSelfEditWorkspaceContext(array $candidateState = [], array $selfEditConfig = []): string
{
    $candidates = isset($candidateState['candidates']) && is_array($candidateState['candidates'])
        ? $candidateState['candidates']
        : [];
    $lines = [
        '[HERMES_SELF_EDIT_CONTEXT]',
        'Write scope hanya boleh di bawah: ' . (string) ($selfEditConfig['allowed_root'] ?? 'hermes') . '.',
        'Maksimal file yang boleh diubah sekali jalan: ' . (int) ($selfEditConfig['max_files_per_edit'] ?? 3) . '.',
        'Gunakan search_replace untuk perubahan kecil pada file existing. Gunakan create hanya untuk file baru yang benar-benar diperlukan.',
    ];

    if (empty($candidates)) {
        $lines[] = 'Tidak ada file Hermes yang cukup relevan ditemukan dari konteks saat ini. Jika butuh edit, user perlu menyebut file/path dengan lebih spesifik.';
        $lines[] = '[/HERMES_SELF_EDIT_CONTEXT]';
        return implode("\n", $lines);
    }

    foreach ($candidates as $candidate) {
        $path = (string) ($candidate['path'] ?? '');
        if ($path === '') {
            continue;
        }

        $lines[] = '';
        $lines[] = '[FILE path=' . $path . ' exists=' . (!empty($candidate['exists']) ? 'yes' : 'no') . ' size=' . (int) ($candidate['size'] ?? 0) . ']';
        if (!empty($candidate['full_content'])) {
            $lines[] = (string) $candidate['full_content'];
        } elseif (!empty($candidate['snippets'])) {
            foreach ($candidate['snippets'] as $snippet) {
                $lines[] = '[SNIPPET lines=' . (int) ($snippet['start'] ?? 0) . '-' . (int) ($snippet['end'] ?? 0) . ']';
                foreach (($snippet['lines'] ?? []) as $snippetLine) {
                    $lines[] = (string) $snippetLine;
                }
                $lines[] = '[/SNIPPET]';
            }
        } else {
            $lines[] = '[NO_CONTENT_AVAILABLE]';
        }
        $lines[] = '[/FILE]';
    }

    $lines[] = '[/HERMES_SELF_EDIT_CONTEXT]';

    return implode("\n", $lines);
}

function aiAgentBuildHermesSelfEditSystemPrompt(array $selfEditConfig = []): string
{
    return implode("\n", [
        'Anda adalah worker edit kode khusus untuk Hermes.',
        'Terapkan perubahan hanya ke folder `' . (string) ($selfEditConfig['allowed_root'] ?? 'hermes') . '`.',
        'Jangan pernah menulis ke luar folder itu.',
        'Balas hanya dengan JSON valid tanpa markdown fence.',
        'Gunakan format:',
        '{',
        '  "summary": "ringkasan singkat",',
        '  "notes": ["catatan opsional"],',
        '  "edits": [',
        '    {',
        '      "path": "hermes/....php",',
        '      "operation": "search_replace|replace|create",',
        '      "reason": "alasan singkat",',
        '      "search": "wajib untuk search_replace",',
        '      "replace": "wajib untuk search_replace",',
        '      "content": "wajib untuk replace/create"',
        '    }',
        '  ]',
        '}',
        'Pilih search_replace jika file existing hanya butuh perubahan lokal.',
        'Jika instruksi belum jelas atau file target tidak cukup pasti, kembalikan edits kosong dan jelaskan kebutuhan user di notes.',
        'Jangan menghasilkan operasi delete.',
        'Jangan mengubah file lebih dari ' . (int) ($selfEditConfig['max_files_per_edit'] ?? 3) . ' file.',
    ]);
}

function aiAgentCallHermesSelfEditModel(array $config = [], array $messages = [], array $selfEditConfig = []): array
{
    $baseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
    $apiKey = trim((string) ($config['api_key'] ?? ''));
    $model = trim((string) ($config['model'] ?? ''));
    $timeout = max(5, (int) ($config['timeout'] ?? 45));

    if ($baseUrl === '' || $apiKey === '' || $model === '') {
        return [
            'ok' => false,
            'error' => 'Konfigurasi provider utama Hermes belum lengkap.',
        ];
    }

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => max(0, min(1, (float) ($selfEditConfig['temperature'] ?? 0.05))),
        'max_tokens' => max(600, (int) ($selfEditConfig['max_tokens'] ?? 2200)),
    ];

    $response = aiAgentHttpRequest('POST', $baseUrl . '/chat/completions', [
        'headers' => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        'body' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        'timeout' => $timeout,
        'connect_timeout' => 10,
    ]);

    $body = (string) ($response['body'] ?? '');
    $httpCode = (int) ($response['http_code'] ?? 0);
    if ($httpCode > 0 && $httpCode < 400 && $body !== '') {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $reply = function_exists('aiAgentExtractProviderReply')
                ? aiAgentExtractProviderReply($decoded)
                : trim((string) (($decoded['choices'][0]['message']['content'] ?? '')));

            if ($reply !== '') {
                return [
                    'ok' => true,
                    'reply' => $reply,
                    'provider' => 'primary',
                ];
            }
        }
    }

    $extConfig = function_exists('aiAgentGetExtendedProviderConfig') ? aiAgentGetExtendedProviderConfig($config) : [];
    if (!empty($extConfig['enabled']) && !empty($extConfig['fallback_on_error']) && function_exists('aiAgentCallExtendedProvider')) {
        $fallback = aiAgentCallExtendedProvider($extConfig, (string) ($messages[0]['content'] ?? ''), array_slice($messages, 1));
        if (!empty($fallback['ok']) && !empty($fallback['reply'])) {
            return [
                'ok' => true,
                'reply' => (string) $fallback['reply'],
                'provider' => 'fallback',
            ];
        }
    }

    return [
        'ok' => false,
        'error' => trim((string) ($response['error'] ?? '')) !== ''
            ? trim((string) ($response['error'] ?? ''))
            : 'Provider edit Hermes tidak mengembalikan respons yang valid.',
    ];
}

function aiAgentExtractHermesSelfEditPlan(string $reply): array
{
    $candidates = [trim($reply)];
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $reply, $matches) === 1) {
        $candidates[] = trim((string) ($matches[1] ?? ''));
    }

    $firstBrace = strpos($reply, '{');
    $lastBrace = strrpos($reply, '}');
    if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
        $candidates[] = trim((string) substr($reply, $firstBrace, ($lastBrace - $firstBrace) + 1));
    }

    foreach (array_values(array_unique(array_filter($candidates))) as $candidate) {
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

function aiAgentNormalizeHermesSelfEditPath(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    if ($path === '') {
        return '';
    }

    return function_exists('aiAgentNormalizeRelativeSegments')
        ? aiAgentNormalizeRelativeSegments($path)
        : trim($path, '/');
}

function aiAgentResolveHermesSelfEditPath(string $path, array $selfEditConfig = []): array
{
    $normalizedPath = aiAgentNormalizeHermesSelfEditPath($path);
    $allowedRoot = trim((string) ($selfEditConfig['allowed_root'] ?? 'hermes'), '/');
    $allowedRootAbsolute = rtrim(str_replace('\\', '/', (string) ($selfEditConfig['allowed_root_absolute'] ?? '')), '/');

    if ($normalizedPath === '' || ($normalizedPath !== $allowedRoot && strpos($normalizedPath, $allowedRoot . '/') !== 0)) {
        return [
            'ok' => false,
            'error' => 'Path di luar folder Hermes tidak diizinkan: ' . $path,
        ];
    }

    $extension = strtolower((string) pathinfo($normalizedPath, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, (array) ($selfEditConfig['allowed_extensions'] ?? []), true)) {
        return [
            'ok' => false,
            'error' => 'Ekstensi file tidak diizinkan untuk self edit: ' . $normalizedPath,
        ];
    }

    $projectRoot = function_exists('aiAgentGetProjectRootPath') ? aiAgentGetProjectRootPath() : dirname(__DIR__, 2);
    $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
    $absolutePathNormalized = str_replace('\\', '/', $absolutePath);
    if ($allowedRootAbsolute !== '' && strpos($absolutePathNormalized, $allowedRootAbsolute) !== 0) {
        return [
            'ok' => false,
            'error' => 'Resolved path keluar dari root Hermes: ' . $normalizedPath,
        ];
    }

    $realExistingPath = realpath($absolutePath);
    if ($realExistingPath !== false) {
        $realExistingPath = str_replace('\\', '/', $realExistingPath);
        if ($allowedRootAbsolute !== '' && strpos($realExistingPath, $allowedRootAbsolute) !== 0) {
            return [
                'ok' => false,
                'error' => 'Real path file di luar root Hermes: ' . $normalizedPath,
            ];
        }
    }

    return [
        'ok' => true,
        'path' => $normalizedPath,
        'absolute_path' => $absolutePath,
        'exists' => is_file($absolutePath),
        'extension' => $extension,
    ];
}

function aiAgentReplaceFirstOccurrence(string $content, string $search, string $replace): array
{
    $position = strpos($content, $search);
    if ($position === false) {
        return [
            'ok' => false,
            'content' => $content,
        ];
    }

    return [
        'ok' => true,
        'content' => substr($content, 0, $position) . $replace . substr($content, $position + strlen($search)),
    ];
}

function aiAgentApplyHermesSelfEditPlan(array $plan, array $selfEditConfig = [], array $context = []): array
{
    aiAgentEnsureHermesSelfEditDirectories($selfEditConfig);

    $summary = trim((string) ($plan['summary'] ?? ''));
    $notes = isset($plan['notes']) && is_array($plan['notes'])
        ? array_values(array_filter(array_map('strval', $plan['notes']), static function ($note) {
            return trim((string) $note) !== '';
        }))
        : [];
    $edits = isset($plan['edits']) && is_array($plan['edits']) ? array_values($plan['edits']) : [];

    if (empty($edits)) {
        return [
            'ok' => true,
            'applied' => false,
            'summary' => $summary,
            'notes' => $notes,
            'files' => [],
            'errors' => [],
            'backup_dir' => '',
            'reindex_signal' => [],
        ];
    }

    $buffers = [];
    $initialState = [];
    $pathMeta = [];
    $errors = [];

    foreach ($edits as $index => $edit) {
        $path = (string) ($edit['path'] ?? '');
        $operation = strtolower(trim((string) ($edit['operation'] ?? '')));
        $resolved = aiAgentResolveHermesSelfEditPath($path, $selfEditConfig);
        if (empty($resolved['ok'])) {
            $errors[] = (string) ($resolved['error'] ?? 'Path edit tidak valid.');
            continue;
        }

        $normalizedPath = (string) ($resolved['path'] ?? '');
        $absolutePath = (string) ($resolved['absolute_path'] ?? '');
        $exists = !empty($resolved['exists']);

        if (!isset($initialState[$normalizedPath])) {
            $initialState[$normalizedPath] = [
                'exists' => $exists,
                'content' => $exists ? (string) @file_get_contents($absolutePath) : null,
            ];
        }
        if (!isset($buffers[$normalizedPath])) {
            $buffers[$normalizedPath] = $exists ? (string) ($initialState[$normalizedPath]['content'] ?? '') : '';
        }
        $pathMeta[$normalizedPath] = $resolved;

        if ($operation === 'search_replace') {
            $search = (string) ($edit['search'] ?? '');
            $replace = (string) ($edit['replace'] ?? '');
            if ($search === '') {
                $errors[] = 'Edit #' . ($index + 1) . ' tidak punya nilai search.';
                continue;
            }

            if (substr_count((string) $buffers[$normalizedPath], $search) !== 1) {
                $errors[] = 'Search pada ' . $normalizedPath . ' harus muncul tepat satu kali.';
                continue;
            }

            $replaceResult = aiAgentReplaceFirstOccurrence((string) $buffers[$normalizedPath], $search, $replace);
            if (empty($replaceResult['ok'])) {
                $errors[] = 'Gagal menerapkan search_replace pada ' . $normalizedPath . '.';
                continue;
            }

            $buffers[$normalizedPath] = (string) ($replaceResult['content'] ?? '');
            continue;
        }

        if ($operation === 'replace') {
            if (!array_key_exists('content', $edit) || (string) ($edit['content'] ?? '') === '') {
                $errors[] = 'Edit #' . ($index + 1) . ' untuk replace harus punya content penuh.';
                continue;
            }

            $buffers[$normalizedPath] = (string) ($edit['content'] ?? '');
            continue;
        }

        if ($operation === 'create') {
            if ($exists) {
                $errors[] = 'File create sudah ada: ' . $normalizedPath . '.';
                continue;
            }
            if (!array_key_exists('content', $edit) || (string) ($edit['content'] ?? '') === '') {
                $errors[] = 'Edit #' . ($index + 1) . ' untuk create harus punya content.';
                continue;
            }

            $buffers[$normalizedPath] = (string) ($edit['content'] ?? '');
            continue;
        }

        $errors[] = 'Operasi edit tidak didukung untuk ' . $normalizedPath . ': ' . $operation . '.';
    }

    if (!empty($errors)) {
        return [
            'ok' => false,
            'applied' => false,
            'summary' => $summary,
            'notes' => $notes,
            'files' => [],
            'errors' => $errors,
            'backup_dir' => '',
            'reindex_signal' => [],
        ];
    }

    if (count($buffers) > max(1, (int) ($selfEditConfig['max_files_per_edit'] ?? 3))) {
        return [
            'ok' => false,
            'applied' => false,
            'summary' => $summary,
            'notes' => $notes,
            'files' => [],
            'errors' => ['Jumlah file melebihi batas self edit sekali jalan.'],
            'backup_dir' => '',
            'reindex_signal' => [],
        ];
    }

    $plannedWrites = [];
    $totalWriteBytes = 0;
    foreach ($buffers as $normalizedPath => $newContent) {
        $meta = $pathMeta[$normalizedPath] ?? [];
        $initial = $initialState[$normalizedPath] ?? ['exists' => false, 'content' => null];
        $oldContent = $initial['exists'] ? (string) ($initial['content'] ?? '') : null;
        $changed = !$initial['exists'] || $oldContent !== $newContent;
        if (!$changed) {
            continue;
        }

        $totalWriteBytes += strlen((string) $newContent);
        $plannedWrites[] = [
            'path' => $normalizedPath,
            'absolute_path' => (string) ($meta['absolute_path'] ?? ''),
            'initial_exists' => !empty($initial['exists']),
            'old_content' => $oldContent,
            'new_content' => (string) $newContent,
        ];
    }

    if ($totalWriteBytes > max(4000, (int) ($selfEditConfig['max_total_write_bytes'] ?? 240000))) {
        return [
            'ok' => false,
            'applied' => false,
            'summary' => $summary,
            'notes' => $notes,
            'files' => [],
            'errors' => ['Ukuran total hasil edit melebihi batas write Hermes.'],
            'backup_dir' => '',
            'reindex_signal' => [],
        ];
    }

    if (empty($plannedWrites)) {
        return [
            'ok' => true,
            'applied' => false,
            'summary' => $summary,
            'notes' => $notes,
            'files' => [],
            'errors' => [],
            'backup_dir' => '',
            'reindex_signal' => [],
        ];
    }

    $backupBase = rtrim((string) ($selfEditConfig['backup_dir'] ?? ''), DIRECTORY_SEPARATOR);
    $backupDir = $backupBase . DIRECTORY_SEPARATOR . date('Ymd-His') . '-' . substr(sha1(json_encode([
        'conversation_id' => $context['conversation_id'] ?? '',
        'user_id' => $context['user_id'] ?? 0,
        'paths' => array_map(static function (array $write): string {
            return (string) ($write['path'] ?? '');
        }, $plannedWrites),
        'time' => microtime(true),
    ], JSON_UNESCAPED_UNICODE)), 0, 10);

    @mkdir($backupDir, 0775, true);

    $manifest = [
        'created_at' => time(),
        'conversation_id' => (string) ($context['conversation_id'] ?? ''),
        'user_id' => (int) ($context['user_id'] ?? 0),
        'user_name' => (string) ($context['user_name'] ?? ''),
        'role' => (string) ($context['role'] ?? ''),
        'summary' => $summary,
        'notes' => $notes,
        'files' => [],
    ];

    foreach ($plannedWrites as $write) {
        if (!empty($write['initial_exists'])) {
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($write['path'] ?? ''));
            $backupDirectory = dirname($backupPath);
            if (!is_dir($backupDirectory)) {
                @mkdir($backupDirectory, 0775, true);
            }
            @file_put_contents($backupPath, (string) ($write['old_content'] ?? ''), LOCK_EX);
        }

        $manifest['files'][] = [
            'path' => (string) ($write['path'] ?? ''),
            'initial_exists' => !empty($write['initial_exists']),
            'size_before' => !empty($write['initial_exists']) ? strlen((string) ($write['old_content'] ?? '')) : 0,
            'size_after' => strlen((string) ($write['new_content'] ?? '')),
        ];
    }

    @file_put_contents(
        $backupDir . DIRECTORY_SEPARATOR . 'manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );

    $written = [];
    foreach ($plannedWrites as $write) {
        $absolutePath = (string) ($write['absolute_path'] ?? '');
        if ($absolutePath === '') {
            $errors[] = 'Absolute path kosong untuk ' . (string) ($write['path'] ?? '') . '.';
            break;
        }

        $directory = dirname($absolutePath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (@file_put_contents($absolutePath, (string) ($write['new_content'] ?? ''), LOCK_EX) === false) {
            $errors[] = 'Gagal menulis file ' . (string) ($write['path'] ?? '') . '.';
            break;
        }

        $written[] = $write;
    }

    if (!empty($errors)) {
        foreach (array_reverse($written) as $write) {
            $absolutePath = (string) ($write['absolute_path'] ?? '');
            if ($absolutePath === '') {
                continue;
            }

            if (!empty($write['initial_exists'])) {
                @file_put_contents($absolutePath, (string) ($write['old_content'] ?? ''), LOCK_EX);
            } elseif (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        return [
            'ok' => false,
            'applied' => false,
            'summary' => $summary,
            'notes' => $notes,
            'files' => [],
            'errors' => $errors,
            'backup_dir' => $backupDir,
            'reindex_signal' => [],
        ];
    }

    $reindexSignal = [];
    if (!empty($selfEditConfig['auto_signal_reindex']) && function_exists('aiAgentTouchProjectIndexWatcherSignal')) {
        $reindexSignal = aiAgentTouchProjectIndexWatcherSignal(
            isset($context['config']) && is_array($context['config']) ? $context['config'] : [],
            [
                'reason' => 'hermes_self_edit_applied',
                'conversation_id' => (string) ($context['conversation_id'] ?? ''),
                'user_id' => (string) ($context['user_id'] ?? 0),
                'files' => implode(', ', array_slice(array_map(static function (array $write): string {
                    return (string) ($write['path'] ?? '');
                }, $plannedWrites), 0, 3)),
            ]
        );
    }

    $logPayload = [
        'category' => 'hermes_self_edit',
        'event' => 'applied',
        'timestamp' => time(),
        'conversation_id' => (string) ($context['conversation_id'] ?? ''),
        'role' => (string) ($context['role'] ?? ''),
        'user_id' => (int) ($context['user_id'] ?? 0),
        'user_name' => (string) ($context['user_name'] ?? ''),
        'summary' => $summary,
        'notes' => $notes,
        'files' => array_map(static function (array $write): array {
            return [
                'path' => (string) ($write['path'] ?? ''),
                'size_after' => strlen((string) ($write['new_content'] ?? '')),
            ];
        }, $plannedWrites),
        'backup_dir' => $backupDir,
    ];
    aiAgentAppendHermesSelfEditLog($logPayload, $selfEditConfig);

    if (function_exists('aiAgentAppendAuditLog')) {
        aiAgentAppendAuditLog($logPayload, isset($context['config']) && is_array($context['config']) ? $context['config'] : []);
    }

    return [
        'ok' => true,
        'applied' => true,
        'summary' => $summary,
        'notes' => $notes,
        'files' => array_map(static function (array $write): array {
            return [
                'path' => (string) ($write['path'] ?? ''),
                'created' => empty($write['initial_exists']),
                'size_after' => strlen((string) ($write['new_content'] ?? '')),
            ];
        }, $plannedWrites),
        'errors' => [],
        'backup_dir' => $backupDir,
        'reindex_signal' => $reindexSignal,
    ];
}

function aiAgentBuildHermesSelfEditReply(array $result = []): string
{
    $summary = trim((string) ($result['summary'] ?? ''));
    $notes = isset($result['notes']) && is_array($result['notes']) ? $result['notes'] : [];
    $files = isset($result['files']) && is_array($result['files']) ? $result['files'] : [];
    $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [];

    if (!empty($result['applied']) && !empty($files)) {
        $lines = ['Perubahan Hermes berhasil diterapkan.'];
        if ($summary !== '') {
            $lines[] = 'Ringkasan: ' . $summary;
        }

        $lines[] = 'File yang diubah:';
        foreach ($files as $file) {
            $lines[] = '- ' . (string) ($file['path'] ?? '');
        }

        if (!empty($result['backup_dir'])) {
            $lines[] = 'Backup otomatis tersimpan di: ' . (string) ($result['backup_dir'] ?? '');
        }

        if (!empty($notes)) {
            $lines[] = 'Catatan:';
            foreach ($notes as $note) {
                $lines[] = '- ' . trim((string) $note);
            }
        }

        $lines[] = 'Silakan uji ulang perubahan ini dari UI Hermes untuk memastikan perilakunya sudah sesuai.';
        return implode("\n", $lines);
    }

    if (!empty($errors)) {
        $lines = ['Hermes belum menerapkan perubahan ke folder `hermes`.'];
        foreach ($errors as $error) {
            $lines[] = '- ' . trim((string) $error);
        }
        if (!empty($notes)) {
            foreach ($notes as $note) {
                $lines[] = '- ' . trim((string) $note);
            }
        }
        return implode("\n", $lines);
    }

    $lines = ['Hermes belum melakukan perubahan file.'];
    if ($summary !== '') {
        $lines[] = 'Ringkasan model: ' . $summary;
    }
    foreach ($notes as $note) {
        $lines[] = '- ' . trim((string) $note);
    }
    $lines[] = 'Jika ingin apply perubahan, sebutkan file/path Hermes yang lebih spesifik atau jelaskan perubahan yang diinginkan dengan lebih detail.';

    return implode("\n", $lines);
}

function aiAgentRunHermesSelfEdit(array $options = []): array
{
    $config = isset($options['config']) && is_array($options['config']) ? $options['config'] : [];
    $selfEditConfig = isset($options['self_edit_config']) && is_array($options['self_edit_config'])
        ? $options['self_edit_config']
        : aiAgentGetHermesSelfEditConfig($config);
    $message = trim((string) ($options['message'] ?? ''));
    $role = trim((string) ($options['role'] ?? ''));
    $hasSensitiveAccess = !empty($options['has_sensitive_access']);

    if (
        empty($selfEditConfig['enabled'])
        || $message === ''
        || !aiAgentMessageRequestsHermesSelfEdit($message)
        || $role !== (string) ($selfEditConfig['allowed_role'] ?? 'admin')
        || (!empty($selfEditConfig['requires_sensitive_access']) && !$hasSensitiveAccess)
    ) {
        return [
            'handled' => false,
        ];
    }

    $candidateState = aiAgentCollectHermesEditCandidates(
        $message,
        isset($options['page_context']) && is_array($options['page_context']) ? $options['page_context'] : [],
        isset($options['shared_state']) && is_array($options['shared_state']) ? $options['shared_state'] : [],
        $selfEditConfig
    );
    $workspaceContext = aiAgentBuildHermesSelfEditWorkspaceContext($candidateState, $selfEditConfig);

    $messages = [
        [
            'role' => 'system',
            'content' => aiAgentBuildHermesSelfEditSystemPrompt($selfEditConfig),
        ],
    ];

    foreach (['mode_prompt', 'skills_context', 'memory_context'] as $key) {
        $content = trim((string) ($options[$key] ?? ''));
        if ($content !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => $content,
            ];
        }
    }

    $messages[] = [
        'role' => 'system',
        'content' => $workspaceContext,
    ];
    $messages[] = [
        'role' => 'user',
        'content' => "Terapkan perubahan berikut ke codebase Hermes jika aman dan cukup jelas.\n\nPermintaan user:\n" . $message,
    ];

    $modelResult = aiAgentCallHermesSelfEditModel($config, $messages, $selfEditConfig);
    if (empty($modelResult['ok'])) {
        return [
            'handled' => true,
            'ok' => false,
            'applied' => false,
            'reply' => 'Hermes belum bisa menerapkan perubahan kode sekarang karena worker edit gagal menghubungi provider. Detail: ' . trim((string) ($modelResult['error'] ?? 'unknown error')),
            'files' => [],
            'backup_dir' => '',
            'reindex_signal' => [],
        ];
    }

    $plan = aiAgentExtractHermesSelfEditPlan((string) ($modelResult['reply'] ?? ''));
    if (empty($plan)) {
        return [
            'handled' => true,
            'ok' => false,
            'applied' => false,
            'reply' => 'Hermes menerima permintaan edit, tetapi rencana perubahan dari model tidak valid sehingga belum ada file yang diubah.',
            'files' => [],
            'backup_dir' => '',
            'reindex_signal' => [],
        ];
    }

    $applyResult = aiAgentApplyHermesSelfEditPlan($plan, $selfEditConfig, [
        'config' => $config,
        'role' => $role,
        'user_id' => (int) ($options['user_id'] ?? 0),
        'user_name' => (string) ($options['user_name'] ?? ''),
        'conversation_id' => (string) ($options['conversation_id'] ?? ''),
    ]);

    $applyResult['handled'] = true;
    $applyResult['reply'] = aiAgentBuildHermesSelfEditReply($applyResult);
    $applyResult['provider'] = (string) ($modelResult['provider'] ?? 'primary');
    $applyResult['raw_plan'] = $plan;

    return $applyResult;
}
