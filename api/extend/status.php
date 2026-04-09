<?php
/**
 * API: Get Extend Status for a Peminjaman (User View)
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../session-helper.php';
require_once __DIR__ . '/../response-helper.php';

header('Content-Type: application/json');

if (!SessionValidator::isLoggedIn()) {
    apiBusinessError('Unauthorized', 401);
}

$allowedRoles = ['user', 'admin', 'manager', 'pic_barang'];
$currentRole = SessionValidator::getRole();
$sessionUserId = (int) (SessionValidator::getUserId() ?? 0);

if (!in_array($currentRole, $allowedRoles, true)) {
    apiBusinessError('Access denied', 403);
}

$peminjamanId = isset($_GET['peminjaman_id']) ? (int) $_GET['peminjaman_id'] : 0;
if ($peminjamanId <= 0) {
    apiBusinessError('Invalid Borrowing ID', 400);
}

try {
    if ($currentRole === 'user') {
        $stmtBorrowing = $conn->prepare("
            SELECT id, user_id, status, rencana_kembali
            FROM peminjaman
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        if (!$stmtBorrowing) {
            throw new RuntimeException('Failed to prepare borrowing ownership query');
        }
        $stmtBorrowing->bind_param("ii", $peminjamanId, $sessionUserId);
    } else {
        $stmtBorrowing = $conn->prepare("
            SELECT id, user_id, status, rencana_kembali
            FROM peminjaman
            WHERE id = ?
            LIMIT 1
        ");
        if (!$stmtBorrowing) {
            throw new RuntimeException('Failed to prepare borrowing query');
        }
        $stmtBorrowing->bind_param("i", $peminjamanId);
    }

    $stmtBorrowing->execute();
    $peminjaman = $stmtBorrowing->get_result()->fetch_assoc();

    if (!$peminjaman) {
        apiBusinessError('Borrowing not found', 404);
    }

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

    if (!$stmt) {
        throw new RuntimeException('Failed to prepare extend status query');
    }

    $stmt->bind_param("i", $peminjamanId);
    $stmt->execute();
    $extend = $stmt->get_result()->fetch_assoc();

    $nearestReturn = getNearestExpectedReturn($conn, $peminjamanId);
    $extendState = getBorrowingExtendState(
        $conn,
        $peminjamanId,
        (string) $peminjaman['status'],
        $peminjaman['rencana_kembali'],
        $extend['extend_status'] ?? null,
        $nearestReturn
    );

    $payload = [
        'status' => true,
        'has_extend' => (bool) $extend,
        'can_extend' => $extendState['can_extend'],
        'peminjaman_status' => $extendState['status'],
        'data' => null
    ];

    if ($extend) {
        $payload['data'] = [
            'extend_id' => (int) $extend['extend_id'],
            'tanggal_kembali_sekarang' => $extend['tanggal_kembali_sekarang'] ? date('d/m/Y', strtotime($extend['tanggal_kembali_sekarang'])) : '-',
            'tanggal_perpanjang' => $extend['tanggal_perpanjang'] ? date('d/m/Y', strtotime($extend['tanggal_perpanjang'])) : '-',
            'alasan' => $extend['alasan'],
            'extend_status' => $extend['extend_status'],
            'created_at' => $extend['created_at'] ? date('d/m/Y H:i', strtotime($extend['created_at'])) : '-',
            'approved_at' => $extend['approved_at'] ? date('d/m/Y H:i', strtotime($extend['approved_at'])) : null,
            'approved_by_nama' => $extend['approved_by_nama']
        ];
    }

    apiJsonResponse(200, $payload);
} catch (Throwable $e) {
    apiServerError($e, 'api/extend/status.php');
}
