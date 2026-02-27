<?php
/**
 * Comprehensive Database Verification
 * Check: Database Connection + All Tables + Query Validity
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../koneksi.php';

$report = [
    'connection' => [
        'status' => 'Connected',
        'host' => 'localhost',
        'database' => 'peminjaman',
        'user' => 'root'
    ],
    'tables' => [],
    'query_tests' => [],
    'data_integrity' => []
];

// ========== TABLE CHECK ==========
$tables = ['barang', 'peminjaman', 'detail_peminjaman', 'users', 'pengembalian'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as total FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        $report['tables'][$table] = [
            'exists' => true,
            'total_records' => $row['total']
        ];
    } else {
        $report['tables'][$table] = [
            'exists' => false,
            'error' => $conn->error
        ];
    }
}

// ========== QUERY TESTS ==========

// Test 1: Status Peminjaman
$query_status = "SELECT status, COUNT(*) as total FROM peminjaman GROUP BY status";
$result = $conn->query($query_status);
$status_counts = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status_counts[$row['status']] = $row['total'];
    }
}
$report['query_tests']['status_distribution'] = $status_counts;

// Test 2: Detail Peminjaman Structure
$query_detail = "SELECT * FROM detail_peminjaman LIMIT 1";
$result = $conn->query($query_detail);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $report['query_tests']['sample_detail_peminjaman'] = [
        'columns_found' => array_keys($row),
        'has_jumlah_column' => isset($row['jumlah']),
        'has_barang_id_column' => isset($row['barang_id']),
        'has_peminjaman_id_column' => isset($row['peminjaman_id'])
    ];
} else {
    $report['query_tests']['sample_detail_peminjaman'] = ['error' => 'No records found'];
}

// Test 3: Top Barang - OLD QUERY (COUNT)
$query_old = "
    SELECT 
        b.nama_barang, 
        b.stok_tersedia, 
        COUNT(*) as jumlah_peminjaman
    FROM detail_peminjaman dp
    JOIN barang b ON dp.barang_id = b.id
    GROUP BY dp.barang_id, b.nama_barang, b.stok_tersedia
    ORDER BY jumlah_peminjaman DESC
    LIMIT 5
";
$result = $conn->query($query_old);
$old_result = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $old_result[] = $row;
    }
}
$report['query_tests']['top_barang_old_query'] = [
    'description' => 'Using COUNT(*) - counts rows, not units',
    'results' => $old_result
];

// Test 4: Top Barang - NEW QUERY (SUM)
$query_new = "
    SELECT 
        b.id,
        b.nama_barang, 
        b.stok_tersedia, 
        SUM(dp.jumlah) as jumlah_dipinjam
    FROM detail_peminjaman dp
    JOIN barang b ON dp.barang_id = b.id
    JOIN peminjaman p ON dp.peminjaman_id = p.id
    GROUP BY b.id, b.nama_barang, b.stok_tersedia
    ORDER BY jumlah_dipinjam DESC
    LIMIT 5
";
$result = $conn->query($query_new);
$new_result = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $new_result[] = $row;
    }
}
$report['query_tests']['top_barang_new_query'] = [
    'description' => 'Using SUM(dp.jumlah) - sums actual units dipinjam',
    'results' => $new_result
];

// ========== DATA INTEGRITY CHECK ==========

// Check 1: Peminjaman with status Menunggu Persetujuan
$query = "SELECT id, kode_peminjaman, nama_peminjam, status FROM peminjaman WHERE status = 'Menunggu Persetujuan' LIMIT 1";
$result = $conn->query($query);
$menunggu_sample = $result->fetch_assoc();

if ($menunggu_sample) {
    // Get detail peminjaman for this request
    $detail_query = "
        SELECT dp.*, b.nama_barang 
        FROM detail_peminjaman dp
        JOIN barang b ON dp.barang_id = b.id
        WHERE dp.peminjaman_id = " . $menunggu_sample['id'];
    $detail_result = $conn->query($detail_query);
    $details = [];
    $total_units = 0;
    while ($row = $detail_result->fetch_assoc()) {
        $details[] = $row;
        $total_units += $row['jumlah'];
    }
    
    $report['data_integrity']['sample_menunggu_persetujuan'] = [
        'peminjaman' => $menunggu_sample,
        'details' => $details,
        'total_units' => $total_units
    ];
}

// Check 2: Verify barang table has stok_tersedia
$query = "SELECT id, nama_barang, stok_tersedia FROM barang LIMIT 3";
$result = $conn->query($query);
$sample_barang = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sample_barang[] = $row;
    }
}
$report['data_integrity']['sample_barang'] = $sample_barang;

// Check 3: Join Test
$join_query = "
    SELECT 
        p.id,
        p.kode_peminjaman,
        p.nama_peminjam,
        p.status,
        b.nama_barang,
        dp.jumlah
    FROM detail_peminjaman dp
    JOIN barang b ON dp.barang_id = b.id
    JOIN peminjaman p ON dp.peminjaman_id = p.id
    LIMIT 3
";
$result = $conn->query($join_query);
$join_sample = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $join_sample[] = $row;
    }
}
$report['data_integrity']['join_test_sample'] = $join_sample;

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$conn->close();
?>
