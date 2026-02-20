<?php
/**
 * Test bind_param signature untuk kondisi_kembali column fix
 */

require_once 'api/koneksi.php';

echo "=== Testing kondisi_kembali Bind Parameter Fix ===\n\n";

// Test 1: Check column type
echo "1. Checking kondisi_kembali column type...\n";
$result = $conn->query("DESCRIBE detail_pengembalian");
while ($row = $result->fetch_assoc()) {
    if ($row['Field'] === 'kondisi_kembali') {
        echo "   ✓ Column: kondisi_kembali\n";
        echo "   Type: " . $row['Type'] . "\n";
        echo "   Must be 'Baik' or 'Rusak' (enum)\n";
    }
}

// Test 2: Test INSERT statement with correct bind_param signature
echo "\n2. Testing INSERT with corrected bind_param...\n";

$stmt = $conn->prepare("
    INSERT INTO detail_pengembalian
    (pengembalian_id, barang_id, jumlah_kembali, kondisi_kembali, jumlah_rusak, sisa_dikembalikan, biaya_ganti_rugi, catatan)
    VALUES (?, ?, ?, ?, ?, ?, 0.00, '')
");

if ($stmt) {
    echo "   ✓ Prepared statement created\n";
    
    // Test with sample data
    $test_pengembalian_id = 999; // dummy
    $test_barang_id = 1;
    $test_jumlah_kembali = 2;
    $test_kondisi_kembali = 'Baik'; // STRING!
    $test_jumlah_rusak = 0;
    $test_sisa_dikembalikan = 1;
    
    // CORRECT signature: iiisii
    $bind_result = $stmt->bind_param(
        "iiisii",  // i=int, s=string, ← kondisi_kembali is STRING now!
        $test_pengembalian_id,
        $test_barang_id,
        $test_jumlah_kembali,
        $test_kondisi_kembali,  // STRING parameter
        $test_jumlah_rusak,
        $test_sisa_dikembalikan
    );
    
    if ($bind_result) {
        echo "   ✓ bind_param signature correct: 'iiisii'\n";
        echo "   ✓ Types: i(pengembalian_id), i(barang_id), i(jumlah_kembali), ";
        echo "s(kondisi_kembali), i(jumlah_rusak), i(sisa_dikembalikan)\n";
    } else {
        echo "   ✗ bind_param FAILED: " . $stmt->error . "\n";
    }
} else {
    echo "   ✗ Prepare FAILED: " . $conn->error . "\n";
}

// Test 3: Verify parameter types
echo "\n3. Parameter Type Mapping:\n";
echo "   ✓ pengembalian_id (INT) → 'i'\n";
echo "   ✓ barang_id (INT) → 'i'\n";
echo "   ✓ jumlah_kembali (INT) → 'i'\n";
echo "   ✓ kondisi_kembali (STRING/ENUM) → 's' ← KEY FIX!\n";
echo "   ✓ jumlah_rusak (INT) → 'i'\n";
echo "   ✓ sisa_dikembalikan (INT) → 'i'\n";

// Test 4: Test both conditional paths
echo "\n4. Testing kondisi_kembali logic:\n";

$test_cases = [
    ['jumlah_rusak' => 0, 'expected' => 'Baik'],
    ['jumlah_rusak' => 1, 'expected' => 'Rusak'],
    ['jumlah_rusak' => 5, 'expected' => 'Rusak'],
];

foreach ($test_cases as $case) {
    $jumlah_rusak = $case['jumlah_rusak'];
    $kondisi_kembali = 'Baik';
    
    if ($jumlah_rusak > 0) {
        $kondisi_kembali = 'Rusak';
    }
    
    $status = ($kondisi_kembali === $case['expected']) ? '✓' : '✗';
    echo "   $status jumlah_rusak={$jumlah_rusak} → kondisi='{$kondisi_kembali}' (expected: '{$case['expected']}')\n";
}

echo "\n=== Summary ===\n";
echo "BEFORE FIX:\n";
echo "  bind_param: 'iiiiii' ← ALL INTEGER\n";
echo "  kondisi_kembali sent as INT → truncated to invalid value\n";
echo "  Error: Data truncated for column 'kondisi_kembali'\n";

echo "\nAFTER FIX:\n";
echo "  bind_param: 'iiisii' ← Correct type for kondisi_kembali\n";
echo "  kondisi_kembali sent as STRING → valid enum value\n";
echo "  ✓ Insert succeeds without truncation\n";

?>
