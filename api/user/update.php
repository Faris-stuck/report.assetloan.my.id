<?php

/**
 * API: Update User Data
 * Purpose: Update user nama, nrp, and/or role
 * Endpoint: /api/user/update.php
 * Method: POST
 * Parameters: id, nama (optional), nrp (optional), role (optional)
 */

header('Content-Type: application/json');
require_once "../koneksi.php";
require_once "../session-helper.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    SessionValidator::requireRole(['admin']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Unauthorized: " . $e->getMessage()]);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nama = isset($_POST['nama']) ? trim($_POST['nama']) : null;
$nrp = isset($_POST['nrp']) ? trim($_POST['nrp']) : null;
$role = isset($_POST['role']) ? trim($_POST['role']) : null;

// Validate user ID
if (!$id) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Invalid user ID"]);
    exit;
}

// Check if user exists
$userCheck = $conn->prepare("SELECT id FROM users WHERE id = ?");
$userCheck->bind_param('i', $id);
$userCheck->execute();
if ($userCheck->get_result()->num_rows === 0) {
    $userCheck->close();
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "User not found"]);
    exit;
}
$userCheck->close();

// Validate role if provided
if ($role) {
    $roleCheck = $conn->prepare("SELECT role_name FROM roles WHERE role_name = ?");
    $roleCheck->bind_param('s', $role);
    $roleCheck->execute();
    if ($roleCheck->get_result()->num_rows === 0) {
        $roleCheck->close();
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "Invalid role"]);
        exit;
    }
    $roleCheck->close();
}

// Build UPDATE query dynamically based on provided fields
$updates = [];
$types = '';
$params = [];

if ($nama !== null) {
    $updates[] = "nama = ?";
    $types .= 's';
    $params[] = $nama;
}

if ($nrp !== null) {
    $updates[] = "nrp = ?";
    $types .= 's';
    $params[] = $nrp;
}

if ($role !== null) {
    $updates[] = "role = ?";
    $types .= 's';
    $params[] = $role;
}

// If nothing to update, return error
if (empty($updates)) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "No fields to update"]);
    exit;
}

// Add user ID to params
$types .= 'i';
$params[] = $id;

// Build and execute query
$query = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Internal server error"]);
    exit;
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "User updated successfully"
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Failed to update user"]);
}

$stmt->close();
