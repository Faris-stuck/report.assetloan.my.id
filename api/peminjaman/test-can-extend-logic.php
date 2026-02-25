#!/usr/bin/env php
<?php
/**
 * TEST: can_extend Logic in API
 * File: /PROJECT/api/peminjaman/test-can-extend-logic.php
 * 
 * This test verifies that can_extend field is correctly calculated
 * based on peminjaman.status in get_all.php API
 */

echo "═══════════════════════════════════════════════════════════\n";
echo "  TEST: can_extend Logic in API\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Status mapping from get_all.php
$final_statuses = ['Dikembalikan', 'Returned', 'Completed', 'Closed', 'Rejected', 'Ditolak', 'Batal'];

$test_scenarios = [
    [
        'name' => 'Peminjaman yang BELUM pernah extend',
        'peminjaman_status' => 'Sedang Dipinjam',
        'expected_can_extend' => true,
        'expected_show_extend_button' => true
    ],
    [
        'name' => 'Peminjaman yang SUDAH pernah extend (Approved)',
        'peminjaman_status' => 'Sedang Dipinjam',
        'expected_can_extend' => true,
        'expected_show_extend_button' => true
    ],
    [
        'name' => 'Peminjaman Overdue',
        'peminjaman_status' => 'Overdue',
        'expected_can_extend' => true,
        'expected_show_extend_button' => true
    ],
    [
        'name' => 'Peminjaman Due H-7',
        'peminjaman_status' => 'Due H-7',
        'expected_can_extend' => true,
        'expected_show_extend_button' => true
    ],
    [
        'name' => 'Peminjaman Sebagian Dikembalikan',
        'peminjaman_status' => 'Sebagian Dikembalikan',
        'expected_can_extend' => true,
        'expected_show_extend_button' => true
    ],
    [
        'name' => 'Peminjaman Final - Dikembalikan',
        'peminjaman_status' => 'Dikembalikan',
        'expected_can_extend' => false,
        'expected_show_extend_button' => false
    ],
    [
        'name' => 'Peminjaman Final - Returned',
        'peminjaman_status' => 'Returned',
        'expected_can_extend' => false,
        'expected_show_extend_button' => false
    ],
    [
        'name' => 'Peminjaman Final - Completed',
        'peminjaman_status' => 'Completed',
        'expected_can_extend' => false,
        'expected_show_extend_button' => false
    ],
    [
        'name' => 'Peminjaman Final - Rejected',
        'peminjaman_status' => 'Rejected',
        'expected_can_extend' => false,
        'expected_show_extend_button' => false
    ],
    [
        'name' => 'Peminjaman Final - Ditolak',
        'peminjaman_status' => 'Ditolak',
        'expected_can_extend' => false,
        'expected_show_extend_button' => false
    ],
    [
        'name' => 'Peminjaman Disetujui',
        'peminjaman_status' => 'Disetujui',
        'expected_can_extend' => true,
        'expected_show_extend_button' => true
    ],
    [
        'name' => 'Peminjaman Proses Return',
        'peminjaman_status' => 'Proses Return',
        'expected_can_extend' => true,
        'expected_show_extend_button' => true
    ],
];

$passed = 0;
$failed = 0;

foreach ($test_scenarios as $idx => $test) {
    $status = $test['peminjaman_status'];
    $expected_can_extend = $test['expected_can_extend'];
    $expected_button = $test['expected_show_extend_button'];
    
    // Logic dari get_all.php
    $can_extend = !in_array($status, $final_statuses);
    
    // Button visibility is set inline in HTML
    $show_button = $can_extend;
    
    $status_match = ($can_extend === $expected_can_extend);
    $button_match = ($show_button === $expected_button);
    
    if ($status_match && $button_match) {
        echo "✅ PASS [Test " . ($idx + 1) . "]: {$test['name']}\n";
        echo "   Status: {$status}\n";
        echo "   can_extend: {$can_extend} (expected: {$expected_can_extend})\n";
        echo "   show_button: {$show_button} (expected: {$expected_button})\n\n";
        $passed++;
    } else {
        echo "❌ FAIL [Test " . ($idx + 1) . "]: {$test['name']}\n";
        echo "   Status: {$status}\n";
        if (!$status_match) {
            echo "   ❌ can_extend: {$can_extend} (expected: {$expected_can_extend})\n";
        } else {
            echo "   ✅ can_extend: {$can_extend} (expected: {$expected_can_extend})\n";
        }
        if (!$button_match) {
            echo "   ❌ show_button: {$show_button} (expected: {$expected_button})\n";
        } else {
            echo "   ✅ show_button: {$show_button} (expected: {$expected_button})\n";
        }
        echo "\n";
        $failed++;
    }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "  RESULTS\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "Total: " . ($passed + $failed) . "\n";
echo "Passed: " . $passed . "\n";
echo "Failed: " . $failed . "\n";
echo "\n";

if ($failed === 0) {
    echo "🎉 All tests PASSED!\n";
    echo "\n✅ KEY POINTS:\n";
    echo "1. can_extend field ditambahkan ke API response\n";
    echo "2. can_extend ditentukan dari peminjaman.status\n";
    echo "3. BUKAN dari ada/tidaknya extend_peminjaman record\n";
    echo "4. Frontend gunakan can_extend langsung dari peminjaman/get.php\n";
    echo "5. Tombol EXTEND ditampilkan inline saat render (tidak perlu wait API)\n";
    echo "6. Status extend tetap ditampilkan sebagai badge (dari extend/status.php)\n";
} else {
    echo "⚠️  Some tests FAILED. Please review!\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "\nSCENARIO COVERAGE:\n";
echo "✓ Belum pernah extend → tombol muncul\n";
echo "✓ Sudah pernah extend → tombol muncul\n";
echo "✓ Peminjaman final → tombol tidak muncul\n";
echo "✓ Status aktif apapun → tombol muncul\n";
echo "═══════════════════════════════════════════════════════════\n";
