<?php

function aiAgentBuildDynamicContextBundle(array $options = []): array
{
    $message = aiAgentCleanText((string) ($options['message'] ?? ''), 1600);
    $pagePath = aiAgentCleanText((string) ($options['page_path'] ?? ''), 220);
    $pageTitle = aiAgentCleanText((string) ($options['page_title'] ?? ''), 160);
    $pageHeading = aiAgentCleanText((string) ($options['page_heading'] ?? ''), 160);
    $focusScopes = isset($options['focus_scopes']) && is_array($options['focus_scopes'])
        ? array_values(array_unique(array_filter(array_map('strval', $options['focus_scopes']))))
        : [];
    $allowTechnicalDetails = !empty($options['allow_technical_details']);
    $pageSnapshot = aiAgentNormalizePageSnapshot($options['page_snapshot'] ?? []);

    $bundle = [
        'shared_lines' => [],
        'technical_lines' => [],
    ];

    foreach (aiAgentBuildPageSnapshotLines($pageSnapshot) as $line) {
        $bundle['shared_lines'][] = $line;
    }

    $matches = aiAgentCollectRelevantCodeMatches([
        'message' => $message,
        'page_path' => $pagePath,
        'page_title' => $pageTitle,
        'page_heading' => $pageHeading,
        'page_snapshot' => $pageSnapshot,
        'focus_scopes' => $focusScopes,
    ]);

    foreach (aiAgentBuildImplementationObservationLines($matches) as $line) {
        $bundle['shared_lines'][] = $line;
    }

    if ($allowTechnicalDetails) {
        foreach (aiAgentBuildTechnicalMatchLines($matches) as $line) {
            $bundle['technical_lines'][] = $line;
        }
    }

    $bundle['shared_lines'] = array_values(array_unique(array_filter($bundle['shared_lines'])));
    $bundle['technical_lines'] = array_values(array_filter($bundle['technical_lines']));

    return $bundle;
}

function aiAgentNormalizePageSnapshot($snapshot): array
{
    $normalized = [
        'breadcrumbs' => [],
        'cards' => [],
        'buttons' => [],
        'table_headers' => [],
        'filters' => [],
        'labels' => [],
    ];

    if (!is_array($snapshot)) {
        return $normalized;
    }

    foreach ($normalized as $key => $_unused) {
        $values = $snapshot[$key] ?? [];
        if (!is_array($values)) {
            $values = [$values];
        }

        $items = [];
        foreach ($values as $value) {
            $text = aiAgentCleanText((string) $value, 80);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        $normalized[$key] = array_values(array_unique(array_slice($items, 0, 12)));
    }

    return $normalized;
}

function aiAgentBuildPageSnapshotLines(array $snapshot): array
{
    $lines = [];

    $map = [
        'breadcrumbs' => 'Snapshot breadcrumb halaman aktif',
        'cards' => 'Snapshot card atau section yang terlihat',
        'buttons' => 'Snapshot tombol aksi yang terlihat',
        'table_headers' => 'Snapshot kolom tabel yang terlihat',
        'filters' => 'Snapshot filter atau field cepat yang terlihat',
        'labels' => 'Snapshot label form yang terlihat',
    ];

    foreach ($map as $key => $label) {
        if (!empty($snapshot[$key])) {
            $lines[] = $label . ': ' . implode(', ', array_slice($snapshot[$key], 0, 12)) . '.';
        }
    }

    return $lines;
}

function aiAgentCollectRelevantCodeMatches(array $options = []): array
{
    $projectRoot = aiAgentGetProjectRootPath();
    $message = aiAgentCleanText((string) ($options['message'] ?? ''), 1600);
    $pagePath = aiAgentCleanText((string) ($options['page_path'] ?? ''), 220);
    $pageTitle = aiAgentCleanText((string) ($options['page_title'] ?? ''), 160);
    $pageHeading = aiAgentCleanText((string) ($options['page_heading'] ?? ''), 160);
    $pageSnapshot = aiAgentNormalizePageSnapshot($options['page_snapshot'] ?? []);
    $focusScopes = isset($options['focus_scopes']) && is_array($options['focus_scopes'])
        ? array_values(array_unique(array_filter(array_map('strval', $options['focus_scopes']))))
        : [];
    $keywords = aiAgentBuildCodeSearchKeywords($message, $pagePath, $pageTitle, $pageHeading, $pageSnapshot);
    $linkedFiles = aiAgentCollectDirectlyLinkedFiles($projectRoot, $pagePath);
    $linkedFileMap = array_fill_keys($linkedFiles, true);
    $candidateFiles = array_values(array_unique(array_merge(
        $linkedFiles,
        aiAgentCollectCandidateFiles($projectRoot, $pagePath, $focusScopes, $keywords)
    )));

    $matches = [];
    foreach ($candidateFiles as $relativePath) {
        $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            continue;
        }

        $fileSize = @filesize($absolutePath);
        if ($fileSize !== false && $fileSize > 250000) {
            continue;
        }

        $content = @file_get_contents($absolutePath);
        if (!is_string($content) || trim($content) === '') {
            continue;
        }

        $score = aiAgentScoreCodeFile(
            $relativePath,
            $content,
            $keywords,
            $pagePath,
            $focusScopes,
            isset($linkedFileMap[$relativePath])
        );
        if ($score <= 0) {
            continue;
        }

        $snippets = aiAgentExtractFileSnippets($relativePath, $absolutePath, $keywords);
        $matches[] = [
            'path' => $relativePath,
            'score' => $score,
            'content' => $content,
            'snippets' => $snippets,
        ];
    }

    usort($matches, static function (array $left, array $right): int {
        $scoreDiff = ($right['score'] ?? 0) <=> ($left['score'] ?? 0);
        if ($scoreDiff !== 0) {
            return $scoreDiff;
        }

        return strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
    });

    return aiAgentSelectBalancedMatches($matches, $pagePath, $keywords);
}

