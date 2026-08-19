<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../koneksi.php";

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$client_user_id = $input['user_id'] ?? null;
$client_role = $input['user_role'] ?? null;

// Verify session exists on server
$server_user_id = $_SESSION['user_id'] ?? null;
$server_role = $_SESSION['user_role'] ?? null;

if (!$server_user_id || !$server_role) {
    // No session on server
    http_response_code(401);
    echo json_encode(["error" => "Session not valid on server"]);
    exit;
}

// Verify client values match server values
if ($client_user_id != $server_user_id || $client_role != $server_role) {
    // Mismatch - possible session hijacking
    http_response_code(401);
    echo json_encode(["error" => "Session mismatch"]);
    exit;
}

// Verify user still exists in database with same role
$stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $server_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User deleted from database
    http_response_code(401);
    echo json_encode(["error" => "User not found"]);
    exit;
}

$db_user = $result->fetch_assoc();
if ($db_user['role'] !== $server_role) {
    // User role changed
    http_response_code(401);
    echo json_encode(["error" => "Role user berubah"]);
    exit;
}

// Session valid
http_response_code(200);
echo json_encode(["status" => "valid"]);
?>
