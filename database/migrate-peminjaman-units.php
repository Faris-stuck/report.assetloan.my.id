<?php
/**
 * Migration: Populate peminjaman_units table from existing aggregate data
 * 
 * This creates one row per unit per detail_peminjaman, with:
 * - return_status derived from detail_pengembalian (finalized returns)
 * - expected_return from detail_peminjaman or extends
 * - unit_display from "Unit X of Y"
 * - kondisi_kembali/tanggal_kembali from return records
 * 
 * Run once to backfill. After this, the system should maintain peminjaman_units 
 * directly when borrowings are created, returned, or extended.
 */
require_once __DIR__ . '/../api/koneksi.php';

echo "=== Migrating peminjaman_units from existing data ===\n\n";

// Clear existing data (safe re-run)
$conn->query("TRUNCATE TABLE peminjaman_units");
echo "Cleared existing peminjaman_units data.\n";

// 1. Get all detail_peminjaman with peminjaman header info
$sql = "
    SELECT 
        dp.id AS detail_id,
        dp.peminjaman_id,
        dp.barang_id,
        dp.jumlah,
        dp.kondisi_pinjam,
        COALESCE(dp.expected_return, p.rencana_kembali) AS base_expected_return,
        p.status AS peminjaman_status,
        p.tanggal_kembali AS peminjaman_tanggal_kembali
    FROM detail_peminjaman dp
    JOIN peminjaman p ON dp.peminjaman_id = p.id
    ORDER BY dp.peminjaman_id, dp.id
";
$details = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// 2. Build return map: barang_id per peminjaman -> total returned/rusak
$returnSql = "
    SELECT 
        pen.peminjaman_id,
        dr.barang_id,
        COALESCE(SUM(dr.jumlah_kembali), 0) AS total_returned,
        COALESCE(SUM(dr.jumlah_rusak), 0) AS total_rusak,
        MAX(dr.kondisi_kembali) AS last_kondisi,
        MAX(pen.selesai_at) AS last_return_date
    FROM detail_pengembalian dr
    JOIN pengembalian pen ON dr.pengembalian_id = pen.id
    WHERE pen.status = 'Selesai'
    GROUP BY pen.peminjaman_id, dr.barang_id
";
$returnRows = $conn->query($returnSql)->fetch_all(MYSQLI_ASSOC);
$returnMap = [];
foreach ($returnRows as $r) {
    $key = $r['peminjaman_id'] . '_' . $r['barang_id'];
    $returnMap[$key] = $r;
}

// 3. Build pending return map (Diajukan/Dicek)
$pendingReturnSql = "
    SELECT 
        pen.peminjaman_id,
        dr.barang_id,
        COALESCE(SUM(dr.jumlah_kembali), 0) AS total_pending
    FROM detail_pengembalian dr
    JOIN pengembalian pen ON dr.pengembalian_id = pen.id
    WHERE pen.status IN ('Diajukan', 'Dicek')
    GROUP BY pen.peminjaman_id, dr.barang_id
";
$pendingRows = $conn->query($pendingReturnSql)->fetch_all(MYSQLI_ASSOC);
$pendingMap = [];
foreach ($pendingRows as $r) {
    $key = $r['peminjaman_id'] . '_' . $r['barang_id'];
    $pendingMap[$key] = (int)$r['total_pending'];
}

// 4. Build extend map: detail_peminjaman_id + unit_number -> approved extend date
$extendSql = "
    SELECT 
        epi.detail_peminjaman_id,
        epi.unit_number,
        epi.tanggal_perpanjang,
        ep.status AS extend_status
    FROM extend_peminjaman_items epi
    JOIN extend_peminjaman ep ON epi.extend_peminjaman_id = ep.id
    WHERE ep.status = 'Approved'
    ORDER BY ep.id DESC
";
$extendRows = $conn->query($extendSql)->fetch_all(MYSQLI_ASSOC);
$extendMap = [];
foreach ($extendRows as $r) {
    $key = $r['detail_peminjaman_id'] . '_' . $r['unit_number'];
    // Only keep the latest approved extend
    if (!isset($extendMap[$key])) {
        $extendMap[$key] = $r['tanggal_perpanjang'];
    }
}

// Also check for blanket extends (no items specified = applies to all)
$blanketExtendSql = "
    SELECT 
        ep.peminjaman_id,
        ep.tanggal_perpanjang
    FROM extend_peminjaman ep
    LEFT JOIN extend_peminjaman_items epi ON epi.extend_peminjaman_id = ep.id
    WHERE ep.status = 'Approved' AND epi.id IS NULL
    ORDER BY ep.id DESC
";
$blanketRows = $conn->query($blanketExtendSql)->fetch_all(MYSQLI_ASSOC);
$blanketExtendMap = [];
foreach ($blanketRows as $r) {
    if (!isset($blanketExtendMap[$r['peminjaman_id']])) {
        $blanketExtendMap[$r['peminjaman_id']] = $r['tanggal_perpanjang'];
    }
}

