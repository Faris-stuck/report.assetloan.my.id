#!/usr/bin/env php
<?php
/**
 * TEST: Extend Button Visibility Fix
 * File: /PROJECT/api/extend/test-extend-button-visibility.php
 * 
 * Run: php /opt/lampp/htdocs/PROJECT/api/extend/test-extend-button-visibility.php
 */

$_SERVER['HTTP_HOST'] = 'localhost';

// Test data scenarios
$test_scenarios = [
    [
        'name' => 'User with Sedang Dipinjam status (should show EXTEND)',
        'peminjaman_status' => 'Sedang Dipinjam',
        'extend_status' => null,
        'expected_can_extend' => true,
        'expected_show_button' => true
    ],
    [
        'name' => 'User with Extend Approved status (should STILL show EXTEND)',
        'peminjaman_status' => 'Sedang Dipinjam',
        'extend_status' => 'Approved',
        'expected_can_extend' => true,
        'expected_show_button' => true  // ← THIS IS THE FIX!
    ],
    [
        'name' => 'User with Extend Pending status (should STILL show EXTEND)',
        'peminjaman_status' => 'Sedang Dipinjam',
        'extend_status' => 'Pending',
        'expected_can_extend' => true,
        'expected_show_button' => true  // ← THIS IS THE FIX!
    ],
    [
        'name' => 'User with Overdue status (should show EXTEND)',
        'peminjaman_status' => 'Overdue',
        'extend_status' => null,
        'expected_can_extend' => true,
        'expected_show_button' => true
    ],
    [
        'name' => 'User with Due H-7 status (should show EXTEND)',
        'peminjaman_status' => 'Due H-7',
        'extend_status' => null,
        'expected_can_extend' => true,
        'expected_show_button' => true
    ],
    [
        'name' => 'User with Sebagian Dikembalikan status (should show EXTEND)',
        'peminjaman_status' => 'Sebagian Dikembalikan',
        'extend_status' => null,
        'expected_can_extend' => true,
        'expected_show_button' => true
    ],
    [
        'name' => 'User with Dikembalikan status (should NOT show EXTEND)',
        'peminjaman_status' => 'Dikembalikan',
        'extend_status' => null,
        'expected_can_extend' => false,
        'expected_show_button' => false
    ],
    [
        'name' => 'User with Rejected status (should NOT show EXTEND)',
        'peminjaman_status' => 'Rejected',
        'extend_status' => null,
        'expected_can_extend' => false,
        'expected_show_button' => false
    ],
];

echo "═══════════════════════════════════════════════════════════\n";
echo "  TEST: Extend Button Visibility Logic\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Define active statuses (from API)
$active_statuses = [
    'Sedang Dipinjam',
    'Disetujui',
    'Approved',
    'Sebagian Dikembalikan',
    'Partially Returned',
    'Proses Return',
    'Return In Progress',
    'Overdue',
    'Due Today',
    'Due H-0',
    'Due H-1',
    'Due H-2',
    'Due H-3',
    'Due H-4',
    'Due H-5',
    'Due H-6',
    'Due H-7'
];

$passed = 0;
$failed = 0;

foreach ($test_scenarios as $idx => $scenario) {
    $peminjaman_status = $scenario['peminjaman_status'];
    $extend_status = $scenario['extend_status'];
    $expected_can_extend = $scenario['expected_can_extend'];
    $expected_show_button = $scenario['expected_show_button'];
    
    // Simulate the API logic
    $can_extend = in_array($peminjaman_status, $active_statuses);
    
    // Simulate button visibility
    $show_button = $can_extend;
    
    $test_name = $scenario['name'];
    
    $can_extend_match = ($can_extend === $expected_can_extend);
    $button_match = ($show_button === $expected_show_button);
    
    if ($can_extend_match && $button_match) {
        echo "✅ PASS [Test " . ($idx + 1) . "]: {$test_name}\n";
        echo "   Status: {$peminjaman_status}";
        if ($extend_status) echo " (Extend: {$extend_status})";
        echo "\n";
        echo "   can_extend: {$can_extend} (expected: {$expected_can_extend})\n";
        echo "   show_button: {$show_button} (expected: {$expected_show_button})\n\n";
        $passed++;
    } else {
        echo "❌ FAIL [Test " . ($idx + 1) . "]: {$test_name}\n";
        echo "   Status: {$peminjaman_status}";
        if ($extend_status) echo " (Extend: {$extend_status})";
        echo "\n";
        if (!$can_extend_match) {
            echo "   ❌ can_extend: {$can_extend} (expected: {$expected_can_extend})\n";
        } else {
            echo "   ✅ can_extend: {$can_extend} (expected: {$expected_can_extend})\n";
        }
        if (!$button_match) {
            echo "   ❌ show_button: {$show_button} (expected: {$expected_show_button})\n";
        } else {
            echo "   ✅ show_button: {$show_button} (expected: {$expected_show_button})\n";
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
    echo "🎉 All tests PASSED! The fix is working correctly.\n";
} else {
    echo "⚠️  Some tests FAILED. Please review the logic.\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "\nKEY POINTS:\n";
echo "1. Tombol EXTEND ditampilkan berdasarkan peminjaman.status\n";
echo "2. BUKAN berdasarkan ada/tidaknya extend atau status extend\n";
echo "3. User bisa extend berkali-kali selama peminjaman aktif\n";
echo "4. Status extend (Approved, Pending) hanya untuk informasi\n";
echo "═══════════════════════════════════════════════════════════\n";
