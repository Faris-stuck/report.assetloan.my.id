<?php

function aiAgentBuildToolRuntimeContext(mysqli $conn, array $options = []): array
{
    $config = isset($options['config']) && is_array($options['config']) ? $options['config'] : [];
    $message = aiAgentCleanText((string) ($options['message'] ?? ''), 1600);
    $role = trim((string) ($options['role'] ?? 'user'));
    $userId = (int) ($options['user_id'] ?? 0);
    $pageContext = isset($options['page_context']) && is_array($options['page_context']) ? $options['page_context'] : [];
    $pageSnapshot = aiAgentNormalizeToolPageSnapshot($options['page_snapshot'] ?? ($pageContext['ui_snapshot'] ?? []));
    $history = isset($options['history']) && is_array($options['history']) ? $options['history'] : [];
    $hasSensitiveAccess = !empty($options['has_sensitive_access']);
    $isSensitiveRequest = !empty($options['is_sensitive_request']);
    $useSensitiveGrounding = !empty($options['use_sensitive_grounding']);

    $toolConfig = aiAgentGetToolLayerConfig($config);
    $accessDecision = isset($options['access_decision']) && is_array($options['access_decision'])
        ? $options['access_decision']
        : aiAgentEvaluateRuntimeAccess([
            'config' => $config,
            'tool_config' => $toolConfig,
            'role' => $role,
            'message' => $message,
            'page_context' => $pageContext,
            'page_snapshot' => $pageSnapshot,
            'has_sensitive_access' => $hasSensitiveAccess,
            'has_business_override_access' => !empty($options['has_business_override_access']),
        ]);
    $roleGuard = aiAgentBuildToolRoleGuard([
        'role' => $role,
        'has_sensitive_access' => $hasSensitiveAccess,
        'is_sensitive_request' => $isSensitiveRequest,
        'use_sensitive_grounding' => $useSensitiveGrounding,
        'tool_config' => $toolConfig,
        'access_decision' => $accessDecision,
    ]);

    $sharedState = aiAgentBuildToolSharedState($conn, [
        'message' => $message,
        'role' => $role,
        'user_id' => $userId,
        'page_context' => $pageContext,
        'page_snapshot' => $pageSnapshot,
        'history' => $history,
        'config' => $config,
        'tool_config' => $toolConfig,
        'access_decision' => $accessDecision,
    ]);

    $toolPlan = aiAgentResolveToolPlan([
        'role_guard' => $roleGuard,
        'tool_config' => $toolConfig,
        'message' => $message,
        'page_context' => $pageContext,
        'page_snapshot' => $pageSnapshot,
        'shared_state' => $sharedState,
    ]);

    $sections = [];
    foreach ($toolPlan as $toolName) {
        $toolResult = aiAgentExecuteRuntimeTool($toolName, $conn, [
            'message' => $message,
            'role' => $role,
            'user_id' => $userId,
            'page_context' => $pageContext,
            'page_snapshot' => $pageSnapshot,
            'history' => $history,
            'shared_state' => $sharedState,
            'role_guard' => $roleGuard,
            'tool_config' => $toolConfig,
            'access_decision' => $accessDecision,
        ]);

        if (!empty($toolResult['safe_lines']) || !empty($toolResult['technical_lines'])) {
            $sections[$toolName] = $toolResult;
        }
    }

    return [
        'enabled' => $toolConfig['enabled'],
        'tool_config' => $toolConfig,
        'role_guard' => $roleGuard,
        'shared_state' => $sharedState,
        'tool_plan' => $toolPlan,
        'sections' => $sections,
        'access_decision' => $accessDecision,
        'grounding' => aiAgentAssembleToolGrounding([
            'role_guard' => $roleGuard,
            'sections' => $sections,
        ]),
    ];
}

function aiAgentGetToolLayerConfig(array $config = []): array
{
    $defaults = [
        'enabled' => !isset($config['tool_layer_enabled']) ? true : (bool) $config['tool_layer_enabled'],
        'baseline_tools' => [
            'role_guard',
            'session_context',
            'page_metadata',
            'project_index',
            'ui_snapshot',
            'runtime_observations',
            'live_schema',
            'live_data',
        ],
        'role_whitelist' => [
            'admin' => [
                'role_guard',
                'session_context',
                'page_metadata',
                'project_index',
                'ui_snapshot',
                'runtime_observations',
                'live_schema',
                'live_data',
            ],
            'manager' => [
                'role_guard',
                'session_context',
                'page_metadata',
                'project_index',
                'ui_snapshot',
                'runtime_observations',
                'live_schema',
                'live_data',
            ],
            'user' => [
                'role_guard',
                'session_context',
                'page_metadata',
                'project_index',
                'ui_snapshot',
                'runtime_observations',
                'live_schema',
                'live_data',
            ],
            'pic_barang' => [
                'role_guard',
                'session_context',
                'page_metadata',
                'project_index',
                'ui_snapshot',
                'runtime_observations',
                'live_schema',
                'live_data',
            ],
        ],
        'sensitive_whitelist' => [
            'admin' => [
                'technical_code_context',
            ],
        ],
        'role_data_scopes' => [
            'admin' => [
                'inventory',
                'borrowing',
                'returns',
                'extensions',
                'users_admin',
                'reports',
                'vendors',
            ],
            'manager' => [
                'inventory',
                'borrowing',
                'returns',
                'extensions',
                'reports',
            ],
            'user' => [
                'inventory',
                'borrowing',
                'returns',
                'extensions',
            ],
            'pic_barang' => [
                'inventory',
                'borrowing',
                'returns',
                'extensions',
                'vendors',
            ],
        ],
        'scope_keywords' => [
            'inventory' => [
                'barang',
                'item',
                'inventory',
                'stok',
                'stock',
                'safety stock',
                'kondisi',
                'vendor',
                'pembelian',
            ],
            'borrowing' => [
                'peminjaman',
                'pinjam',
                'borrow',
                'loan',
                'approval',
                'disetujui',
                'jatuh tempo',
                'overdue',
                'due',
            ],
            'returns' => [
                'pengembalian',
                'return',
                'kembali',
                'inspection',
                'inspected',
                'rusak',
                'damaged',
            ],
            'extensions' => [
                'extend',
                'perpanjang',
                'perpanjangan',
            ],
            'users_admin' => [
                'administrator',
                'user list',
                'role list',
                'akun',
                'account',
                'email',
                'nrp',
                'data user',
            ],
            'reports' => [
                'report',
                'laporan',
                'rekap',
                'ringkasan',
                'summary',
            ],
            'vendors' => [
                'vendor',
                'purchase',
                'pembelian',
            ],
        ],
        'cross_role_markers' => [
            'semua',
            'seluruh',
            'global',
            'keseluruhan',
            'semua user',
            'semua pengguna',
            'semua peminjam',
            'semua data',
            'user lain',
            'orang lain',
            'selain saya',
            'lintas role',
            'all users',
            'all borrowing',
            'all data',
            'other users',
        ],
        'technical_keywords' => [
            'nama file',
            'file php',
            'file html',
            'folder',
            'path',
            'direktori',
            'schema',
            'sql',
            'nama tabel',
            'nama kolom',
            'kolom',
            'query',
            'endpoint',
            'source code',
            'kode backend',
            'backend',
            'frontend',
            'implementasi',
            'logika',
            'widget',
            'system prompt',
            'grounding',
            'lokasi file',
            'struktur folder',
            'function',
            'class',
            'javascript',
            'information_schema',
        ],
        'max_tables' => max(3, (int) ($config['tool_max_tables'] ?? 6)),
        'max_linked_files' => max(4, (int) ($config['tool_max_linked_files'] ?? 12)),
        'max_status_values' => max(3, (int) ($config['tool_max_status_values'] ?? 6)),
    ];

    if (isset($config['tool_baseline']) && is_array($config['tool_baseline'])) {
        $defaults['baseline_tools'] = aiAgentNormalizeToolNames($config['tool_baseline']);
    }

    if (isset($config['tool_role_whitelist']) && is_array($config['tool_role_whitelist'])) {
        foreach ($config['tool_role_whitelist'] as $role => $tools) {
            if (!is_array($tools)) {
                continue;
            }
            $defaults['role_whitelist'][(string) $role] = aiAgentNormalizeToolNames($tools);
        }
    }

    if (isset($config['tool_sensitive_whitelist']) && is_array($config['tool_sensitive_whitelist'])) {
        foreach ($config['tool_sensitive_whitelist'] as $role => $tools) {
            if (!is_array($tools)) {
                continue;
            }
            $defaults['sensitive_whitelist'][(string) $role] = aiAgentNormalizeToolNames($tools);
        }
    }

    if (isset($config['tool_role_data_scopes']) && is_array($config['tool_role_data_scopes'])) {
        foreach ($config['tool_role_data_scopes'] as $role => $scopes) {
            if (!is_array($scopes)) {
                continue;
            }
            $defaults['role_data_scopes'][(string) $role] = aiAgentNormalizeToolScopes($scopes);
        }
    }

    if (isset($config['tool_scope_keywords']) && is_array($config['tool_scope_keywords'])) {
        $defaults['scope_keywords'] = aiAgentNormalizeToolKeywordMap($config['tool_scope_keywords']);
    }

    if (isset($config['tool_cross_role_markers']) && is_array($config['tool_cross_role_markers'])) {
        $defaults['cross_role_markers'] = aiAgentNormalizeToolKeywords($config['tool_cross_role_markers']);
    }

    if (isset($config['tool_technical_keywords']) && is_array($config['tool_technical_keywords'])) {
        $defaults['technical_keywords'] = aiAgentNormalizeToolKeywords($config['tool_technical_keywords']);
    }

    return $defaults;
}

function aiAgentNormalizeToolNames(array $toolNames): array
{
    $normalized = [];
    foreach ($toolNames as $toolName) {
        $toolName = trim((string) $toolName);
        if ($toolName !== '') {
            $normalized[] = $toolName;
        }
    }

    return array_values(array_unique($normalized));
}

function aiAgentNormalizeToolScopes(array $scopes): array
{
    $normalized = [];
    foreach ($scopes as $scope) {
        $scope = strtolower(trim((string) $scope));
        if ($scope !== '') {
            $normalized[] = $scope;
        }
    }

    return array_values(array_unique($normalized));
}

function aiAgentNormalizeToolKeywords(array $keywords): array
{
    $normalized = [];
    foreach ($keywords as $keyword) {
        $keyword = strtolower(trim((string) $keyword));
        if ($keyword !== '') {
            $normalized[] = $keyword;
        }
    }

    return array_values(array_unique($normalized));
}

function aiAgentNormalizeToolKeywordMap(array $keywordMap): array
{
    $normalized = [];
    foreach ($keywordMap as $scope => $keywords) {
        if (!is_array($keywords)) {
            continue;
        }
        $scope = strtolower(trim((string) $scope));
        if ($scope === '') {
            continue;
        }
        $normalized[$scope] = aiAgentNormalizeToolKeywords($keywords);
    }

    return $normalized;
}

