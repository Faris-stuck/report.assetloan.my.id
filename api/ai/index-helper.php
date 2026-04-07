<?php

function aiAgentGetProjectIndexConfig(array $config = []): array
{
    $storageDir = trim((string) ($config['project_index_storage_dir'] ?? ''));
    if ($storageDir === '') {
        $storageDir = 'tmp/ai';
    }

    $storageDir = aiAgentResolveProjectIndexPath($storageDir);

    return [
        'enabled' => !isset($config['project_index_enabled']) ? true : (bool) $config['project_index_enabled'],
        'lazy_rebuild' => !isset($config['project_index_lazy_rebuild']) ? true : (bool) $config['project_index_lazy_rebuild'],
        'storage_dir' => $storageDir,
        'project_index_file' => $storageDir . DIRECTORY_SEPARATOR . 'project-index.json',
        'feature_manifest_file' => $storageDir . DIRECTORY_SEPARATOR . 'feature-manifest.json',
        'meta_file' => $storageDir . DIRECTORY_SEPARATOR . 'project-index-meta.json',
        'lock_file' => aiAgentResolveProjectIndexLockPath(
            (string) ($config['project_index_lock_file'] ?? ''),
            $storageDir
        ),
        'watcher_signal_file' => aiAgentResolveProjectIndexWatcherSignalPath(
            (string) ($config['project_index_watcher_signal_file'] ?? ''),
            $storageDir
        ),
        'lock_timeout_seconds' => max(1, (int) ($config['project_index_lock_timeout_seconds'] ?? 15)),
        'max_age_seconds' => max(30, (int) ($config['project_index_max_age_seconds'] ?? 300)),
        'max_file_size_bytes' => max(20000, (int) ($config['project_index_max_file_size_bytes'] ?? 200000)),
        'max_relevant_entries' => max(2, (int) ($config['project_index_max_relevant_entries'] ?? 6)),
        'fingerprint_mode' => 'content_hash',
        'manifest_version' => 2,
    ];
}

function aiAgentResolveProjectIndexPath(string $path): string
{
    $path = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    if ($path === '') {
        return aiAgentGetProjectRootPath() . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'ai';
    }

    if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1 || strpos($path, DIRECTORY_SEPARATOR) === 0) {
        return $path;
    }

    return aiAgentGetProjectRootPath() . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function aiAgentResolveProjectIndexWatcherSignalPath(string $path, string $storageDir): string
{
    $path = trim($path);
    if ($path === '') {
        return $storageDir . DIRECTORY_SEPARATOR . 'watcher.signal';
    }

    return aiAgentResolveProjectIndexPath($path);
}

function aiAgentResolveProjectIndexLockPath(string $path, string $storageDir): string
{
    $path = trim($path);
    if ($path === '') {
        return $storageDir . DIRECTORY_SEPARATOR . 'project-index.lock';
    }

    return aiAgentResolveProjectIndexPath($path);
}

function aiAgentEnsureProjectIndexBundle(mysqli $conn, array $options = []): array
{
    $config = isset($options['config']) && is_array($options['config']) ? $options['config'] : [];
    $toolConfig = isset($options['tool_config']) && is_array($options['tool_config'])
        ? $options['tool_config']
        : aiAgentGetToolLayerConfig($config);
    $indexConfig = aiAgentGetProjectIndexConfig($config);

    if (empty($indexConfig['enabled'])) {
        return aiAgentBuildProjectIndexState($indexConfig, [
            'project_index' => [],
            'feature_manifest' => [],
            'meta' => [],
            'watcher_signal' => aiAgentGetProjectIndexWatcherSignalState($indexConfig),
        ], [
            'enabled' => false,
            'rebuilt' => false,
            'reused' => false,
            'rebuild_required' => false,
            'reason' => 'disabled',
        ]);
    }

    aiAgentEnsureProjectIndexStorageDirectory($indexConfig['storage_dir']);
    $lock = aiAgentAcquireProjectIndexLock($indexConfig);
    if (empty($lock['acquired'])) {
        $storedBundle = aiAgentLoadStoredProjectIndexBundle($indexConfig);
        return aiAgentBuildProjectIndexState($indexConfig, $storedBundle, [
            'enabled' => true,
            'rebuilt' => false,
            'reused' => !empty($storedBundle['project_index']) && !empty($storedBundle['feature_manifest']),
            'rebuild_required' => true,
            'reason' => (string) ($lock['error'] ?? 'lock_timeout'),
            'lock' => $lock,
        ]);
    }

    try {
        $schemaIndex = isset($options['schema_index']) && is_array($options['schema_index'])
            ? $options['schema_index']
            : aiAgentGetLiveSchemaIndex($conn);
        $schemaFingerprint = aiAgentComputeSchemaFingerprint($schemaIndex);
        $filesystemState = aiAgentComputeProjectFilesystemState(aiAgentGetProjectRootPath());
        $storedBundle = aiAgentLoadStoredProjectIndexBundle($indexConfig);
        $watcherSignal = $storedBundle['watcher_signal'] ?? aiAgentGetProjectIndexWatcherSignalState($indexConfig);

        $rebuildReason = aiAgentDetermineProjectIndexRebuildReason(
            isset($storedBundle['meta']) && is_array($storedBundle['meta']) ? $storedBundle['meta'] : [],
            isset($storedBundle['project_index']) && is_array($storedBundle['project_index']) ? $storedBundle['project_index'] : [],
            isset($storedBundle['feature_manifest']) && is_array($storedBundle['feature_manifest']) ? $storedBundle['feature_manifest'] : [],
            $filesystemState,
            $schemaFingerprint,
            $watcherSignal,
            $indexConfig
        );

        if ($rebuildReason !== '') {
            return aiAgentRebuildProjectIndexBundle(
                $conn,
                $filesystemState,
                $schemaIndex,
                $schemaFingerprint,
                $watcherSignal,
                $toolConfig,
                $indexConfig,
                $rebuildReason,
                [
                    'lock' => $lock,
                ]
            );
        }

        return aiAgentBuildProjectIndexState($indexConfig, $storedBundle, [
            'enabled' => true,
            'rebuilt' => false,
            'reused' => true,
            'rebuild_required' => false,
            'reason' => 'up_to_date',
            'lock' => $lock,
        ]);
    } finally {
        aiAgentReleaseProjectIndexLock($lock);
    }
}

