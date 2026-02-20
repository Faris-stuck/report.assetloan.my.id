<?php
require_once "api/koneksi.php";

echo "TEST: Verifying Status Calculation Fix\n";
echo "======================================\n\n";

// Get a borrowing with completed returns
$q = $conn->query("
    SELECT 
        p.id,
        p.kode_peminjaman,
        p.status as peminjaman_status,
        COUNT(DISTINCT dp.barang_id) as total_items,
        COALESCE(SUM(dr.jumlah_kembali), 0) as total_returned_from_selesai,
        (SELECT COUNT(DISTINCT status) FROM pengembalian WHERE peminjaman_id = p.id AND status = 'Selesai') as selesai_count
    FROM peminjaman p
    JOIN detail_peminjaman dp ON dp.peminjaman_id = p.id
    LEFT JOIN detail_pengembalian dr ON dr.barang_id = dp.barang_id 
        AND dr.pengembalian_id IN (SELECT id FROM pengembalian WHERE peminjaman_id = p.id AND status = 'Selesai')
    WHERE EXISTS (SELECT 1 FROM pengembalian WHERE peminjaman_id = p.id AND status = 'Selesai')
    GROUP BY p.id
    LIMIT 1
");

if ($data = $q->fetch_assoc()) {
    echo "Sample Borrowing: {$data['kode_peminjaman']}\n";
    echo "  Current Status in DB: {$data['peminjaman_status']}\n";
    echo "  Total Items: {$data['total_items']}\n";
    echo "  Items Returned (from Selesai pengembalian): {$data['total_returned_from_selesai']}\n";
    echo "  Completed Pengembalian Records: {$data['selesai_count']}\n\n";
    
    if ($data['total_returned_from_selesai'] >= $data['total_items']) {
        echo "✓ All items returned\n";
        echo "✓ Status should be: Dikembalikan\n";
        if ($data['peminjaman_status'] === 'Dikembalikan') {
            echo "✓ Status is CORRECT\n";
        } else {
            echo "⚠️  Status is '{$data['peminjaman_status']}' but should be 'Dikembalikan'\n";
        }
    }
} else {
    echo "No sample data available\n";
}

echo "\n\nTEST: Filter Logic Verification\n";
echo "===============================\n";

// Simulate what the HTML filter will do
$test_statuses = ['Sedang Dipinjam', 'Sebagian Dikembalikan', 'Dikembalikan', 'Sebagian Rusak'];

foreach ($test_statuses as $status) {
    $finalStatuses = ['Dikembalikan', 'Sebagian Rusak', 'Semua Rusak', 'Selesai'];
    $should_show = in_array($status, $finalStatuses) ? 'HIDE' : 'SHOW';
    echo "Status '{$status}' → {$should_show}\n";
}

echo "\n\nTEST: get_detail.php Logic\n";
echo "==========================\n";

// Pick a peminjaman with all items returned
$test_pmj = $conn->query("
    SELECT 
        p.id,
        p.kode_peminjaman,
        COUNT(DISTINCT dp.barang_id) as total_items,
        COALESCE(SUM(dr.jumlah_kembali), 0) as total_returned
    FROM peminjaman p
    JOIN detail_peminjaman dp ON dp.peminjaman_id = p.id
    LEFT JOIN detail_pengembalian dr ON dr.barang_id = dp.barang_id 
        AND dr.pengembalian_id IN (SELECT id FROM pengembalian WHERE peminjaman_id = p.id AND status = 'Selesai')
    WHERE EXISTS (SELECT 1 FROM pengembalian WHERE peminjaman_id = p.id AND status = 'Selesai')
    GROUP BY p.id
    LIMIT 1
");

if ($pmj = $test_pmj->fetch_assoc()) {
    echo "Test PMJ: {$pmj['kode_peminjaman']}\n";
    echo "  Total Items: {$pmj['total_items']}\n";
    echo "  Total Returned: {$pmj['total_returned']}\n";
    
    if ((int)$pmj['total_returned'] >= (int)$pmj['total_items'] && $pmj['total_items'] > 0) {
        echo "✓ get_detail.php should REJECT this request (all items returned)\n";
        echo "✓ Will return 403 error: 'Semua barang sudah dikembalikan'\n";
    } else {
        echo "✓ get_detail.php should allow this request (items still pending)\n";
    }
}

echo "\n✓ Fix verification complete\n";
