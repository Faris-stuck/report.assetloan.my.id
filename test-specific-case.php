<?php
require_once "api/koneksi.php";

// Search for the specific borrowing that might match the screenshot  
$q = $conn->query("
    SELECT 
        p.id,
        p.kode_peminjaman,
        p.nama_peminjam,
        p.nrp,
        p.status as peminjaman_status,
        k.id as latest_pengembalian_id,
        k.status as latest_pengembalian_status,
        COUNT(DISTINCT dp.barang_id) as total_items,
        COALESCE(SUM(dr_selesai.jumlah_kembali), 0) as total_selesai_items,
        COALESCE(SUM(dr_latest.jumlah_kembali), 0) as total_latest_items
    FROM peminjaman p
    LEFT JOIN detail_peminjaman dp ON dp.peminjaman_id = p.id
    LEFT JOIN pengembalian k ON k.peminjaman_id = p.id AND k.id = (
        SELECT MAX(id) FROM pengembalian WHERE peminjaman_id = p.id
    )
    LEFT JOIN detail_pengembalian dr_selesai ON dr_selesai.pengembalian_id IN (
        SELECT id FROM pengembalian WHERE peminjaman_id = p.id AND status = 'Selesai'
    )
    LEFT JOIN detail_pengembalian dr_latest ON dr_latest.pengembalian_id = k.id
    WHERE EXISTS (SELECT 1 FROM pengembalian WHERE peminjaman_id = p.id)
    AND p.status != 'Dikembalikan'
    GROUP BY p.id
");

echo "Peminjaman yang MASIH berstatus Sedang/Sebagian dengan pengembalian:\n";
echo "===================================================================\n\n";

while($row = $q->fetch_assoc()) {
    echo "PMJ: {$row['kode_peminjaman']}\n";
    echo "  Peminjam: {$row['nama_peminjam']} ({$row['nrp']})\n";
    echo "  Peminjaman Status: {$row['peminjaman_status']}\n";
    echo "  Latest Pengembalian: ID {$row['latest_pengembalian_id']}, Status: {$row['latest_pengembalian_status']}\n";
    echo "  Items: {$row['total_items']} total\n";
    echo "  Dikembalikan via Selesai: {$row['total_selesai_items']}\n";
    echo "  Dikembalikan via Latest: {$row['total_latest_items']}\n";
    echo "\n";
}

echo "\n\nDETAIL: Semua pengembalian untuk setiap peminjaman:\n";
echo "====================================================\n";

$all = $conn->query("
    SELECT 
        p.id as peminjaman_id,
        p.kode_peminjaman,
        p.status as peminjaman_status,
        k.id as pengembalian_id,
        k.status as pengembalian_status,
        COUNT(DISTINCT dr.barang_id) as items_dalam_pengembalian
    FROM peminjaman p
    LEFT JOIN pengembalian k ON k.peminjaman_id = p.id
    LEFT JOIN detail_pengembalian dr ON dr.pengembalian_id = k.id
    WHERE EXISTS (SELECT 1 FROM pengembalian WHERE peminjaman_id = p.id)
    AND p.status != 'Dikembalikan'
    GROUP BY p.id, k.id
    ORDER BY p.id, k.id
");

while($row = $all->fetch_assoc()) {
    if ($row['pengembalian_id']) {
        echo "PMJ {$row['kode_peminjaman']} → Pengembalian #{$row['pengembalian_id']} (Status: {$row['pengembalian_status']}) - {$row['items_dalam_pengembalian']} items\n";
    }
}
