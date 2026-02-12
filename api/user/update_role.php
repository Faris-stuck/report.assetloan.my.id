<?php
header('Content-Type: application/json');
require_once "../koneksi.php";
require_once "../session-helper.php";

try {
    SessionValidator::requireRole(['admin']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Unauthorized: " . $e->getMessage()]);
    exit;
}

$id = $_POST['id'] ?? 0;
$role = $_POST['role'] ?? '';

$id = (int) $id;

// Validate role against database roles table
if ($role) {
    $roleCheck = $conn->prepare("SELECT role_name FROM roles WHERE role_name = ?");
    $roleCheck->bind_param('s', $role);
    $roleCheck->execute();
    if ($roleCheck->get_result()->num_rows === 0) {
        $role = ''; // invalid role
    }
    $roleCheck->close();
}

if (!$id || !$role) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Invalid parameters"]);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Internal server error"]);
    exit;
}

$stmt->bind_param("si", $role, $id);
if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Role updated"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Failed to update role"]);
}

$stmt->close();

?>
