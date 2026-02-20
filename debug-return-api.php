<?php
/**
 * Debug script untuk test API return.php
 */

// Simulate session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simulating logged-in user
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'user';
$_SESSION['user_nama'] = 'Test User';

echo "=== Debugging return.php API ===\n\n";

// Check koneksi.php
echo "1. Checking koneksi.php...\n";
if (file_exists('api/koneksi.php')) {
    echo "   ✓ koneksi.php exists\n";
    ob_start();
    require_once 'api/koneksi.php';
    $output = ob_get_clean();
    if (!empty($output)) {
        echo "   ⚠ WARNING: koneksi.php output something: " . substr($output, 0, 100) . "\n";
    } else {
        echo "   ✓ koneksi.php loaded silently\n";
    }
} else {
    echo "   ✗ koneksi.php NOT FOUND\n";
}

// Check session-helper.php
echo "\n2. Checking session-helper.php...\n";
if (file_exists('api/session-helper.php')) {
    echo "   ✓ session-helper.php exists\n";
    ob_start();
    require_once 'api/session-helper.php';
    $output = ob_get_clean();
    if (!empty($output)) {
        echo "   ⚠ WARNING: session-helper.php output something: " . substr($output, 0, 100) . "\n";
    } else {
        echo "   ✓ session-helper.php loaded silently\n";
    }
} else {
    echo "   ✗ session-helper.php NOT FOUND\n";
}

// Check return.php syntax
echo "\n3. Checking return.php syntax...\n";
$syntax_check = shell_exec('php -l api/peminjaman/return.php 2>&1');
echo "   " . trim($syntax_check) . "\n";

// Try to parse and check return.php
echo "\n4. Checking return.php for potential issues...\n";
$return_content = file_get_contents('api/peminjaman/return.php');

// Check for common issues
$issues = [];

if (strpos($return_content, 'error_log(') !== false) {
    echo "   ✓ Error logging found (good for debugging)\n";
}

if (strpos($return_content, '$conn->begin_transaction()') !== false) {
    echo "   ✓ Transaction handling found\n";
}

if (preg_match('/\$insd\s*=\s*\$conn->prepare/', $return_content)) {
    echo "   ✓ Detail insertion prepared statement found\n";
}

// Check for potential undefined variables
if (strpos($return_content, 'SessionValidator::requireRole') !== false) {
    echo "   ✓ SessionValidator role check found\n";
}

echo "\n5. Checking database tables...\n";
if (isset($conn)) {
    // Check tables existence
    $tables = ['pengembalian', 'detail_pengembalian', 'peminjaman', 'users'];
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            echo "   ✓ Table '$table' exists\n";
        } else {
            echo "   ✗ Table '$table' NOT FOUND\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Check the logs at: /opt/lampp/logs/php_error.log\n";
echo "Or monitor with: tail -f /opt/lampp/logs/php_error.log\n";
?>
