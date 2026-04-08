<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../api/session-helper.php';
require_once __DIR__ . '/../api/koneksi.php';
require_once __DIR__ . '/context-helper.php';
require_once __DIR__ . '/codebase-helper.php';
require_once __DIR__ . '/index-helper.php';
require_once __DIR__ . '/tool-helper.php';
require_once __DIR__ . '/config-helper.php';
require_once __DIR__ . '/runtime-helper.php';
require_once __DIR__ . '/memory-helper.php';
require_once __DIR__ . '/skills-helper.php';
require_once __DIR__ . '/self-improve-helper.php';

SessionValidator::requireRole(['admin']);

$config = aiAgentLoadConfig([
    __DIR__ . '/../config/ai_agent.php',
]);
aiAgentBootstrapRuntimeConfig($config);

$agentBaseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
$agentApiKey = trim((string) ($config['api_key'] ?? ''));
$agentModel = trim((string) ($config['model'] ?? ''));
$agentName = trim((string) ($config['agent_name'] ?? 'Hermes Agent'));

$projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$assetChecks = [
    'base_url_js' => is_file($projectRoot . DIRECTORY_SEPARATOR . 'assets/js/base-url.js'),
    'ai_widget_js' => is_file($projectRoot . DIRECTORY_SEPARATOR . 'assets/js/ai-agent-widget.js'),
    'ai_widget_css' => is_file($projectRoot . DIRECTORY_SEPARATOR . 'assets/css/ai-agent-widget.css'),
    'chat_endpoint' => is_file(__DIR__ . '/chat.php'),
    'lock_endpoint' => is_file(__DIR__ . '/lock.php'),
];

$providerProbe = aiAgentProbeProviderReachability($agentBaseUrl);
$databaseName = null;
$projectIndexStatus = aiAgentGetProjectIndexStatusSnapshot($conn, [
    'config' => $config,
]);
$projectIndexSummary = aiAgentSummarizeProjectIndexState($projectIndexStatus);
$memoryConfig = aiAgentGetMemoryConfig($config);
$skillsConfig = aiAgentGetSkillsConfig($config);
$selfImproveConfig = aiAgentGetSelfImproveConfig($config);
aiAgentEnsureMemoryDirectories($memoryConfig);
aiAgentEnsureSkillsDirectory($skillsConfig);
aiAgentEnsureSelfImproveDirectories($selfImproveConfig);

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
    $dbResult = $conn->query('SELECT DATABASE() AS db_name');
    if ($dbResult instanceof mysqli_result) {
        $dbRow = $dbResult->fetch_assoc();
        $databaseName = $dbRow['db_name'] ?? null;
        $dbResult->free();
    }
}

$skillFiles = aiAgentListSkillFiles($skillsConfig);

