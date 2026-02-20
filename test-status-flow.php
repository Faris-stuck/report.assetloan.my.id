<?php
require_once "api/koneksi.php";

// Find peminjaman that have pengembalian
$q = $conn->query("
    SELECT 
        p.id,
        p.kode_peminjaman,
        p.status as peminjaman_status,
        k.id as pengembalian_id,
        k.status as pengembalian_status,
        GROUP_CONCAT(DISTINCT dr.barang_id) as barang_ids,
        COUNT(DISTINCT dp.barang_id) as total_items,
        COALESCE(SUM(dr.jumlah_kembali), 0) as total_returned
    FROM peminjaman p
    LEFT JOIN pengembalian k ON k.peminjaman_id = p.id
    LEFT JOIN detail_pengembalian dr ON dr.pengembalian_id = k.id
    JOIN detail_peminjaman dp ON dp.peminjaman_id = p.id
    WHERE p.id IN (SELECT peminjaman_id FROM pengembalian)
    GROUP BY p.id, k.id
    LIMIT 5
");

echo "Peminjaman with Pengembalian Status:\n";
echo "====================================\n\n";

while($row = $q->fetch_assoc()) {
    echo "Peminjaman: {$row['kode_peminjaman']} (ID:{$row['id']})\n";
    echo "  Status: {$row['peminjaman_status']}\n";
    echo "  Pengembalian ID: {$row['pengembalian_id']}\n";
    echo "  Pengembalian Status: {$row['pengembalian_status']}\n";
    echo "  Items: {$row['total_items']} total, {$row['total_returned']} returned\n";
    echo "  ---\n\n";
}

// Check specific case: fully returned but still showing as Sebagian Dikembalikan
$check = $conn->query("
    SELECT 
        p.id,
        p.kode_peminjaman,
        p.status,
        k.status as pengembalian_status,
        COUNT(DISTINCT dp.barang_id) as total_pinjam,
        COALESCE(SUM(dr.jumlah_kembali), 0) as total_kembali,
        COUNT(DISTINCT k.id) as pengembalian_count
    FROM peminjaman p
    LEFT JOIN pengembalian k ON k.peminjaman_id = p.id
    LEFT JOIN detail_pengembalian dr ON dr.pengembalian_id = k.id AND k.status = 'Selesai'
    JOIN detail_peminjaman dp ON dp.peminjaman_id = p.id
    WHERE p.status IN ('Sedang Dipinjam', 'Sebagian Dikembalikan')
    AND EXISTS (SELECT 1 FROM pengembalian WHERE peminjaman_id = p.id AND status = 'Selesai')
    GROUP BY p.id
");

echo "\nCases: Status=Sedang/Sebagian BUT has Selesai pengembalian:\n";
echo "=========================================================\n";

$found_issue = false;
while($row = $check->fetch_assoc()) {
    if ($row['total_kembali'] >= $row['total_pinjam']) {
        $found_issue = true;
        echo "⚠️  ISSUE: {$row['kode_peminjaman']}\n";
        echo "     Status: {$row['status']}\n";
        echo "     All {$row['total_pinjam']} items returned, but showing as '{$row['status']}'\n";
        echo "     Pengembalian records: {$row['pengembalian_count']}\n\n";
    }
}

if (!$found_issue) {
    echo "✓ No issues found.\n";
}
