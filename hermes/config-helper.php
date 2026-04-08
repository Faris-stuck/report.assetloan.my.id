<?php

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
        'agent_name' => getenv('AI_AGENT_NAME') ?: 'Hermes Agent',
        'base_url' => getenv('AI_AGENT_BASE_URL') ?: 'https://ai.sumopod.com/v1',
        'api_key' => getenv('AI_AGENT_API_KEY') ?: 'sk-jyoOBJSlENl4HMAEHH_5Rw',
        'model' => getenv('AI_AGENT_MODEL') ?: 'seed-2-0-pro-free',
        'temperature' => (float) (getenv('AI_AGENT_TEMPERATURE') ?: 0.12),
        'max_tokens' => (int) (getenv('AI_AGENT_MAX_TOKENS') ?: 900),
        'timeout' => (int) (getenv('AI_AGENT_TIMEOUT') ?: 45),
        'sensitive_access_password' => getenv('AI_AGENT_SENSITIVE_PASSWORD') ?: 'kacamatafaris',
        'sensitive_access_duration_minutes' => (int) (getenv('AI_AGENT_SENSITIVE_DURATION_MINUTES') ?: 30),
        'sensitive_access_unlimited' => getenv('AI_AGENT_SENSITIVE_UNLIMITED') !== false
            ? filter_var(getenv('AI_AGENT_SENSITIVE_UNLIMITED'), FILTER_VALIDATE_BOOLEAN)
            : false,
        'system_prompt' => getenv('AI_AGENT_SYSTEM_PROMPT') ?: 'Anda adalah Hermes Agent, asisten AI internal untuk Sistem Informasi Peminjaman Barang. Gunakan hanya fakta dari system messages, konteks aplikasi, riwayat chat, dan pertanyaan user. Jangan mengarang. Dalam mode normal, prioritaskan jawaban berbasis menu, submenu, card, halaman, tombol, langkah penggunaan, dan status bisnis. Jangan ungkap nama file, folder, path, endpoint, database, tabel, kolom, query, atau detail backend internal kecuali sistem secara eksplisit mengaktifkan mode sensitif. Jika konteks belum cukup, katakan dengan jujur apa yang masih kurang.',
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
        'summarization_enabled' => getenv('AI_AGENT_SUMMARIZATION_ENABLED') !== false
            ? filter_var(getenv('AI_AGENT_SUMMARIZATION_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'summarization_threshold_messages' => (int) (getenv('AI_AGENT_SUMMARIZATION_THRESHOLD_MESSAGES') ?: 20),
        'summarization_preserve_recent' => (int) (getenv('AI_AGENT_SUMMARIZATION_PRESERVE_RECENT') ?: 5),
        'summarization_min_lines' => (int) (getenv('AI_AGENT_SUMMARIZATION_MIN_LINES') ?: 3),
        'summarization_max_lines' => (int) (getenv('AI_AGENT_SUMMARIZATION_MAX_LINES') ?: 15),
        'summarization_target_tokens' => (int) (getenv('AI_AGENT_SUMMARIZATION_TARGET_TOKENS') ?: 2000),
        // Priority 4: Extended provider fallback config
        'extended_provider_enabled' => getenv('AI_AGENT_EXTENDED_PROVIDER_ENABLED') !== false
            ? filter_var(getenv('AI_AGENT_EXTENDED_PROVIDER_ENABLED'), FILTER_VALIDATE_BOOLEAN)
            : false,
        'extended_provider_type' => getenv('AI_AGENT_EXTENDED_PROVIDER_TYPE') ?: 'openai',
        'extended_provider_base_url' => getenv('AI_AGENT_EXTENDED_PROVIDER_BASE_URL') ?: '',
        'extended_provider_api_key' => getenv('AI_AGENT_EXTENDED_PROVIDER_API_KEY') ?: '',
        'extended_provider_model' => getenv('AI_AGENT_EXTENDED_PROVIDER_MODEL') ?: 'gpt-4o-mini',
        'extended_provider_fallback_on_error' => getenv('AI_AGENT_EXTENDED_PROVIDER_FALLBACK_ON_ERROR') !== false
            ? filter_var(getenv('AI_AGENT_EXTENDED_PROVIDER_FALLBACK_ON_ERROR'), FILTER_VALIDATE_BOOLEAN)
            : true,
        'extended_provider_timeout' => max(5, (int) (getenv('AI_AGENT_EXTENDED_PROVIDER_TIMEOUT') ?: 30)),
    ];
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
        'type' => strtolower(trim((string) ($config['extended_provider_type'] ?? 'openai'))),
        'base_url' => trim((string) ($config['extended_provider_base_url'] ?? '')),
        'api_key' => trim((string) ($config['extended_provider_api_key'] ?? '')),
        'model' => trim((string) ($config['extended_provider_model'] ?? 'gpt-4o-mini')),
        'fallback_on_error' => !isset($config['extended_provider_fallback_on_error'])
            ? true
            : (bool) $config['extended_provider_fallback_on_error'],
        'timeout' => max(5, (int) ($config['extended_provider_timeout'] ?? 30)),
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
