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

// Validate session using static method (consistent with other API files)
try {
    SessionValidator::requireRole(['user', 'admin', 'manager', 'pic_barang']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    exit;
}

$peminjaman_id = isset($_GET['peminjaman_id']) ? (int)$_GET['peminjaman_id'] : 0;

if ($peminjaman_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Invalid Borrowing ID']);
    exit;
}

try {
    // ========================================
    // 1. GET PEMINJAMAN STATUS - to determine if can extend
    // ========================================
    $stmt_p = $conn->prepare("SELECT status FROM peminjaman WHERE id = ?");
    $stmt_p->bind_param("i", $peminjaman_id);
    $stmt_p->execute();
    $peminjaman_result = $stmt_p->get_result();
    $peminjaman_row = $peminjaman_result->fetch_assoc();
    
    $peminjaman_status = $peminjaman_row ? $peminjaman_row['status'] : '';
    
    // ========================================
    // 2. DETERMINE IF CAN EXTEND - based on peminjaman status
    // ========================================
    // Status peminjaman yang memungkinkan extend (dinamis, bukan hardcoded)
    $active_statuses = [
        'Borrowed',
        'Approved',
        'Partially Returned',
        'Return in Process',
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
    
    // Allow extend jika peminjaman masih aktif (status bukan final/closed)
    $can_extend = in_array($peminjaman_status, $active_statuses);
    
    // ========================================
    // 3. GET EXTEND STATUS - latest record
    // ========================================
    // Use ORDER BY id DESC (not created_at DESC) for deterministic latest-record retrieval
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
        ORDER BY e.id DESC
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
            'can_extend' => $can_extend,
            'peminjaman_status' => $peminjaman_status,
            'data' => [
                'extend_id' => (int)$extend['extend_id'],
                'tanggal_kembali_sekarang' => $extend['tanggal_kembali_sekarang'] ? date('d/m/Y', strtotime($extend['tanggal_kembali_sekarang'])) : '-',
                'tanggal_perpanjang' => $extend['tanggal_perpanjang'] ? date('d/m/Y', strtotime($extend['tanggal_perpanjang'])) : '-',
                'alasan' => $extend['alasan'],
                'extend_status' => $extend['extend_status'],
                'created_at' => $extend['created_at'] ? date('d/m/Y H:i', strtotime($extend['created_at'])) : '-',
                'approved_at' => $extend['approved_at'] ? date('d/m/Y H:i', strtotime($extend['approved_at'])) : null,
                'approved_by_nama' => $extend['approved_by_nama']
            ]
        ]);
    } else {
        echo json_encode([
            'status' => true,
            'has_extend' => false,
            'can_extend' => $can_extend,
            'peminjaman_status' => $peminjaman_status,
            'data' => null
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
