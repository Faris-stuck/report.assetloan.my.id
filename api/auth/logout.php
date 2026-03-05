<?php
/**
 * Logout API Endpoint
 * - Destroy session completely
 * - Clear session cookies
 * - Return JSON response
 * - Handle both POST requests
 */

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Only accept POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Method not allowed"
    ]);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store message before destroying session
$previous_id = session_id();

// Unset all session variables
$_SESSION = [];
session_unset();

// Destroy the session
session_destroy();

// Also manually clear session files if needed
if (ini_get('session.save_handler') === 'files') {
    $sess_path = ini_get('session.save_path');
    $session_file = "{$sess_path}/sess_{$previous_id}";
    if (file_exists($session_file)) {
        @unlink($session_file);
    }
}

// Clear the main session cookie
$session_name = session_name();
if (isset($_COOKIE[$session_name])) {
    setcookie($session_name, '', array(
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ));
    unset($_COOKIE[$session_name]);
}

// Also clear PHPSESSID explicitly as fallback
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', array(
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ));
    unset($_COOKIE['PHPSESSID']);
}

// Return success response
http_response_code(200);
echo json_encode([
    "status" => true,
    "message" => "Logout successful. Session cleared.",
    "timestamp" => date('Y-m-d H:i:s')
]);
?>

