<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../api/session-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
    ]);
    exit;
}

SessionValidator::requireRole(['user', 'manager', 'admin', 'pic_barang']);

$wasSensitive = (int) ($_SESSION['ai_sensitive_access_expires_at'] ?? 0) > time();
$wasOverride = (int) ($_SESSION['ai_business_override_expires_at'] ?? 0) > time();
$wasSensitiveUnlimited = !empty($_SESSION['ai_sensitive_access_unlimited']);
$wasOverrideUnlimited = !empty($_SESSION['ai_business_override_unlimited']);
unset($_SESSION['ai_sensitive_access_expires_at']);
unset($_SESSION['ai_business_override_expires_at']);
unset($_SESSION['ai_sensitive_access_unlimited']);
unset($_SESSION['ai_business_override_unlimited']);

session_write_close();

echo json_encode([
    'success' => true,
    'revoked' => $wasSensitive || $wasOverride || $wasSensitiveUnlimited || $wasOverrideUnlimited,
    'mode' => 'native_php',
    'sensitive_access_active' => false,
    'timestamp' => time(),
], JSON_UNESCAPED_UNICODE);