function aiAgentEvaluateRuntimeAccess(array $options = []): array
{
    $config = isset($options['config']) && is_array($options['config']) ? $options['config'] : [];
    $toolConfig = isset($options['tool_config']) && is_array($options['tool_config'])
        ? $options['tool_config']
        : aiAgentGetToolLayerConfig($config);

    $role = trim((string) ($options['role'] ?? 'user'));
    $message = aiAgentCleanText((string) ($options['message'] ?? ''), 1600);
    $pageContext = isset($options['page_context']) && is_array($options['page_context']) ? $options['page_context'] : [];
    $pageSnapshot = aiAgentNormalizeToolPageSnapshot($options['page_snapshot'] ?? []);
    $hasTechnicalAccess = !empty($options['has_sensitive_access']);
    $hasBusinessOverrideAccess = !empty($options['has_business_override_access']);

    $requestedScopes = aiAgentResolveRequestedBusinessScopes($message, $pageContext, $pageSnapshot, $toolConfig);
    $allowedScopes = aiAgentNormalizeToolScopes(
        $toolConfig['role_data_scopes'][$role] ?? ['inventory']
    );
    $deniedScopes = array_values(array_diff($requestedScopes, $allowedScopes));
    $technicalRequested = aiAgentMessageRequestsTechnicalInfo($message, $toolConfig);
    $crossRoleRequested = aiAgentMessageRequestsCrossRoleBusinessData($message, $role, $requestedScopes, $toolConfig);
    $requiresScopeOverride = !empty($deniedScopes) || $crossRoleRequested;
    $hasScopeOverrideAccess = $requiresScopeOverride && $hasBusinessOverrideAccess;
    $canUseTechnicalTools = $technicalRequested && $hasTechnicalAccess;

    $effectiveScopes = $allowedScopes;
    if ($hasScopeOverrideAccess && !empty($requestedScopes)) {
        $effectiveScopes = aiAgentNormalizeToolScopes(array_merge($allowedScopes, $requestedScopes));
    }

    $mode = 'public';
    if ($canUseTechnicalTools) {
        $mode = 'technical_sensitive';
    } elseif ($hasScopeOverrideAccess) {
        $mode = 'scope_override';
    }

    return [
        'role' => $role,
        'requested_scopes' => $requestedScopes,
        'allowed_scopes' => $allowedScopes,
        'effective_scopes' => $effectiveScopes,
        'denied_scopes' => $deniedScopes,
        'technical_requested' => $technicalRequested,
        'cross_role_requested' => $crossRoleRequested,
        'requires_scope_override' => $requiresScopeOverride,
        'has_scope_override_access' => $hasScopeOverrideAccess,
        'has_technical_access' => $hasTechnicalAccess,
        'has_business_override_access' => $hasBusinessOverrideAccess,
        'can_use_technical_tools' => $canUseTechnicalTools,
        'requires_any_elevated_access' => $technicalRequested || $requiresScopeOverride,
        'should_use_elevated_grounding' => $canUseTechnicalTools || $hasScopeOverrideAccess,
        'sensitive_reason' => $technicalRequested ? 'technical' : ($requiresScopeOverride ? 'scope_override' : ''),
        'mode' => $mode,
    ];
}

function aiAgentResolveRequestedBusinessScopes(
    string $message,
    array $pageContext = [],
    array $pageSnapshot = [],
    array $toolConfig = []
): array {
    $parts = [strtolower($message)];

    foreach (['path', 'title', 'heading', 'query'] as $key) {
        $value = trim((string) ($pageContext[$key] ?? ''));
        if ($value !== '') {
            $parts[] = strtolower($value);
        }
    }

    if (isset($pageContext['route_segments']) && is_array($pageContext['route_segments'])) {
        foreach ($pageContext['route_segments'] as $segment) {
            $segment = trim((string) $segment);
            if ($segment !== '') {
                $parts[] = strtolower($segment);
            }
        }
    }

    foreach ($pageSnapshot as $values) {
        if (!is_array($values)) {
            continue;
        }
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $parts[] = strtolower($value);
            }
        }
    }

    $haystack = implode(' ', array_filter($parts));
    if ($haystack === '') {
        return [];
    }

    $scopes = [];
    foreach (($toolConfig['scope_keywords'] ?? []) as $scope => $keywords) {
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && strpos($haystack, strtolower($keyword)) !== false) {
                $scopes[] = strtolower((string) $scope);
                break;
            }
        }
    }

    return aiAgentNormalizeToolScopes($scopes);
}

function aiAgentMessageRequestsTechnicalInfo(string $message, array $toolConfig = []): bool
{
    $source = strtolower(trim($message));
    if ($source === '') {
        return false;
    }

    foreach (($toolConfig['technical_keywords'] ?? []) as $keyword) {
        if ($keyword !== '' && strpos($source, strtolower($keyword)) !== false) {
            return true;
        }
    }

    if (strpos($source, 'database') !== false || strpos($source, 'db ') !== false || strpos($source, 'database ') !== false) {
        foreach (['schema', 'struktur', 'kolom', 'query', 'sql', 'endpoint', 'path', 'tabel', 'nama tabel'] as $pairKeyword) {
            if (strpos($source, $pairKeyword) !== false) {
                return true;
            }
        }
    }

    return false;
}

function aiAgentMessageRequestsCrossRoleBusinessData(
    string $message,
    string $role,
    array $requestedScopes = [],
    array $toolConfig = []
): bool {
    $source = strtolower(trim($message));
    if ($source === '') {
        return false;
    }

    $hasCrossRoleMarker = false;
    foreach (($toolConfig['cross_role_markers'] ?? []) as $marker) {
        if ($marker !== '' && strpos($source, strtolower($marker)) !== false) {
            $hasCrossRoleMarker = true;
            break;
        }
    }

    if (!$hasCrossRoleMarker) {
        return false;
    }

    if ($role === 'user') {
        if (preg_match('/\b(saya|milik saya|akun saya|pinjaman saya|pengembalian saya)\b/u', $source) === 1) {
            return false;
        }

        return !empty(array_intersect($requestedScopes, ['borrowing', 'returns', 'extensions', 'users_admin', 'reports']));
    }

    if ($role === 'pic_barang') {
        return !empty(array_intersect($requestedScopes, ['users_admin', 'reports']));
    }

    if ($role === 'manager') {
        return !empty(array_intersect($requestedScopes, ['users_admin', 'vendors']));
    }

    return false;
}

function aiAgentBuildToolRoleGuard(array $options = []): array
{
    $role = trim((string) ($options['role'] ?? 'user'));
    $toolConfig = isset($options['tool_config']) && is_array($options['tool_config']) ? $options['tool_config'] : aiAgentGetToolLayerConfig();
    $useSensitiveGrounding = !empty($options['use_sensitive_grounding']);
    $hasSensitiveAccess = !empty($options['has_sensitive_access']);
    $isSensitiveRequest = !empty($options['is_sensitive_request']);
    $accessDecision = isset($options['access_decision']) && is_array($options['access_decision'])
        ? $options['access_decision']
        : aiAgentEvaluateRuntimeAccess([
            'tool_config' => $toolConfig,
            'role' => $role,
            'has_sensitive_access' => $hasSensitiveAccess,
            'has_business_override_access' => false,
        ]);

    $publicAllowed = aiAgentNormalizeToolNames(
        $toolConfig['role_whitelist'][$role] ?? $toolConfig['baseline_tools']
    );
    $sensitiveExtras = !empty($accessDecision['can_use_technical_tools']) && $useSensitiveGrounding
        ? aiAgentNormalizeToolNames($toolConfig['sensitive_whitelist'][$role] ?? [])
        : [];

    $allowedTools = aiAgentNormalizeToolNames(array_merge($publicAllowed, $sensitiveExtras));
    $technicalTools = aiAgentNormalizeToolNames($toolConfig['sensitive_whitelist'][$role] ?? []);
    $canUseTechnicalTools = !empty($accessDecision['can_use_technical_tools']) && $useSensitiveGrounding && !empty($technicalTools);
    $mode = (string) ($accessDecision['mode'] ?? ($canUseTechnicalTools ? 'technical_sensitive' : 'public'));
    $requestedScopes = aiAgentNormalizeToolScopes($accessDecision['requested_scopes'] ?? []);
    $allowedScopes = aiAgentNormalizeToolScopes($accessDecision['allowed_scopes'] ?? []);
    $deniedScopes = aiAgentNormalizeToolScopes($accessDecision['denied_scopes'] ?? []);

    $summaryLines = [
        'Tool layer runtime aktif dengan mode ' . $mode . ' untuk role ' . $role . '.',
        'Whitelist tool publik untuk role ini: ' . implode(', ', $publicAllowed) . '.',
        'Scope bisnis normal untuk role ini: ' . implode(', ', !empty($allowedScopes) ? $allowedScopes : ['inventory']) . '.',
    ];

    if (!empty($requestedScopes)) {
        $summaryLines[] = 'Scope yang diminta dari chat atau halaman aktif: ' . implode(', ', $requestedScopes) . '.';
    }

    if (!empty($accessDecision['has_scope_override_access'])) {
        $summaryLines[] = 'Akses data lintas-role sedang aktif sementara melalui password, tetapi detail teknis internal tetap dibatasi terpisah.';
    } elseif (!empty($accessDecision['requires_scope_override'])) {
        $summaryLines[] = 'Permintaan ini menyentuh data bisnis di luar scope role saat ini. Tanpa password, data live tetap dibatasi ke scope role.';
        if (!empty($deniedScopes)) {
            $summaryLines[] = 'Scope yang masih terkunci untuk sesi ini: ' . implode(', ', $deniedScopes) . '.';
        }
    } else {
        $summaryLines[] = 'Data bisnis live dalam scope role ini boleh dipakai tanpa password tambahan.';
    }

    if ($canUseTechnicalTools) {
        $summaryLines[] = 'Role guard mengizinkan tool teknis internal: ' . implode(', ', $technicalTools) . '.';
    } elseif ($hasSensitiveAccess && $isSensitiveRequest && !empty($technicalTools)) {
        $summaryLines[] = 'Role guard mengenali permintaan teknis, tetapi mode sensitif belum aktif penuh untuk tool internal.';
    } else {
        $summaryLines[] = 'Role guard mengunci detail path, cuplikan kode, dan metadata teknis internal untuk sesi ini.';
    }

    return [
        'mode' => $mode,
        'allowed_tools' => $allowedTools,
        'public_allowed_tools' => $publicAllowed,
        'technical_tools' => $technicalTools,
        'can_use_technical_tools' => $canUseTechnicalTools,
        'access_decision' => $accessDecision,
        'summary_lines' => $summaryLines,
    ];
}

function aiAgentBuildToolSharedState(mysqli $conn, array $options = []): array
{
    $message = aiAgentCleanText((string) ($options['message'] ?? ''), 1600);
    $config = isset($options['config']) && is_array($options['config']) ? $options['config'] : [];
    $pageContext = isset($options['page_context']) && is_array($options['page_context']) ? $options['page_context'] : [];
    $pageSnapshot = aiAgentNormalizeToolPageSnapshot($options['page_snapshot'] ?? []);
    $toolConfig = isset($options['tool_config']) && is_array($options['tool_config']) ? $options['tool_config'] : aiAgentGetToolLayerConfig();

    $pagePath = (string) ($pageContext['path'] ?? '');
    $pageTitle = (string) ($pageContext['title'] ?? '');
    $pageHeading = (string) ($pageContext['heading'] ?? '');
    $keywords = aiAgentBuildCodeSearchKeywords($message, $pagePath, $pageTitle, $pageHeading, $pageSnapshot);
    $pageInspection = aiAgentInspectRuntimePage($pagePath, (int) $toolConfig['max_linked_files']);
    $schemaIndex = aiAgentGetLiveSchemaIndex($conn);
    $projectIndexState = aiAgentEnsureProjectIndexBundle($conn, [
        'config' => $config,
        'tool_config' => $toolConfig,
        'schema_index' => $schemaIndex,
    ]);
    $relevantTables = aiAgentRankRelevantTables($schemaIndex, [
        'keywords' => $keywords,
        'max_tables' => (int) $toolConfig['max_tables'],
    ]);
    $relevantManifestEntries = aiAgentFindRelevantProjectManifestEntries(
        isset($projectIndexState['feature_manifest']) && is_array($projectIndexState['feature_manifest'])
            ? $projectIndexState['feature_manifest']
            : [],
        [
            'keywords' => $keywords,
            'page_path' => $pagePath,
            'page_inspection' => $pageInspection,
            'page_snapshot' => $pageSnapshot,
            'limit' => (int) ($projectIndexState['paths']['max_relevant_entries'] ?? 6),
        ]
    );

    return [
        'keywords' => $keywords,
        'page_inspection' => $pageInspection,
        'schema_index' => $schemaIndex,
        'relevant_tables' => $relevantTables,
        'project_index_state' => $projectIndexState,
        'relevant_manifest_entries' => $relevantManifestEntries,
    ];
}

