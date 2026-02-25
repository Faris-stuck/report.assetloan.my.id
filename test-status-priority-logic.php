<?php
/**
 * SYNTHETIC TEST: Verify computeDueStatus Priority Logic
 * 
 * Memverifikasi bahwa logic priority sudah bekerja dengan benar
 * untuk berbagai scenario status dan expected_return.
 */

require_once 'api/koneksi.php';

echo "=== SYNTHETIC TEST: computeDueStatus Priority Logic ===\n\n";

// Test Case 1: Partial Return dengan Due In 5 Days
echo "Test Case 1: Partial Return dengan Due In 5 Days\n";
echo str_repeat("-", 60) . "\n";
$today = new DateTime('2026-02-25');
$expectedReturn = '2026-03-02'; // 5 hari dari hari ini
$status = 'Sebagian Dikembalikan';
$result = computeDueStatus($status, $expectedReturn);
echo "Input Status: $status\n";
echo "Expected Return: $expectedReturn\n";
echo "Today: " . $today->format('Y-m-d') . "\n";
echo "Days Remaining: 5\n";
echo "Output Status: $result\n";
echo "Expected: 'Due In 5 Days'\n";
echo "Result: " . ($result === 'Due In 5 Days' ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test Case 2: Partial Return dengan Overdue
echo "Test Case 2: Partial Return dengan Overdue\n";
echo str_repeat("-", 60) . "\n";
$expectedReturn = '2026-02-20'; // Sudah 5 hari yang lalu
$status = 'Sebagian Dikembalikan';
$result = computeDueStatus($status, $expectedReturn);
echo "Input Status: $status\n";
echo "Expected Return: $expectedReturn\n";
echo "Today: 2026-02-25\n";
echo "Days Remaining: -5\n";
echo "Output Status: $result\n";
echo "Expected: 'Overdue'\n";
echo "Result: " . ($result === 'Overdue' ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test Case 3: Partial Return dengan Due Today
echo "Test Case 3: Partial Return dengan Due Today\n";
echo str_repeat("-", 60) . "\n";
$expectedReturn = '2026-02-25'; // Hari ini
$status = 'Sebagian Dikembalikan';
$result = computeDueStatus($status, $expectedReturn);
echo "Input Status: $status\n";
echo "Expected Return: $expectedReturn\n";
echo "Today: 2026-02-25\n";
echo "Days Remaining: 0\n";
echo "Output Status: $result\n";
echo "Expected: 'Due Today'\n";
echo "Result: " . ($result === 'Due Today' ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test Case 4: Partial Return dengan Due In 1 Day
echo "Test Case 4: Partial Return dengan Due In 1 Day\n";
echo str_repeat("-", 60) . "\n";
$expectedReturn = '2026-02-26'; // Besok
$status = 'Sebagian Dikembalikan';
$result = computeDueStatus($status, $expectedReturn);
echo "Input Status: $status\n";
echo "Expected Return: $expectedReturn\n";
echo "Today: 2026-02-25\n";
echo "Days Remaining: 1\n";
echo "Output Status: $result\n";
echo "Expected: 'Due In 1 Day'\n";
echo "Result: " . ($result === 'Due In 1 Day' ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test Case 5: Partial Return dengan > 7 Hari
echo "Test Case 5: Partial Return dengan > 7 Hari (Keep Original Status)\n";
echo str_repeat("-", 60) . "\n";
$expectedReturn = '2026-03-10'; // 13 hari dari hari ini
$status = 'Sebagian Dikembalikan';
$result = computeDueStatus($status, $expectedReturn);
echo "Input Status: $status\n";
echo "Expected Return: $expectedReturn\n";
echo "Today: 2026-02-25\n";
echo "Days Remaining: 13\n";
echo "Output Status: $result\n";
echo "Expected: 'Sebagian Dikembalikan' (keep original)\n";
echo "Result: " . ($result === 'Sebagian Dikembalikan' ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test Case 6: Sedang Dipinjam dengan Overdue
echo "Test Case 6: Sedang Dipinjam dengan Overdue\n";
echo str_repeat("-", 60) . "\n";
$expectedReturn = '2026-02-10'; // 15 hari yang lalu
$status = 'Sedang Dipinjam';
$result = computeDueStatus($status, $expectedReturn);
echo "Input Status: $status\n";
echo "Expected Return: $expectedReturn\n";
echo "Today: 2026-02-25\n";
echo "Days Remaining: -15\n";
echo "Output Status: $result\n";
echo "Expected: 'Overdue'\n";
echo "Result: " . ($result === 'Overdue' ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test Case 7: Already Completed (Should Not Override)
echo "Test Case 7: Dikembalikan (Final Status - Should NOT Override)\n";
echo str_repeat("-", 60) . "\n";
$expectedReturn = '2026-02-20'; // Overdue date
$status = 'Dikembalikan';
$result = computeDueStatus($status, $expectedReturn);
echo "Input Status: $status\n";
echo "Expected Return: $expectedReturn\n";
echo "Today: 2026-02-25\n";
echo "Days Remaining: -5\n";
echo "Output Status: $result\n";
echo "Expected: 'Dikembalikan' (NOT active borrowing)\n";
echo "Result: " . ($result === 'Dikembalikan' ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test Case 8: Ditolak (Final Status - Should NOT Override)
echo "Test Case 8: Ditolak (Final Status - Should NOT Override)\n";
echo str_repeat("-", 60) . "\n";
$expectedReturn = '2026-03-02'; // Dalam 5 hari
$status = 'Ditolak';
$result = computeDueStatus($status, $expectedReturn);
echo "Input Status: $status\n";
echo "Expected Return: $expectedReturn\n";
echo "Today: 2026-02-25\n";
echo "Days Remaining: 5\n";
echo "Output Status: $result\n";
echo "Expected: 'Ditolak' (NOT active borrowing)\n";
echo "Result: " . ($result === 'Ditolak' ? "✓ PASS" : "✗ FAIL") . "\n\n";

echo "=== END TESTS ===\n";
?>
