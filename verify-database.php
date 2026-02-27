<?php
// Use centralized database configuration
require_once __DIR__ . '/config/database.php';

echo "✅ Database Connected\n\n";

// Check peminjaman table
$result = $conn->query('SELECT id, kode_peminjaman, nama_peminjam, status FROM peminjaman LIMIT 5');

if ($result && $result->num_rows > 0) {
    echo "✅ Sample Peminjaman Records Found:\n";
    echo str_repeat("=", 80) . "\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']} | Kode: {$row['kode_peminjaman']} | Nama: {$row['nama_peminjam']} | Status: {$row['status']}\n";
    }
    echo str_repeat("=", 80) . "\n\n";
} else {
    echo "⚠️  No peminjaman records found. Database may be empty.\n";
}

// Check detail_peminjaman
$result2 = $conn->query('SELECT COUNT(*) as count FROM detail_peminjaman');
$row2 = $result2->fetch_assoc();
echo "Detail Peminjaman Count: " . $row2['count'] . "\n";

// Check barang
$result3 = $conn->query('SELECT COUNT(*) as count FROM barang');
$row3 = $result3->fetch_assoc();
echo "Barang Count: " . $row3['count'] . "\n";

// Check users
$result4 = $conn->query('SELECT COUNT(*) as count FROM users');
$row4 = $result4->fetch_assoc();
echo "Users Count: " . $row4['count'] . "\n";

$conn->close();
echo "\n✅ All systems verified\n";
?>
