<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once 'config/database.php';

// Simple test without session validation
$conn_test = new mysqli('localhost', 'peminjaman_app', '', 'peminjaman');

if ($conn_test->connect_error) {
    die("Connection Error: " . $conn_test->connect_error);
}

echo "=== Database Connection Test ===\n";
echo "Status: Connected to 'peminjaman' database\n\n";

echo "=== Transaction Count by Status ===\n";
$query = "SELECT status, COUNT(*) as total FROM peminjaman GROUP BY status";
$result = $conn_test->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['status'] . ": " . $row['total'] . " transactions\n";
    }
} else {
    echo "Query Error: " . $conn_test->error;
}

echo "\n=== Specific Status Counts ===\n";

$statuses = ['Menunggu Persetujuan', 'Sedang Dipinjam', 'Dikembalikan', 'Ditolak', 'Sebagian Dikembalikan', 'Due Tomorrow', 'Due In 7 Days', 'Overdue'];

foreach ($statuses as $status) {
    $stmt = $conn_test->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE status = ?");
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    echo "$status: " . $row['total'] . "\n";
    $stmt->close();
}

$conn_test->close();
?>
