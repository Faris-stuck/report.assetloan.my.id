<?php
require_once "../koneksi.php";
require_once "../session-helper.php";
require_once "../response-helper.php";

header('Content-Type: application/json');

if (!SessionValidator::isLoggedIn()) {
    apiBusinessError('Unauthorized', 401);
}

$allowedRoles = ['admin', 'manager', 'pic_barang', 'user'];
$currentRole = SessionValidator::getRole();
$sessionUserId = (int) (SessionValidator::getUserId() ?? 0);

if (!in_array($currentRole, $allowedRoles, true)) {
    apiBusinessError('Unauthorized', 403);
}

$requestedId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$targetUserId = $currentRole === 'user'
    ? $sessionUserId
    : ($requestedId > 0 ? $requestedId : $sessionUserId);

if ($targetUserId <= 0) {
    apiBusinessError('User not found', 404);
}

try {
    $stmt = $conn->prepare("
        SELECT nama, nrp, email
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException('Failed to prepare profile query');
    }

    $stmt->bind_param("i", $targetUserId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'nama' => $row['nama'],
            'nrp' => $row['nrp'],
            'email' => $row['email'],
            'avatar' => ''
        ]);
        exit;
    }

    apiBusinessError('User not found', 404);
} catch (Throwable $e) {
    apiServerError($e, 'api/user/profile.php');
}
