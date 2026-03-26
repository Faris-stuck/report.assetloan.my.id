<?php
require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['admin', 'manager', 'pic_barang', 'user']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
    exit;
}

$id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("
    SELECT nama, nrp, email, COALESCE(avatar, '') AS avatar
    FROM users
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo json_encode(["error" => "User not found"]);
}
