<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
    ]);
    exit;
}

SessionValidator::requireRole(['user', 'manager', 'admin', 'pic_barang']);

$wasActive = (int) ($_SESSION['ai_sensitive_access_expires_at'] ?? 0) > time();
unset($_SESSION['ai_sensitive_access_expires_at']);

session_write_close();

echo json_encode([
    'success' => true,
    'revoked' => $wasActive,
    'sensitive_access_active' => false,
    'timestamp' => time(),
], JSON_UNESCAPED_UNICODE);