// 5. Insert units
$insertStmt = $conn->prepare("
    INSERT INTO peminjaman_units 
    (peminjaman_id, detail_peminjaman_id, barang_id, unit_number, unit_display, 
     return_status, expected_return, kondisi_kembali, tanggal_kembali)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$totalInserted = 0;

foreach ($details as $detail) {
    $peminjaman_id = (int)$detail['peminjaman_id'];
    $detail_id = (int)$detail['detail_id'];
    $barang_id = (int)$detail['barang_id'];
    $qty = (int)$detail['jumlah'];
    $base_expected = $detail['base_expected_return'];
    $pStatus = $detail['peminjaman_status'];
    $pTanggalKembali = $detail['peminjaman_tanggal_kembali'];

    // Get return data for this barang in this peminjaman
    $rKey = $peminjaman_id . '_' . $barang_id;
    $totalReturned = isset($returnMap[$rKey]) ? (int)$returnMap[$rKey]['total_returned'] : 0;
    $totalRusak = isset($returnMap[$rKey]) ? (int)$returnMap[$rKey]['total_rusak'] : 0;
    $lastReturnDate = isset($returnMap[$rKey]) ? $returnMap[$rKey]['last_return_date'] : null;
    $totalPending = isset($pendingMap[$rKey]) ? $pendingMap[$rKey] : 0;

    $goodReturned = $totalReturned - $totalRusak;

    for ($u = 1; $u <= $qty; $u++) {
        // Unit display
        $unit_display = "Unit {$u} of {$qty}";

        // Expected return: check per-unit extend first, then blanket extend, then base
        $expected_return = $base_expected;
        $extKey = $detail_id . '_' . $u;
        if (isset($extendMap[$extKey])) {
            $expected_return = $extendMap[$extKey];
        } elseif (isset($blanketExtendMap[$peminjaman_id])) {
            $expected_return = $blanketExtendMap[$peminjaman_id];
        }

        // Determine return_status and kondisi_kembali
        $return_status = 'Belum Dikembalikan';
        $kondisi_kembali = null;
        $tanggal_kembali = null;

        // If peminjaman is Ditolak, all units are Ditolak
        if ($pStatus === 'Ditolak') {
            $return_status = 'Ditolak';
        }
        // If peminjaman is fully returned (status = Dikembalikan)
        elseif ($pStatus === 'Dikembalikan' && $totalReturned >= $qty) {
            if ($u <= $goodReturned) {
                $return_status = 'Dikembalikan';
                $kondisi_kembali = 'Baik';
            } else {
                $return_status = 'Rusak';
                $kondisi_kembali = 'Rusak';
            }
            $tanggal_kembali = $lastReturnDate ? date('Y-m-d', strtotime($lastReturnDate)) : $pTanggalKembali;
        }
        // If unit is returned (within returned count)
        elseif ($u <= $totalReturned) {
            if ($u <= $goodReturned) {
                $return_status = 'Dikembalikan';
                $kondisi_kembali = 'Baik';
            } else {
                $return_status = 'Rusak';
                $kondisi_kembali = 'Rusak';
            }
            $tanggal_kembali = $lastReturnDate ? date('Y-m-d', strtotime($lastReturnDate)) : null;
        }
        // If unit is pending return
        elseif ($u <= $totalReturned + $totalPending) {
            $return_status = 'Proses Return';
        }
        // Not yet returned - check if still active
        elseif (in_array($pStatus, ['Menunggu Persetujuan'])) {
            $return_status = 'Menunggu Persetujuan';
        }
        elseif ($pStatus === 'Disetujui') {
            $return_status = 'Disetujui';
        }
        elseif (in_array($pStatus, ['Sedang Dipinjam', 'Proses Return', 'Sebagian Dikembalikan'])) {
            // Active loan - check due proximity
            $return_status = 'Dipinjam';
        }

        $insertStmt->bind_param(
            "iiiisssss",
            $peminjaman_id, $detail_id, $barang_id, $u, $unit_display,
            $return_status, $expected_return, $kondisi_kembali, $tanggal_kembali
        );

        if ($insertStmt->execute()) {
            $totalInserted++;
        } else {
            echo "ERROR inserting unit {$u} for detail {$detail_id}: {$conn->error}\n";
        }
    }
}

echo "\nMigration complete! Inserted {$totalInserted} unit records.\n";

// Summary
$countResult = $conn->query("SELECT COUNT(*) as cnt FROM peminjaman_units")->fetch_assoc();
echo "Total records in peminjaman_units: {$countResult['cnt']}\n";

// Show sample
echo "\n=== Sample data (peminjaman 83) ===\n";
$sample = $conn->query("
    SELECT pu.*, b.nama_barang, b.kode_barang
    FROM peminjaman_units pu
    JOIN barang b ON pu.barang_id = b.id
    WHERE pu.peminjaman_id = 83
    ORDER BY pu.unit_number
");
while ($row = $sample->fetch_assoc()) {
    echo "Unit {$row['unit_number']}: {$row['nama_barang']} ({$row['kode_barang']}) | display={$row['unit_display']} | status={$row['return_status']} | expected={$row['expected_return']} | kondisi={$row['kondisi_kembali']}\n";
}

echo "\n=== Sample data (peminjaman 84) ===\n";
$sample = $conn->query("
    SELECT pu.*, b.nama_barang, b.kode_barang
    FROM peminjaman_units pu
    JOIN barang b ON pu.barang_id = b.id
    WHERE pu.peminjaman_id = 84
    ORDER BY pu.unit_number
");
while ($row = $sample->fetch_assoc()) {
    echo "Unit {$row['unit_number']}: {$row['nama_barang']} ({$row['kode_barang']}) | display={$row['unit_display']} | status={$row['return_status']} | expected={$row['expected_return']} | kondisi={$row['kondisi_kembali']}\n";
}
