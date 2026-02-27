<?php

date_default_timezone_set('Asia/Jakarta');

$host = $_SERVER['HTTP_HOST'] ?? '';

// config default
$db_host = 'localhost';
$db_name = 'peminjaman';

// DETEKSI LOCAL ATAU VPS
$is_local =
    strpos($host, 'localhost') !== false ||
    strpos($host, '127.0.0.1') !== false;

// CONFIG BERDASARKAN ENVIRONMENT
if ($is_local) {

    // LAPTOP
    $db_user = 'peminjaman_app';
    $db_pass = '';

} else {

    // VPS
    $db_user = 'peminjaman_app';
    $db_pass = 'PASSWORD_VPS_KAMU';

}

// CONNECT
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// CHECK ERROR
if ($conn->connect_error) {

    http_response_code(500);

    die("Database Error: " . $conn->connect_error);

}

// charset
$conn->set_charset('utf8mb4');

$conn->query("SET time_zone = '+07:00'");

