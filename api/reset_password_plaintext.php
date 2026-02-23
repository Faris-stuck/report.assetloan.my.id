<?php
/**
 * Reset All Passwords to Plaintext Default
 * ═══════════════════════════════════════════════════════════════
 * Development/Testing Only
 * 
 * Converts all hashed passwords in database to plaintext default: 123456
 * This is for development/testing environments ONLY
 * 
 * Run via browser or command line:
 * - http://localhost/PROJECT/api/reset_password_plaintext.php
 * - php api/reset_password_plaintext.php
 */

require_once "koneksi.php";

$default_password = "123456";

try {
    // Update all users with default plaintext password
    $sql = "UPDATE users SET password = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $default_password);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Password reset berhasil",
        "details" => [
            "default_password" => $default_password,
            "users_updated" => $affected,
            "note" => "Semua password telah direset ke plaintext mode"
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Reset password gagal",
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>
