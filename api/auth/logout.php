<?php
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy all session data
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Return success
http_response_code(200);
echo json_encode(["message" => "Logout berhasil"]);
?>
