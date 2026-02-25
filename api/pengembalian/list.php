<?php
/**
 * API: List pengembalian yang Diajukan (Admin/PIC Barang)
 * Endpoint: /api/pengembalian/list.php
 *
 * Query params:
 * - status (optional) default 'Diajukan'
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    SessionValidator::requireRole(['admin', 'pic_barang']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit;
}

$status_input = $_GET['status'] ?? 'Diajukan';
// Support comma-separated statuses: e.g., "Diajukan,Dicek"
$statuses = array_map('trim', explode(',', $status_input));
$statuses = array_filter(array_unique($statuses)); // remove empty/duplicate values

// If empty, default to just Diajukan
if (empty($statuses)) {
    $statuses = ['Diajukan'];
}

// Build WHERE clause with multiple status values
$placeholders = implode(',', array_fill(0, count($statuses), '?'));

// Subquery: Get the OLDEST (first submitted) pengembalian per peminjaman
// This prevents showing duplicate pengembalian for the same borrowing
// ALSO: Only show if there are still items NOT returned (sisa > 0)
$sql = "
    SELECT
        k.id AS pengembalian_id,
        k.kode_pengembalian,
        k.status AS status_pengembalian,
        k.diajukan_at,
        p.id AS peminjaman_id,
        p.kode_peminjaman,
        p.nama_peminjam,
        p.nrp,
        p.tanggal_pinjam,
        p.rencana_kembali,
        p.status AS status_peminjaman,
        COALESCE(SUM(dp.jumlah_rusak),0) AS total_rusak,
        COALESCE(SUM(dp.jumlah_kembali),0) AS total_kembali,
        (
            SELECT COALESCE(SUM(dp2.jumlah), 0)
            FROM detail_peminjaman dp2
            WHERE dp2.peminjaman_id = p.id
        ) as total_items,
        (
            SELECT COALESCE(SUM(dr.jumlah_kembali), 0)
            FROM detail_pengembalian dr
            JOIN pengembalian kr ON kr.id = dr.pengembalian_id
            WHERE kr.peminjaman_id = p.id AND kr.status = 'Selesai'
        ) as total_finalized
    FROM pengembalian k
    JOIN peminjaman p ON p.id = k.peminjaman_id
    LEFT JOIN detail_pengembalian dp ON dp.pengembalian_id = k.id
    WHERE k.status IN ($placeholders)
    AND k.id IN (
        -- Subquery: Get OLDEST pengembalian per peminjaman for each status
        SELECT MIN(k2.id)
        FROM pengembalian k2
        WHERE k2.status IN ($placeholders)
        GROUP BY k2.peminjaman_id, k2.status
    )
    GROUP BY k.id
    HAVING total_items > total_finalized
    ORDER BY k.diajukan_at DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Prepare error: " . $conn->error]);
    exit;
}

// Build type string and pass references individually
// Since placeholders appear twice in the query, we need to bind statuses twice
$type_single = str_repeat('s', count($statuses));
$types = $type_single . $type_single; // statuses appear twice in the query

// Pass status values by reference for bind_param (for both occurrences in query)
$statusRefs = [];
foreach ($statuses as $k => $v) {
    $statusRefs[] = &$statuses[$k];
}
// Add references again for the second occurrence in the subquery
foreach ($statuses as $k => $v) {
    $statusRefs[] = &$statuses[$k];
}

// Use call_user_func_array to bind with proper reference handling
$bindParams = [$types];
foreach ($statusRefs as $ref) {
    $bindParams[] = $ref;
}
call_user_func_array([$stmt, 'bind_param'], $bindParams);

$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $row['diajukan_at_f'] = date('d/m/Y H:i', strtotime($row['diajukan_at']));
    $row['tanggal_pinjam_f'] = $row['tanggal_pinjam'] ? date('d/m/Y', strtotime($row['tanggal_pinjam'])) : '-';
    $row['rencana_kembali_f'] = $row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-';
    // REAL-TIME DUE STATUS for peminjaman status (use nearest expected return)
    $row['status_peminjaman'] = computeDueStatus($row['status_peminjaman'], getNearestExpectedReturn($conn, $row['peminjaman_id']) ?? $row['rencana_kembali']);
    
    // Remove internal calculation fields (not for display)
    unset($row['total_items']);
    unset($row['total_finalized']);
    
    // derive display status based on damaged counts
    $total_rusak = (int)($row['total_rusak'] ?? 0);
    $total_kembali = (int)($row['total_kembali'] ?? 0);
    $display_status = $row['status_pengembalian'];
    $display_status_en = $row['status_pengembalian'];
    if ($total_rusak > 0 && $total_kembali > 0) {
        if ($total_rusak >= $total_kembali) {
            $display_status = 'Semua Rusak';
            $display_status_en = 'Fully Damaged';
        } else {
            $display_status = 'Sebagian Rusak';
            $display_status_en = 'Partially Damaged';
        }
    }
    $row['display_status'] = $display_status;
    $row['display_status_en'] = $display_status_en;
    $row['total_rusak'] = $total_rusak;
    $row['total_kembali'] = $total_kembali;
    $data[] = $row;
}

echo json_encode(["status" => true, "data" => $data]);

