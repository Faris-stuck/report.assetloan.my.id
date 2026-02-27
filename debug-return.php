<?php
/**
 * Debug script untuk check status peminjaman dan detail items
 * Access: http://localhost/PROJECT/debug-return.php?peminjaman_id=81&user_id=1
 */

// Use centralized database configuration
require_once __DIR__ . '/config/database.php';

$peminjaman_id = $_GET['peminjaman_id'] ?? 81;
$user_id = $_GET['user_id'] ?? 1;

echo "<h2>DEBUG RETURN.PHP - Peminjaman ID: $peminjaman_id</h2>";

// 1. Check peminjaman
echo "<h3>1. Peminjaman Data:</h3>";
$query = "SELECT id, user_id, status, kode FROM peminjaman WHERE id = '$peminjaman_id' LIMIT 1";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) > 0) {
    $p = mysqli_fetch_assoc($result);
    echo "<pre>";
    print_r($p);
    echo "</pre>";
    echo "✓ Found | Status: <strong>" . $p['status'] . "</strong> | Owner: User ID " . $p['user_id'];
    if ($p['user_id'] != $user_id) {
        echo " <span style='color:red'>⚠️ NOT OWNED BY CURRENT USER!</span>";
    }
} else {
    echo "❌ NOT FOUND";
}

// 2. Check detail items
echo "<h3>2. Detail Items:</h3>";
$query = "SELECT * FROM detail_peminjaman WHERE peminjaman_id = '$peminjaman_id'";
$result = mysqli_query($conn, $query);
$item_count = mysqli_num_rows($result);
echo "Total Items: <strong>$item_count</strong><br>";
if ($item_count > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Barang ID</th><th>Qty</th><th>Status</th></tr>";
    while ($item = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $item['barang_id'] . "</td>";
        echo "<td>" . $item['jumlah'] . "</td>";
        echo "<td>" . ($item['status'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Check pengembalian records
echo "<h3>3. Pengembalian Records:</h3>";
$query = "SELECT * FROM pengembalian WHERE peminjaman_id = '$peminjaman_id' ORDER BY id DESC";
$result = mysqli_query($conn, $query);
$return_count = mysqli_num_rows($result);
echo "Pending Returns: <strong>$return_count</strong><br>";
if ($return_count > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Return ID</th><th>Kode</th><th>Status</th><th>Created At</th></tr>";
    while ($pgk = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $pgk['id'] . "</td>";
        echo "<td>" . $pgk['kode_pengembalian'] . "</td>";
        echo "<td>" . $pgk['status'] . "</td>";
        echo "<td>" . ($pgk['created_at'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 4. Determine what the API would return
echo "<h3>4. API Logic Analysis:</h3>";
if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM peminjaman WHERE id = '$peminjaman_id' LIMIT 1")) === 0) {
    echo "❌ Would return 404 - Peminjaman not found";
} else {
    $p_result = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM peminjaman WHERE id = '$peminjaman_id' LIMIT 1"));
    $allowed = ['Sedang Dipinjam', 'Sebagian Dikembalikan', 'Proses Return'];
    if (!in_array($p_result['status'], $allowed)) {
        echo "❌ Would return 400 - Status '" . $p_result['status'] . "' not allowed<br>";
        echo "Allowed statuses: " . implode(", ", $allowed);
    } else {
        $existing_result = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM pengembalian WHERE peminjaman_id = '$peminjaman_id' AND status != 'Selesai' LIMIT 1"));
        if ($existing_result) {
            echo "❌ Would return 400 - Pengembalian pending dengan status '" . $existing_result['status'] . "'";
        } else {
            echo "✅ Would ALLOW return submission";
        }
    }
}

echo "<hr>";
echo "<p><small>Debug Info - Change query params: ?peminjaman_id=XX&user_id=YY</small></p>";

mysqli_close($conn);
?>