function aiAgentCollectDirectlyLinkedFiles(string $projectRoot, string $pagePath): array
{
    $relativePagePath = aiAgentNormalizeProjectRelativePath($pagePath);
    if ($relativePagePath === '') {
        return [];
    }

    $absolutePagePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePagePath);
    if (!is_file($absolutePagePath) || !is_readable($absolutePagePath)) {
        return [];
    }

    $files = [];
    $queue = [
        [$relativePagePath, 0],
    ];
    $seen = [];

    while (!empty($queue) && count($files) < 24) {
        [$relativePath, $depth] = array_shift($queue);
        if (isset($seen[$relativePath])) {
            continue;
        }

        $seen[$relativePath] = true;
        $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($absolutePath) || !is_readable($absolutePath) || !aiAgentIsGroundableFile($absolutePath)) {
            continue;
        }

        $files[] = $relativePath;
        if ($depth >= 2) {
            continue;
        }

        $content = @file_get_contents($absolutePath);
        if (!is_string($content) || trim($content) === '') {
            continue;
        }

        foreach (aiAgentExtractReferencedProjectPaths($relativePath, $content) as $referencePath) {
            if (isset($seen[$referencePath])) {
                continue;
            }

            $absoluteReferencePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $referencePath);
            if (!is_file($absoluteReferencePath) || !is_readable($absoluteReferencePath) || !aiAgentIsGroundableFile($absoluteReferencePath)) {
                continue;
            }

            $queue[] = [$referencePath, $depth + 1];
        }
    }

    return array_values(array_unique($files));
}

function aiAgentSelectBalancedMatches(array $matches, string $pagePath, array $keywords): array
{
    if (empty($matches)) {
        return [];
    }

    $selected = [];
    $seen = [];
    $relativePagePath = aiAgentNormalizeProjectRelativePath($pagePath);
    $needsAiContext = count(array_intersect($keywords, ['ai', 'chat', 'hermes', 'widget', 'prompt', 'grounding', 'assistant'])) > 0;

    $rules = [
        static function (array $match) use ($relativePagePath): bool {
            return $relativePagePath !== '' && (string) ($match['path'] ?? '') === $relativePagePath;
        },
        static function (array $match): bool {
            return strpos((string) ($match['path'] ?? ''), 'assets/js/') === 0;
        },
        static function (array $match): bool {
            $path = (string) ($match['path'] ?? '');
            return strpos($path, 'api/') === 0;
        },
    ];

    if ($needsAiContext) {
        $rules[] = static function (array $match): bool {
            return strpos((string) ($match['path'] ?? ''), 'hermes/') === 0;
        };
    }

    foreach ($rules as $rule) {
        foreach ($matches as $match) {
            $path = (string) ($match['path'] ?? '');
            if ($path === '' || isset($seen[$path]) || !$rule($match)) {
                continue;
            }

            $seen[$path] = true;
            $selected[] = $match;
            break;
        }
    }

    foreach ($matches as $match) {
        $path = (string) ($match['path'] ?? '');
        if ($path === '' || isset($seen[$path])) {
            continue;
        }

        $seen[$path] = true;
        $selected[] = $match;

        if (count($selected) >= 6) {
            break;
        }
    }

    return array_slice($selected, 0, 6);
}

