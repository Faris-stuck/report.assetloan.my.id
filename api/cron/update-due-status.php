<?php
/**
 * ============================================================
 * CRON: Auto-Update Dynamic Return Status
 * ============================================================
 * 
 * File   : /PROJECT/api/cron/update-due-status.php
 * Access : http://localhost/PROJECT/api/cron/update-due-status.php
 * 
 * Logic:
 *   - Active status (Borrowed / Due% / Overdue / Partially Returned / Return in Process)
 *     updated based on remaining days from NEAREST expected return (considering extensions):
 *     > 7 days  → Keep original status (Sedang Dipinjam / Sebagian Dikembalikan / Proses Return)
 *     2-7 days  → Due In X Days
 *     1 day     → Due In 1 Day
 *     0 days    → Due Today
 *     < 0 days  → Overdue
 *   - Inactive status (Dikembalikan, Ditolak, etc.) NOT changed.
 *   - Uses getNearestExpectedReturn() for accuracy with per-unit extensions.
 * 
 * Execution notes:
 *   - Can be executed manually via browser/CLI (with token for browser).
 *   - In this project, due-status refresh is also triggered from
 *     /PROJECT/api/koneksi.php on every request.
 * 
 * ============================================================
 */

// ============================================================
// 0. CRON SECURITY — Verify token for web access
// ============================================================
if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    if ($token !== (getenv('CRON_SECRET') ?: 'K0m4tsu_Cr0n_2026')) {
        http_response_code(403);
        echo 'Forbidden: Invalid cron token';
        exit;
    }
}

// ============================================================
// 1. OUTPUT HEADER
// ============================================================
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}

echo "============================================================\n";
echo "  CRON: Auto-Update Dynamic Return Status\n";
echo "  Execution time: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n\n";

// ============================================================
// 2. DATABASE CONNECTION
// ============================================================
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

require_once __DIR__ . '/../koneksi.php';

if ($conn->connect_error) {
    echo "[ERROR] Database connection failed: " . $conn->connect_error . "\n";
    exit(1);
}
echo "[OK] Database connection successful.\n\n";

// ============================================================
// 3. DYNAMIC UPDATE: All active borrowings
//    Active status = 'Borrowed' OR LIKE 'Due%' OR 'Overdue'
//                    OR 'Partially Returned' OR 'Return in Process'
//    Uses getNearestExpectedReturn() so extensions are accounted for
// ============================================================
echo "--- Dynamic status update based on remaining days (nearest expected return) ---\n";

$sql_active = "
    SELECT id, kode_peminjaman, status, rencana_kembali
    FROM peminjaman
    WHERE status = 'Borrowed' 
       OR status LIKE 'Due%' 
       OR status = 'Overdue'
       OR status = 'Partially Returned'
       OR status = 'Return in Process'
       OR status = 'Partial Approved'
       OR status = 'Approved'
    ORDER BY rencana_kembali ASC
";

$active_result = $conn->query($sql_active);
$affected = 0;

if ($active_result && $active_result->num_rows > 0) {
    $update_stmt = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id = ?");

    while ($row = $active_result->fetch_assoc()) {
        $nearest = getNearestExpectedReturn($conn, $row['id']);
        $effectiveDate = $nearest ?? $row['rencana_kembali'];
        $newStatus = computeDueStatus($row['status'], $effectiveDate);

        if ($newStatus !== $row['status']) {
            $update_stmt->bind_param('si', $newStatus, $row['id']);
            $update_stmt->execute();
            if ($update_stmt->affected_rows > 0) {
                $affected++;
                echo "  [{$row['kode_peminjaman']}] {$row['status']} → {$newStatus} (return: {$effectiveDate})\n";
            }
        }
    }
    $update_stmt->close();
    echo "\n[OK] {$affected} borrowing statuses updated.\n\n";
} else {
    echo "[OK] No active borrowings to update.\n\n";
}

// ============================================================
// 4. DISPLAY CURRENT STATUS DETAILS
// ============================================================
echo "--- Active borrowing status details ---\n";

$sql_detail = "
    SELECT id, kode_peminjaman, status, rencana_kembali, 
           DATEDIFF(rencana_kembali, CURDATE()) as sisa_hari
    FROM peminjaman 
    WHERE status = 'Borrowed' OR status LIKE 'Due%' OR status = 'Overdue'
       OR status = 'Partially Returned' OR status = 'Return in Process'
       OR status = 'Partial Approved' OR status = 'Approved'
    ORDER BY rencana_kembali ASC
";

$detail = $conn->query($sql_detail);
if ($detail && $detail->num_rows > 0) {
    while ($row = $detail->fetch_assoc()) {
        echo "  [{$row['kode_peminjaman']}] Status: {$row['status']} | Kembali: {$row['rencana_kembali']} | Sisa: {$row['sisa_hari']} hari\n";
    }
} else {
    echo "  No active borrowings.\n";
}

// ============================================================
// 5. SUMMARY
// ============================================================
echo "\n============================================================\n";
echo "  SUMMARY:\n";
echo "  - Total statuses updated: " . ($affected ?? 0) . "\n";
echo "  - Completed: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n";

$conn->close();
