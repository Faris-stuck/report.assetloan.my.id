<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session-helper.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/config-helper.php';
require_once __DIR__ . '/runtime-helper.php';

SessionValidator::requireRole(['admin']);

$config = aiAgentLoadConfig([
    __DIR__ . '/../../config/ai_agent.php',
    __DIR__ . '/../../config/ai_agent.example.php',
]);

$agentBaseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
$agentApiKey = trim((string) ($config['api_key'] ?? ''));
$agentModel = trim((string) ($config['model'] ?? ''));
$agentName = trim((string) ($config['agent_name'] ?? 'Hermes Agent'));

$projectRoot = realpath(__DIR__ . '/../../');
$assetChecks = [
    'base_url_js' => is_file($projectRoot . DIRECTORY_SEPARATOR . 'assets/js/base-url.js'),
    'ai_widget_js' => is_file($projectRoot . DIRECTORY_SEPARATOR . 'assets/js/ai-agent-widget.js'),
    'ai_widget_css' => is_file($projectRoot . DIRECTORY_SEPARATOR . 'assets/css/ai-agent-widget.css'),
    'chat_endpoint' => is_file(__DIR__ . '/chat.php'),
    'lock_endpoint' => is_file(__DIR__ . '/lock.php'),
];

$providerProbe = aiAgentProbeProviderReachability($agentBaseUrl);
$databaseName = null;

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
    $dbResult = $conn->query('SELECT DATABASE() AS db_name');
    if ($dbResult instanceof mysqli_result) {
        $dbRow = $dbResult->fetch_assoc();
        $databaseName = $dbRow['db_name'] ?? null;
        $dbResult->free();
    }
}

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
        'sensitive_password_set' => trim((string) ($config['sensitive_access_password'] ?? '')) !== '',
        'sensitive_duration_minutes' => (int) ($config['sensitive_access_duration_minutes'] ?? 0),
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
        'transports' => [
            'http_available' => aiAgentHasHttpTransport(),
            'stream_socket_client' => function_exists('stream_socket_client'),
        ],
    ],
    'assets' => [
        'ok' => !in_array(false, $assetChecks, true),
        'files' => $assetChecks,
    ],
    'provider' => $providerProbe,
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
        ? 'Hermes Agent deployment checks passed.'
        : 'Hermes Agent deployment checks found issues that should be reviewed.',
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

    $httpCode = (int) ($probe['http_code'] ?? 0);
    $error = trim((string) ($probe['error'] ?? ''));

    return [
        'ok' => $httpCode > 0,
        'reachable' => $httpCode > 0,
        'http_code' => $httpCode,
        'error' => $error,
        'transport' => (string) ($probe['transport'] ?? 'unknown'),
    ];
}