function aiAgentBuildCodeSearchKeywords(
    string $message,
    string $pagePath,
    string $pageTitle,
    string $pageHeading,
    array $pageSnapshot
): array {
    $sourceParts = [$message, $pagePath, $pageTitle, $pageHeading];
    foreach ($pageSnapshot as $items) {
        if (is_array($items) && !empty($items)) {
            $sourceParts[] = implode(' ', $items);
        }
    }

    $source = strtolower(implode(' ', array_filter($sourceParts)));
    $source = preg_replace('/[^a-z0-9_\\-\\s]+/i', ' ', (string) $source);
    $tokens = preg_split('/\\s+/', trim((string) $source)) ?: [];

    $stopwords = [
        'yang',
        'dan',
        'atau',
        'untuk',
        'dengan',
        'pada',
        'dari',
        'agar',
        'saja',
        'sudah',
        'belum',
        'bisa',
        'bukan',
        'mengenai',
        'tentang',
        'seperti',
        'supaya',
        'tetapi',
        'karena',
        'kalau',
        'saya',
        'aku',
        'anda',
        'kamu',
        'kami',
        'mereka',
        'tidak',
        'iya',
        'ya',
        'kok',
        'masih',
        'full',
        'lebih',
        'semua',
        'aktif',
        'halaman',
        'sistem',
        'fitur',
        'perubahan',
        'baru',
        'this',
        'that',
        'with',
        'from',
        'have',
        'has',
        'your',
        'about',
        'into',
        'only',
        'also',
        'page',
        'card',
        'menu',
        'sub',
        'the',
        'and',
        'for',
        'are',
        'was',
        'were',
        'how',
        'why',
    ];

    $keywords = [];
    foreach ($tokens as $token) {
        $token = trim($token);
        if ($token === '' || strlen($token) < 3) {
            continue;
        }
        if (in_array($token, $stopwords, true)) {
            continue;
        }
        $keywords[] = $token;
    }

    $keywords = array_values(array_unique($keywords));
    $priorityKeywords = [];
    foreach ($keywords as $keyword) {
        if (in_array($keyword, ['ai', 'chat', 'hermes', 'widget', 'backend', 'frontend', 'filter', 'barang', 'stok', 'user', 'approval'], true)) {
            $priorityKeywords[] = $keyword;
        }
    }

    $ordered = array_values(array_unique(array_merge($priorityKeywords, $keywords)));
    return array_slice($ordered, 0, 14);
}

function aiAgentCollectCandidateFiles(string $projectRoot, string $pagePath, array $focusScopes, array $keywords): array
{
    $candidateHints = aiAgentBuildCandidatePathHints($pagePath, $focusScopes, $keywords);
    $files = [];
    $seen = [];

    foreach ($candidateHints as $hint) {
        $normalizedHint = str_replace('\\', '/', trim((string) $hint, '/'));
        if ($normalizedHint === '') {
            continue;
        }

        $absoluteHint = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedHint);
        if (is_file($absoluteHint)) {
            if (aiAgentIsGroundableFile($absoluteHint) && !isset($seen[$normalizedHint])) {
                $seen[$normalizedHint] = true;
                $files[] = $normalizedHint;
            }
            continue;
        }

        if (is_dir($absoluteHint)) {
            foreach (aiAgentListGroundableFiles($absoluteHint, $projectRoot, 120) as $relativePath) {
                if (!isset($seen[$relativePath])) {
                    $seen[$relativePath] = true;
                    $files[] = $relativePath;
                }
            }
        }
    }

    if (count($files) < 20) {
        $fallbackHints = ['api', 'assets/js', 'admin', 'manager', 'user', 'pic-barang', 'config'];
        $fallbackHints = array_values(array_unique(array_merge($fallbackHints, aiAgentListTopLevelGroundingHints($projectRoot, 24))));
        foreach ($fallbackHints as $hint) {
            $absoluteHint = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $hint);
            if (!is_dir($absoluteHint)) {
                if (is_file($absoluteHint) && aiAgentIsGroundableFile($absoluteHint) && !isset($seen[$hint])) {
                    $seen[$hint] = true;
                    $files[] = str_replace('\\', '/', $hint);
                }
                continue;
            }

            foreach (aiAgentListGroundableFiles($absoluteHint, $projectRoot, 80) as $relativePath) {
                if (!isset($seen[$relativePath])) {
                    $seen[$relativePath] = true;
                    $files[] = $relativePath;
                }
            }
        }
    }

    return array_slice($files, 0, 220);
}

