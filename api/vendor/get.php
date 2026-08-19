<?php
header('Content-Type: application/json');
require_once "../koneksi.php";

$q = $conn->query("SELECT id, nama_vendor, alamat, kontak FROM vendor ORDER BY nama_vendor ASC");

if (!$q) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Database query failed"]);
    exit;
}

$data = [];

while($d = $q->fetch_assoc()){
    $data[] = $d;
}

echo json_encode([
    "status"=>true,
    "data"=>$data
]);
