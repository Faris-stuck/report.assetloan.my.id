<?php
require_once "api/koneksi.php";

$result = $conn->query("SELECT id, kode_pengembalian, status FROM pengembalian ORDER BY id DESC LIMIT 15");
if (!$result) {
    echo "Query error: " . $conn->error;
    exit;
}

echo "Pengembalian records in database:\n";
echo str_repeat("=", 60) . "\n";
printf("%-5s | %-15s | %-15s\n", "ID", "KODE", "STATUS");
echo str_repeat("-", 60) . "\n";

while ($row = $result->fetch_assoc()) {
    printf("%-5s | %-15s | %-15s\n", $row['id'], $row['kode_pengembalian'], $row['status']);
}
