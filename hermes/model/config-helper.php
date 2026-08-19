<?php

function aiAgentNormalizeBoolean($value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if ($value === null) {
        return $default;
    }

    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            return $default;
        }
    }

    $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $normalized === null ? $default : $normalized;
}

function aiAgentGetEnvBoolean(string $name, bool $default = false): bool
{
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }

    return aiAgentNormalizeBoolean($value, $default);
}

function aiAgentLoadConfig(array $candidates = []): array
{
    $config = aiAgentGetDefaultConfig();

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '' || !is_file($candidate)) {
            continue;
        }

        $loaded = require $candidate;
        if (!is_array($loaded)) {
            continue;
        }

        foreach ($loaded as $key => $value) {
            $key = (string) $key;
            if ($key === '' || !aiAgentConfigValueIsUsable($key, $value)) {
                continue;
            }

            $config[$key] = $value;
        }
    }

    return $config;
}

function aiAgentGetDefaultConfig(): array
{
    return [
        'sensitive_access_password' => getenv('AI_AGENT_SENSITIVE_PASSWORD') ?: 'kacamatafaris',
        'sensitive_access_duration_minutes' => (int) (getenv('AI_AGENT_SENSITIVE_DURATION_MINUTES') ?: 30),
        'sensitive_access_unlimited' => aiAgentGetEnvBoolean('AI_AGENT_SENSITIVE_UNLIMITED', false),
        'tool_layer_enabled' => true,
        'tool_baseline' => [
            'role_guard',
            'session_context',
            'page_metadata',
            'memory_search',
            'project_index',
            'workspace_visibility',
            'ui_snapshot',
            'runtime_observations',
            'structured_task_flow',
            'live_schema',
            'live_data',
        ],
        'tool_role_whitelist' => [
            'admin' => [
                'role_guard',
                'session_context',
                'page_metadata',
                'memory_search',
                'project_index',
                'workspace_visibility',
                'ui_snapshot',
                'runtime_observations',
                'structured_task_flow',
                'live_schema',
                'live_data',
            ],
            'manager' => [
                'role_guard',
                'session_context',
                'page_metadata',
                'memory_search',
                'project_index',
                'workspace_visibility',
                'ui_snapshot',
                'runtime_observations',
                'structured_task_flow',
                'live_schema',
                'live_data',
            ],
            'user' => [
                'role_guard',
                'session_context',
                'page_metadata',
                'memory_search',
                'project_index',
                'workspace_visibility',
                'ui_snapshot',
                'runtime_observations',
                'structured_task_flow',
                'live_schema',
                'live_data',
            ],
            'pic_barang' => [
                'role_guard',
                'session_context',
                'page_metadata',
                'memory_search',
                'project_index',
                'workspace_visibility',
                'ui_snapshot',
                'runtime_observations',
                'structured_task_flow',
                'live_schema',
                'live_data',
            ],
        ],
        'tool_sensitive_whitelist' => [
            'admin' => [
                'technical_code_context',
            ],
        ],
        'tool_role_data_scopes' => [
            'admin' => ['inventory', 'borrowing', 'returns', 'extensions', 'users_admin', 'reports', 'vendors'],
            'manager' => ['inventory', 'borrowing', 'returns', 'extensions', 'reports'],
            'user' => ['inventory', 'borrowing', 'returns', 'extensions'],
            'pic_barang' => ['inventory', 'borrowing', 'returns', 'extensions', 'vendors'],
        ],
        'tool_scope_keywords' => [
            'inventory' => ['barang', 'item', 'inventory', 'stok', 'stock', 'safety stock', 'kondisi', 'vendor', 'pembelian'],
            'borrowing' => ['peminjaman', 'pinjam', 'borrow', 'loan', 'approval', 'disetujui', 'jatuh tempo', 'overdue', 'due'],
            'returns' => ['pengembalian', 'return', 'kembali', 'inspection', 'inspected', 'rusak', 'damaged'],
            'extensions' => ['extend', 'perpanjang', 'perpanjangan'],
            'users_admin' => ['administrator', 'user list', 'role list', 'akun', 'account', 'email', 'nrp', 'data user'],
            'reports' => ['report', 'laporan', 'rekap', 'ringkasan', 'summary'],
            'vendors' => ['vendor', 'purchase', 'pembelian'],
        ],
        'tool_cross_role_markers' => [
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
        'tool_technical_keywords' => [
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
        'tool_max_tables' => 6,
        'tool_max_linked_files' => 12,
        'tool_max_status_values' => 6,
        'project_index_enabled' => getenv('AI_AGENT_PROJECT_INDEX_ENABLED') !== false
            ? filter_var(getenv('AI_AGENT_PROJECT_INDEX_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'project_index_lazy_rebuild' => getenv('AI_AGENT_PROJECT_INDEX_LAZY_REBUILD') !== false
            ? filter_var(getenv('AI_AGENT_PROJECT_INDEX_LAZY_REBUILD'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'project_index_storage_dir' => getenv('AI_AGENT_PROJECT_INDEX_STORAGE_DIR') ?: 'hermes/data/project-index',
        'project_index_lock_file' => getenv('AI_AGENT_PROJECT_INDEX_LOCK_FILE') ?: '',
        'project_index_lock_timeout_seconds' => (int) (getenv('AI_AGENT_PROJECT_INDEX_LOCK_TIMEOUT_SECONDS') ?: 15),
        'project_index_watcher_signal_file' => getenv('AI_AGENT_PROJECT_INDEX_WATCHER_SIGNAL_FILE') ?: '',
        'project_index_reindex_token' => getenv('AI_AGENT_PROJECT_INDEX_REINDEX_TOKEN') ?: '',
        'project_index_max_age_seconds' => (int) (getenv('AI_AGENT_PROJECT_INDEX_MAX_AGE_SECONDS') ?: 300),
        'project_index_max_file_size_bytes' => (int) (getenv('AI_AGENT_PROJECT_INDEX_MAX_FILE_SIZE_BYTES') ?: 200000),
        'project_index_max_relevant_entries' => (int) (getenv('AI_AGENT_PROJECT_INDEX_MAX_RELEVANT_ENTRIES') ?: 6),
        'memory_enabled' => getenv('AI_AGENT_MEMORY_ENABLED') !== false
            ? filter_var(getenv('AI_AGENT_MEMORY_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'memory_storage_dir' => getenv('AI_AGENT_MEMORY_STORAGE_DIR') ?: 'hermes/data/memory',
        'memory_max_messages_per_conversation' => (int) (getenv('AI_AGENT_MEMORY_MAX_MESSAGES_PER_CONVERSATION') ?: 40),
        'memory_max_notes_per_user' => (int) (getenv('AI_AGENT_MEMORY_MAX_NOTES_PER_USER') ?: 30),
        'memory_max_search_results' => (int) (getenv('AI_AGENT_MEMORY_MAX_SEARCH_RESULTS') ?: 5),
        'memory_max_search_conversations' => (int) (getenv('AI_AGENT_MEMORY_MAX_SEARCH_CONVERSATIONS') ?: 10),
        'memory_database_enabled' => getenv('AI_AGENT_MEMORY_DATABASE_ENABLED') !== false
            ? filter_var(getenv('AI_AGENT_MEMORY_DATABASE_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'memory_db_host' => getenv('AI_AGENT_DB_HOST') ?: 'localhost',
        'memory_db_port' => (int) (getenv('AI_AGENT_DB_PORT') ?: 3306),
        'memory_db_name' => getenv('AI_AGENT_DB_NAME') ?: 'information_schema',
        'memory_db_username' => getenv('AI_AGENT_DB_USER') ?: 'root',
        'memory_db_password' => getenv('AI_AGENT_DB_PASSWORD') ?: '',
        'memory_db_auto_init' => getenv('AI_AGENT_MEMORY_DB_AUTO_INIT') !== false
            ? filter_var(getenv('AI_AGENT_MEMORY_DB_AUTO_INIT'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'memory_db_fallback_to_files' => getenv('AI_AGENT_MEMORY_DB_FALLBACK_TO_FILES') !== false
            ? filter_var(getenv('AI_AGENT_MEMORY_DB_FALLBACK_TO_FILES'), FILTER_VALIDATE_BOOLEAN)
            : false,
        'skills_enabled' => getenv('AI_AGENT_SKILLS_ENABLED') !== false
            ? filter_var(getenv('AI_AGENT_SKILLS_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'skills_storage_dir' => getenv('AI_AGENT_SKILLS_STORAGE_DIR') ?: 'hermes/skills',
        'skills_max_matches' => (int) (getenv('AI_AGENT_SKILLS_MAX_MATCHES') ?: 3),
        'skills_max_chars_per_skill' => (int) (getenv('AI_AGENT_SKILLS_MAX_CHARS_PER_SKILL') ?: 1400),
        'codebase_visibility_mode' => getenv('AI_AGENT_CODEBASE_VISIBILITY_MODE') ?: 'extended',
        'groundable_extensions' => getenv('AI_AGENT_GROUNDABLE_EXTENSIONS') ?: '',
        'grounding_exclude_paths' => getenv('AI_AGENT_GROUNDING_EXCLUDE_PATHS') ?: '',
        'self_improvement_enabled' => getenv('AI_AGENT_SELF_IMPROVEMENT_ENABLED') !== false
            ? filter_var(getenv('AI_AGENT_SELF_IMPROVEMENT_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'self_improvement_patches_dir' => getenv('AI_AGENT_SELF_IMPROVEMENT_PATCHES_DIR') ?: 'hermes/patches',
        'self_improvement_logs_dir' => getenv('AI_AGENT_SELF_IMPROVEMENT_LOGS_DIR') ?: 'hermes/logs',
        'hermes_self_edit_enabled' => getenv('AI_AGENT_HERMES_SELF_EDIT_ENABLED') !== false
            ? filter_var(getenv('AI_AGENT_HERMES_SELF_EDIT_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'hermes_self_edit_allowed_role' => getenv('AI_AGENT_HERMES_SELF_EDIT_ALLOWED_ROLE') ?: 'admin',
        'hermes_self_edit_requires_sensitive_access' => getenv('AI_AGENT_HERMES_SELF_EDIT_REQUIRES_SENSITIVE') !== false
            ? filter_var(getenv('AI_AGENT_HERMES_SELF_EDIT_REQUIRES_SENSITIVE'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'hermes_self_edit_root' => getenv('AI_AGENT_HERMES_SELF_EDIT_ROOT') ?: 'hermes',
        'hermes_self_edit_allowed_extensions' => getenv('AI_AGENT_HERMES_SELF_EDIT_ALLOWED_EXTENSIONS') ?: 'php,js,css,md,json,txt,sql,html,yml,yaml,ini',
        'hermes_self_edit_max_files' => (int) (getenv('AI_AGENT_HERMES_SELF_EDIT_MAX_FILES') ?: 3),
        'hermes_self_edit_max_context_files' => (int) (getenv('AI_AGENT_HERMES_SELF_EDIT_MAX_CONTEXT_FILES') ?: 4),
        'hermes_self_edit_max_prompt_file_chars' => (int) (getenv('AI_AGENT_HERMES_SELF_EDIT_MAX_PROMPT_FILE_CHARS') ?: 16000),
        'hermes_self_edit_max_total_prompt_chars' => (int) (getenv('AI_AGENT_HERMES_SELF_EDIT_MAX_TOTAL_PROMPT_CHARS') ?: 28000),
        'hermes_self_edit_max_total_write_bytes' => (int) (getenv('AI_AGENT_HERMES_SELF_EDIT_MAX_TOTAL_WRITE_BYTES') ?: 240000),
        'hermes_self_edit_max_tokens' => (int) (getenv('AI_AGENT_HERMES_SELF_EDIT_MAX_TOKENS') ?: 2200),
        'hermes_self_edit_temperature' => (float) (getenv('AI_AGENT_HERMES_SELF_EDIT_TEMPERATURE') ?: 0.05),
        'hermes_self_edit_log_file' => getenv('AI_AGENT_HERMES_SELF_EDIT_LOG_FILE') ?: 'hermes/logs/code-edit-log.jsonl',
        'hermes_self_edit_backup_dir' => getenv('AI_AGENT_HERMES_SELF_EDIT_BACKUP_DIR') ?: 'hermes/patches/applied-backups',
        'hermes_self_edit_auto_signal_reindex' => getenv('AI_AGENT_HERMES_SELF_EDIT_AUTO_SIGNAL_REINDEX') !== false
            ? filter_var(getenv('AI_AGENT_HERMES_SELF_EDIT_AUTO_SIGNAL_REINDEX'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'summarization_enabled' => getenv('AI_AGENT_SUMMARIZATION_ENABLED') !== false
            ? filter_var(getenv('AI_AGENT_SUMMARIZATION_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'summarization_threshold_messages' => (int) (getenv('AI_AGENT_SUMMARIZATION_THRESHOLD_MESSAGES') ?: 20),
        'summarization_preserve_recent' => (int) (getenv('AI_AGENT_SUMMARIZATION_PRESERVE_RECENT') ?: 5),
        'summarization_min_lines' => (int) (getenv('AI_AGENT_SUMMARIZATION_MIN_LINES') ?: 3),
        'summarization_max_lines' => (int) (getenv('AI_AGENT_SUMMARIZATION_MAX_LINES') ?: 15),
        'summarization_target_tokens' => (int) (getenv('AI_AGENT_SUMMARIZATION_TARGET_TOKENS') ?: 2000),
    ];
}

function aiAgentGetPrimaryAiRuntimeConfigKeys(): array
{
    return [
        'agent_name',
        'base_url',
        'api_key',
        'model',
        'temperature',
        'max_tokens',
        'timeout',
        'system_prompt',
    ];
}

function aiAgentGetMissingPrimaryAiRuntimeConfigKeys(array $config = []): array
{
    $missing = [];

    foreach (aiAgentGetPrimaryAiRuntimeConfigKeys() as $key) {
        if (!array_key_exists($key, $config)) {
            $missing[] = $key;
            continue;
        }

        $value = $config[$key];
        if (is_string($value) && trim($value) === '') {
            $missing[] = $key;
            continue;
        }

        if (in_array($key, ['temperature', 'max_tokens', 'timeout'], true) && (!is_numeric($value) || (float) $value <= 0)) {
            $missing[] = $key;
        }
    }

    return $missing;
}

function aiAgentHasCompletePrimaryAiRuntimeConfig(array $config = []): bool
{
    return empty(aiAgentGetMissingPrimaryAiRuntimeConfigKeys($config));
}

function aiAgentGetMissingExtendedProviderConfigKeys(array $config = []): array
{
    if (empty($config['extended_provider_enabled'])) {
        return [];
    }

    $requiredKeys = [
        'extended_provider_type',
        'extended_provider_base_url',
        'extended_provider_api_key',
        'extended_provider_model',
        'extended_provider_timeout',
    ];

    $missing = [];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $config)) {
            $missing[] = $key;
            continue;
        }

        $value = $config[$key];
        if (is_string($value) && trim($value) === '') {
            $missing[] = $key;
            continue;
        }

        if ($key === 'extended_provider_timeout' && (!is_numeric($value) || (int) $value <= 0)) {
            $missing[] = $key;
        }
    }

    return $missing;
}

function aiAgentConfigValueIsUsable(string $key, $value): bool
{
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^replace-with-/i', $trimmed)) {
            return false;
        }

        if ($key === 'base_url' && !preg_match('#^https?://#i', $trimmed)) {
            return false;
        }

        if ($key === 'api_key' && !preg_match('/^sk-[A-Za-z0-9._-]{10,}$/', $trimmed)) {
            return false;
        }

        return true;
    }

    if (is_int($value) || is_float($value)) {
        if (in_array($key, ['max_tokens', 'timeout', 'sensitive_access_duration_minutes'], true)) {
            return $value > 0;
        }

        return true;
    }

    return $value !== null;
}

/**
 * Priority 4: Get extended provider configuration
 */
function aiAgentGetExtendedProviderConfig(array $config = []): array
{
    return [
        'enabled' => !isset($config['extended_provider_enabled'])
            ? false
            : (bool) $config['extended_provider_enabled'],
        'type' => strtolower(trim((string) ($config['extended_provider_type'] ?? ''))),
        'base_url' => trim((string) ($config['extended_provider_base_url'] ?? '')),
        'api_key' => trim((string) ($config['extended_provider_api_key'] ?? '')),
        'model' => trim((string) ($config['extended_provider_model'] ?? '')),
        'fallback_on_error' => !isset($config['extended_provider_fallback_on_error'])
            ? false
            : (bool) $config['extended_provider_fallback_on_error'],
        'timeout' => (int) ($config['extended_provider_timeout'] ?? 0),
    ];
}

function aiAgentBootstrapRuntimeConfig(array $config = []): void
{
    $envMap = [
        'codebase_visibility_mode' => 'AI_AGENT_CODEBASE_VISIBILITY_MODE',
        'groundable_extensions' => 'AI_AGENT_GROUNDABLE_EXTENSIONS',
        'grounding_exclude_paths' => 'AI_AGENT_GROUNDING_EXCLUDE_PATHS',
    ];

    foreach ($envMap as $configKey => $envName) {
        if (!array_key_exists($configKey, $config)) {
            continue;
        }

        $value = $config[$configKey];
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $value = implode(',', array_map('strval', $value));
        } else {
            $value = (string) $value;
        }

        if ($value === '') {
            continue;
        }

        putenv($envName . '=' . $value);
        $_ENV[$envName] = $value;
        $_SERVER[$envName] = $value;
    }
}
