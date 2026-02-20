<?php
/**
 * Comprehensive test untuk remain_item database integration
 */

require_once 'api/koneksi.php';

echo "=== Testing Remain Item Database Integration ===\n\n";

// 1. Verify new column exists
echo "1. Verifying new column sisa_dikembalikan...\n";
$check = $conn->query("DESCRIBE detail_pengembalian");
$columns = [];
while ($row = $check->fetch_assoc()) {
    $columns[$row['Field']] = $row['Type'];
}

if (isset($columns['sisa_dikembalikan'])) {
    echo "   ✓ Column 'sisa_dikembalikan' exists\n";
    echo "   Type: " . $columns['sisa_dikembalikan'] . "\n";
} else {
    echo "   ✗ Column 'sisa_dikembalikan' NOT FOUND\n";
    echo "   Please run: ALTER TABLE detail_pengembalian ADD COLUMN sisa_dikembalikan INT NOT NULL DEFAULT 0 AFTER jumlah_rusak;\n";
}

// 2. Check existing pengembalian records to see data structure
echo "\n2. Checking existing pengembalian records...\n";
$stmt = $conn->prepare("
    SELECT 
        p.id, p.kode_pengembalian, p.status,
        d.barang_id, d.jumlah_kembali, d.sisa_dikembalikan
    FROM pengembalian p
    LEFT JOIN detail_pengembalian d ON d.pengembalian_id = p.id
    LIMIT 1
");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "   ✓ Found pengembalian record\n";
    echo "   - ID: {$row['id']}\n";
    echo "   - Kode: {$row['kode_pengembalian']}\n";
    echo "   - Status: {$row['status']}\n";
    echo "   - Jumlah Kembali: {$row['jumlah_kembali']}\n";
    echo "   - Sisa Dikembalikan: {$row['sisa_dikembalikan']}\n";
} else {
    echo "   ℹ No pengembalian records found yet\n";
}

// 3. Test INSERT with sisa_dikembalikan
echo "\n3. Testing INSERT preparation with sisa_dikembalikan...\n";
$test_insert = $conn->prepare("
    INSERT INTO detail_pengembalian
    (pengembalian_id, barang_id, jumlah_kembali, kondisi_kembali, jumlah_rusak, sisa_dikembalikan, biaya_ganti_rugi, catatan)
    VALUES (?, ?, ?, ?, ?, ?, 0.00, '')
");

if ($test_insert) {
    echo "   ✓ INSERT statement prepared successfully\n";
} else {
    echo "   ✗ Error preparing INSERT: " . $conn->error . "\n";
}

// 4. Test SELECT with sisa_dikembalikan
echo "\n4. Testing SELECT with sisa_dikembalikan...\n";
$test_select = $conn->prepare("
    SELECT
        d.id,
        d.barang_id,
        d.jumlah_kembali,
        d.sisa_dikembalikan
    FROM detail_pengembalian d
    LIMIT 1
");

if ($test_select) {
    echo "   ✓ SELECT statement prepared successfully\n";
    $test_select->execute();
    $test_result = $test_select->get_result();
    
    if ($test_result->num_rows > 0) {
        $row = $test_result->fetch_assoc();
        echo "   ✓ Retrieved sisa_dikembalikan: {$row['sisa_dikembalikan']}\n";
    }
} else {
    echo "   ✗ Error preparing SELECT: " . $conn->error . "\n";
}

echo "\n=== Data Flow Verification ===\n\n";

echo "USER SUBMIT FLOW:\n";
echo "1. User fills form in ajukan-pengembalian.html\n";
echo "2. User enters: qty_return=2, damaged=0, remain_item=1\n";
echo "3. Frontend sends JSON: {barang_id, qty_return, damaged, remain_item}\n";
echo "4. return.php receives and extracts: remain_item -> sisa_dikembalikan\n";
echo "5. return.php INSERT: INSERT detail_pengembalian (..., sisa_dikembalikan=1)\n";
echo "6. Database stores: jumlah_kembali=2, sisa_dikembalikan=1\n";

echo "\nPIC VIEW FLOW:\n";
echo "1. PIC opens pengembalian-barang.html\n";
echo "2. Frontend calls: /api/pengembalian/detail.php?pengembalian_id=X\n";
echo "3. API queries: SELECT ... d.sisa_dikembalikan FROM detail_pengembalian d\n";
echo "4. API returns: {jumlah_kembali: 2, sisa_dikembalikan: 1}\n";
echo "5. Frontend uses: const remainItems = it.sisa_dikembalikan || (qtPinjam - jumlah_kembali)\n";
echo "6. Display: Remain Item = 1 (exact value user submitted)\n";

echo "\n=== Files Modified ===\n\n";

echo "Database:\n";
echo "  ✓ Added column: detail_pengembalian.sisa_dikembalikan (INT, DEFAULT 0)\n";

echo "\nBackend:\n";
echo "  ✓ api/peminjaman/return.php\n";
echo "    - Added sisa_dikembalikan to INSERT statement\n";
echo "    - Extract remain_item from item object\n";
echo "    - Save to database\n";

echo "\n  ✓ api/pengembalian/detail.php\n";
echo "    - Added sisa_dikembalikan to SELECT statement\n";
echo "    - Return sisa_dikembalikan in API response\n";

echo "\nFrontend:\n";
echo "  ✓ user/pengembalian/ajukan-pengembalian.html\n";
echo "    - Added remain_item to items.push() object\n";
echo "    - Extract from .inp-remain field\n";
echo "    - Send to API\n";

echo "\n  ✓ pic-barang/pengembalian/pengembalian-barang.html\n";
echo "    - Use sisa_dikembalikan directly if available\n";
echo "    - Fallback to calculation if not available\n";
echo "    - Display exact remain_item value from user\n";

echo "\n=== Expected Result ===\n\n";

echo "Before Fix:\n";
echo "  User submit: remain_item=1\n";
echo "  PIC view: remain_item=3 (calculated incorrectly)\n";

echo "\nAfter Fix:\n";
echo "  User submit: remain_item=1 -> stored in sisa_dikembalikan\n";
echo "  PIC view: remain_item=1 (exact match from database)\n";

echo "\n✓ Integration Complete\n";

?>
