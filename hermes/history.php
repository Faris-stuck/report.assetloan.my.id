<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../api/session-helper.php';
require_once __DIR__ . '/model/config-helper.php';
require_once __DIR__ . '/engine/runtime-helper.php';
require_once __DIR__ . '/memory/memory-helper.php';
require_once __DIR__ . '/database/integrated-memory-helper.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'error' => 'Method not allowed.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

SessionValidator::requireRole(['user', 'manager', 'admin', 'pic_barang']);

$config = aiAgentLoadConfig([
    __DIR__ . '/config/ai_agent.php',
]);
aiAgentBootstrapRuntimeConfig($config);
$memoryConfig = aiAgentGetMemoryConfig($config);

require_once __DIR__ . '/../config/database.php';
if (
    !empty($memoryConfig['enabled'])
    && !empty($memoryConfig['database_enabled'])
    && isset($conn)
    && $conn instanceof mysqli
    && @$conn->ping()
) {
    aiAgentInitializeIntegratedMemoryTables($conn);
}

$request = aiAgentReadHistoryRequest();
$action = strtolower(trim((string) ($request['action'] ?? 'sync')));
if ($action === '') {
    $action = 'sync';
}

$sessionRole = (string) SessionValidator::getRole();
$sessionUserId = (int) SessionValidator::getUserId();
$limit = max(1, min(25, (int) ($request['limit'] ?? 15)));
$requestedConversationId = aiAgentNormalizeMemoryConversationId(
    (string) ($request['conversation_id'] ?? $request['current_conversation_id'] ?? ''),
    ''
);

