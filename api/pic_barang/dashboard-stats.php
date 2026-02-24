<?php
/**
 * API: Pic Barang Dashboard Statistics
 * Endpoint: /api/pic_barang/dashboard-stats.php
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['pic_barang']);

    $stats = [];

    // Sedang Dipinjam
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE (status = 'Sedang Dipinjam' OR status LIKE 'Due%' OR status = 'Overdue')");
    $stmt->execute();
    $stats['sedang_dipinjam'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Dikembalikan hari ini
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman
        WHERE status = 'Dikembalikan' AND DATE(tanggal_kembali) = CURDATE()
    ");
    $stmt->execute();
    $stats['dikembalikan_hari_ini'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Total barang / stok tersedia
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM barang");
    $stmt->execute();
    $stats['total_barang'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    $stmt = $conn->prepare("SELECT COALESCE(SUM(stok_tersedia), 0) as total FROM barang");
    $stmt->execute();
    $stats['stok_tersedia'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Barang stok menipis
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM barang
        WHERE stok_tersedia > 0 AND stok_tersedia <= safety_stock
    ");
    $stmt->execute();
    $stats['stok_menipis'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Recent peminjaman sedang dipinjam (untuk pengembalian)
    $stmt = $conn->prepare("
        SELECT id, kode_peminjaman, nama_peminjam, status, tanggal_pinjam, rencana_kembali
        FROM peminjaman
        WHERE (status = 'Sedang Dipinjam' OR status LIKE 'Due%' OR status = 'Overdue')
        ORDER BY rencana_kembali ASC
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['recent_sedang_dipinjam'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['recent_sedang_dipinjam'][] = [
            'id' => $row['id'],
            'kode' => $row['kode_peminjaman'],
            'nama_peminjam' => $row['nama_peminjam'],
            'status' => $row['status'],
            'tanggal_pinjam' => $row['tanggal_pinjam'],
            'rencana_kembali' => $row['rencana_kembali']
        ];
    }

    echo json_encode([
        'status' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}
