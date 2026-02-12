<?php
require_once "../koneksi.php";
header('Content-Type: application/json');
// Server-side session validation
require_once "../session-helper.php";

// Validate user role
try {
    SessionValidator::requireRole(['admin']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status" => false,
        "message" => "Unauthorized: " . $e->getMessage()
    ]);
    exit;
}



$stmt = $conn->prepare("
    SELECT id, nama, nrp, email, role, created_at
    FROM users
    ORDER BY id DESC
");
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    // Tampilkan role sebagai label: user=Requester, manager=Approver
            $row['role_label'] = $row['role'] === 'user' ? 'Requester' : ($row['role'] === 'manager' ? 'Approver' : ($row['role'] === 'admin' ? 'Admin' : $row['role']));
            $data[] = $row;
}

echo json_encode([
    "status" => true,
    "data" => $data
]);
