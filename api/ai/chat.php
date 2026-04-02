<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session-helper.php';
require_once __DIR__ . '/context-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
    exit;
}

SessionValidator::requireRole(['user', 'manager', 'admin', 'pic_barang']);

$configPath = __DIR__ . '/../../config/ai_agent.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'AI agent configuration is missing.',
    ]);
    exit;
}

$config = require $configPath;
$agentEndpoint = trim((string) ($config['endpoint'] ?? ''));
$agentApiKey = trim((string) ($config['api_key'] ?? ''));
$agentTimeout = (int) ($config['timeout'] ?? 25);

if ($agentEndpoint === '' || $agentApiKey === '') {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'AI agent configuration is incomplete.',
    ]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => 'Invalid JSON payload.',
    ]);
    exit;
}

$message = trim((string) ($payload['message'] ?? ''));
if ($message === '') {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'error' => 'Message is required.',
    ]);
    exit;
}

if (mb_strlen($message) > 2000) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'error' => 'Message is too long. Maximum 2000 characters.',
    ]);
    exit;
}

$pageContext = [];
if (isset($payload['page_context']) && is_array($payload['page_context'])) {
    $pageContext = $payload['page_context'];
}

$sessionRole = SessionValidator::getRole();
$agentRole = $sessionRole === 'pic_barang' ? 'pic' : $sessionRole;
$sessionUserId = (int) SessionValidator::getUserId();

$agentRequest = [
    'user_id' => (string) $sessionUserId,
    'user_nama' => (string) SessionValidator::getUserName(),
    'user_role' => (string) $agentRole,
    'message' => aiAgentBuildOutboundMessage($conn, [
        'role' => $sessionRole,
        'user_id' => $sessionUserId,
        'message' => $message,
        'page_path' => (string) ($pageContext['path'] ?? ''),
        'page_title' => (string) ($pageContext['title'] ?? ''),
        'page_heading' => (string) ($pageContext['heading'] ?? ''),
    ]),
];

session_write_close();

$curl = curl_init($agentEndpoint);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => json_encode($agentRequest, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $agentApiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => max(5, $agentTimeout),
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$result = curl_exec($curl);
$curlError = curl_error($curl);
$httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($result === false) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'error' => 'Failed to connect to Hermes Agent.',
        'details' => $curlError,
    ]);
    exit;
}

$decoded = json_decode($result, true);
if (!is_array($decoded)) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'error' => 'Invalid response from Hermes Agent.',
    ]);
    exit;
}

if ($httpCode >= 400) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'error' => trim((string) ($decoded['error'] ?? $decoded['message'] ?? 'Hermes Agent returned an error.')),
    ]);
    exit;
}

$reply = trim((string) ($decoded['reply'] ?? ''));
if (($decoded['status'] ?? '') !== 'ok' || $reply === '') {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'error' => trim((string) ($decoded['error'] ?? $decoded['message'] ?? 'Hermes Agent did not return a valid reply.')),
    ]);
    exit;
}

echo json_encode([
    'status' => 'ok',
    'reply' => $reply,
    'processing_time_ms' => (int) ($decoded['processing_time_ms'] ?? 0),
    'timestamp' => (int) ($decoded['timestamp'] ?? time()),
], JSON_UNESCAPED_UNICODE);