function aiAgentNormalizeToolPageSnapshot($snapshot): array
{
    $normalized = [
        'breadcrumbs' => [],
        'cards' => [],
        'buttons' => [],
        'table_headers' => [],
        'filters' => [],
        'active_filters' => [],
        'labels' => [],
        'links' => [],
        'forms' => [],
        'modals' => [],
        'sections' => [],
        'stats' => [],
        'table_facts' => [],
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
            $text = aiAgentCleanText((string) $value, 120);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        $normalized[$key] = array_values(array_unique(array_slice($items, 0, 16)));
    }

    return $normalized;
}

function aiAgentResolveToolPlan(array $options = []): array
{
    $roleGuard = isset($options['role_guard']) && is_array($options['role_guard']) ? $options['role_guard'] : [];
    $toolConfig = isset($options['tool_config']) && is_array($options['tool_config']) ? $options['tool_config'] : aiAgentGetToolLayerConfig();

    if (empty($toolConfig['enabled'])) {
        return [];
    }

    $plan = aiAgentNormalizeToolNames($roleGuard['public_allowed_tools'] ?? $toolConfig['baseline_tools']);
    if (!empty($roleGuard['can_use_technical_tools'])) {
        $plan = aiAgentNormalizeToolNames(array_merge($plan, $roleGuard['technical_tools'] ?? []));
    }

    return array_values(array_unique(array_filter($plan, static function ($toolName) use ($roleGuard) {
        return in_array($toolName, $roleGuard['allowed_tools'] ?? [], true);
    })));
}

function aiAgentExecuteRuntimeTool(string $toolName, mysqli $conn, array $options = []): array
{
    switch ($toolName) {
        case 'role_guard':
            return aiAgentToolRoleGuard($options);
        case 'session_context':
            return aiAgentToolSessionContext($options);
        case 'page_metadata':
            return aiAgentToolPageMetadata($options);
        case 'project_index':
            return aiAgentToolProjectIndex($options);
        case 'ui_snapshot':
            return aiAgentToolUiSnapshot($options);
        case 'runtime_observations':
            return aiAgentToolRuntimeObservations($options);
        case 'live_schema':
            return aiAgentToolLiveSchema($conn, $options);
        case 'live_data':
            return aiAgentToolLiveData($conn, $options);
        case 'technical_code_context':
            return aiAgentToolTechnicalCodeContext($options);
        default:
            return [
                'safe_lines' => [],
                'technical_lines' => [],
            ];
    }
}

function aiAgentToolRoleGuard(array $options = []): array
{
    $roleGuard = isset($options['role_guard']) && is_array($options['role_guard']) ? $options['role_guard'] : [];

    return [
        'safe_lines' => $roleGuard['summary_lines'] ?? [],
        'technical_lines' => [],
    ];
}

function aiAgentToolSessionContext(array $options = []): array
{
    $role = trim((string) ($options['role'] ?? 'user'));
    $userId = (int) ($options['user_id'] ?? 0);
    $pageContext = isset($options['page_context']) && is_array($options['page_context']) ? $options['page_context'] : [];
    $history = isset($options['history']) && is_array($options['history']) ? $options['history'] : [];
    $roleGuard = isset($options['role_guard']) && is_array($options['role_guard']) ? $options['role_guard'] : [];
    $canUseTechnicalTools = !empty($roleGuard['can_use_technical_tools']);

    $pageBits = [];
    foreach (['path', 'title', 'heading'] as $key) {
        $value = aiAgentCleanText((string) ($pageContext[$key] ?? ''), 180);
        if ($value !== '') {
            $pageBits[] = $key . '=' . $value;
        }
    }

    $lines = [
        'Session aktif: role=' . $role . ($userId > 0 ? ', akun login terverifikasi' : '') . '.',
    ];

    if (!empty($pageBits)) {
        $lines[] = 'Konteks halaman aktif dari browser: ' . implode(' | ', $pageBits) . '.';
    }

    if (!empty($history)) {
        $lines[] = 'Riwayat chat yang ikut dikirim untuk konteks runtime berjumlah ' . count($history) . ' pesan.';
    }

    return [
        'safe_lines' => $lines,
        'technical_lines' => $canUseTechnicalTools && $userId > 0
            ? ['- Session user_id internal: ' . $userId . '.']
            : [],
    ];
}

function aiAgentToolPageMetadata(array $options = []): array
{
    $pageContext = isset($options['page_context']) && is_array($options['page_context']) ? $options['page_context'] : [];
    $pageSnapshot = aiAgentNormalizeToolPageSnapshot($options['page_snapshot'] ?? []);
    $sharedState = isset($options['shared_state']) && is_array($options['shared_state']) ? $options['shared_state'] : [];
    $pageInspection = isset($sharedState['page_inspection']) && is_array($sharedState['page_inspection']) ? $sharedState['page_inspection'] : [];

    $lines = [
        'Metadata halaman aktif dibangun dari browser dan file PROJECT terkini, bukan dari map statis.',
    ];

    $routeSegments = [];
    if (isset($pageContext['route_segments']) && is_array($pageContext['route_segments'])) {
        foreach ($pageContext['route_segments'] as $segment) {
            $segment = aiAgentCleanText((string) $segment, 40);
            if ($segment !== '') {
                $routeSegments[] = $segment;
            }
        }
    }
    if (!empty($routeSegments)) {
        $lines[] = 'Segmen route aktif: ' . implode(' / ', array_slice($routeSegments, 0, 8)) . '.';
    }

    if (!empty($pageSnapshot['buttons'])) {
        $lines[] = 'Aksi UI yang terlihat: ' . implode(', ', array_slice($pageSnapshot['buttons'], 0, 8)) . '.';
    }

    if (!empty($pageSnapshot['table_headers'])) {
        $lines[] = 'Kolom tabel yang terlihat: ' . implode(', ', array_slice($pageSnapshot['table_headers'], 0, 10)) . '.';
    }

    if (!empty($pageSnapshot['active_filters'])) {
        $lines[] = 'Filter aktif yang sedang terpasang di browser: ' . implode(', ', array_slice($pageSnapshot['active_filters'], 0, 8)) . '.';
    }

    if (!empty($pageSnapshot['table_facts'])) {
        $lines[] = 'Fakta tabel yang sedang terlihat di browser: ' . implode(', ', array_slice($pageSnapshot['table_facts'], 0, 6)) . '.';
    }

    if (!empty($pageSnapshot['forms'])) {
        $lines[] = 'Form yang terdeteksi dari browser: ' . implode(', ', array_slice($pageSnapshot['forms'], 0, 8)) . '.';
    }

    if (!empty($pageInspection['linked_files'])) {
        $frontendLinked = 0;
        $backendLinked = 0;
        foreach ($pageInspection['linked_files'] as $linkedFile) {
            if (strpos($linkedFile, 'api/') === 0) {
                $backendLinked += 1;
            } else {
                $frontendLinked += 1;
            }
        }

        $lines[] = 'Halaman aktif terhubung ke runtime PROJECT terkini dengan ' . $frontendLinked . ' file frontend dan ' . $backendLinked . ' file backend yang relevan.';
    }

    $projectIndexState = isset($sharedState['project_index_state']) && is_array($sharedState['project_index_state'])
        ? $sharedState['project_index_state']
        : [];
    $featureSummary = isset($projectIndexState['feature_manifest']['summary']) && is_array($projectIndexState['feature_manifest']['summary'])
        ? $projectIndexState['feature_manifest']['summary']
        : [];
    if (!empty($featureSummary)) {
        $lines[] = 'Manifest fitur dinamis PROJECT saat ini mencakup '
            . (int) ($featureSummary['total_features'] ?? 0)
            . ' entri fitur, '
            . (int) ($featureSummary['total_modules'] ?? 0)
            . ' modul, dan '
            . (int) ($featureSummary['total_scopes'] ?? 0)
            . ' scope bisnis.';
    }

    $technicalLines = [];
    if (!empty($pageInspection['relative_path'])) {
        $technicalLines[] = '- File halaman aktif: ' . $pageInspection['relative_path'] . '.';
    }
    if (!empty($pageInspection['form_actions'])) {
        $technicalLines[] = '- Form action terdeteksi: ' . implode(', ', array_slice($pageInspection['form_actions'], 0, 8)) . '.';
    }
    if (!empty($pageInspection['script_sources'])) {
        $technicalLines[] = '- Script source terdeteksi: ' . implode(', ', array_slice($pageInspection['script_sources'], 0, 8)) . '.';
    }
    if (!empty($pageInspection['linked_files'])) {
        $technicalLines[] = '- Linked runtime files: ' . implode(', ', array_slice($pageInspection['linked_files'], 0, 12)) . '.';
    }

    return [
        'safe_lines' => $lines,
        'technical_lines' => $technicalLines,
    ];
}

