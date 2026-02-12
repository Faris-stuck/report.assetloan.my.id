<?php
require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['admin','manager','pic_barang','user']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit;
}

$user_id = (int)SessionValidator::getUserId();
if (!$user_id) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "Not authenticated"]);
    exit;
}

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "No file uploaded"]);
    exit;
}

$f = $_FILES['avatar'];
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
if (!isset($allowed[$f['type']])) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Unsupported file type"]);
    exit;
}

$uploaddir = __DIR__ . '/../../assets/uploads/avatars';
if (!is_dir($uploaddir)) @mkdir($uploaddir, 0755, true);
$ext = $allowed[$f['type']];
$filename = sprintf('user_%d_%s.%s', $user_id, time(), $ext);
$dest = $uploaddir . '/' . $filename;

if (!move_uploaded_file($f['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Failed to save file"]);
    exit;
}

// Ensure avatar column exists
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if (!$colCheck || $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL DEFAULT NULL");
}

$relpath = 'assets/uploads/avatars/' . $filename;
$u = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
$u->bind_param("si", $relpath, $user_id);
if (!$u->execute()) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Failed to update user record"]);
    exit;
}

echo json_encode(["status" => true, "message" => "Avatar uploaded", "avatar" => $relpath]);

?>