switch ($action) {
    case 'sync':
        $payload = aiAgentBuildHistorySyncPayload(
            $memoryConfig,
            $sessionRole,
            $sessionUserId,
            $requestedConversationId,
            $limit
        );

        echo json_encode([
            'status' => 'ok',
            'action' => 'sync',
            'timestamp' => time(),
            'current_conversation_id' => $payload['current_conversation_id'],
            'current_conversation' => $payload['current_conversation'],
            'conversations' => $payload['conversations'],
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'activate':
        if ($requestedConversationId === '') {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'error' => 'conversation_id is required.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        aiAgentSetUserActiveConversationId($memoryConfig, $sessionRole, $sessionUserId, $requestedConversationId);
        $payload = aiAgentBuildHistorySyncPayload(
            $memoryConfig,
            $sessionRole,
            $sessionUserId,
            $requestedConversationId,
            $limit
        );

        echo json_encode([
            'status' => 'ok',
            'action' => 'activate',
            'timestamp' => time(),
            'current_conversation_id' => $payload['current_conversation_id'],
            'current_conversation' => $payload['current_conversation'],
            'conversations' => $payload['conversations'],
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'select':
        if ($requestedConversationId === '') {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'error' => 'conversation_id is required.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $selectedState = aiAgentLoadConversationMemory($memoryConfig, $sessionRole, $sessionUserId, $requestedConversationId);
        if (!aiAgentConversationMemoryStateHasContent($selectedState)) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'error' => 'Conversation not found.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        aiAgentSetUserActiveConversationId($memoryConfig, $sessionRole, $sessionUserId, $requestedConversationId);
        $payload = aiAgentBuildHistorySyncPayload(
            $memoryConfig,
            $sessionRole,
            $sessionUserId,
            $requestedConversationId,
            $limit
        );

        echo json_encode([
            'status' => 'ok',
            'action' => 'select',
            'timestamp' => time(),
            'current_conversation_id' => $payload['current_conversation_id'],
            'current_conversation' => $payload['current_conversation'],
            'conversations' => $payload['conversations'],
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'delete':
        if ($requestedConversationId === '') {
            http_response_code(422);
            echo json_encode([
                'status' => 'error',
                'error' => 'conversation_id is required.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $activeConversationId = aiAgentGetUserActiveConversationId($memoryConfig, $sessionRole, $sessionUserId);
        aiAgentDeleteConversationMemory($memoryConfig, $sessionRole, $sessionUserId, $requestedConversationId);

        if ($activeConversationId === $requestedConversationId) {
            aiAgentSetUserActiveConversationId($memoryConfig, $sessionRole, $sessionUserId, '');
        }

        $payload = aiAgentBuildHistorySyncPayload(
            $memoryConfig,
            $sessionRole,
            $sessionUserId,
            '',
            $limit
        );

        if ($activeConversationId === $requestedConversationId && $payload['current_conversation_id'] !== '') {
            aiAgentSetUserActiveConversationId(
                $memoryConfig,
                $sessionRole,
                $sessionUserId,
                (string) $payload['current_conversation_id']
            );
        }

        echo json_encode([
            'status' => 'ok',
            'action' => 'delete',
            'timestamp' => time(),
            'deleted_conversation_id' => $requestedConversationId,
            'current_conversation_id' => $payload['current_conversation_id'],
            'current_conversation' => $payload['current_conversation'],
            'conversations' => $payload['conversations'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
}

http_response_code(422);
echo json_encode([
    'status' => 'error',
    'error' => 'Unknown action. Supported actions: sync, activate, select, delete.',
], JSON_UNESCAPED_UNICODE);

function aiAgentReadHistoryRequest(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        return is_array($_GET) ? $_GET : [];
    }

    $rawBody = @file_get_contents('php://input');
    $decoded = json_decode((string) $rawBody, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return !empty($_POST) && is_array($_POST) ? $_POST : [];
}

function aiAgentBuildHistorySyncPayload(
    array $memoryConfig,
    string $role,
    int $userId,
    string $preferredConversationId = '',
    int $limit = 15
): array {
    $preferredConversationId = aiAgentNormalizeMemoryConversationId($preferredConversationId, '');
    $activeConversationId = '';
    $states = aiAgentListUserConversationMemoryStates($memoryConfig, $role, $userId, '', $limit);
    $conversations = [];
    $currentConversation = null;
    $currentConversationId = $preferredConversationId;

    foreach ($states as $state) {
        $record = aiAgentBuildConversationArchiveRecord($state, 60);
        $conversations[] = $record;

        if ($currentConversationId !== '' && $record['conversation_id'] === $currentConversationId) {
            $currentConversation = $record;
        }
    }

    if ($currentConversationId === '') {
        $activeConversationId = aiAgentGetUserActiveConversationId($memoryConfig, $role, $userId);
        $currentConversationId = $activeConversationId;
    }

    if ($currentConversationId !== '' && $currentConversation === null) {
        $state = aiAgentLoadConversationMemory($memoryConfig, $role, $userId, $currentConversationId);
        if (aiAgentConversationMemoryStateHasContent($state)) {
            $currentConversation = aiAgentBuildConversationArchiveRecord($state, 60);

            $existsInList = false;
            foreach ($conversations as $conversation) {
                if (($conversation['conversation_id'] ?? '') === $currentConversationId) {
                    $existsInList = true;
                    break;
                }
            }

            if (!$existsInList) {
                array_unshift($conversations, $currentConversation);
            }
        }
    }

    if ($currentConversation === null) {
        if ($preferredConversationId !== '') {
            $currentConversationId = $preferredConversationId;
        } elseif ($activeConversationId !== '') {
            $currentConversationId = $activeConversationId;
        } elseif (!empty($conversations)) {
            $currentConversation = $conversations[0];
            $currentConversationId = (string) ($currentConversation['conversation_id'] ?? '');
        } else {
            $currentConversationId = '';
        }
    }

    foreach ($conversations as &$conversation) {
        $conversation['is_current'] = ($conversation['conversation_id'] ?? '') === $currentConversationId;
    }
    unset($conversation);

    if ($currentConversation !== null) {
        $currentConversation['is_current'] = true;
    }

    return [
        'current_conversation_id' => $currentConversationId,
        'current_conversation' => $currentConversation,
        'conversations' => array_slice($conversations, 0, $limit),
    ];
}