function aiAgentEnsureProjectIndexStorageDirectory(string $storageDir): void
{
    if (is_dir($storageDir)) {
        return;
    }

    @mkdir($storageDir, 0775, true);
}

function aiAgentAcquireProjectIndexLock(array $indexConfig = []): array
{
    $path = trim((string) ($indexConfig['lock_file'] ?? ''));
    $timeoutSeconds = max(1, (int) ($indexConfig['lock_timeout_seconds'] ?? 15));

    if ($path === '') {
        return [
            'acquired' => false,
            'path' => '',
            'waited_ms' => 0,
            'error' => 'lock_file_missing',
        ];
    }

    aiAgentEnsureProjectIndexStorageDirectory(dirname($path));

    $handle = @fopen($path, 'c+');
    if (!is_resource($handle)) {
        return [
            'acquired' => false,
            'path' => $path,
            'waited_ms' => 0,
            'error' => 'lock_open_failed',
        ];
    }

    $startedAt = microtime(true);
    do {
        if (@flock($handle, LOCK_EX | LOCK_NB)) {
            $lockInfo = [
                'acquired_at' => time(),
                'acquired_at_iso' => date('c'),
                'pid' => function_exists('getmypid') ? (int) getmypid() : 0,
            ];

            @ftruncate($handle, 0);
            @rewind($handle);
            @fwrite($handle, json_encode($lockInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            @fflush($handle);

            return [
                'acquired' => true,
                'handle' => $handle,
                'path' => $path,
                'waited_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => '',
            ];
        }

        usleep(100000);
    } while ((microtime(true) - $startedAt) < $timeoutSeconds);

    @fclose($handle);

    return [
        'acquired' => false,
        'path' => $path,
        'waited_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'error' => 'lock_timeout',
    ];
}

function aiAgentReleaseProjectIndexLock(array $lock = []): void
{
    if (!isset($lock['handle']) || !is_resource($lock['handle'])) {
        return;
    }

    @flock($lock['handle'], LOCK_UN);
    @fclose($lock['handle']);
}

function aiAgentNormalizeProjectIndexLockInfo(array $lock = []): array
{
    return [
        'acquired' => !empty($lock['acquired']),
        'path' => (string) ($lock['path'] ?? ''),
        'waited_ms' => (int) ($lock['waited_ms'] ?? 0),
        'error' => (string) ($lock['error'] ?? ''),
    ];
}

function aiAgentGetProjectIndexWatcherSignalState(array $indexConfig = []): array
{
    $path = (string) ($indexConfig['watcher_signal_file'] ?? '');
    if ($path === '') {
        return [
            'path' => '',
            'exists' => false,
            'mtime' => 0,
            'payload' => [],
        ];
    }

    $mtime = is_file($path) ? (int) @filemtime($path) : 0;

    return [
        'path' => $path,
        'exists' => is_file($path),
        'mtime' => $mtime,
        'payload' => aiAgentLoadProjectIndexSignalPayload($path),
    ];
}

function aiAgentLoadProjectIndexSignalPayload(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $content = @file_get_contents($path);
    if (!is_string($content) || trim($content) === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return [
        'raw' => aiAgentCleanText($content, 200),
    ];
}

function aiAgentTouchProjectIndexWatcherSignal(array $config = [], array $payload = []): array
{
    $indexConfig = aiAgentGetProjectIndexConfig($config);
    $path = (string) ($indexConfig['watcher_signal_file'] ?? '');
    if ($path === '') {
        return [
            'path' => '',
            'exists' => false,
            'mtime' => 0,
            'payload' => [],
        ];
    }

    aiAgentEnsureProjectIndexStorageDirectory(dirname($path));

    $now = time();
    $signalPayload = [
        'touched_at' => $now,
        'touched_at_iso' => date('c', $now),
        'reason' => aiAgentCleanText((string) ($payload['reason'] ?? 'manual_signal'), 120),
        'source' => aiAgentCleanText((string) ($payload['source'] ?? 'manual'), 80),
    ];

    foreach ($payload as $key => $value) {
        $key = trim((string) $key);
        if ($key === '' || array_key_exists($key, $signalPayload)) {
            continue;
        }

        if (is_scalar($value)) {
            $signalPayload[$key] = aiAgentCleanText((string) $value, 200);
        }
    }

    @file_put_contents(
        $path,
        json_encode($signalPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
    clearstatcache(true, $path);

    return aiAgentGetProjectIndexWatcherSignalState($indexConfig);
}

function aiAgentDetermineProjectIndexRebuildReason(
    array $meta,
    array $projectIndex,
    array $featureManifest,
    array $filesystemState,
    string $schemaFingerprint,
    array $watcherSignal,
    array $indexConfig = []
): string {
    if (empty($meta) || empty($projectIndex) || empty($featureManifest)) {
        return 'manifest_missing';
    }

    if ((int) ($meta['manifest_version'] ?? 0) !== (int) ($indexConfig['manifest_version'] ?? 1)) {
        return 'manifest_version_changed';
    }

    if (!empty($watcherSignal['exists']) && (int) ($watcherSignal['mtime'] ?? 0) > (int) ($meta['generated_at'] ?? 0)) {
        return 'watcher_signal_updated';
    }

    if (empty($indexConfig['lazy_rebuild'])) {
        return '';
    }

    if ((string) ($meta['project_fingerprint'] ?? '') !== (string) ($filesystemState['fingerprint'] ?? '')) {
        return 'project_files_changed';
    }

    if ((string) ($meta['schema_fingerprint'] ?? '') !== $schemaFingerprint) {
        return 'schema_changed';
    }

    $generatedAt = (int) ($meta['generated_at'] ?? 0);
    if ($generatedAt <= 0 || (time() - $generatedAt) >= (int) ($indexConfig['max_age_seconds'] ?? 300)) {
        return 'manifest_stale';
    }

    return '';
}

function aiAgentComputeProjectFilesystemState(string $projectRoot): array
{
    $files = aiAgentListGroundableFiles($projectRoot, $projectRoot, 0);
    sort($files);

    $signatures = [];
    $latestMtime = 0;

    foreach ($files as $relativePath) {
        $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $mtime = is_file($absolutePath) ? (int) @filemtime($absolutePath) : 0;
        $size = is_file($absolutePath) ? (int) @filesize($absolutePath) : 0;
        $contentHash = aiAgentComputeProjectFileContentHash($absolutePath);

        $latestMtime = max($latestMtime, $mtime);
        $signatures[] = $relativePath . '|' . $size . '|' . $mtime . '|' . $contentHash;
    }

    return [
        'total_files' => count($files),
        'latest_mtime' => $latestMtime,
        'fingerprint' => hash('sha256', implode("\n", $signatures)),
        'files' => $files,
    ];
}

function aiAgentComputeProjectFileContentHash(string $absolutePath): string
{
    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        return '';
    }

    $hash = @hash_file('sha256', $absolutePath);
    return is_string($hash) ? $hash : '';
}

function aiAgentComputeSchemaFingerprint(array $schemaIndex): string
{
    $signatures = [];

    ksort($schemaIndex);
    foreach ($schemaIndex as $tableName => $meta) {
        $columns = $meta['columns'] ?? [];
        foreach ($columns as $columnName) {
            $signatures[] = $tableName
                . '|'
                . $columnName
                . '|'
                . (string) (($meta['data_types'][$columnName] ?? ''))
                . '|'
                . (string) (($meta['keys'][$columnName] ?? ''));
        }
    }

    return hash('sha256', implode("\n", $signatures));
}

function aiAgentLoadProjectIndexJson(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $content = @file_get_contents($path);
    if (!is_string($content) || trim($content) === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function aiAgentWriteProjectIndexJson(string $path, array $payload): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    @file_put_contents(
        $path,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function aiAgentLoadStoredProjectIndexBundle(array $indexConfig = []): array
{
    return [
        'project_index' => aiAgentLoadProjectIndexJson((string) ($indexConfig['project_index_file'] ?? '')),
        'feature_manifest' => aiAgentLoadProjectIndexJson((string) ($indexConfig['feature_manifest_file'] ?? '')),
        'meta' => aiAgentLoadProjectIndexJson((string) ($indexConfig['meta_file'] ?? '')),
        'watcher_signal' => aiAgentGetProjectIndexWatcherSignalState($indexConfig),
    ];
}

function aiAgentBuildProjectIndexState(array $indexConfig, array $storedBundle = [], array $options = []): array
{
    $projectIndex = isset($storedBundle['project_index']) && is_array($storedBundle['project_index'])
        ? $storedBundle['project_index']
        : [];
    $featureManifest = isset($storedBundle['feature_manifest']) && is_array($storedBundle['feature_manifest'])
        ? $storedBundle['feature_manifest']
        : [];
    $meta = isset($storedBundle['meta']) && is_array($storedBundle['meta']) ? $storedBundle['meta'] : [];
    $watcherSignal = isset($storedBundle['watcher_signal']) && is_array($storedBundle['watcher_signal'])
        ? $storedBundle['watcher_signal']
        : aiAgentGetProjectIndexWatcherSignalState($indexConfig);
    $available = !empty($projectIndex) && !empty($featureManifest);

    return [
        'enabled' => !isset($options['enabled']) ? true : (bool) $options['enabled'],
        'available' => !isset($options['available']) ? $available : (bool) $options['available'],
        'rebuilt' => !empty($options['rebuilt']),
        'reused' => !empty($options['reused']),
        'rebuild_required' => !empty($options['rebuild_required']),
        'reason' => (string) ($options['reason'] ?? ''),
        'project_index' => $projectIndex,
        'feature_manifest' => $featureManifest,
        'meta' => $meta,
        'paths' => $indexConfig,
        'watcher_signal' => $watcherSignal,
        'lock' => aiAgentNormalizeProjectIndexLockInfo(
            isset($options['lock']) && is_array($options['lock']) ? $options['lock'] : []
        ),
    ];
}

function aiAgentForceProjectIndexBundleRebuild(mysqli $conn, array $options = []): array
{
    $config = isset($options['config']) && is_array($options['config']) ? $options['config'] : [];
    $toolConfig = isset($options['tool_config']) && is_array($options['tool_config'])
        ? $options['tool_config']
        : aiAgentGetToolLayerConfig($config);
    $indexConfig = aiAgentGetProjectIndexConfig($config);

    if (empty($indexConfig['enabled'])) {
        return aiAgentBuildProjectIndexState($indexConfig, [
            'project_index' => [],
            'feature_manifest' => [],
            'meta' => [],
            'watcher_signal' => aiAgentGetProjectIndexWatcherSignalState($indexConfig),
        ], [
            'enabled' => false,
            'rebuilt' => false,
            'reused' => false,
            'rebuild_required' => false,
            'reason' => 'disabled',
        ]);
    }

    aiAgentEnsureProjectIndexStorageDirectory($indexConfig['storage_dir']);
    $lock = aiAgentAcquireProjectIndexLock($indexConfig);
    if (empty($lock['acquired'])) {
        $storedBundle = aiAgentLoadStoredProjectIndexBundle($indexConfig);
        return aiAgentBuildProjectIndexState($indexConfig, $storedBundle, [
            'enabled' => true,
            'rebuilt' => false,
            'reused' => !empty($storedBundle['project_index']) && !empty($storedBundle['feature_manifest']),
            'rebuild_required' => true,
            'reason' => (string) ($lock['error'] ?? 'lock_timeout'),
            'lock' => $lock,
        ]);
    }

    try {
        $schemaIndex = isset($options['schema_index']) && is_array($options['schema_index'])
            ? $options['schema_index']
            : aiAgentGetLiveSchemaIndex($conn);
        $schemaFingerprint = aiAgentComputeSchemaFingerprint($schemaIndex);
        $filesystemState = aiAgentComputeProjectFilesystemState(aiAgentGetProjectRootPath());
        $watcherSignal = aiAgentGetProjectIndexWatcherSignalState($indexConfig);
        $reason = aiAgentCleanText((string) ($options['reason'] ?? 'manual_rebuild'), 120);

        return aiAgentRebuildProjectIndexBundle(
            $conn,
            $filesystemState,
            $schemaIndex,
            $schemaFingerprint,
            $watcherSignal,
            $toolConfig,
            $indexConfig,
            $reason,
            [
                'lock' => $lock,
            ]
        );
    } finally {
        aiAgentReleaseProjectIndexLock($lock);
    }
}

function aiAgentGetProjectIndexStatusSnapshot(mysqli $conn, array $options = []): array
{
    $config = isset($options['config']) && is_array($options['config']) ? $options['config'] : [];
    $indexConfig = aiAgentGetProjectIndexConfig($config);

    if (empty($indexConfig['enabled'])) {
        $disabledState = aiAgentBuildProjectIndexState($indexConfig, [
            'project_index' => [],
            'feature_manifest' => [],
            'meta' => [],
            'watcher_signal' => aiAgentGetProjectIndexWatcherSignalState($indexConfig),
        ], [
            'enabled' => false,
            'rebuilt' => false,
            'reused' => false,
            'rebuild_required' => false,
            'reason' => 'disabled',
        ]);

        $disabledState['current'] = [
            'filesystem' => [],
            'schema_fingerprint' => '',
        ];

        return $disabledState;
    }

    aiAgentEnsureProjectIndexStorageDirectory($indexConfig['storage_dir']);
    $lock = aiAgentAcquireProjectIndexLock($indexConfig);
    if (empty($lock['acquired'])) {
        $storedBundle = aiAgentLoadStoredProjectIndexBundle($indexConfig);
        $fallbackState = aiAgentBuildProjectIndexState($indexConfig, $storedBundle, [
            'enabled' => true,
            'rebuilt' => false,
            'reused' => !empty($storedBundle['project_index']) && !empty($storedBundle['feature_manifest']),
            'rebuild_required' => true,
            'reason' => (string) ($lock['error'] ?? 'lock_timeout'),
            'lock' => $lock,
        ]);
        $fallbackState['current'] = [
            'filesystem' => [],
            'schema_fingerprint' => '',
        ];

        return $fallbackState;
    }

    try {
        $schemaIndex = isset($options['schema_index']) && is_array($options['schema_index'])
            ? $options['schema_index']
            : aiAgentGetLiveSchemaIndex($conn);
        $schemaFingerprint = aiAgentComputeSchemaFingerprint($schemaIndex);
        $filesystemState = aiAgentComputeProjectFilesystemState(aiAgentGetProjectRootPath());
        $storedBundle = aiAgentLoadStoredProjectIndexBundle($indexConfig);
        $watcherSignal = $storedBundle['watcher_signal'] ?? aiAgentGetProjectIndexWatcherSignalState($indexConfig);
        $reason = aiAgentDetermineProjectIndexRebuildReason(
            isset($storedBundle['meta']) && is_array($storedBundle['meta']) ? $storedBundle['meta'] : [],
            isset($storedBundle['project_index']) && is_array($storedBundle['project_index']) ? $storedBundle['project_index'] : [],
            isset($storedBundle['feature_manifest']) && is_array($storedBundle['feature_manifest']) ? $storedBundle['feature_manifest'] : [],
            $filesystemState,
            $schemaFingerprint,
            $watcherSignal,
            $indexConfig
        );

        $state = aiAgentBuildProjectIndexState($indexConfig, $storedBundle, [
            'enabled' => true,
            'rebuilt' => false,
            'reused' => $reason === '',
            'rebuild_required' => $reason !== '',
            'reason' => $reason !== '' ? $reason : 'up_to_date',
            'lock' => $lock,
        ]);
        $state['current'] = [
            'filesystem' => [
                'total_files' => (int) ($filesystemState['total_files'] ?? 0),
                'latest_mtime' => (int) ($filesystemState['latest_mtime'] ?? 0),
                'fingerprint' => (string) ($filesystemState['fingerprint'] ?? ''),
            ],
            'schema_fingerprint' => $schemaFingerprint,
        ];

        return $state;
    } finally {
        aiAgentReleaseProjectIndexLock($lock);
    }
}

function aiAgentSummarizeProjectIndexState(array $state = []): array
{
    $paths = isset($state['paths']) && is_array($state['paths']) ? $state['paths'] : [];
    $watcherSignal = isset($state['watcher_signal']) && is_array($state['watcher_signal']) ? $state['watcher_signal'] : [];
    $meta = isset($state['meta']) && is_array($state['meta']) ? $state['meta'] : [];

    return [
        'enabled' => !empty($state['enabled']),
        'available' => !empty($state['available']),
        'rebuilt' => !empty($state['rebuilt']),
        'reused' => !empty($state['reused']),
        'rebuild_required' => !empty($state['rebuild_required']),
        'reason' => (string) ($state['reason'] ?? ''),
        'project_index_summary' => isset($state['project_index']['summary']) && is_array($state['project_index']['summary'])
            ? $state['project_index']['summary']
            : [],
        'feature_manifest_summary' => isset($state['feature_manifest']['summary']) && is_array($state['feature_manifest']['summary'])
            ? $state['feature_manifest']['summary']
            : [],
        'meta' => $meta,
        'paths' => [
            'storage_dir' => (string) ($paths['storage_dir'] ?? ''),
            'project_index_file' => (string) ($paths['project_index_file'] ?? ''),
            'feature_manifest_file' => (string) ($paths['feature_manifest_file'] ?? ''),
            'meta_file' => (string) ($paths['meta_file'] ?? ''),
            'watcher_signal_file' => (string) ($paths['watcher_signal_file'] ?? ''),
            'lock_file' => (string) ($paths['lock_file'] ?? ''),
        ],
        'watcher_signal' => [
            'path' => (string) ($watcherSignal['path'] ?? ''),
            'exists' => !empty($watcherSignal['exists']),
            'mtime' => (int) ($watcherSignal['mtime'] ?? 0),
            'payload' => isset($watcherSignal['payload']) && is_array($watcherSignal['payload'])
                ? $watcherSignal['payload']
                : [],
        ],
        'current' => isset($state['current']) && is_array($state['current']) ? $state['current'] : [],
        'lock' => isset($state['lock']) && is_array($state['lock']) ? $state['lock'] : [],
    ];
}

function aiAgentRebuildProjectIndexBundle(
    mysqli $conn,
    array $filesystemState,
    array $schemaIndex,
    string $schemaFingerprint,
    array $watcherSignal,
    array $toolConfig,
    array $indexConfig,
    string $reason,
    array $options = []
): array {
    $projectRoot = aiAgentGetProjectRootPath();
    $projectIndex = [
        'generated_at' => time(),
        'summary' => [
            'total_files' => 0,
            'pages' => 0,
            'apis' => 0,
            'scripts' => 0,
            'configs' => 0,
            'styles' => 0,
            'sql' => 0,
            'docs' => 0,
            'helpers' => 0,
        ],
        'files' => [],
    ];

    foreach ($filesystemState['files'] as $relativePath) {
        $entry = aiAgentBuildProjectIndexEntry($projectRoot, $relativePath, $toolConfig, $indexConfig);
        $projectIndex['files'][$relativePath] = $entry;

        $summaryKey = aiAgentMapProjectIndexTypeToSummaryKey((string) ($entry['type'] ?? 'helper'));
        if (!isset($projectIndex['summary'][$summaryKey])) {
            $projectIndex['summary'][$summaryKey] = 0;
        }
        $projectIndex['summary'][$summaryKey] += 1;
        $projectIndex['summary']['total_files'] += 1;
    }

    $featureManifest = aiAgentBuildProjectFeatureManifest($projectIndex, $schemaIndex);
    $meta = [
        'manifest_version' => (int) ($indexConfig['manifest_version'] ?? 1),
        'generated_at' => time(),
        'generated_at_iso' => date('c'),
        'project_root' => $projectRoot,
        'project_fingerprint_mode' => (string) ($indexConfig['fingerprint_mode'] ?? 'content_hash'),
        'project_fingerprint' => (string) ($filesystemState['fingerprint'] ?? ''),
        'schema_fingerprint' => $schemaFingerprint,
        'total_files' => (int) ($filesystemState['total_files'] ?? 0),
        'latest_file_mtime' => (int) ($filesystemState['latest_mtime'] ?? 0),
        'watcher_signal_path' => (string) ($watcherSignal['path'] ?? ''),
        'watcher_signal_mtime' => (int) ($watcherSignal['mtime'] ?? 0),
        'last_rebuild_reason' => $reason,
        'database_name' => aiAgentGetDatabaseName($conn),
    ];

    aiAgentWriteProjectIndexJson($indexConfig['project_index_file'], $projectIndex);
    aiAgentWriteProjectIndexJson($indexConfig['feature_manifest_file'], $featureManifest);
    aiAgentWriteProjectIndexJson($indexConfig['meta_file'], $meta);

    return aiAgentBuildProjectIndexState($indexConfig, [
        'project_index' => $projectIndex,
        'feature_manifest' => $featureManifest,
        'meta' => $meta,
        'watcher_signal' => $watcherSignal,
    ], [
        'enabled' => true,
        'rebuilt' => true,
        'reused' => false,
        'rebuild_required' => false,
        'reason' => $reason,
        'lock' => isset($options['lock']) && is_array($options['lock']) ? $options['lock'] : [],
    ]);
}

function aiAgentBuildProjectIndexEntry(
    string $projectRoot,
    string $relativePath,
    array $toolConfig = [],
    array $indexConfig = []
): array {
    $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $size = is_file($absolutePath) ? (int) @filesize($absolutePath) : 0;
    $mtime = is_file($absolutePath) ? (int) @filemtime($absolutePath) : 0;
    $type = aiAgentInferProjectIndexFileType($relativePath);

    $entry = [
        'path' => $relativePath,
        'type' => $type,
        'module' => aiAgentInferProjectIndexModule($relativePath),
        'size' => $size,
        'mtime' => $mtime,
        'display_name' => aiAgentBuildProjectIndexDisplayName($relativePath, []),
        'scopes' => [],
        'linked_paths' => [],
        'title' => '',
        'headings' => [],
        'buttons' => [],
        'table_headers' => [],
        'labels' => [],
        'form_actions' => [],
        'functions' => [],
    ];

    if (!is_file($absolutePath) || $size <= 0 || $size > (int) ($indexConfig['max_file_size_bytes'] ?? 200000)) {
        return $entry;
    }

    $content = @file_get_contents($absolutePath);
    if (!is_string($content) || trim($content) === '') {
        return $entry;
    }

    $entry['scopes'] = aiAgentInferProjectIndexScopes($relativePath, $content, $toolConfig);
    $entry['linked_paths'] = array_slice(aiAgentExtractReferencedProjectPaths($relativePath, $content), 0, 24);
    $entry['functions'] = aiAgentExtractProjectIndexFunctionNames($content, 8);

    if (in_array($type, ['page', 'api', 'helper', 'config'], true)) {
        $entry['title'] = aiAgentExtractFirstProjectIndexTextMatch($content, '/<title[^>]*>(.*?)<\/title>/is', 120);
        $entry['headings'] = aiAgentExtractProjectIndexTextMatches($content, '/<h[1-4][^>]*>(.*?)<\/h[1-4]>/is', 8, 120);
        $entry['buttons'] = array_values(array_unique(array_merge(
            aiAgentExtractProjectIndexTextMatches($content, '/<button[^>]*>(.*?)<\/button>/is', 8, 80),
            aiAgentExtractProjectIndexTextMatches($content, '/<a[^>]*class=["\'][^"\']*btn[^"\']*["\'][^>]*>(.*?)<\/a>/is', 8, 80)
        )));
        $entry['table_headers'] = aiAgentExtractProjectIndexTextMatches($content, '/<th[^>]*>(.*?)<\/th>/is', 10, 80);
        $entry['labels'] = aiAgentExtractProjectIndexTextMatches($content, '/<label[^>]*>(.*?)<\/label>/is', 10, 80);
        $entry['form_actions'] = aiAgentExtractProjectIndexActionReferences($relativePath, $content);
    }

    $entry['display_name'] = aiAgentBuildProjectIndexDisplayName($relativePath, $entry);
    return $entry;
}

function aiAgentInferProjectIndexFileType(string $relativePath): string
{
    $normalized = strtolower(str_replace('\\', '/', $relativePath));

    if (strpos($normalized, 'api/') === 0 && preg_match('/\.php$/', $normalized) === 1) {
        return 'api';
    }
    if (strpos($normalized, 'assets/js/') === 0 && preg_match('/\.js$/', $normalized) === 1) {
        return 'script';
    }
    if (strpos($normalized, 'assets/css/') === 0 && preg_match('/\.css$/', $normalized) === 1) {
        return 'style';
    }
    if (strpos($normalized, 'config/') === 0) {
        return 'config';
    }
    if (preg_match('/\.(md)$/', $normalized) === 1) {
        return 'doc';
    }
    if (preg_match('/\.(sql)$/', $normalized) === 1) {
        return 'sql';
    }
    if (preg_match('#^(admin|manager|user|pic-barang)/#', $normalized) === 1 || preg_match('/\.(html)$/', $normalized) === 1) {
        return 'page';
    }

    return 'helper';
}

function aiAgentMapProjectIndexTypeToSummaryKey(string $type): string
{
    $map = [
        'page' => 'pages',
        'api' => 'apis',
        'script' => 'scripts',
        'config' => 'configs',
        'style' => 'styles',
        'sql' => 'sql',
        'doc' => 'docs',
        'helper' => 'helpers',
    ];

    return $map[$type] ?? 'helpers';
}

function aiAgentInferProjectIndexModule(string $relativePath): string
{
    $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $relativePath)), static function ($part) {
        return $part !== '';
    }));

    if (empty($parts)) {
        return 'root';
    }

    if (in_array($parts[0], ['admin', 'manager', 'user', 'pic-barang'], true)) {
        return $parts[1] ?? $parts[0];
    }

    if ($parts[0] === 'api') {
        return $parts[1] ?? 'api';
    }

    if ($parts[0] === 'assets' && ($parts[1] ?? '') === 'js') {
        return $parts[2] ?? 'frontend';
    }

    return $parts[0];
}

function aiAgentInferProjectIndexScopes(string $relativePath, string $content, array $toolConfig = []): array
{
    $haystack = strtolower($relativePath . "\n" . aiAgentStringSubstring($content, 0, 50000));
    $scopes = [];

    foreach (($toolConfig['scope_keywords'] ?? []) as $scope => $keywords) {
        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim((string) $keyword));
            if ($keyword !== '' && strpos($haystack, $keyword) !== false) {
                $scopes[] = (string) $scope;
                break;
            }
        }
    }

    return array_values(array_unique(array_filter($scopes)));
}

function aiAgentExtractProjectIndexFunctionNames(string $content, int $limit = 8): array
{
    $matches = [];
    preg_match_all('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $content, $functionMatches);
    foreach (($functionMatches[1] ?? []) as $name) {
        $name = aiAgentCleanText((string) $name, 80);
        if ($name !== '') {
            $matches[] = $name;
        }
    }

    preg_match_all('/(?:const|let|var)\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?:async\s*)?\(/', $content, $callableMatches);
    foreach (($callableMatches[1] ?? []) as $name) {
        $name = aiAgentCleanText((string) $name, 80);
        if ($name !== '') {
            $matches[] = $name;
        }
    }

    return array_values(array_unique(array_slice($matches, 0, max(1, $limit))));
}

function aiAgentExtractProjectIndexTextMatches(string $content, string $pattern, int $limit = 8, int $maxLength = 120): array
{
    if (!preg_match_all($pattern, $content, $matches)) {
        return [];
    }

    $results = [];
    foreach (($matches[1] ?? []) as $rawText) {
        $text = aiAgentNormalizeProjectIndexText((string) $rawText, $maxLength);
        if ($text !== '') {
            $results[] = $text;
        }
    }

    return array_values(array_unique(array_slice($results, 0, max(1, $limit))));
}

function aiAgentExtractFirstProjectIndexTextMatch(string $content, string $pattern, int $maxLength = 120): string
{
    $matches = aiAgentExtractProjectIndexTextMatches($content, $pattern, 1, $maxLength);
    return $matches[0] ?? '';
}

function aiAgentNormalizeProjectIndexText(string $text, int $maxLength = 120): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = aiAgentCleanText($text, $maxLength);

    if ($text === '' || $text === '-' || $text === '--') {
        return '';
    }

    return $text;
}

function aiAgentExtractProjectIndexActionReferences(string $relativePath, string $content): array
{
    $actions = [];

    if (preg_match_all('/<form[^>]+action=["\']([^"\']+)["\']/i', $content, $matches)) {
        foreach (($matches[1] ?? []) as $reference) {
            $resolved = aiAgentResolveProjectRelativeReference($relativePath, (string) $reference);
            if ($resolved !== '') {
                $actions[] = $resolved;
            }
        }
    }

    return array_values(array_unique(array_slice($actions, 0, 12)));
}

function aiAgentBuildProjectIndexDisplayName(string $relativePath, array $entry = []): string
{
    $labels = [];

    $title = aiAgentCleanText((string) ($entry['title'] ?? ''), 120);
    if ($title !== '') {
        $labels[] = $title;
    }

    foreach (($entry['headings'] ?? []) as $heading) {
        $heading = aiAgentCleanText((string) $heading, 120);
        if ($heading !== '') {
            $labels[] = $heading;
        }
    }

    if (!empty($labels)) {
        return $labels[0];
    }

    $basename = basename($relativePath);
    $basename = preg_replace('/\.[^.]+$/', '', $basename);
    $basename = str_replace(['-', '_'], ' ', (string) $basename);
    $basename = ucwords(trim($basename));

    return $basename !== '' ? $basename : $relativePath;
}

function aiAgentBuildProjectFeatureManifest(array $projectIndex, array $schemaIndex): array
{
    $features = [];
    $modules = [];
    $scopes = [];

    foreach (($projectIndex['files'] ?? []) as $relativePath => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $feature = [
            'path' => (string) ($entry['path'] ?? $relativePath),
            'type' => (string) ($entry['type'] ?? 'helper'),
            'module' => (string) ($entry['module'] ?? 'root'),
            'display_name' => (string) ($entry['display_name'] ?? $relativePath),
            'scopes' => array_values(array_filter(array_map('strval', $entry['scopes'] ?? []))),
            'linked_paths' => array_values(array_filter(array_map('strval', $entry['linked_paths'] ?? []))),
            'title' => (string) ($entry['title'] ?? ''),
            'headings' => array_values(array_filter(array_map('strval', $entry['headings'] ?? []))),
            'buttons' => array_values(array_filter(array_map('strval', $entry['buttons'] ?? []))),
            'table_headers' => array_values(array_filter(array_map('strval', $entry['table_headers'] ?? []))),
            'form_actions' => array_values(array_filter(array_map('strval', $entry['form_actions'] ?? []))),
            'functions' => array_values(array_filter(array_map('strval', $entry['functions'] ?? []))),
            'mtime' => (int) ($entry['mtime'] ?? 0),
        ];

        $features[] = $feature;

        $module = $feature['module'] !== '' ? $feature['module'] : 'root';
        if (!isset($modules[$module])) {
            $modules[$module] = [
                'total_features' => 0,
                'examples' => [],
            ];
        }
        $modules[$module]['total_features'] += 1;
        if (count($modules[$module]['examples']) < 6 && $feature['display_name'] !== '') {
            $modules[$module]['examples'][] = $feature['display_name'];
        }

        foreach ($feature['scopes'] as $scope) {
            if (!isset($scopes[$scope])) {
                $scopes[$scope] = [
                    'total_features' => 0,
                    'examples' => [],
                ];
            }
            $scopes[$scope]['total_features'] += 1;
            if (count($scopes[$scope]['examples']) < 8 && $feature['display_name'] !== '') {
                $scopes[$scope]['examples'][] = $feature['display_name'];
            }
        }
    }

    ksort($modules);
    ksort($scopes);

    return [
        'generated_at' => time(),
        'summary' => [
            'total_features' => count($features),
            'total_modules' => count($modules),
            'total_scopes' => count($scopes),
            'schema_tables' => count($schemaIndex),
        ],
        'modules' => $modules,
        'scopes' => $scopes,
        'features' => $features,
    ];
}

function aiAgentFindRelevantProjectManifestEntries(array $featureManifest, array $options = []): array
{
    $features = isset($featureManifest['features']) && is_array($featureManifest['features'])
        ? $featureManifest['features']
        : [];
    if (empty($features)) {
        return [];
    }

    $keywords = isset($options['keywords']) && is_array($options['keywords']) ? $options['keywords'] : [];
    $pagePath = aiAgentNormalizeProjectRelativePath((string) ($options['page_path'] ?? ''));
    $pageInspection = isset($options['page_inspection']) && is_array($options['page_inspection']) ? $options['page_inspection'] : [];
    $pageSnapshot = isset($options['page_snapshot']) && is_array($options['page_snapshot']) ? $options['page_snapshot'] : [];
    $limit = max(1, (int) ($options['limit'] ?? 6));

    $linkedPaths = [];
    foreach (($pageInspection['linked_files'] ?? []) as $linkedPath) {
        $linkedPath = aiAgentNormalizeProjectRelativePath((string) $linkedPath);
        if ($linkedPath !== '') {
            $linkedPaths[] = $linkedPath;
        }
    }

    $snapshotTerms = [];
    foreach ($pageSnapshot as $group) {
        if (!is_array($group)) {
            continue;
        }
        foreach ($group as $term) {
            $term = strtolower(aiAgentCleanText((string) $term, 80));
            if ($term !== '') {
                $snapshotTerms[] = $term;
            }
        }
    }

    $scored = [];
    foreach ($features as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $score = aiAgentScoreProjectManifestEntry($entry, $pagePath, $linkedPaths, $keywords, $snapshotTerms);
        if ($score <= 0) {
            continue;
        }

        $entry['score'] = $score;
        $scored[] = $entry;
    }

    usort($scored, static function (array $left, array $right): int {
        $scoreDiff = (int) ($right['score'] ?? 0) <=> (int) ($left['score'] ?? 0);
        if ($scoreDiff !== 0) {
            return $scoreDiff;
        }

        return strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
    });

    return array_slice($scored, 0, $limit);
}

function aiAgentScoreProjectManifestEntry(
    array $entry,
    string $pagePath,
    array $linkedPaths,
    array $keywords,
    array $snapshotTerms
): int {
    $path = aiAgentNormalizeProjectRelativePath((string) ($entry['path'] ?? ''));
    $linkedEntryPaths = array_values(array_filter(array_map('aiAgentNormalizeProjectRelativePath', $entry['linked_paths'] ?? [])));
    $blobParts = [
        strtolower((string) ($entry['display_name'] ?? '')),
        strtolower((string) ($entry['title'] ?? '')),
        strtolower(implode(' ', $entry['headings'] ?? [])),
        strtolower(implode(' ', $entry['buttons'] ?? [])),
        strtolower(implode(' ', $entry['table_headers'] ?? [])),
        strtolower(implode(' ', $entry['functions'] ?? [])),
        strtolower(implode(' ', $entry['form_actions'] ?? [])),
        strtolower(implode(' ', $entry['scopes'] ?? [])),
        strtolower((string) ($entry['module'] ?? '')),
        strtolower($path),
    ];
    $blob = implode("\n", array_filter($blobParts));

    $score = 0;

    if ($pagePath !== '' && $path === $pagePath) {
        $score += 140;
    }

    if ($pagePath !== '' && in_array($path, $linkedPaths, true)) {
        $score += 90;
    }

    if (!empty(array_intersect($linkedPaths, $linkedEntryPaths))) {
        $score += 40;
    }

    foreach ($keywords as $keyword) {
        $keyword = strtolower(trim((string) $keyword));
        if ($keyword !== '' && strpos($blob, $keyword) !== false) {
            $score += 8;
        }
    }

    foreach ($snapshotTerms as $term) {
        if ($term !== '' && strpos($blob, $term) !== false) {
            $score += 4;
        }
    }

    if ($score === 0 && in_array((string) ($entry['type'] ?? ''), ['page', 'api', 'script'], true)) {
        $score = 2;
    }

    return $score;
}