function aiAgentBuildCandidatePathHints(string $pagePath, array $focusScopes, array $keywords): array
{
    $relativePagePath = aiAgentNormalizeProjectRelativePath($pagePath);
    $hints = [];

    if ($relativePagePath !== '') {
        $hints[] = $relativePagePath;

        $pageDirectory = dirname($relativePagePath);
        if ($pageDirectory !== '' && $pageDirectory !== '.') {
            $hints[] = $pageDirectory;
            $parentDirectory = dirname($pageDirectory);
            if ($parentDirectory !== '' && $parentDirectory !== '.') {
                $hints[] = $parentDirectory;
            }
        }

        $basename = basename($relativePagePath);
        if ($basename !== '') {
            if (strpos($relativePagePath, 'barang') !== false) {
                $hints[] = 'assets/js/barang';
                $hints[] = 'api/barang';
            }
            if (strpos($relativePagePath, 'user') !== false) {
                $hints[] = 'assets/js/user';
                $hints[] = 'api/admin';
                $hints[] = 'api/user';
            }
            if (strpos($relativePagePath, 'peminjaman') !== false) {
                $hints[] = 'api/peminjaman';
                $hints[] = 'api/user';
            }
            if (strpos($relativePagePath, 'pengembalian') !== false) {
                $hints[] = 'api/pengembalian';
                $hints[] = 'api/admin';
            }
        }
    }

    $scopeMap = [
        'barang' => ['admin/barang', 'pic-barang/update-barang', 'api/barang', 'assets/js/barang'],
        'users' => ['admin/user', 'api/admin', 'api/user', 'assets/js/user'],
        'approval' => ['manager/persetujuan', 'admin/peminjaman', 'api/approver', 'api/admin'],
        'peminjaman' => ['user/peminjaman', 'admin/peminjaman', 'api/peminjaman', 'api/user'],
        'pengembalian' => ['user/pengembalian', 'admin/pengembalian', 'pic-barang/pengembalian', 'api/pengembalian'],
        'extend' => ['api/extend', 'user/peminjaman', 'admin/peminjaman'],
        'dashboard' => ['admin', 'manager', 'user', 'pic-barang', 'api/admin', 'api/approver', 'api/user'],
        'laporan' => ['admin/laporan', 'manager/laporan', 'api'],
        'auth' => ['api/auth', 'api/user', 'assets/js/auth'],
        'ai' => ['hermes', 'assets/js/ai-agent-widget.js', 'assets/css/ai-agent-widget.css', 'hermes/config/ai_agent.php'],
    ];

    foreach ($focusScopes as $scope) {
        foreach ($scopeMap[$scope] ?? [] as $hint) {
            $hints[] = $hint;
        }
    }

    $joinedKeywords = ' ' . implode(' ', $keywords) . ' ';
    if (
        strpos($joinedKeywords, ' ai ') !== false
        || strpos($joinedKeywords, ' chat ') !== false
        || strpos($joinedKeywords, ' hermes ') !== false
        || strpos($joinedKeywords, ' widget ') !== false
        || strpos($joinedKeywords, ' backend ') !== false
        || strpos($joinedKeywords, ' frontend ') !== false
    ) {
        $hints = array_merge($hints, $scopeMap['ai']);
    }

    if (strpos($joinedKeywords, ' barang ') !== false || strpos($joinedKeywords, ' stok ') !== false || strpos($joinedKeywords, ' filter ') !== false) {
        $hints = array_merge($hints, $scopeMap['barang']);
    }

    return array_values(array_unique(array_filter($hints)));
}

