<?php
require_once "../koneksi.php";

$q = $conn->query("SELECT id, nama_vendor, alamat, kontak FROM vendor ORDER BY nama_vendor ASC");

$data = [];

while($d = $q->fetch_assoc()){
    $data[] = $d;
}

echo json_encode([
    "status"=>true,
    "data"=>$data
]);
