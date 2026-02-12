<?php
/**
 * API: Get Peminjaman by Status (Approver/Manager version)
 * Purpose: Fetch peminjaman list with filters
 * Endpoint: /api/approver/peminjaman-list.php
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['manager']);
    
    $status = $_GET['status'] ?? 'Menunggu Persetujuan';
    $limit = intval($_GET['limit'] ?? 10);
    $offset = intval($_GET['offset'] ?? 0);
    
    // Get total count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE status = ?");
    $count_stmt->bind_param("s", $status);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    
    // Get data with pagination
    $stmt = $conn->prepare("
        SELECT 
            p.id, p.kode_peminjaman, p.nama_peminjam, p.nrp,
            p.tanggal_pinjam, p.rencana_kembali, p.status,
            p.catatan, p.created_at
        FROM peminjaman p
        WHERE p.status = ?
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sii", $status, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'kode' => $row['kode_peminjaman'],
            'nama' => $row['nama_peminjam'],
            'nrp' => $row['nrp'],
            'tanggal_pinjam' => date('d/m/Y', strtotime($row['tanggal_pinjam'])),
            'rencana_kembali' => date('d/m/Y', strtotime($row['rencana_kembali'])),
            'status' => $row['status'],
            'catatan' => $row['catatan']
        ];
    }
    
    echo json_encode([
        'status' => true,
        'data' => $data,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}
?>
