<?php
header('Content-Type: application/json');
require_once "../koneksi.php";

$nama = $_POST['nama'] ?? '';
$nrp = $_POST['nrp'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Validate input
if (!$nama || !$nrp || !$email || !$password) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "All fields are required"
    ]);
    exit;
}

// Check if email is already registered
$check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if (!$check_stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Internal server error"
    ]);
    exit;
}

$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result && $check_result->num_rows > 0) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Email already registered. Use a different email or log in."
    ]);
    exit;
}

$check_stmt->close();

// Hash password with bcrypt
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new user with default role 'user'
$insert_stmt = $conn->prepare("INSERT INTO users (nama, nrp, email, password, role) VALUES (?, ?, ?, ?, 'user')");
if (!$insert_stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Internal server error"
    ]);
    exit;
}

$insert_stmt->bind_param("ssss", $nama, $nrp, $email, $hashed_password);

if ($insert_stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Registration successful. Please log in."
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Registration failed. Please try again later."
    ]);
}

$insert_stmt->close();
?>
