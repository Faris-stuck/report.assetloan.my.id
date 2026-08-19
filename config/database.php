<?php

// Security: Suppress error display to prevent information leakage
error_reporting(0);
ini_set('display_errors', 0);

date_default_timezone_set('Asia/Jakarta');

$rawHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$host = strtolower(trim(explode(':', $rawHost)[0]));

// Config defaults (override with environment variables when needed)
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'peminjaman';

$isPrivateIpv4 = (bool) preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host);
$isLocalHostname = $host !== '' && strpos($host, '.') === false && !is_numeric(str_replace('-', '', $host));
$isLocalDomain = substr($host, -6) === '.local';

// DETEKSI LOCAL ATAU VPS
$is_local =
    $host === '' ||
    $host === 'localhost' ||
    $host === '127.0.0.1' ||
    $host === '::1' ||
    $isPrivateIpv4 ||
    $isLocalHostname ||
    $isLocalDomain;

// CONFIG BERDASARKAN ENVIRONMENT
if ($is_local) {

    // Local default (XAMPP/WAMP default)
    $db_user = getenv('DB_USER_LOCAL') ?: (getenv('DB_USER') ?: 'root');
    $db_pass = getenv('DB_PASSWORD_LOCAL');
    if ($db_pass === false) {
        $db_pass = getenv('DB_PASSWORD');
    }
    if ($db_pass === false) {
        $db_pass = '';
    }
} else {

    // VPS / production (set via env)
    $db_user = getenv('DB_USER') ?: 'peminjaman_app';
    $db_pass = getenv('DB_PASSWORD') ?: '';
}

// CONNECT
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// CHECK ERROR
if ($conn->connect_error) {

    http_response_code(500);

    header('Content-Type: application/json');
    die(json_encode(["status" => false, "message" => "Database connection failed"]));
}

// charset
$conn->set_charset('utf8mb4');

$conn->query("SET time_zone = '+07:00'");
