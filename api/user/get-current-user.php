<?php
/**
 * Get current logged-in user profile
 * Returns: nama, email, role, user_id
 */

require_once "../koneksi.php";
require_once "../session-helper.php";

header('Content-Type: application/json');

SessionValidator::requireRole(['user', 'manager', 'admin', 'pic_barang']);

$userId = SessionValidator::getUserId();
$userName = SessionValidator::getUserName();
$userEmail = SessionValidator::getUserEmail();
$userRole = SessionValidator::getRole();

// Get additional details from database if needed
$stmt = $conn->prepare("
    SELECT nama, email, id 
    FROM users 
    WHERE id = ?
");

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'nama' => $row['nama'] ?? $userName,
        'email' => $row['email'] ?? $userEmail,
        'role' => $userRole
    ]);
} else {
    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'nama' => $userName,
        'email' => $userEmail,
        'role' => $userRole
    ]);
}