function aiAgentToolProjectIndex(array $options = []): array
{
    $sharedState = isset($options['shared_state']) && is_array($options['shared_state']) ? $options['shared_state'] : [];
    $projectIndexState = isset($sharedState['project_index_state']) && is_array($sharedState['project_index_state'])
        ? $sharedState['project_index_state']
        : [];

    if (empty($projectIndexState['enabled'])) {
        return [
            'safe_lines' => [],
            'technical_lines' => [],
        ];
    }

    $isAvailable = !empty($projectIndexState['available']);
    $summary = isset($projectIndexState['project_index']['summary']) && is_array($projectIndexState['project_index']['summary'])
        ? $projectIndexState['project_index']['summary']
        : [];
    $featureManifest = isset($projectIndexState['feature_manifest']) && is_array($projectIndexState['feature_manifest'])
        ? $projectIndexState['feature_manifest']
        : [];
    $featureSummary = isset($featureManifest['summary']) && is_array($featureManifest['summary'])
        ? $featureManifest['summary']
        : [];
    $relevantEntries = isset($sharedState['relevant_manifest_entries']) && is_array($sharedState['relevant_manifest_entries'])
        ? $sharedState['relevant_manifest_entries']
        : [];

    $lines = [
        'Project index dinamis aktif dengan model hybrid auto rebuild: request chat dapat memicu rebuild manifest saat fingerprint file PROJECT atau schema database berubah, dan watcher signal opsional juga bisa memaksa refresh.',
    ];

    if (!$isAvailable) {
        $lines[] = 'Manifest PROJECT belum tersedia untuk request ini karena ' . aiAgentFormatProjectIndexReason((string) ($projectIndexState['reason'] ?? '')) . '.';
    } elseif ((string) ($projectIndexState['reason'] ?? '') === 'lock_timeout') {
        $lines[] = 'Manifest PROJECT sedang dipakai proses lain, jadi request ini memakai snapshot terakhir yang tersedia sambil menunggu lock rebuild dilepas.';
    } elseif ((string) ($projectIndexState['reason'] ?? '') === 'lock_open_failed') {
        $lines[] = 'Manifest PROJECT saat ini memakai snapshot terakhir yang tersedia karena lock rebuild tidak bisa dibuka.';
    } elseif (!empty($projectIndexState['rebuilt'])) {
        $lines[] = 'Manifest PROJECT untuk request ini direbuild otomatis karena ' . aiAgentFormatProjectIndexReason((string) ($projectIndexState['reason'] ?? '')) . '.';
    } else {
        $lines[] = 'Manifest PROJECT untuk request ini memakai hasil scan terbaru yang masih valid, tanpa rebuild penuh.';
    }

    if (!empty($summary)) {
        $lines[] = 'Cakupan index saat ini: '
            . (int) ($summary['total_files'] ?? 0)
            . ' file groundable, '
            . (int) ($summary['pages'] ?? 0)
            . ' halaman, '
            . (int) ($summary['apis'] ?? 0)
            . ' endpoint API, dan '
            . (int) ($summary['scripts'] ?? 0)
            . ' script frontend.';
        $lines[] = 'Index ini membaca file groundable utama PROJECT seperti .php, .html, .js, .css, .md, dan .sql. Folder vendor, assets vendor/minified, tmp, cache, node_modules, binary, dan gambar tidak dipakai sebagai sumber grounding isi.';
        $lines[] = 'Saat menjawab, runtime tidak membawa seluruh isi PROJECT sekaligus; hanya entri manifest, file linked, dan tabel database yang paling relevan dengan pertanyaan yang dimasukkan ke grounding. Jika bukti exact belum ada di konteks ini, jawaban harus jujur menyebut konteks belum cukup.';
    }

    if (!empty($featureSummary)) {
        $lines[] = 'Feature manifest saat ini merangkum '
            . (int) ($featureSummary['total_features'] ?? 0)
            . ' entri fitur lintas '
            . (int) ($featureSummary['total_modules'] ?? 0)
            . ' modul dan '
            . (int) ($featureSummary['total_scopes'] ?? 0)
            . ' scope bisnis.';
    }

    if (!empty($relevantEntries)) {
        $labels = [];
        foreach (array_slice($relevantEntries, 0, 4) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $label = aiAgentCleanText((string) ($entry['display_name'] ?? ''), 100);
            if ($label === '') {
                continue;
            }

            $signals = [];
            if (!empty($entry['buttons'])) {
                $signals[] = 'aksi ' . implode(', ', array_slice(array_map('strval', $entry['buttons']), 0, 3));
            } elseif (!empty($entry['table_headers'])) {
                $signals[] = 'kolom ' . implode(', ', array_slice(array_map('strval', $entry['table_headers']), 0, 4));
            } elseif (!empty($entry['functions'])) {
                $signals[] = 'fungsi ' . implode(', ', array_slice(array_map('strval', $entry['functions']), 0, 3));
            }

            if (!empty($entry['scopes'])) {
                $signals[] = 'scope ' . implode('/', array_slice(array_map('strval', $entry['scopes']), 0, 2));
            }

            $labels[] = !empty($signals)
                ? $label . ' (' . implode('; ', $signals) . ')'
                : $label;
        }

        if (!empty($labels)) {
            $lines[] = 'Entri manifest yang paling relevan dengan konteks saat ini: ' . implode(', ', $labels) . '.';
        }
    }

    $technicalLines = [];
    $meta = isset($projectIndexState['meta']) && is_array($projectIndexState['meta']) ? $projectIndexState['meta'] : [];
    $paths = isset($projectIndexState['paths']) && is_array($projectIndexState['paths']) ? $projectIndexState['paths'] : [];
    $watcherSignal = isset($projectIndexState['watcher_signal']) && is_array($projectIndexState['watcher_signal'])
        ? $projectIndexState['watcher_signal']
        : [];

    if (!empty($paths['storage_dir'])) {
        $technicalLines[] = '- Storage project index: ' . $paths['storage_dir'] . '.';
    }
    if (!empty($meta['project_fingerprint'])) {
        $technicalLines[] = '- Project fingerprint aktif: ' . $meta['project_fingerprint'] . '.';
    }
    if (!empty($meta['project_fingerprint_mode'])) {
        $technicalLines[] = '- Project fingerprint mode: ' . $meta['project_fingerprint_mode'] . '.';
    }
    if (!empty($meta['schema_fingerprint'])) {
        $technicalLines[] = '- Schema fingerprint aktif: ' . $meta['schema_fingerprint'] . '.';
    }
    if (!empty($watcherSignal['path'])) {
        $technicalLines[] = '- Watcher signal path: ' . $watcherSignal['path'] . ' (exists=' . (!empty($watcherSignal['exists']) ? 'yes' : 'no') . ', mtime=' . (int) ($watcherSignal['mtime'] ?? 0) . ').';
    }
    if (!empty($watcherSignal['payload']['reason'])) {
        $technicalLines[] = '- Watcher signal reason terakhir: ' . aiAgentCleanText((string) ($watcherSignal['payload']['reason'] ?? ''), 120) . '.';
    }
    if (!empty($projectIndexState['lock']['path'])) {
        $technicalLines[] = '- Lock project index: ' . $projectIndexState['lock']['path'] . ' (waited_ms=' . (int) ($projectIndexState['lock']['waited_ms'] ?? 0) . ', error=' . ((string) ($projectIndexState['lock']['error'] ?? '') !== '' ? (string) ($projectIndexState['lock']['error'] ?? '') : 'none') . ').';
    }

    foreach (array_slice($relevantEntries, 0, 5) as $entry) {
        if (!is_array($entry) || empty($entry['path'])) {
            continue;
        }

        $technicalLines[] = '- Manifest entry relevan: ' . $entry['path'] . '.';

        if (!empty($entry['linked_paths'])) {
            $technicalLines[] = '- Linked paths ' . $entry['path'] . ': ' . implode(', ', array_slice(array_map('strval', $entry['linked_paths']), 0, 10)) . '.';
        }
    }

    return [
        'safe_lines' => $lines,
        'technical_lines' => $technicalLines,
    ];
}

function aiAgentToolUiSnapshot(array $options = []): array
{
    $pageSnapshot = aiAgentNormalizeToolPageSnapshot($options['page_snapshot'] ?? []);
    $lines = [];

    $labels = [
        'breadcrumbs' => 'Breadcrumb browser',
        'cards' => 'Card atau section aktif',
        'filters' => 'Filter yang terlihat',
        'active_filters' => 'Nilai filter aktif',
        'links' => 'Link navigasi atau aksi yang terlihat',
        'modals' => 'Modal title yang terlihat',
        'stats' => 'Statistik struktur UI',
        'table_facts' => 'Fakta tabel yang terlihat',
    ];

    foreach ($labels as $key => $label) {
        if (!empty($pageSnapshot[$key])) {
            $lines[] = $label . ': ' . implode(', ', array_slice($pageSnapshot[$key], 0, 8)) . '.';
        }
    }

    return [
        'safe_lines' => $lines,
        'technical_lines' => [],
    ];
}

function aiAgentToolRuntimeObservations(array $options = []): array
{
    $pageContext = isset($options['page_context']) && is_array($options['page_context']) ? $options['page_context'] : [];
    $pageSnapshot = aiAgentNormalizeToolPageSnapshot($options['page_snapshot'] ?? []);
    $sharedState = isset($options['shared_state']) && is_array($options['shared_state']) ? $options['shared_state'] : [];

    $bundle = aiAgentBuildDynamicContextBundle([
        'message' => (string) ($options['message'] ?? ''),
        'page_path' => (string) ($pageContext['path'] ?? ''),
        'page_title' => (string) ($pageContext['title'] ?? ''),
        'page_heading' => (string) ($pageContext['heading'] ?? ''),
        'page_snapshot' => $pageSnapshot,
        'focus_scopes' => $sharedState['keywords'] ?? [],
        'allow_technical_details' => false,
    ]);

    $sharedLines = $bundle['shared_lines'] ?? [];
    $relevantEntries = isset($sharedState['relevant_manifest_entries']) && is_array($sharedState['relevant_manifest_entries'])
        ? $sharedState['relevant_manifest_entries']
        : [];
    if (!empty($relevantEntries)) {
        $sharedLines[] = 'Runtime observations saat ini juga ditopang oleh feature manifest PROJECT yang dibangun dari scan file terbaru.';
    }

    return [
        'safe_lines' => $sharedLines,
        'technical_lines' => [],
    ];
}

function aiAgentToolTechnicalCodeContext(array $options = []): array
{
    $pageContext = isset($options['page_context']) && is_array($options['page_context']) ? $options['page_context'] : [];
    $pageSnapshot = aiAgentNormalizeToolPageSnapshot($options['page_snapshot'] ?? []);
    $sharedState = isset($options['shared_state']) && is_array($options['shared_state']) ? $options['shared_state'] : [];

    $bundle = aiAgentBuildDynamicContextBundle([
        'message' => (string) ($options['message'] ?? ''),
        'page_path' => (string) ($pageContext['path'] ?? ''),
        'page_title' => (string) ($pageContext['title'] ?? ''),
        'page_heading' => (string) ($pageContext['heading'] ?? ''),
        'page_snapshot' => $pageSnapshot,
        'focus_scopes' => $sharedState['keywords'] ?? [],
        'allow_technical_details' => true,
    ]);

    $technicalLines = $bundle['technical_lines'] ?? [];
    $relevantEntries = isset($sharedState['relevant_manifest_entries']) && is_array($sharedState['relevant_manifest_entries'])
        ? $sharedState['relevant_manifest_entries']
        : [];
    foreach (array_slice($relevantEntries, 0, 4) as $entry) {
        if (!is_array($entry) || empty($entry['path'])) {
            continue;
        }

        $technicalLines[] = '- Manifest feature relevan: ' . $entry['path'] . '.';
    }

    return [
        'safe_lines' => [],
        'technical_lines' => array_values(array_unique($technicalLines)),
    ];
}

function aiAgentFormatProjectIndexReason(string $reason): string
{
    $map = [
        'disabled' => 'fitur project index dimatikan',
        'manifest_missing' => 'manifest belum ada atau belum valid',
        'manifest_version_changed' => 'versi struktur manifest berubah',
        'watcher_signal_updated' => 'watcher signal lebih baru dari manifest',
        'project_files_changed' => 'isi file PROJECT berubah sejak build terakhir',
        'schema_changed' => 'struktur schema database berubah sejak build terakhir',
        'manifest_stale' => 'umur manifest melewati batas refresh',
        'manual_rebuild' => 'dipicu manual di luar request chat',
        'manual_signal' => 'watcher signal disentuh manual',
        'deploy_update' => 'dipicu oleh deploy atau update eksternal',
        'endpoint_deploy' => 'dipicu oleh endpoint deploy atau update eksternal',
        'watcher_native_change' => 'watcher native mendeteksi perubahan file',
        'lock_timeout' => 'proses lain masih memegang lock rebuild',
        'lock_open_failed' => 'file lock rebuild tidak bisa dibuka',
        'lock_file_missing' => 'lokasi file lock belum tersedia',
        'up_to_date' => 'manifest masih sinkron',
    ];

    return $map[$reason] ?? ($reason !== '' ? $reason : 'alasan tidak diketahui');
}

