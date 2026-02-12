<?php
require_once "../koneksi.php";

$q = $conn->query("SELECT id, nama_vendor FROM vendor");

$data = [];

while($d = $q->fetch_assoc()){
    $data[] = $d;
}

echo json_encode([
    "status"=>true,
    "data"=>$data
]);
