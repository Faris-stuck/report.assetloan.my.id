<?php
/**
 * API: Get detail items dari peminjaman (untuk user's return inspection form)
 * Endpoint: /api/peminjaman/get_detail.php
 *
 * Query params:
 * - peminjaman_id (int) required
 *
 * Returns header (peminjaman info) + items (detail_peminjaman rows)
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    SessionValidator::requireRole(['user']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit;
}

$user_id = (int) (SessionValidator::getUserId() ?? 0);
$peminjaman_id = (int)($_GET['peminjaman_id'] ?? 0);

if (!$peminjaman_id) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "peminjaman_id is required"]);
    exit;
}

try {
    // Get peminjaman header (and verify it belongs to current user)
    $h = $conn->prepare("
        SELECT id, kode_peminjaman, nama_peminjam, nrp
        FROM peminjaman
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    
    if (!$h) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $h->bind_param("ii", $peminjaman_id, $user_id);
    if (!$h->execute()) {
        throw new Exception("Database execute error: " . $h->error);
    }
    $header = $h->get_result()->fetch_assoc();

    if (!$header) {
        http_response_code(404);
        echo json_encode(["status" => false, "message" => "Borrowing not found or does not belong to this user"]);
        exit;
    }

    // Get detail items WITH aggregate return history from ONLY FINALIZED pengembalian records
    // This shows user what's already been returned and approved
    $stmt = $conn->prepare("
        SELECT
            d.id,
            d.barang_id,
            d.jumlah,
            b.kode_barang,
            b.nama_barang,
            COALESCE(SUM(dr.jumlah_kembali), 0) as sudah_dikembalikan
        FROM detail_peminjaman d
        JOIN barang b ON b.id = d.barang_id
        LEFT JOIN detail_pengembalian dr ON dr.barang_id = d.barang_id 
            AND dr.pengembalian_id IN (
                SELECT id FROM pengembalian 
                WHERE peminjaman_id = ? AND status = 'Selesai'
            )
        WHERE d.peminjaman_id = ?
        GROUP BY d.id, d.barang_id, d.jumlah, b.kode_barang, b.nama_barang
        ORDER BY b.nama_barang ASC
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $peminjaman_id, $peminjaman_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate sisa (remaining) = original qty - already returned qty
        $row['sisa_dikembalikan'] = (int)$row['jumlah'] - (int)$row['sudah_dikembalikan'];
        $items[] = $row;
    }

    http_response_code(200);
    echo json_encode([
        "status" => true,
        "header" => $header,
        "items" => $items
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Server error: " . $e->getMessage()]);
}
