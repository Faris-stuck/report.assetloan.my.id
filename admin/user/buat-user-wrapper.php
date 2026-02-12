<?php
// Start session dan validasi admin
session_start();

// Redirect jika belum login
$user_role = $_SESSION['user_role'] ?? null;
$user_email = $_SESSION['user_email'] ?? null;

if (!$user_role || !in_array($user_role, ['admin'])) {
    // Return JSON error untuk AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Unauthorized: Silakan login sebagai admin']);
        exit;
    }
    // Redirect ke login untuk page load biasa
    header('Location: ../../auth/login.html');
    exit;
}

// Jika sudah tervalidasi, include file HTML
include 'buat-user.html';
?>
