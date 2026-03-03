<?php
/**
 * Test Script for Profile Header Standardization
 * Tests the API endpoint and JavaScript integration
 */

// Start session
session_start();

// Simulate logged-in user for testing
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['user_nama'] = 'Test Admin';
$_SESSION['user_email'] = 'admin@test.com';

require_once 'api/koneksi.php';
require_once 'api/session-helper.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile Header Test</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/theme.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <style>
        body { padding: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .header { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Profile Header Standardization Test</h1>
        
        <div class="test-section">
            <h3>1. API Endpoint Test</h3>
            <p>Testing: /api/user/get-current-user.php</p>
            <?php
            $ch = curl_init('http://localhost/PROJECT/api/user/get-current-user.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            if ($data && isset($data['success']) && $data['success']) {
                echo '<p class="success">✓ API Response Success</p>';
                echo '<pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
            } else {
                echo '<p class="error">✗ API Response Failed</p>';
                echo '<pre>' . htmlspecialchars($response) . '</pre>';
            }
            ?>
        </div>
        
        <div class="test-section">
            <h3>2. Profile Header Display Test</h3>
            <p>Profile header with database values:</p>
            
            <header class="nxl-header" style="margin: 20px 0;">
                <div class="header-wrapper">
                    <div class="header-left"></div>
                    <div class="header-right ms-auto">
                        <div class="dropdown nxl-h-item user-profile-header" data-profile-header>
                            <div class="user-profile-info" data-bs-toggle="dropdown" role="button">
                                <div class="user-name" data-user-name>Loading...</div>
                                <div class="user-email" data-user-email></div>
                            </div>
                            <div class="dropdown-menu dropdown-menu-end user-profile-dropdown">
                                <a href="javascript:void(0);" data-logout class="dropdown-item">
                                    <i class="feather-log-out"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
        </div>
        
        <div class="test-section">
            <h3>3. Session Data</h3>
            <table class="table">
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>user_id</td>
                    <td><?= $_SESSION['user_id'] ?? 'Not set' ?></td>
                </tr>
                <tr>
                    <td>user_role</td>
                    <td><?= $_SESSION['user_role'] ?? 'Not set' ?></td>
                </tr>
                <tr>
                    <td>user_nama</td>
                    <td><?= $_SESSION['user_nama'] ?? 'Not set' ?></td>
                </tr>
                <tr>
                    <td>user_email</td>
                    <td><?= $_SESSION['user_email'] ?? 'Not set' ?></td>
                </tr>
            </table>
        </div>
        
        <div class="test-section">
            <h3>4. Database Connection Test</h3>
            <?php
            $test_query = "SELECT COUNT(*) as count FROM users LIMIT 1";
            $result = $conn->query($test_query);
            if ($result) {
                $row = $result->fetch_assoc();
                echo '<p class="success">✓ Database connection successful</p>';
                echo '<p>Users table accessible: ' . ($row['count'] > 0 ? 'Yes' : 'No records') . '</p>';
            } else {
                echo '<p class="error">✗ Database connection failed</p>';
                echo '<p>' . $conn->error . '</p>';
            }
            ?>
        </div>
        
        <div class="test-section">
            <h3>5. Files Check</h3>
            <?php
            $files_to_check = [
                'api/user/get-current-user.php',
                'assets/js/profile-header.js',
                'assets/js/auth/logout.js',
            ];
            
            foreach ($files_to_check as $file) {
                if (file_exists($file)) {
                    echo '<p class="success">✓ ' . $file . ' - EXISTS</p>';
                } else {
                    echo '<p class="error">✗ ' . $file . ' - MISSING</p>';
                }
            }
            ?>
        </div>
    </div>
    
    <script src="assets/js/base-url.js"></script>
    <script src="assets/js/profile-header.js"></script>
    <script src="assets/vendors/js/vendors.min.js"></script>
    
    <script>
        // Debug log
        console.log('Profile Header Test Script Loaded');
        console.log('BASE_URL:', window.BASE_URL);
        
        // Manual test
        async function testProfileHeaderManually() {
            try {
                const response = await fetch(BASE_URL + '/api/user/get-current-user.php');
                const data = await response.json();
                console.log('API Response:', data);
                
                if (data.success) {
                    console.log('✓ Test passed - user data fetched');
                    console.log('  Name:', data.nama);
                    console.log('  Email:', data.email);
                } else {
                    console.error('✗ Test failed - API error');
                }
            } catch (error) {
                console.error('✗ Test failed - fetch error:', error);
            }
        }
        
        // Run test when ready
        document.addEventListener('DOMContentLoaded', testProfileHeaderManually);
    </script>
</body>
</html>
