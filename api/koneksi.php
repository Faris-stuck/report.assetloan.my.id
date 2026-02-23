<?php
/**
 * Database Connection — Auto-detect localhost vs VPS
 */
$host = "localhost";
$database = "peminjaman";

if ($_SERVER['HTTP_HOST'] == "localhost" || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    // Localhost / XAMPP
    $user = "root";
    $password = "";
} else {
    // VPS / Production — sesuaikan credentials di sini
    $user = "root";
    $password = "";
}

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Koneksi DB gagal"]);
    exit;
}