function aiAgentToolLiveSchema(mysqli $conn, array $options = []): array
{
    $role = trim((string) ($options['role'] ?? 'user'));
    $sharedState = isset($options['shared_state']) && is_array($options['shared_state']) ? $options['shared_state'] : [];
    $schemaIndex = isset($sharedState['schema_index']) && is_array($sharedState['schema_index']) ? $sharedState['schema_index'] : [];
    $relevantTables = isset($sharedState['relevant_tables']) && is_array($sharedState['relevant_tables']) ? $sharedState['relevant_tables'] : [];
    $toolConfig = isset($options['tool_config']) && is_array($options['tool_config']) ? $options['tool_config'] : aiAgentGetToolLayerConfig();
    $roleGuard = isset($options['role_guard']) && is_array($options['role_guard']) ? $options['role_guard'] : [];
    $accessDecision = isset($roleGuard['access_decision']) && is_array($roleGuard['access_decision']) ? $roleGuard['access_decision'] : [];
    $dbName = aiAgentGetDatabaseName($conn);
    $accessibleTables = aiAgentSelectAccessibleRelevantTables($schemaIndex, $relevantTables, $role, $accessDecision, $toolConfig, 4);

    $lines = [
        'Schema live database ' . $dbName . ' dibaca langsung dari information_schema saat request ini berjalan.',
        'Jumlah tabel yang terdeteksi saat ini: ' . count($schemaIndex) . '.',
    ];

    if (!empty($accessDecision['requires_scope_override']) && empty($accessDecision['has_scope_override_access'])) {
        $lines[] = 'Snapshot schema untuk jawaban ini tetap dibatasi ke area data yang sesuai role aktif.';
    }

    foreach ($accessibleTables as $tableName) {
        if (!isset($schemaIndex[$tableName])) {
            continue;
        }

        $columns = aiAgentSelectRepresentativeColumns($schemaIndex[$tableName], 5);
        if (!empty($columns)) {
            $lines[] = $tableName . ' -> kolom representatif: ' . implode(', ', $columns) . '.';
        }
    }

    $technicalLines = [];
    foreach ($accessibleTables as $tableName) {
        if (!isset($schemaIndex[$tableName])) {
            continue;
        }
        $columns = $schemaIndex[$tableName]['columns'] ?? [];
        if (!empty($columns)) {
            $technicalLines[] = '- Detail schema ' . $tableName . ': ' . implode(', ', array_slice($columns, 0, 14)) . '.';
        }
    }

    return [
        'safe_lines' => $lines,
        'technical_lines' => $technicalLines,
    ];
}

function aiAgentToolLiveData(mysqli $conn, array $options = []): array
{
    $role = trim((string) ($options['role'] ?? 'user'));
    $userId = (int) ($options['user_id'] ?? 0);
    $message = aiAgentCleanText((string) ($options['message'] ?? ''), 1600);
    $pageContext = isset($options['page_context']) && is_array($options['page_context']) ? $options['page_context'] : [];
    $pageSnapshot = aiAgentNormalizeToolPageSnapshot($options['page_snapshot'] ?? []);
    $sharedState = isset($options['shared_state']) && is_array($options['shared_state']) ? $options['shared_state'] : [];
    $schemaIndex = isset($sharedState['schema_index']) && is_array($sharedState['schema_index']) ? $sharedState['schema_index'] : [];
    $relevantTables = isset($sharedState['relevant_tables']) && is_array($sharedState['relevant_tables']) ? $sharedState['relevant_tables'] : [];
    $toolConfig = isset($options['tool_config']) && is_array($options['tool_config']) ? $options['tool_config'] : aiAgentGetToolLayerConfig();
    $roleGuard = isset($options['role_guard']) && is_array($options['role_guard']) ? $options['role_guard'] : [];
    $accessDecision = isset($roleGuard['access_decision']) && is_array($roleGuard['access_decision']) ? $roleGuard['access_decision'] : [];
    $accessibleTables = aiAgentSelectAccessibleRelevantTables($schemaIndex, $relevantTables, $role, $accessDecision, $toolConfig, 4);

    $lines = [
        'Data live untuk konteks ini dibaca ulang dari database saat request chat berjalan.',
    ];

    if (!empty($accessDecision['has_scope_override_access'])) {
        $lines[] = 'Password override aktif, sehingga data bisnis lintas scope role boleh dipakai untuk jawaban ini.';
    } elseif (!empty($accessDecision['requires_scope_override'])) {
        $lines[] = 'Permintaan menyentuh data di luar scope role, jadi snapshot live yang dipakai tetap dibatasi sesuai role aktif.';
    } else {
        $lines[] = 'Snapshot live ini hanya memakai data yang berada dalam scope role aktif, tanpa perlu password.';
    }

    $userRoleBundle = aiAgentBuildUserRoleLiveDetailBundle($conn, [
        'message' => $message,
        'role' => $role,
        'page_context' => $pageContext,
        'page_snapshot' => $pageSnapshot,
        'access_decision' => $accessDecision,
        'schema_index' => $schemaIndex,
    ]);
    foreach (($userRoleBundle['lines'] ?? []) as $detailLine) {
        $detailLine = trim((string) $detailLine);
        if ($detailLine !== '') {
            $lines[] = $detailLine;
        }
    }

    $handledTables = isset($userRoleBundle['handled_tables']) && is_array($userRoleBundle['handled_tables'])
        ? array_values(array_unique(array_map('strval', $userRoleBundle['handled_tables'])))
        : [];
    $hasSpecialLiveDetail = !empty($userRoleBundle['lines']);
    $renderedTableCount = 0;
    foreach ($accessibleTables as $tableName) {
        if (in_array($tableName, $handledTables, true)) {
            continue;
        }

        if (!isset($schemaIndex[$tableName])) {
            continue;
        }

        $tableMeta = $schemaIndex[$tableName];
        $tableScopes = aiAgentInferTableScopes($tableName, $tableMeta, $toolConfig);
        $scopeFilter = aiAgentBuildLiveDataScopeFilter($tableName, $tableMeta, $role, $userId, $accessDecision, $tableScopes);
        if (empty($scopeFilter['allowed'])) {
            continue;
        }

        $summaryParts = [];
        $totalRows = aiAgentQueryScopedTableTotal($conn, $tableName, $scopeFilter);
        if ($totalRows !== null) {
            $summaryParts[] = 'total=' . $totalRows;
        }

        if (aiAgentSchemaTableHasColumn($tableMeta, 'status')) {
            $statusCounts = aiAgentQueryScopedStatusCounts($conn, $tableName, $scopeFilter, (int) $toolConfig['max_status_values']);
            if (!empty($statusCounts)) {
                $summaryParts[] = 'status ' . aiAgentFormatCountMap($statusCounts);
            }
        }

        if ($userId > 0 && $role === 'user' && aiAgentSchemaTableHasColumn($tableMeta, 'user_id') && empty($accessDecision['has_scope_override_access'])) {
            $userScopedTotal = aiAgentQueryUserScopedTotal($conn, $tableName, $userId);
            if ($userScopedTotal !== null) {
                $summaryParts[] = 'user aktif=' . $userScopedTotal;
            }
        }

        if (aiAgentSchemaTableHasColumn($tableMeta, 'stok_tersedia')
            && aiAgentSchemaTableHasColumn($tableMeta, 'safety_stock')) {
            $inventorySignals = aiAgentQueryInventorySignals($conn, $tableName, $tableMeta, $scopeFilter);
            if ($inventorySignals['low_stock_total'] !== null) {
                $summaryParts[] = 'low_stock=' . $inventorySignals['low_stock_total'];
            }
            if (!empty($inventorySignals['rows'])) {
                $summaryParts[] = 'contoh alert ' . aiAgentFormatInventorySignalRows($inventorySignals['rows']);
            }
        }

        $recentRows = aiAgentQueryRecentStatusRows($conn, $tableName, $tableMeta, $scopeFilter, 3);
        if (!empty($recentRows)) {
            $summaryParts[] = 'snapshot terbaru ' . aiAgentFormatRecentStatusRows($recentRows);
        }

        if (!empty($summaryParts)) {
            $lines[] = $tableName . ' => ' . implode('; ', $summaryParts) . '.';
            $renderedTableCount += 1;
        }
    }

    if ($renderedTableCount === 0 && !$hasSpecialLiveDetail) {
        $lines[] = 'Belum ada snapshot live yang cocok dengan scope role dan konteks pertanyaan saat ini.';
    }

    $borrowingDetailLines = aiAgentBuildBorrowingLiveDetailLines($conn, [
        'message' => $message,
        'role' => $role,
        'user_id' => $userId,
        'access_decision' => $accessDecision,
    ]);
    foreach ($borrowingDetailLines as $detailLine) {
        $lines[] = $detailLine;
    }

    return [
        'safe_lines' => $lines,
        'technical_lines' => [],
    ];
}

function aiAgentBuildUserRoleLiveDetailBundle(mysqli $conn, array $options = []): array
{
    $role = trim((string) ($options['role'] ?? 'user'));
    $message = strtolower(aiAgentCleanText((string) ($options['message'] ?? ''), 1600));
    $pageContext = isset($options['page_context']) && is_array($options['page_context']) ? $options['page_context'] : [];
    $pageSnapshot = isset($options['page_snapshot']) && is_array($options['page_snapshot'])
        ? $options['page_snapshot']
        : aiAgentNormalizeToolPageSnapshot($options['page_snapshot'] ?? []);
    $schemaIndex = isset($options['schema_index']) && is_array($options['schema_index']) ? $options['schema_index'] : [];

    $bundle = [
        'lines' => [],
        'handled_tables' => [],
    ];

    if ($role !== 'admin' || !isset($schemaIndex['users'])) {
        return $bundle;
    }

    if (!aiAgentPageContextFocusesUserRoleData($message, $pageContext, $pageSnapshot)) {
        return $bundle;
    }

    $roleCounts = aiAgentFetchLabelTotals($conn, 'SELECT role AS label, COUNT(*) AS total FROM users GROUP BY role');
    if (empty($roleCounts)) {
        return $bundle;
    }

    $bundle['handled_tables'][] = 'users';
    $bundle['lines'][] = 'Distribusi akun live saat ini per role dari tabel users: ' . aiAgentFormatCountMap($roleCounts) . '.';
    $bundle['lines'][] = 'Role yang benar-benar aktif di data live users saat ini hanya: ' . implode(', ', array_keys($roleCounts)) . '. Jangan tambahkan label role lain di jawaban jika tidak muncul di data live ini.';

    $requestedRoles = aiAgentExtractRequestedLiveRoles($message, $pageSnapshot);
    foreach ($requestedRoles as $requestedRole) {
        $total = aiAgentQueryLiveUserCountByRole($conn, $requestedRole);
        $bundle['lines'][] = 'Jumlah akun live dengan role ' . $requestedRole . ' saat ini: ' . $total . '.';
        if (!array_key_exists($requestedRole, $roleCounts)) {
            $bundle['lines'][] = 'Label role ' . $requestedRole . ' tidak muncul sebagai role aktif pada data live users saat ini.';
        }
    }

    $activeRoleFilter = aiAgentExtractActiveRoleFilterFromSnapshot($pageSnapshot);
    if ($activeRoleFilter !== '') {
        $bundle['lines'][] = 'Filter role aktif di halaman browser saat ini: ' . $activeRoleFilter . '.';
        $bundle['lines'][] = 'Jumlah akun live yang cocok dengan filter role ' . $activeRoleFilter . ' saat ini: ' . aiAgentQueryLiveUserCountByRole($conn, $activeRoleFilter) . '.';
    }

    return $bundle;
}