function aiAgentExtractReferencedProjectPaths(string $relativePath, string $content): array
{
    $references = [];
    $isMarkupFile = preg_match('/\.(php|html)$/i', $relativePath) === 1;
    $patterns = $isMarkupFile
        ? [
            '/<script[^>]+src=["\']([^"\']+?\.(?:js|php)(?:\?[^"\']*)?)["\']/i',
            '/<form[^>]+action=["\']([^"\']+?\.(?:php|html)(?:\?[^"\']*)?)["\']/i',
            '/fetch\(\s*["\'`]([^"\'`\r\n]+?\.(?:php|html)(?:\?[^"\'`\r\n]*)?)["\'`]/i',
            '/API_BASE_URL\}\s*\/([A-Za-z0-9_\/.-]+\.php(?:\?[^"\'`\r\n]*)?)/',
            '/BASE_URL\}\s*\/([A-Za-z0-9_\/.-]+?\.(?:php|html|js|css)(?:\?[^"\'`\r\n]*)?)/',
        ]
        : [
            '/["\'`]([^"\'`\r\n]+?\.(?:php|html|js|css|sql)(?:\?[^"\'`\r\n]*)?)["\'`]/i',
            '/API_BASE_URL\}\s*\/([A-Za-z0-9_\/.-]+\.php(?:\?[^"\'`\r\n]*)?)/',
            '/BASE_URL\}\s*\/([A-Za-z0-9_\/.-]+?\.(?:php|html|js|css)(?:\?[^"\'`\r\n]*)?)/',
        ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $content, $matches)) {
            continue;
        }

        foreach (($matches[1] ?? []) as $match) {
            $resolved = aiAgentResolveProjectRelativeReference($relativePath, (string) $match);
            if ($resolved !== '') {
                $references[] = $resolved;
            }
        }
    }

    return array_values(array_unique(array_filter($references)));
}

function aiAgentResolveProjectRelativeReference(string $fromRelativePath, string $reference): string
{
    $reference = trim(str_replace('\\', '/', $reference));
    if ($reference === '') {
        return '';
    }

    if (preg_match('#^(?:https?:)?//#i', $reference) || strpos($reference, 'javascript:') === 0 || strpos($reference, 'data:') === 0) {
        return '';
    }

    $reference = preg_replace('/([.](?:php|html|js|css|sql)).*$/i', '$1', $reference);
    $reference = preg_replace('/^\$\{API_BASE_URL\}\//', 'api/', $reference);
    $reference = preg_replace('/^\$\{BASE_URL\}\//', '', $reference);
    $reference = preg_replace('/^API_BASE_URL\s*\+\s*[\'"]\//', 'api/', $reference);
    $reference = preg_replace('/^BASE_URL\s*\+\s*[\'"]\//', '', $reference);
    $reference = trim((string) $reference, "\"'`");

    if ($reference === '' || strpos($reference, '${') !== false) {
        return '';
    }

    if (preg_match('#^(api|assets|admin|manager|user|pic-barang|config)/#i', $reference) === 1) {
        return aiAgentNormalizeRelativeSegments($reference);
    }

    $baseDirectory = dirname($fromRelativePath);
    if ($baseDirectory === '.' || $baseDirectory === DIRECTORY_SEPARATOR) {
        $baseDirectory = '';
    }

    return aiAgentNormalizeRelativeSegments(trim($baseDirectory . '/' . ltrim($reference, '/'), '/'));
}

function aiAgentNormalizeRelativeSegments(string $path): string
{
    $path = trim(str_replace('\\', '/', $path), '/');
    if ($path === '') {
        return '';
    }

    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($segments);
            continue;
        }

        $segments[] = $segment;
    }

    return implode('/', $segments);
}

function aiAgentNormalizeProjectRelativePath(string $pagePath): string
{
    $normalized = trim(str_replace('\\', '/', $pagePath));
    if ($normalized === '') {
        return '';
    }

    $normalized = preg_replace('#^https?://[^/]+#i', '', $normalized);
    $parts = array_values(array_filter(explode('/', trim((string) $normalized, '/')), static function ($part) {
        return $part !== '';
    }));

    $projectIndex = array_search('PROJECT', $parts, true);
    if ($projectIndex !== false) {
        $parts = array_slice($parts, $projectIndex + 1);
    }

    return implode('/', $parts);
}

