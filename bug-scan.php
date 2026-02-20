<?php
/**
 * Comprehensive Bug Scan
 */

echo "=== BUG SCAN REPORT ===\n\n";

// BUG 1: ajukan-pengembalian.html filtering logic
echo "BUG #1: ajukan-pengembalian.html filter status\n";
echo "=" . str_repeat("=", 40) . "\n";
echo "Issue: Allows users to return fully returned items (status='Dikembalikan')\n";
echo "Filter: (p.status === 'Sedang Dipinjam' || p.status === 'Sebagian Dikembalikan')\n";
echo "Problem: 'Dikembalikan' status means fully returned, shouldn't be in list\n";
echo "Fix: Add check to exclude 'Dikembalikan' if it represents complete return\n\n";

// BUG 2: Validation in return.php
echo "BUG #2: api/peminjaman/return.php validation\n";
echo "=" . str_repeat("=", 40) . "\n";
echo "Issue: API accepts return requests for already-fully-returned items\n";
echo "Current allowed statuses: 'Sedang Dipinjam', 'Dikembalikan', 'Proses Return'\n";
echo "Problem: 'Dikembalikan' should only be allowed if still has pending items\n";
echo "Fix: Add check - if total_kembali >= total_pinjam, reject return request\n\n";

// BUG 3: Database validation
echo "BUG #3: Database constraint check\n";
echo "=" . str_repeat("=", 40) . "\n";
echo "Issue: No database-level constraint to prevent duplicate returns\n";
echo "Problem: Multiple return requests for same items possible\n";
echo "Fix: Add database validation or unique constraint\n\n";

// List files to check
echo "FILES TO AUDIT:\n";
echo "=" . str_repeat("=", 40) . "\n";

$files_to_scan = [
    // USER PENGEMBALIAN
    ['user/pengembalian/ajukan-pengembalian.html', 'Filter logic, validation, pending checks'],
    
    // PIC BARANG PENGEMBALIAN
    ['pic-barang/pengembalian/pengembalian-barang.html', 'Inspection form, calculation logic'],
    
    // ADMIN PENGEMBALIAN
    ['admin/pengembalian/barang-rusak.html', 'Damaged tracking, status updates'],
    ['admin/pengembalian/pengembalian-barang.html', 'Admin inspection logic'],
    
    // API PEMINJAMAN
    ['api/peminjaman/return.php', 'Validation, allowed status check'],
    ['api/peminjaman/get_all.php', 'Status calculation, filtering logic'],
    
    // API PENGEMBALIAN
    ['api/pengembalian/detail.php', 'Data retrieval, filtering'],
    ['api/pengembalian/inspect.php', 'Inspection logic, status updates'],
];

foreach ($files_to_scan as [$file, $check]) {
    echo "\n[$file]\n";
    echo "  Check: $check\n";
}

echo "\n=== RECOMMENDED FIXES ===\n\n";

echo "1. ajukan-pengembalian.html\n";
echo "   - Update filter to exclude 'Dikembalikan' if fully returned\n";
echo "   - Add validation: check if still has pending items to return\n\n";

echo "2. api/peminjaman/return.php\n";
echo "   - Add check: verify not all items already returned\n";
echo "   - Query: SELECT SUM(jumlah_kembali) FROM detail_pengembalian\n";
echo "   - If sum >= total_pinjam: reject as 'sudah dikembalikan semua'\n\n";

echo "3. api/pengembalian/detail.php\n";
echo "   - Ensure returning only pending/unfinished items\n";
echo "   - Filter by pengembalian.status != 'Selesai'\n\n";

echo "4. Database\n";
echo "   - Consider adding constraint to prevent full returns of already-returned\n\n";

?>
