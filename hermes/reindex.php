<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../api/session-helper.php';
require_once __DIR__ . '/../api/koneksi.php';
require_once __DIR__ . '/engine/context-helper.php';
require_once __DIR__ . '/engine/codebase-helper.php';
require_once __DIR__ . '/engine/index-helper.php';
require_once __DIR__ . '/engine/tool-helper.php';
require_once __DIR__ . '/model/config-helper.php';
require_once __DIR__ . '/engine/runtime-helper.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'error' => 'Method not allowed.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = aiAgentLoadConfig([
    __DIR__ . '/config/ai_agent.php',
]);
aiAgentBootstrapRuntimeConfig($config);
$requestData = aiAgentReadProjectIndexControlRequest();
$auth = aiAgentAuthorizeProjectIndexControl($config, $requestData);

if (empty($auth['ok'])) {
    http_response_code((int) ($auth['http_code'] ?? 401));
    echo json_encode([
        'status' => 'error',
        'error' => (string) ($auth['error'] ?? 'Unauthorized request.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$defaultAction = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'status' : 'rebuild';
$action = strtolower(trim((string) ($requestData['action'] ?? $defaultAction)));
if ($action === '') {
    $action = $defaultAction;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action !== 'status') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'error' => 'GET only supports action=status.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($action) {
    case 'status':
        $state = aiAgentGetProjectIndexStatusSnapshot($conn, [
            'config' => $config,
        ]);
        $summary = aiAgentSummarizeProjectIndexState($state);
        $summary['reason_label'] = aiAgentFormatProjectIndexReason((string) ($summary['reason'] ?? ''));

        echo json_encode([
            'status' => 'ok',
            'action' => 'status',
            'auth_mode' => (string) ($auth['mode'] ?? 'unknown'),
            'timestamp' => time(),
            'project_index' => $summary,
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'signal':
        $reason = aiAgentNormalizeProjectIndexControlReason((string) ($requestData['reason'] ?? 'manual_signal'), 'manual_signal');
        $signalState = aiAgentTouchProjectIndexWatcherSignal($config, [
            'reason' => $reason,
            'source' => (string) ($auth['mode'] ?? 'manual'),
            'requested_by' => (string) ($auth['label'] ?? 'unknown'),
        ]);
        $statusState = aiAgentGetProjectIndexStatusSnapshot($conn, [
            'config' => $config,
        ]);
        $summary = aiAgentSummarizeProjectIndexState($statusState);
        $summary['reason_label'] = aiAgentFormatProjectIndexReason((string) ($summary['reason'] ?? ''));

        echo json_encode([
            'status' => 'ok',
            'action' => 'signal',
            'auth_mode' => (string) ($auth['mode'] ?? 'unknown'),
            'timestamp' => time(),
            'signal' => $signalState,
            'project_index' => $summary,
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'rebuild':
        if (!empty($requestData['touch_signal'])) {
            aiAgentTouchProjectIndexWatcherSignal($config, [
                'reason' => aiAgentNormalizeProjectIndexControlReason((string) ($requestData['reason'] ?? 'manual_rebuild'), 'manual_rebuild'),
                'source' => (string) ($auth['mode'] ?? 'manual'),
                'requested_by' => (string) ($auth['label'] ?? 'unknown'),
            ]);
        }

        $rebuildState = aiAgentForceProjectIndexBundleRebuild($conn, [
            'config' => $config,
            'reason' => aiAgentNormalizeProjectIndexControlReason((string) ($requestData['reason'] ?? 'manual_rebuild'), 'manual_rebuild'),
        ]);
        $summary = aiAgentSummarizeProjectIndexState($rebuildState);
        $summary['reason_label'] = aiAgentFormatProjectIndexReason((string) ($summary['reason'] ?? ''));

        echo json_encode([
            'status' => 'ok',
            'action' => 'rebuild',
            'auth_mode' => (string) ($auth['mode'] ?? 'unknown'),
            'timestamp' => time(),
            'project_index' => $summary,
        ], JSON_UNESCAPED_UNICODE);
        exit;
}

http_response_code(422);
echo json_encode([
    'status' => 'error',
    'error' => 'Unknown action. Supported actions: status, signal, rebuild.',
], JSON_UNESCAPED_UNICODE);

function aiAgentReadProjectIndexControlRequest(): array
{
    $request = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $data = [];

    if ($request === 'GET') {
        $data = $_GET;
    } else {
        $rawBody = @file_get_contents('php://input');
        $decoded = json_decode((string) $rawBody, true);
        if (is_array($decoded)) {
            $data = $decoded;
        } elseif (!empty($_POST) && is_array($_POST)) {
            $data = $_POST;
        }
    }

    return is_array($data) ? $data : [];
}

function aiAgentAuthorizeProjectIndexControl(array $config, array $requestData = []): array
{
    $role = (string) (SessionValidator::getRole() ?? '');
    $userName = (string) (SessionValidator::getUserName() ?? '');
    if (SessionValidator::isLoggedIn() && $role === 'admin') {
        return [
            'ok' => true,
            'mode' => 'session_admin',
            'label' => $userName !== '' ? $userName : 'admin',
        ];
    }

    $expectedToken = trim((string) ($config['project_index_reindex_token'] ?? ''));
    $providedToken = trim((string) ($requestData['token'] ?? ''));
    if ($providedToken === '' && isset($_SERVER['HTTP_X_AI_AGENT_REINDEX_TOKEN'])) {
        $providedToken = trim((string) $_SERVER['HTTP_X_AI_AGENT_REINDEX_TOKEN']);
    }

    if ($expectedToken !== '' && $providedToken !== '' && hash_equals($expectedToken, $providedToken)) {
        return [
            'ok' => true,
            'mode' => 'token',
            'label' => 'token',
        ];
    }

    if ($expectedToken !== '') {
        return [
            'ok' => false,
            'http_code' => $providedToken !== '' ? 403 : 401,
            'error' => 'Admin session or valid reindex token is required.',
        ];
    }

    return [
        'ok' => false,
        'http_code' => SessionValidator::isLoggedIn() ? 403 : 401,
        'error' => 'Admin session is required. Configure project_index_reindex_token for deploy-script access.',
    ];
}

function aiAgentNormalizeProjectIndexControlReason(string $reason, string $fallback): string
{
    $reason = strtolower(trim($reason));
    $reason = preg_replace('/[^a-z0-9._-]+/', '_', $reason);
    $reason = trim((string) $reason, '_');

    return $reason !== '' ? $reason : $fallback;
}