function aiAgentListGroundableFiles(string $directory, string $projectRoot, int $limit = 120): array
{
    $files = [];
    $flags = FilesystemIterator::SKIP_DOTS;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, $flags),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }

        $pathName = str_replace('\\', '/', $item->getPathname());

        if ($item->isDir()) {
            if (aiAgentShouldSkipGroundingPath($pathName)) {
                $iterator->next();
            }
            continue;
        }

        if (!aiAgentIsGroundableFile($pathName)) {
            continue;
        }

        $relativePath = ltrim(str_replace('\\', '/', substr($pathName, strlen(str_replace('\\', '/', $projectRoot)))), '/');
        if ($relativePath !== '') {
            $files[] = $relativePath;
        }

        if ($limit > 0 && count($files) >= $limit) {
            break;
        }
    }

    return $files;
}

function aiAgentGetCodebaseVisibilityMode(): string
{
    $mode = strtolower(trim((string) getenv('AI_AGENT_CODEBASE_VISIBILITY_MODE')));
    if (!in_array($mode, ['default', 'extended', 'full'], true)) {
        $mode = 'extended';
    }

    return $mode;
}

function aiAgentGetGroundableExtensions(): array
{
    $extensions = ['php', 'html', 'js', 'css', 'md', 'sql'];
    $mode = aiAgentGetCodebaseVisibilityMode();

    if ($mode === 'extended' || $mode === 'full') {
        $extensions = array_merge($extensions, ['json', 'txt', 'xml', 'yml', 'yaml', 'ini', 'ts', 'tsx', 'jsx', 'cs', 'csproj', 'sln', 'props']);
    }

    $extra = trim((string) getenv('AI_AGENT_GROUNDABLE_EXTENSIONS'));
    if ($extra !== '') {
        foreach (preg_split('/\s*,\s*/', $extra) ?: [] as $item) {
            $item = strtolower(trim($item, " ."));
            if ($item !== '') {
                $extensions[] = $item;
            }
        }
    }

    return array_values(array_unique($extensions));
}

function aiAgentGetAdditionalGroundingExcludes(): array
{
    $raw = trim((string) getenv('AI_AGENT_GROUNDING_EXCLUDE_PATHS'));
    if ($raw === '') {
        return [];
    }

    $items = [];
    foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $item) {
        $item = strtolower(trim(str_replace('\\', '/', $item)));
        if ($item !== '') {
            $items[] = '/' . trim($item, '/') . '/';
        }
    }

    return array_values(array_unique($items));
}

function aiAgentListTopLevelGroundingHints(string $projectRoot, int $limit = 24): array
{
    $entries = @scandir($projectRoot);
    if (!is_array($entries)) {
        return [];
    }

    $hints = [];
    foreach ($entries as $entry) {
        if (!is_string($entry) || $entry === '.' || $entry === '..') {
            continue;
        }

        $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . $entry;
        if (aiAgentShouldSkipGroundingPath($absolutePath)) {
            continue;
        }

        $hints[] = str_replace('\\', '/', $entry);
        if (count($hints) >= $limit) {
            break;
        }
    }

    return $hints;
}

function aiAgentShouldSkipGroundingPath(string $pathName): bool
{
    $normalized = strtolower(str_replace('\\', '/', $pathName));
    $mode = aiAgentGetCodebaseVisibilityMode();
    $blockedParts = [
        '/.git/',
        '/vendor/',
        '/node_modules/',
        '/cache/',
    ];

    if ($mode === 'default') {
        $blockedParts = array_merge($blockedParts, [
            '/assets/vendors/',
            '/phpmailer/',
            '/tmp/',
        ]);
    }

    $blockedParts = array_values(array_unique(array_merge($blockedParts, aiAgentGetAdditionalGroundingExcludes())));

    foreach ($blockedParts as $blockedPart) {
        if (strpos($normalized, $blockedPart) !== false) {
            return true;
        }
    }

    return false;
}

