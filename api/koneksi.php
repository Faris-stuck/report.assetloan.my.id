<?php
$conn = new mysqli("localhost", "root", "", "peminjaman");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Koneksi DB gagal"]);
    exit;
}