function aiAgentPageContextFocusesUserRoleData(string $message, array $pageContext = [], array $pageSnapshot = []): bool
{
    $pagePath = strtolower(aiAgentNormalizeProjectRelativePath((string) ($pageContext['path'] ?? '')));
    $pageTitle = strtolower(aiAgentCleanText((string) ($pageContext['title'] ?? ''), 160));
    $pageHeading = strtolower(aiAgentCleanText((string) ($pageContext['heading'] ?? ''), 160));
    $snapshotBlob = strtolower(implode(' ', array_merge(
        $pageSnapshot['breadcrumbs'] ?? [],
        $pageSnapshot['cards'] ?? [],
        $pageSnapshot['table_headers'] ?? [],
        $pageSnapshot['filters'] ?? [],
        $pageSnapshot['active_filters'] ?? [],
        $pageSnapshot['table_facts'] ?? [],
        $pageSnapshot['links'] ?? []
    )));

    foreach ([
        'admin/user/',
        'admin/pengaturan',
        'user list',
        'role list',
        'administrator',
        'akun',
        'role ',
        'pic_barang',
    ] as $needle) {
        if (($needle !== '' && strpos($pagePath, $needle) !== false)
            || ($needle !== '' && strpos($pageTitle, $needle) !== false)
            || ($needle !== '' && strpos($pageHeading, $needle) !== false)
            || ($needle !== '' && strpos($snapshotBlob, $needle) !== false)
            || ($needle !== '' && strpos($message, trim($needle)) !== false)) {
            return true;
        }
    }

    return false;
}

function aiAgentExtractRequestedLiveRoles(string $message, array $pageSnapshot = []): array
{
    $requestedRoles = [];
    $sources = [$message];

    $activeRoleFilter = aiAgentExtractActiveRoleFilterFromSnapshot($pageSnapshot);
    if ($activeRoleFilter !== '') {
        $requestedRoles[] = $activeRoleFilter;
    }

    $sources = array_merge($sources, $pageSnapshot['active_filters'] ?? []);
    foreach ($sources as $source) {
        $source = strtolower(aiAgentCleanText((string) $source, 200));
        if ($source === '') {
            continue;
        }

        foreach (['admin', 'manager', 'user', 'pic_barang', 'pic barang'] as $candidate) {
            if (strpos($source, strtolower($candidate)) !== false) {
                $normalized = aiAgentNormalizeLiveRoleValue($candidate);
                if ($normalized !== '') {
                    $requestedRoles[] = $normalized;
                }
            }
        }
    }

    return array_values(array_unique(array_filter($requestedRoles)));
}

function aiAgentExtractActiveRoleFilterFromSnapshot(array $pageSnapshot = []): string
{
    foreach (($pageSnapshot['active_filters'] ?? []) as $filterLine) {
        $parts = explode('=', (string) $filterLine, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = strtolower(preg_replace('/[^a-z0-9]+/', '', (string) ($parts[0] ?? '')));
        $value = aiAgentNormalizeLiveRoleValue((string) ($parts[1] ?? ''));
        if ($value === '') {
            continue;
        }

        if (strpos($key, 'role') !== false) {
            return $value;
        }
    }

    return '';
}

function aiAgentNormalizeLiveRoleValue(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    $normalized = preg_replace('/[^a-z0-9_ ]+/', ' ', $value);
    $normalized = preg_replace('/\s+/', ' ', (string) $normalized);
    $normalized = trim((string) $normalized);

    $map = [
        'all' => '',
        'all roles' => '',
        'semua' => '',
        'semua role' => '',
        'user biasa' => 'user',
        'requester' => 'user',
        'approver' => 'manager',
        'pic barang' => 'pic_barang',
        'pic' => 'pic_barang',
    ];

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    return str_replace(' ', '_', $normalized);
}

function aiAgentQueryLiveUserCountByRole(mysqli $conn, string $role): int
{
    $role = aiAgentNormalizeLiveRoleValue($role);
    if ($role === '') {
        $row = aiAgentFetchSingleRow($conn, 'SELECT COUNT(*) AS total_rows FROM users');
        return (int) ($row['total_rows'] ?? 0);
    }

    $row = aiAgentFetchSingleRow(
        $conn,
        'SELECT COUNT(*) AS total_rows FROM users WHERE role = ?',
        's',
        [$role]
    );

    return (int) ($row['total_rows'] ?? 0);
}

function aiAgentBuildBorrowingLiveDetailLines(mysqli $conn, array $options = []): array
{
    $message = strtolower(trim((string) ($options['message'] ?? '')));
    $role = trim((string) ($options['role'] ?? 'user'));
    $userId = (int) ($options['user_id'] ?? 0);
    $accessDecision = isset($options['access_decision']) && is_array($options['access_decision']) ? $options['access_decision'] : [];

    if ($message === '') {
        return [];
    }

    $wantsBorrowingDetail = false;
    foreach ([
        'overdue',
        'due today',
        'due in',
        'jatuh tempo',
        'terlambat',
        'nama peminjam',
        'peminjam',
        'siapa',
        'borrower',
        'transaksi',
        'pinjaman',
        'peminjaman',
    ] as $keyword) {
        if (strpos($message, $keyword) !== false) {
            $wantsBorrowingDetail = true;
            break;
        }
    }

    if (!$wantsBorrowingDetail) {
        return [];
    }

    $statusFilters = aiAgentResolveBorrowingStatusFiltersFromMessage($message);
    $rows = aiAgentQueryBorrowingLiveRows($conn, [
        'role' => $role,
        'user_id' => $userId,
        'access_decision' => $accessDecision,
        'status_filters' => $statusFilters,
        'limit' => 6,
    ]);

    if (empty($rows)) {
        if (!empty($statusFilters)) {
            return ['Tidak ada transaksi peminjaman live yang cocok dengan status yang diminta pada scope akses saat ini.'];
        }

        return [];
    }

    $lines = [];
    if (!empty($statusFilters)) {
        $lines[] = 'Detail transaksi peminjaman live untuk status ' . implode(', ', $statusFilters) . ': ' . aiAgentFormatBorrowingDetailRows($rows) . '.';
    } else {
        $lines[] = 'Contoh transaksi peminjaman live yang paling relevan dengan pertanyaan ini: ' . aiAgentFormatBorrowingDetailRows($rows) . '.';
    }

    return $lines;
}

function aiAgentResolveBorrowingStatusFiltersFromMessage(string $message): array
{
    $message = strtolower(trim($message));
    if ($message === '') {
        return [];
    }

    $filters = [];
    $map = [
        'Overdue' => ['overdue', 'terlambat'],
        'Due Today' => ['due today', 'jatuh tempo hari ini'],
        'Waiting for Approval' => ['waiting for approval', 'menunggu approval', 'menunggu persetujuan'],
        'Rejected' => ['rejected', 'ditolak'],
        'Returned' => ['returned', 'dikembalikan'],
        'Partial Approved' => ['partial approved', 'parsial disetujui', 'partial approval'],
        'Return in Process' => ['return in process', 'pengembalian diproses', 'sedang diperiksa'],
        'Partially Returned' => ['partially returned', 'sebagian dikembalikan'],
    ];

    foreach ($map as $status => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $filters[] = $status;
                break;
            }
        }
    }

    if (strpos($message, 'due in') !== false || strpos($message, 'jatuh tempo') !== false) {
        $filters[] = '__DUE_DYNAMIC__';
    }

    return array_values(array_unique($filters));
}

function aiAgentQueryBorrowingLiveRows(mysqli $conn, array $options = []): array
{
    $role = trim((string) ($options['role'] ?? 'user'));
    $userId = (int) ($options['user_id'] ?? 0);
    $accessDecision = isset($options['access_decision']) && is_array($options['access_decision']) ? $options['access_decision'] : [];
    $statusFilters = isset($options['status_filters']) && is_array($options['status_filters']) ? $options['status_filters'] : [];
    $limit = max(1, (int) ($options['limit'] ?? 6));

    $whereSql = '';
    $bindTypes = '';
    $params = [];

    if ($role === 'user' && empty($accessDecision['has_scope_override_access']) && $userId > 0) {
        $whereSql = 'WHERE p.user_id = ?';
        $bindTypes = 'i';
        $params[] = $userId;
    }

    $rows = aiAgentFetchRows(
        $conn,
        '
            SELECT
                p.id,
                p.kode_peminjaman,
                p.user_id,
                p.nama_peminjam,
                p.nrp,
                p.tanggal_pinjam,
                p.rencana_kembali,
                p.status
            FROM peminjaman p
            ' . $whereSql . '
            ORDER BY p.tanggal_pinjam DESC, p.id DESC
            LIMIT 40
        ',
        $bindTypes,
        $params
    );

    $selected = [];
    foreach ($rows as $row) {
        $currentStatus = trim((string) ($row['status'] ?? ''));
        $computedStatus = $currentStatus;
        if (!in_array($currentStatus, ['Rejected', 'Waiting for Approval'], true)
            && function_exists('computeStatusFromUnits')) {
            $computedStatus = (string) computeStatusFromUnits($conn, (int) ($row['id'] ?? 0), $currentStatus);
        } elseif (function_exists('computeDueStatus')) {
            $computedStatus = (string) computeDueStatus($currentStatus, (string) ($row['rencana_kembali'] ?? ''));
        }

        if (!aiAgentBorrowingRowMatchesStatusFilters($computedStatus, $statusFilters)) {
            continue;
        }

        $row['computed_status'] = $computedStatus;
        $selected[] = $row;

        if (count($selected) >= $limit) {
            break;
        }
    }

    return $selected;
}

function aiAgentBorrowingRowMatchesStatusFilters(string $computedStatus, array $statusFilters = []): bool
{
    if (empty($statusFilters)) {
        return true;
    }

    foreach ($statusFilters as $filter) {
        if ($filter === '__DUE_DYNAMIC__' && strpos($computedStatus, 'Due In ') === 0) {
            return true;
        }

        if ($computedStatus === $filter) {
            return true;
        }
    }

    return false;
}

function aiAgentFormatBorrowingDetailRows(array $rows): string
{
    $parts = [];
    foreach ($rows as $row) {
        $code = trim((string) ($row['kode_peminjaman'] ?? ''));
        $name = trim((string) ($row['nama_peminjam'] ?? ''));
        $nrp = trim((string) ($row['nrp'] ?? ''));
        $status = trim((string) ($row['computed_status'] ?? $row['status'] ?? ''));
        $dueDate = aiAgentFormatRuntimeDateLabel((string) ($row['rencana_kembali'] ?? ''));

        if ($code === '' || $status === '') {
            continue;
        }

        $segment = $code;
        if ($name !== '') {
            $segment .= ' - ' . $name;
        }
        if ($nrp !== '' && $nrp !== '-') {
            $segment .= ' (NRP ' . $nrp . ')';
        }
        $segment .= ' [' . $status . ']';
        if ($dueDate !== '') {
            $segment .= ' jatuh tempo ' . $dueDate;
        }

        $parts[] = $segment;
    }

    return !empty($parts) ? implode(', ', $parts) : '';
}

function aiAgentInferTableScopes(string $tableName, array $tableMeta, array $toolConfig = []): array
{
    $haystack = strtolower($tableName . ' ' . implode(' ', $tableMeta['columns'] ?? []));
    if ($haystack === '') {
        return [];
    }

    $scopes = [];
    foreach (($toolConfig['scope_keywords'] ?? []) as $scope => $keywords) {
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && strpos($haystack, strtolower($keyword)) !== false) {
                $scopes[] = strtolower((string) $scope);
                break;
            }
        }
    }

    return aiAgentNormalizeToolScopes($scopes);
}