$checks = [
    'session' => [
        'ok' => SessionValidator::isLoggedIn() && SessionValidator::getRole() === 'admin',
        'role' => SessionValidator::getRole(),
        'user_id' => SessionValidator::getUserId(),
        'user_name' => SessionValidator::getUserName(),
    ],
    'database' => [
        'ok' => isset($conn) && $conn instanceof mysqli && !$conn->connect_errno,
        'name' => $databaseName,
        'server_info' => isset($conn) && $conn instanceof mysqli ? $conn->server_info : null,
    ],
    'config' => [
        'ok' => $agentBaseUrl !== '' && $agentApiKey !== '' && $agentModel !== '',
        'agent_name' => $agentName,
        'base_url' => $agentBaseUrl,
        'model' => $agentModel,
        'api_key_masked' => $agentApiKey !== '' ? substr($agentApiKey, 0, 6) . str_repeat('*', max(4, strlen($agentApiKey) - 10)) . substr($agentApiKey, -4) : '',
        'memory_enabled' => !empty($memoryConfig['enabled']),
        'skills_enabled' => !empty($skillsConfig['enabled']),
        'self_improvement_enabled' => !empty($selfImproveConfig['enabled']),
    ],
    'php' => [
        'ok' => extension_loaded('json') && aiAgentHasHttpTransport(),
        'version' => PHP_VERSION,
        'extensions' => [
            'curl' => extension_loaded('curl'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
        ],
        'ini' => [
            'allow_url_fopen' => aiAgentIniFlagEnabled('allow_url_fopen'),
        ],
    ],
    'assets' => [
        'ok' => !in_array(false, $assetChecks, true),
        'files' => $assetChecks,
    ],
    'provider' => $providerProbe,
    'project_index' => [
        'ok' => !empty($projectIndexSummary['enabled']) && !empty($projectIndexSummary['available']),
        'rebuild_required' => !empty($projectIndexSummary['rebuild_required']),
        'reason' => (string) ($projectIndexSummary['reason'] ?? ''),
        'reason_label' => aiAgentFormatProjectIndexReason((string) ($projectIndexSummary['reason'] ?? '')),
        'project_index_summary' => $projectIndexSummary['project_index_summary'] ?? [],
        'feature_manifest_summary' => $projectIndexSummary['feature_manifest_summary'] ?? [],
        'watcher_signal' => $projectIndexSummary['watcher_signal'] ?? [],
        'lock' => $projectIndexSummary['lock'] ?? [],
    ],
    'memory' => [
        'ok' => is_dir((string) ($memoryConfig['storage_dir'] ?? '')),
        'storage_dir' => (string) ($memoryConfig['storage_dir'] ?? ''),
        'profiles_dir' => (string) ($memoryConfig['profiles_dir'] ?? ''),
        'conversations_dir' => (string) ($memoryConfig['conversations_dir'] ?? ''),
        'lessons_dir' => (string) ($memoryConfig['lessons_dir'] ?? ''),
        'reflections_log' => aiAgentGetReflectionLogPath($memoryConfig),
    ],
    'skills' => [
        'ok' => is_dir((string) ($skillsConfig['storage_dir'] ?? '')),
        'storage_dir' => (string) ($skillsConfig['storage_dir'] ?? ''),
        'count' => count($skillFiles),
        'files' => array_map(static function ($path) {
            return basename((string) $path);
        }, array_slice($skillFiles, 0, 12)),
    ],
    'self_improvement' => [
        'ok' => is_dir((string) ($selfImproveConfig['patches_dir'] ?? '')) && is_dir((string) ($selfImproveConfig['logs_dir'] ?? '')),
        'patches_dir' => (string) ($selfImproveConfig['patches_dir'] ?? ''),
        'logs_dir' => (string) ($selfImproveConfig['logs_dir'] ?? ''),
        'suggestions_file' => (string) ($selfImproveConfig['suggestions_file'] ?? ''),
        'log_file' => (string) ($selfImproveConfig['log_file'] ?? ''),
    ],
];

$allOk = true;
foreach ($checks as $check) {
    if (isset($check['ok']) && !$check['ok']) {
        $allOk = false;
        break;
    }
}

echo json_encode([
    'status' => $allOk ? 'ok' : 'warning',
    'message' => $allOk
        ? 'Hermes-style PHP engine checks passed.'
        : 'Hermes-style PHP engine checks found issues that should be reviewed.',
    'timestamp' => time(),
    'checks' => $checks,
], JSON_UNESCAPED_UNICODE);

function aiAgentProbeProviderReachability(string $baseUrl): array
{
    if ($baseUrl === '') {
        return [
            'ok' => false,
            'reachable' => false,
            'http_code' => 0,
            'error' => 'AI base URL is empty.',
        ];
    }

    $probe = aiAgentHttpRequest('GET', $baseUrl, [
        'headers' => [
            'Connection: close',
        ],
        'timeout' => 10,
        'connect_timeout' => 5,
    ]);

    return [
        'ok' => (int) ($probe['http_code'] ?? 0) > 0,
        'reachable' => (int) ($probe['http_code'] ?? 0) > 0,
        'http_code' => (int) ($probe['http_code'] ?? 0),
        'error' => trim((string) ($probe['error'] ?? '')),
        'transport' => (string) ($probe['transport'] ?? 'unknown'),
    ];
}
