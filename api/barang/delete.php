<?php
require_once "../koneksi.php";
header('Content-Type: application/json');
// Server-side session validation
require_once "../session-helper.php";

// Validate user role
try {
    SessionValidator::requireRole(['admin', 'pic_barang']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status" => false,
        "message" => "Unauthorized: " . $e->getMessage()
    ]);
    exit;
}



$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode([
        "status" => false,
        "message" => "Item ID not found"
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM barang WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    echo json_encode([
        "status" => true,
        "message" => "Item deleted successfully"
    ]);
} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Item cannot be deleted because it is in use"
    ]);
}