function aiAgentSelectAccessibleRelevantTables(
    array $schemaIndex,
    array $relevantTables,
    string $role,
    array $accessDecision = [],
    array $toolConfig = [],
    int $limit = 4
): array {
    $selected = [];

    foreach ($relevantTables as $tableName) {
        if (!isset($schemaIndex[$tableName])) {
            continue;
        }

        if (aiAgentCanReadTableLiveData($tableName, $schemaIndex[$tableName], $role, $accessDecision, $toolConfig)) {
            $selected[] = $tableName;
        }
    }

    if (empty($selected)) {
        foreach (array_keys($schemaIndex) as $tableName) {
            if (aiAgentCanReadTableLiveData($tableName, $schemaIndex[$tableName], $role, $accessDecision, $toolConfig)) {
                $selected[] = $tableName;
            }
        }
    }

    return array_slice(array_values(array_unique($selected)), 0, max(1, $limit));
}

function aiAgentCanReadTableLiveData(
    string $tableName,
    array $tableMeta,
    string $role,
    array $accessDecision = [],
    array $toolConfig = []
): bool {
    $tableScopes = aiAgentInferTableScopes($tableName, $tableMeta, $toolConfig);
    $focusScopes = aiAgentNormalizeToolScopes(
        !empty($accessDecision['requested_scopes'])
            ? ($accessDecision['has_scope_override_access'] ? ($accessDecision['effective_scopes'] ?? []) : ($accessDecision['requested_scopes'] ?? []))
            : ($accessDecision['effective_scopes'] ?? $accessDecision['allowed_scopes'] ?? [])
    );

    if (!empty($tableScopes) && !empty($focusScopes) && empty(array_intersect($tableScopes, $focusScopes))) {
        return false;
    }

    if ($role === 'user' && empty($accessDecision['has_scope_override_access'])) {
        if (aiAgentTableIsPublicInventoryReference($tableName, $tableMeta, $tableScopes)) {
            return true;
        }

        return aiAgentSchemaTableHasColumn($tableMeta, 'user_id')
            && !empty(array_intersect($tableScopes, ['borrowing', 'returns', 'extensions']));
    }

    if ($role === 'manager' && empty($accessDecision['has_scope_override_access']) && !empty(array_intersect($tableScopes, ['users_admin']))) {
        return false;
    }

    if ($role === 'pic_barang' && empty($accessDecision['has_scope_override_access']) && !empty(array_intersect($tableScopes, ['users_admin', 'reports']))) {
        return false;
    }

    if (empty($tableScopes)) {
        return $role === 'admin';
    }

    return true;
}

function aiAgentBuildLiveDataScopeFilter(
    string $tableName,
    array $tableMeta,
    string $role,
    int $userId,
    array $accessDecision = [],
    array $tableScopes = []
): array {
    $filter = [
        'allowed' => true,
        'where_sql' => '',
        'bind_types' => '',
        'params' => [],
        'label' => 'scope role aktif',
    ];

    if (!empty($accessDecision['has_scope_override_access'])) {
        $filter['label'] = 'scope lintas-role sementara';
        return $filter;
    }

    if ($role === 'user') {
        if (aiAgentTableIsPublicInventoryReference($tableName, $tableMeta, $tableScopes)) {
            $filter['label'] = 'scope inventaris umum';
            return $filter;
        }

        if ($userId > 0
            && aiAgentSchemaTableHasColumn($tableMeta, 'user_id')
            && !empty(array_intersect($tableScopes, ['borrowing', 'returns', 'extensions']))) {
            $filter['where_sql'] = ' WHERE `user_id` = ?';
            $filter['bind_types'] = 'i';
            $filter['params'] = [$userId];
            $filter['label'] = 'scope user aktif';
            return $filter;
        }

        $filter['allowed'] = false;
        return $filter;
    }

    if ($role === 'manager' && !empty(array_intersect($tableScopes, ['users_admin']))) {
        $filter['allowed'] = false;
        return $filter;
    }

    if ($role === 'pic_barang' && !empty(array_intersect($tableScopes, ['users_admin', 'reports']))) {
        $filter['allowed'] = false;
        return $filter;
    }

    return $filter;
}

function aiAgentTableIsPublicInventoryReference(string $tableName, array $tableMeta, array $tableScopes = []): bool
{
    $tableName = strtolower(trim($tableName));
    if ($tableName === 'vendor') {
        return true;
    }

    if (empty(array_intersect($tableScopes, ['inventory', 'vendors']))) {
        return false;
    }

    if (aiAgentSchemaTableHasColumn($tableMeta, 'stok_tersedia') || aiAgentSchemaTableHasColumn($tableMeta, 'safety_stock')) {
        return true;
    }

    return $tableName === 'barang' || $tableName === 'pembelian_barang' || $tableName === 'riwayat_pembelian';
}

function aiAgentQueryScopedTableTotal(mysqli $conn, string $tableName, array $scopeFilter = []): ?int
{
    $tableIdentifier = aiAgentQuoteIdentifier($tableName);
    if ($tableIdentifier === '' || empty($scopeFilter['allowed'])) {
        return null;
    }

    $row = aiAgentFetchSingleRow(
        $conn,
        'SELECT COUNT(*) AS total_rows FROM ' . $tableIdentifier . ($scopeFilter['where_sql'] ?? ''),
        (string) ($scopeFilter['bind_types'] ?? ''),
        $scopeFilter['params'] ?? []
    );
    if (empty($row)) {
        return null;
    }

    return (int) ($row['total_rows'] ?? 0);
}

function aiAgentQueryScopedStatusCounts(mysqli $conn, string $tableName, array $scopeFilter = [], int $limit = 6): array
{
    $tableIdentifier = aiAgentQuoteIdentifier($tableName);
    if ($tableIdentifier === '' || empty($scopeFilter['allowed'])) {
        return [];
    }

    $whereSql = trim((string) ($scopeFilter['where_sql'] ?? ''));
    $statusClause = $whereSql === '' ? ' WHERE ' : $whereSql . ' AND ';

    $rows = aiAgentFetchRows(
        $conn,
        'SELECT `status` AS label, COUNT(*) AS total
         FROM ' . $tableIdentifier . $statusClause . '`status` IS NOT NULL AND `status` <> ""
         GROUP BY `status`
         ORDER BY total DESC, label ASC
         LIMIT ' . max(1, $limit),
        (string) ($scopeFilter['bind_types'] ?? ''),
        $scopeFilter['params'] ?? []
    );

    $counts = [];
    foreach ($rows as $row) {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label !== '') {
            $counts[$label] = (int) ($row['total'] ?? 0);
        }
    }

    return $counts;
}

function aiAgentQueryRecentStatusRows(
    mysqli $conn,
    string $tableName,
    array $tableMeta,
    array $scopeFilter = [],
    int $limit = 3
): array {
    $tableIdentifier = aiAgentQuoteIdentifier($tableName);
    if ($tableIdentifier === '' || empty($scopeFilter['allowed'])) {
        return [];
    }

    $codeColumn = aiAgentFindFirstExistingColumn($tableMeta, [
        'kode_peminjaman',
        'kode_pengembalian',
        'kode_barang',
        'nama_barang',
        'nama',
        'nrp',
        'role_name',
        'nama_vendor',
        'id',
    ]);
    $stateColumn = aiAgentFindFirstExistingColumn($tableMeta, [
        'status',
        'return_status',
        'approval_status',
        'kondisi',
    ]);
    $dateColumn = aiAgentFindFirstExistingColumn($tableMeta, [
        'updated_at',
        'approval_time',
        'approved_at',
        'selesai_at',
        'dicek_at',
        'diajukan_at',
        'created_at',
        'tanggal_kembali',
        'tanggal_perpanjang',
        'tanggal_pinjam',
        'tanggal_pembelian',
        'tanggal',
    ]);

    if ($codeColumn === '' || $stateColumn === '' || $dateColumn === '') {
        return [];
    }

    $idColumn = aiAgentFindFirstExistingColumn($tableMeta, ['id']);
    $orderSql = ' ORDER BY ' . aiAgentQuoteIdentifier($dateColumn) . ' DESC';
    if ($idColumn !== '') {
        $orderSql .= ', ' . aiAgentQuoteIdentifier($idColumn) . ' DESC';
    }

    $rows = aiAgentFetchRows(
        $conn,
        'SELECT '
        . aiAgentQuoteIdentifier($codeColumn) . ' AS code, '
        . aiAgentQuoteIdentifier($stateColumn) . ' AS state, '
        . aiAgentQuoteIdentifier($dateColumn) . ' AS event_date
        FROM ' . $tableIdentifier
        . ($scopeFilter['where_sql'] ?? '')
        . $orderSql
        . ' LIMIT ' . max(1, $limit),
        (string) ($scopeFilter['bind_types'] ?? ''),
        $scopeFilter['params'] ?? []
    );

    return is_array($rows) ? $rows : [];
}

function aiAgentQueryInventorySignals(
    mysqli $conn,
    string $tableName,
    array $tableMeta,
    array $scopeFilter = []
): array {
    $result = [
        'low_stock_total' => null,
        'rows' => [],
    ];

    $tableIdentifier = aiAgentQuoteIdentifier($tableName);
    if ($tableIdentifier === '' || empty($scopeFilter['allowed'])) {
        return $result;
    }

    $whereSql = trim((string) ($scopeFilter['where_sql'] ?? ''));
    $lowStockClause = $whereSql === '' ? ' WHERE ' : $whereSql . ' AND ';
    $labelColumn = aiAgentFindFirstExistingColumn($tableMeta, ['nama_barang', 'kode_barang', 'id']);
    if ($labelColumn === '') {
        return $result;
    }

    $countRow = aiAgentFetchSingleRow(
        $conn,
        'SELECT COUNT(*) AS low_stock_items FROM ' . $tableIdentifier
        . $lowStockClause
        . aiAgentQuoteIdentifier('stok_tersedia') . ' <= ' . aiAgentQuoteIdentifier('safety_stock'),
        (string) ($scopeFilter['bind_types'] ?? ''),
        $scopeFilter['params'] ?? []
    );
    if (!empty($countRow)) {
        $result['low_stock_total'] = (int) ($countRow['low_stock_items'] ?? 0);
    }

    $rows = aiAgentFetchRows(
        $conn,
        'SELECT '
        . aiAgentQuoteIdentifier($labelColumn) . ' AS label, '
        . aiAgentQuoteIdentifier('stok_tersedia') . ' AS stok_tersedia, '
        . aiAgentQuoteIdentifier('safety_stock') . ' AS safety_stock, '
        . aiAgentQuoteIdentifier('kondisi') . ' AS kondisi
        FROM ' . $tableIdentifier
        . $lowStockClause
        . aiAgentQuoteIdentifier('stok_tersedia') . ' <= ' . aiAgentQuoteIdentifier('safety_stock')
        . ' ORDER BY ' . aiAgentQuoteIdentifier('stok_tersedia') . ' ASC, ' . aiAgentQuoteIdentifier('safety_stock') . ' DESC, ' . aiAgentQuoteIdentifier($labelColumn) . ' ASC
        LIMIT 3',
        (string) ($scopeFilter['bind_types'] ?? ''),
        $scopeFilter['params'] ?? []
    );
    if (is_array($rows)) {
        $result['rows'] = $rows;
    }

    return $result;
}

function aiAgentFindFirstExistingColumn(array $tableMeta, array $candidates): string
{
    foreach ($candidates as $candidate) {
        if (aiAgentSchemaTableHasColumn($tableMeta, (string) $candidate)) {
            return (string) $candidate;
        }
    }

    return '';
}