function aiAgentIsGroundableFile(string $pathName): bool
{
    $normalized = strtolower(str_replace('\\', '/', $pathName));
    if (aiAgentShouldSkipGroundingPath($normalized)) {
        return false;
    }

    if (preg_match('/\\.(min\\.js|map)$/i', $normalized)) {
        return false;
    }

    $extensions = aiAgentGetGroundableExtensions();
    if (empty($extensions)) {
        return false;
    }

    return preg_match('/\\.(' . implode('|', array_map('preg_quote', $extensions)) . ')$/i', $normalized) === 1;
}

function aiAgentScoreCodeFile(
    string $relativePath,
    string $content,
    array $keywords,
    string $pagePath,
    array $focusScopes,
    bool $isDirectlyLinked = false
): int {
    $pathLower = strtolower($relativePath);
    $contentLower = strtolower($content);
    $score = 0;

    $relativePagePath = strtolower(aiAgentNormalizeProjectRelativePath($pagePath));
    if ($relativePagePath !== '' && $pathLower === $relativePagePath) {
        $score += 80;
    } elseif ($relativePagePath !== '' && strpos($relativePagePath, dirname($pathLower)) !== false) {
        $score += 20;
    }

    if ($isDirectlyLinked) {
        $score += 65;
    }

    foreach ($keywords as $keyword) {
        $keywordLower = strtolower($keyword);
        if ($keywordLower === '') {
            continue;
        }

        if (strpos($pathLower, $keywordLower) !== false) {
            $score += 18;
        }

        $matchCount = substr_count($contentLower, $keywordLower);
        if ($matchCount > 0) {
            $score += min(24, $matchCount * 6);
        }
    }

    foreach ($focusScopes as $scope) {
        if ($scope !== '' && strpos($pathLower, strtolower($scope)) !== false) {
            $score += 8;
        }
    }

    if (strpos($pathLower, 'hermes/') === 0) {
        $score += 14;
    }
    if (strpos($pathLower, 'api/') === 0) {
        $score += 8;
    }
    if (strpos($pathLower, 'assets/js/') === 0) {
        $score += 8;
    }
    if (preg_match('/\\.(html|php)$/i', $relativePath)) {
        $score += 4;
    }

    return $score;
}

function aiAgentExtractFileSnippets(string $relativePath, string $absolutePath, array $keywords): array
{
    $lines = @file($absolutePath, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines) || empty($lines)) {
        return [];
    }

    $lineNumbers = [];
    foreach ($lines as $index => $line) {
        $lineLower = strtolower((string) $line);
        foreach ($keywords as $keyword) {
            $keywordLower = strtolower($keyword);
            if ($keywordLower !== '' && strpos($lineLower, $keywordLower) !== false) {
                $lineNumbers[] = $index + 1;
                break;
            }
        }
    }

    if (empty($lineNumbers)) {
        $fallbackHints = ['fetch(', 'function ', 'class ', 'return ', 'page_context', 'json_encode', 'require_once'];
        foreach ($lines as $index => $line) {
            $lineLower = strtolower((string) $line);
            foreach ($fallbackHints as $hint) {
                if (strpos($lineLower, strtolower($hint)) !== false) {
                    $lineNumbers[] = $index + 1;
                    if (count($lineNumbers) >= 2) {
                        break 2;
                    }
                }
            }
        }
    }

    $lineNumbers = array_values(array_unique(array_slice($lineNumbers, 0, 4)));
    $snippets = [];
    foreach ($lineNumbers as $lineNumber) {
        $start = max(1, $lineNumber - 2);
        $end = min(count($lines), $lineNumber + 2);
        $snippetLines = [];
        for ($currentLine = $start; $currentLine <= $end; $currentLine++) {
            $text = rtrim((string) ($lines[$currentLine - 1] ?? ''));
            $snippetLines[] = sprintf('%d: %s', $currentLine, aiAgentPrepareSnippetText($text, 220));
        }

        $snippets[] = [
            'start' => $start,
            'end' => $end,
            'lines' => $snippetLines,
        ];
    }

    return array_slice($snippets, 0, 2);
}

function aiAgentPrepareSnippetText(string $text, int $maxLength = 220): string
{
    $text = preg_replace('/\\s+/', ' ', trim($text));
    if ($text === '') {
        return '';
    }

    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength - 3) . '...';
    }

    return $text;
}

