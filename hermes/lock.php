<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../api/session-helper.php';
require_once __DIR__ . '/logger/audit-log.php';
require_once __DIR__ . '/memory/conversation-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
    ]);
    exit;
}

SessionValidator::requireRole(['user', 'manager', 'admin', 'pic_barang']);

$sensitiveState = aiAgentGetSensitiveAccessState();
$businessOverrideState = aiAgentGetBusinessOverrideState();
$wasSensitive = !empty($sensitiveState['active']);
$wasOverride = !empty($businessOverrideState['active']);
$wasSensitiveUnlimited = !empty($sensitiveState['unlimited']);
$wasOverrideUnlimited = !empty($businessOverrideState['unlimited']);

aiAgentRevokeSensitiveAccess();
aiAgentRevokeBusinessOverrideAccess();

if ($wasSensitive || $wasSensitiveUnlimited) {
    aiAgentLogSensitiveModeEvent('sensitive_access', 'revoked', [
        'reason' => 'manual_lock',
        'metadata' => [
            'previous_unlimited' => $wasSensitiveUnlimited,
            'previous_expires_at' => (int) ($sensitiveState['expires_at'] ?? 0),
            'previous_granted_at' => (int) ($sensitiveState['granted_at'] ?? 0),
        ],
    ]);
}

if ($wasOverride || $wasOverrideUnlimited) {
    aiAgentLogSensitiveModeEvent('business_override', 'revoked', [
        'reason' => 'manual_lock',
        'metadata' => [
            'previous_unlimited' => $wasOverrideUnlimited,
            'previous_expires_at' => (int) ($businessOverrideState['expires_at'] ?? 0),
            'previous_granted_at' => (int) ($businessOverrideState['granted_at'] ?? 0),
        ],
    ]);
}

session_write_close();

echo json_encode([
    'success' => true,
    'revoked' => $wasSensitive || $wasOverride || $wasSensitiveUnlimited || $wasOverrideUnlimited,
    'mode' => 'native_php',
    'sensitive_access_active' => false,
    'sensitive_access_unlimited' => false,
    'timestamp' => time(),
], JSON_UNESCAPED_UNICODE);
