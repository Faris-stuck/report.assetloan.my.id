<?php
/**
 * Direct Logic Test for Per-Unit Modal Display
 * Tests the API logic without requiring HTTP/Session
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate database connection
$localhost = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'peminjaman';

$conn = new mysqli($localhost, $db_user, $db_password, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$results = [];

// Test 1: Verify table structure
echo "=== Test 1: Verify extend_peminjaman_items table structure ===\n";
$result = $conn->query("DESCRIBE extend_peminjaman_items");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[$row['Field']] = $row['Type'];
    echo "  - {$row['Field']}: {$row['Type']}\n";
}

$expected = ['id', 'extend_peminjaman_id', 'detail_peminjaman_id', 'unit_number', 'tanggal_perpanjang', 'created_at'];
$has_all = array_diff($expected, array_keys($columns)) === [];
echo "Has all required columns: " . ($has_all ? "✓ YES" : "✗ NO") . "\n\n";

// Test 2: Get detail items for peminjaman 83
echo "=== Test 2: Fetch detail items for peminjaman 83 ===\n";
$stmt = $conn->prepare("
    SELECT 
        d.id as detail_id,
        d.barang_id,
        d.jumlah as qty_total,
        d.kondisi_pinjam,
        b.kode_barang,
        b.nama_barang
    FROM detail_peminjaman d
    JOIN barang b ON b.id = d.barang_id
    WHERE d.peminjaman_id = ?
    ORDER BY d.id
");
$peminjaman_id = 83;
$stmt->bind_param("i", $peminjaman_id);
$stmt->execute();
$detail_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "Found " . count($detail_items) . " detail items\n";
foreach ($detail_items as $item) {
    echo "  - Detail ID: {$item['detail_id']}, Barang: {$item['nama_barang']}, Qty: {$item['qty_total']}\n";
}
echo "\n";

// Test 3: Generate per-unit data
echo "=== Test 3: Generate per-unit data ===\n";
$total_units = 0;
foreach ($detail_items as $item) {
    $qty = (int)$item['qty_total'];
    for ($u = 1; $u <= $qty; $u++) {
        $total_units++;
        printf("  - Unit {$u}/{$qty} of {$item['nama_barang']} (detail_id: {$item['detail_id']})\n");
    }
}
echo "Total units generated: $total_units\n\n";

// Test 4: Expected return calculation
echo "=== Test 4: Expected return calculation ===\n";

// Get peminjaman info
$stmt = $conn->prepare("SELECT rencana_kembali, status FROM peminjaman WHERE id = ?");
$stmt->bind_param("i", $peminjaman_id);
$stmt->execute();
$peminjaman = $stmt->get_result()->fetch_assoc();
echo "Peminjaman rencana_kembali: {$peminjaman['rencana_kembali']}\n";
echo "Peminjaman status: {$peminjaman['status']}\n";

// Get approved extends
$stmt = $conn->prepare("
    SELECT 
        ep.id as extend_id,
        ep.tanggal_perpanjang,
        epi.detail_peminjaman_id,
        epi.unit_number,
        epi.tanggal_perpanjang as unit_tanggal_perpanjang
    FROM extend_peminjaman ep
    LEFT JOIN extend_peminjaman_items epi ON epi.extend_peminjaman_id = ep.id
    WHERE ep.peminjaman_id = ? AND ep.status = 'Approved'
");
$stmt->bind_param("i", $peminjaman_id);
$stmt->execute();
$extends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "Found " . count($extends) . " approved extends\n";
foreach ($extends as $ext) {
    if ($ext['unit_number']) {
        echo "  - Unit {$ext['unit_number']} of detail {$ext['detail_peminjaman_id']}: extended to {$ext['unit_tanggal_perpanjang']}\n";
    } else {
        echo "  - Whole peminjaman extend: {$ext['tanggal_perpanjang']}\n";
    }
}
echo "\n";

// Test 5: Simulate JSON response structure
echo "=== Test 5: Simulate JSON response ===\n";

$extend_map = [];
foreach ($extends as $ext) {
    $detail_id = $ext['detail_peminjaman_id'];
    $unit_num = $ext['unit_number'];
    
    if (!isset($extend_map[$detail_id])) {
        $extend_map[$detail_id] = [];
    }
    
    if ($detail_id && $unit_num) {
        $extend_map[$detail_id][$unit_num] = [
            'is_extended' => true,
            'extend_date' => $ext['unit_tanggal_perpanjang']
        ];
    }
}

// Generate units with expected_return
$units = [];
foreach ($detail_items as $item) {
    $detail_id = (int)$item['detail_id'];
    $qty = (int)$item['qty_total'];
    
    for ($unit_num = 1; $unit_num <= $qty; $unit_num++) {
        $expected_return = $peminjaman['rencana_kembali'];
        $is_extended = false;
        $extend_date = null;
        
        if (isset($extend_map[$detail_id][$unit_num])) {
            $extend_info = $extend_map[$detail_id][$unit_num];
            $is_extended = $extend_info['is_extended'];
            $extend_date = $extend_info['extend_date'];
            $expected_return = $extend_date;
        }
        
        $units[] = [
            'unit_id' => "detail_{$detail_id}_unit_{$unit_num}",
            'detail_peminjaman_id' => $detail_id,
            'unit_number' => $unit_num,
            'nama_barang' => $item['nama_barang'],
            'qty_dipinjam' => 1,
            'expected_return' => $expected_return,
            'is_extended' => $is_extended,
            'extend_date' => $extend_date
        ];
    }
}

echo "Generated " . count($units) . " unit rows for JSON response\n";
echo json_encode([
    'status' => true,
    'data' => [
        'peminjaman_id' => $peminjaman_id,
        'peminjaman_rencana_kembali' => $peminjaman['rencana_kembali'],
        'units' => array_slice($units, 0, 3) // Show only first 3 for brevity
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

echo "\n\n=== All Tests Complete ===\n";
?>