function aiAgentBuildImplementationObservationLines(array $matches): array
{
    if (empty($matches)) {
        return [];
    }

    $areas = [];
    $contentBlob = '';
    foreach ($matches as $match) {
        $path = strtolower((string) ($match['path'] ?? ''));
        $content = strtolower((string) ($match['content'] ?? ''));
        $contentBlob .= "\n" . $content;

        if ($path !== '') {
            if (strpos($path, 'hermes/') === 0) {
                $areas['modul ai'] = true;
            } elseif (strpos($path, 'api/') === 0) {
                $areas['backend api'] = true;
            } elseif (strpos($path, 'assets/js/') === 0) {
                $areas['script frontend'] = true;
            } elseif (preg_match('#^(admin|manager|user|pic-barang)/#', $path)) {
                $areas['halaman frontend'] = true;
            } elseif (strpos($path, 'config/') === 0) {
                $areas['konfigurasi aplikasi'] = true;
            }
        }
    }

    $lines = [];
    if (!empty($areas)) {
        $lines[] = 'Konteks implementasi dinamis yang berhasil dibaca saat ini mencakup area: ' . implode(', ', array_keys($areas)) . '.';
    }

    $observationMap = [
        'fetch(' => 'Implementasi aktif memakai request async antara frontend dan backend.',
        'page_context' => 'Hermes Agent saat ini menerima konteks halaman aktif dari widget browser.',
        'ui_snapshot' => 'Hermes Agent saat ini juga menerima snapshot elemen UI yang terlihat di halaman aktif.',
        'sessionvalidator::requirerole' => 'Akses backend di area ini divalidasi berdasarkan session dan role.',
        'content-type: application/json' => 'Endpoint backend di area yang relevan mengirim respons JSON terstruktur.',
        'json_encode(' => 'Respons backend di area yang relevan dibentuk sebagai payload JSON.',
        'pagination' => 'Implementasi saat ini memiliki mekanisme pagination atau pembagian data.',
        'applyfilters' => 'Implementasi saat ini memiliki mekanisme filter dinamis di sisi aplikasi.',
        'item_code' => 'Area yang relevan sudah mengenali filter spesifik seperti item code atau field inventaris lain.',
        'paginate' => 'Area yang relevan sudah memiliki filter, sort, atau pagination yang diproses sebagai parameter request.',
        'per_page' => 'Jumlah data per halaman dapat dikendalikan oleh request aplikasi.',
        'sort' => 'Urutan data di area yang relevan dapat diubah secara dinamis.',
        'formdata' => 'Aksi simpan atau update data dikirim dari frontend ke backend menggunakan form submission atau FormData.',
        'prepare(' => 'Backend area ini terhubung ke database memakai query terparameterisasi atau prepared statement.',
        'system_prompt' => 'Jawaban Hermes Agent tetap dipengaruhi oleh system prompt dan grounding backend sebelum dikirim ke model.',
        'computebarangstatus' => 'Status stok item di area inventaris dihitung oleh backend berdasarkan data stok yang aktif.',
    ];

    foreach ($observationMap as $needle => $line) {
        if (strpos($contentBlob, $needle) !== false) {
            $lines[] = $line;
        }
    }

    return array_slice(array_values(array_unique($lines)), 0, 12);
}

function aiAgentBuildTechnicalMatchLines(array $matches): array
{
    if (empty($matches)) {
        return [];
    }

    $lines = [];
    $lines[] = '[DYNAMIC_CODE_CONTEXT]';

    foreach (array_slice($matches, 0, 4) as $match) {
        $path = (string) ($match['path'] ?? '');
        if ($path === '') {
            continue;
        }

        $lines[] = '- File relevan: ' . $path . '.';
        foreach (array_slice($match['snippets'] ?? [], 0, 2) as $snippet) {
            $start = (int) ($snippet['start'] ?? 0);
            $end = (int) ($snippet['end'] ?? 0);
            $lines[] = '- Cuplikan ' . $path . ' baris ' . $start . '-' . $end . ':';
            foreach ($snippet['lines'] ?? [] as $snippetLine) {
                $lines[] = '  ' . $snippetLine;
            }
        }
    }

    $lines[] = '[/DYNAMIC_CODE_CONTEXT]';
    return $lines;
}
