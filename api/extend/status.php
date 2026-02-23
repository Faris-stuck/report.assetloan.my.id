<?php
/**
 * API: Get Extend Status for a Peminjaman (User View)
 * Method: GET
 * Params: peminjaman_id
 * Role: any logged-in user
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../session-helper.php';

header('Content-Type: application/json');

$session = new SessionValidator();
if (!$session->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$peminjaman_id = isset($_GET['peminjaman_id']) ? (int)$_GET['peminjaman_id'] : 0;

if ($peminjaman_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Peminjaman ID tidak valid']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT 
            e.id AS extend_id,
            e.tanggal_kembali_sekarang,
            e.tanggal_perpanjang,
            e.alasan,
            e.status AS extend_status,
            e.created_at,
            e.approved_at,
            u.nama AS approved_by_nama
        FROM extend_peminjaman e
        LEFT JOIN users u ON e.approved_by = u.id
        WHERE e.peminjaman_id = ?
        ORDER BY e.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $extend = $result->fetch_assoc();

    if ($extend) {
        echo json_encode([
            'status' => true,
            'has_extend' => true,
            'data' => [
                'extend_id' => (int)$extend['extend_id'],
                'tanggal_kembali_sekarang' => $extend['tanggal_kembali_sekarang'],
                'tanggal_perpanjang' => $extend['tanggal_perpanjang'],
                'alasan' => $extend['alasan'],
                'extend_status' => $extend['extend_status'],
                'created_at' => $extend['created_at'] ? date('d/m/Y H:i', strtotime($extend['created_at'])) : '-',
                'approved_at' => $extend['approved_at'] ? date('d/m/Y H:i', strtotime($extend['approved_at'])) : null,
                'approved_by_nama' => $extend['approved_by_nama']
            ]
        ]);
    } else {
        echo json_encode(['status' => true, 'has_extend' => false, 'data' => null]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