function aiAgentFormatRecentStatusRows(array $rows): string
{
    $parts = [];
    foreach ($rows as $row) {
        $code = trim((string) ($row['code'] ?? ''));
        $state = trim((string) ($row['state'] ?? ''));
        $eventDate = aiAgentFormatRuntimeDateLabel((string) ($row['event_date'] ?? ''));
        if ($code === '' || $state === '') {
            continue;
        }
        $parts[] = $code . ' [' . $state . ']' . ($eventDate !== '' ? ' @ ' . $eventDate : '');
    }

    return !empty($parts) ? implode(', ', $parts) : '';
}

function aiAgentFormatInventorySignalRows(array $rows): string
{
    $parts = [];
    foreach ($rows as $row) {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') {
            continue;
        }

        $stokTersedia = (int) ($row['stok_tersedia'] ?? 0);
        $safetyStock = (int) ($row['safety_stock'] ?? 0);
        $kondisi = trim((string) ($row['kondisi'] ?? ''));
        $segment = $label . ' (stok ' . $stokTersedia . ', safety ' . $safetyStock;
        if ($kondisi !== '') {
            $segment .= ', kondisi ' . $kondisi;
        }
        $segment .= ')';
        $parts[] = $segment;
    }

    return !empty($parts) ? implode(', ', $parts) : '';
}

function aiAgentFormatRuntimeDateLabel(string $value): string
{
    $value = trim($value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d M Y H:i', $timestamp);
}

function aiAgentAssembleToolGrounding(array $runtime = []): string
{
    $sections = isset($runtime['sections']) && is_array($runtime['sections']) ? $runtime['sections'] : [];

    $lines = ['[TOOL_LAYER_CONTEXT]'];

    foreach ($sections as $toolName => $section) {
        aiAgentAppendNamedSection($lines, $toolName, $section['safe_lines'] ?? [], true);
        if (!empty($runtime['role_guard']['can_use_technical_tools']) && !empty($section['technical_lines'])) {
            aiAgentAppendNamedSection($lines, $toolName . '_technical', $section['technical_lines'], false);
        }
    }

    $lines[] = '[/TOOL_LAYER_CONTEXT]';
    return implode("\n", $lines);
}

function aiAgentAppendNamedSection(array &$lines, string $sectionName, array $sectionLines, bool $useBullets = true): void
{
    $cleanLines = [];
    foreach ($sectionLines as $sectionLine) {
        $sectionLine = trim((string) $sectionLine);
        if ($sectionLine !== '') {
            $cleanLines[] = $sectionLine;
        }
    }

    if (empty($cleanLines)) {
        return;
    }

    $tag = strtoupper(preg_replace('/[^A-Z0-9_]+/i', '_', $sectionName));
    $lines[] = '[' . $tag . ']';

    foreach ($cleanLines as $sectionLine) {
        if ($useBullets && strpos($sectionLine, '[') !== 0 && strpos($sectionLine, '- ') !== 0) {
            $lines[] = '- ' . $sectionLine;
        } else {
            $lines[] = $sectionLine;
        }
    }

    $lines[] = '[/' . $tag . ']';
}

function aiAgentInspectRuntimePage(string $pagePath, int $maxLinkedFiles = 12): array
{
    $projectRoot = aiAgentGetProjectRootPath();
    $relativePath = aiAgentNormalizeProjectRelativePath($pagePath);
    $result = [
        'relative_path' => $relativePath,
        'form_actions' => [],
        'script_sources' => [],
        'linked_files' => [],
    ];

    if ($relativePath === '') {
        return $result;
    }

    $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        return $result;
    }

    $content = @file_get_contents($absolutePath);
    if (!is_string($content) || trim($content) === '') {
        return $result;
    }

    $formActions = aiAgentExtractTagAttributeValues($content, 'form', 'action', 8);
    foreach ($formActions as $action) {
        $resolved = aiAgentResolveProjectRelativeReference($relativePath, $action);
        $result['form_actions'][] = $resolved !== '' ? $resolved : $action;
    }

    $scriptSources = aiAgentExtractTagAttributeValues($content, 'script', 'src', 12);
    foreach ($scriptSources as $source) {
        if (preg_match('#^(?:https?:)?//#i', $source)) {
            continue;
        }
        $resolved = aiAgentResolveProjectRelativeReference($relativePath, $source);
        $candidate = $resolved !== '' ? $resolved : $source;
        if (preg_match('#^(api|assets/js|admin|manager|user|pic-barang|config)/#i', $candidate) !== 1) {
            continue;
        }
        $result['script_sources'][] = $candidate;
    }

    $result['form_actions'] = array_values(array_unique(array_filter($result['form_actions'])));
    $result['script_sources'] = array_values(array_unique(array_filter($result['script_sources'])));
    $result['linked_files'] = array_slice(aiAgentCollectDirectlyLinkedFiles($projectRoot, $pagePath), 0, max(4, $maxLinkedFiles));

    return $result;
}

function aiAgentExtractTagAttributeValues(string $content, string $tagName, string $attributeName, int $limit = 12): array
{
    $values = [];
    $pattern = '/<' . preg_quote($tagName, '/') . '\b[^>]*\b' . preg_quote($attributeName, '/') . '=["\']([^"\']+)["\']/i';
    if (preg_match_all($pattern, $content, $matches)) {
        foreach (($matches[1] ?? []) as $match) {
            $value = trim((string) $match);
            if ($value !== '') {
                $values[] = $value;
            }
        }
    }

    return array_values(array_unique(array_slice($values, 0, $limit)));
}

function aiAgentGetLiveSchemaIndex(mysqli $conn): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $sql = '
        SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_KEY
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        ORDER BY TABLE_NAME, ORDINAL_POSITION
    ';

    $rows = aiAgentFetchRows($conn, $sql);
    $index = [];

    foreach ($rows as $row) {
        $table = trim((string) ($row['TABLE_NAME'] ?? ''));
        $column = trim((string) ($row['COLUMN_NAME'] ?? ''));
        if ($table === '' || $column === '') {
            continue;
        }

        if (!isset($index[$table])) {
            $index[$table] = [
                'columns' => [],
                'data_types' => [],
                'keys' => [],
            ];
        }

        $index[$table]['columns'][] = $column;
        $index[$table]['data_types'][$column] = trim((string) ($row['DATA_TYPE'] ?? ''));
        $index[$table]['keys'][$column] = trim((string) ($row['COLUMN_KEY'] ?? ''));
    }

    $cache = $index;
    return $cache;
}

function aiAgentRankRelevantTables(array $schemaIndex, array $options = []): array
{
    $keywords = isset($options['keywords']) && is_array($options['keywords']) ? $options['keywords'] : [];
    $maxTables = max(3, (int) ($options['max_tables'] ?? 6));
    $priorityColumns = ['status', 'user_id', 'created_at', 'updated_at', 'nama', 'email', 'role', 'kode_barang', 'nama_barang', 'stok_tersedia', 'safety_stock'];

    $scores = [];
    foreach ($schemaIndex as $tableName => $meta) {
        $score = 0;
        $tableLower = strtolower($tableName);
        $columns = array_map('strtolower', $meta['columns'] ?? []);

        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim((string) $keyword));
            if ($keyword === '') {
                continue;
            }

            if (strpos($tableLower, $keyword) !== false) {
                $score += 18;
            }

            foreach ($columns as $columnName) {
                if (strpos($columnName, $keyword) !== false) {
                    $score += 6;
                    break;
                }
            }
        }

        foreach ($priorityColumns as $priorityColumn) {
            if (in_array($priorityColumn, $columns, true)) {
                $score += 2;
            }
        }

        if (!empty($columns)) {
            $score += min(8, count($columns));
        }

        $scores[$tableName] = $score;
    }

    arsort($scores);
    $selected = array_keys(array_filter($scores, static function ($score) {
        return $score > 0;
    }));

    if (empty($selected)) {
        $fallback = [];
        foreach ($schemaIndex as $tableName => $meta) {
            $columns = array_map('strtolower', $meta['columns'] ?? []);
            $fallbackScore = 0;
            foreach ($priorityColumns as $priorityColumn) {
                if (in_array($priorityColumn, $columns, true)) {
                    $fallbackScore += 2;
                }
            }
            if ($fallbackScore > 0) {
                $fallback[$tableName] = $fallbackScore;
            }
        }
        arsort($fallback);
        $selected = array_keys($fallback);
    }

    if (empty($selected)) {
        $selected = array_keys($schemaIndex);
        sort($selected);
    }

    return array_slice($selected, 0, $maxTables);
}

function aiAgentSelectRepresentativeColumns(array $tableMeta, int $limit = 5): array
{
    $columns = $tableMeta['columns'] ?? [];
    if (empty($columns)) {
        return [];
    }

    $priorityOrder = [
        'id',
        'kode_barang',
        'nama_barang',
        'nama',
        'nrp',
        'email',
        'role',
        'status',
        'user_id',
        'barang_id',
        'peminjaman_id',
        'tanggal_pinjam',
        'tanggal_kembali',
        'tanggal_perpanjang',
        'created_at',
        'updated_at',
        'stok_tersedia',
        'safety_stock',
        'kondisi',
    ];

    $selected = [];
    foreach ($priorityOrder as $priorityColumn) {
        if (in_array($priorityColumn, $columns, true)) {
            $selected[] = $priorityColumn;
        }
    }

    foreach ($columns as $column) {
        if (!in_array($column, $selected, true)) {
            $selected[] = $column;
        }
    }

    return array_slice($selected, 0, $limit);
}

function aiAgentSchemaTableHasColumn(array $tableMeta, string $columnName): bool
{
    return in_array($columnName, $tableMeta['columns'] ?? [], true);
}

function aiAgentQuoteIdentifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        return '';
    }

    return '`' . $identifier . '`';
}

function aiAgentQueryTableTotal(mysqli $conn, string $tableName): ?int
{
    $tableIdentifier = aiAgentQuoteIdentifier($tableName);
    if ($tableIdentifier === '') {
        return null;
    }

    $row = aiAgentFetchSingleRow($conn, 'SELECT COUNT(*) AS total_rows FROM ' . $tableIdentifier);
    if (empty($row)) {
        return null;
    }

    return (int) ($row['total_rows'] ?? 0);
}

function aiAgentQueryStatusCounts(mysqli $conn, string $tableName, int $limit = 6): array
{
    $tableIdentifier = aiAgentQuoteIdentifier($tableName);
    if ($tableIdentifier === '') {
        return [];
    }

    $rows = aiAgentFetchRows(
        $conn,
        'SELECT `status` AS label, COUNT(*) AS total
         FROM ' . $tableIdentifier . '
         WHERE `status` IS NOT NULL AND `status` <> ""
         GROUP BY `status`
         ORDER BY total DESC, label ASC
         LIMIT ' . max(1, $limit)
    );

    $counts = [];
    foreach ($rows as $row) {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label !== '') {
            $counts[$label] = (int) ($row['total'] ?? 0);
        }
    }

    return $counts;
}

function aiAgentQueryUserScopedTotal(mysqli $conn, string $tableName, int $userId): ?int
{
    $tableIdentifier = aiAgentQuoteIdentifier($tableName);
    if ($tableIdentifier === '' || $userId <= 0) {
        return null;
    }

    $row = aiAgentFetchSingleRow(
        $conn,
        'SELECT COUNT(*) AS total_rows FROM ' . $tableIdentifier . ' WHERE `user_id` = ?',
        'i',
        [$userId]
    );
    if (empty($row)) {
        return null;
    }

    return (int) ($row['total_rows'] ?? 0);
}
