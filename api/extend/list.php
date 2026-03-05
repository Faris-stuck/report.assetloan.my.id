<?php
/**
 * API: Get Extend Requests List
 * Method: GET
 * Params: status (optional, default 'Pending')
 * Role: admin, pic_barang, manager
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

$role = $session->getRole();
$allowed_roles = ['admin', 'pic_barang', 'manager'];
if (!in_array($role, $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Access denied']);
    exit;
}

$filter_status = $_GET['status'] ?? null;

try {
    $query = "
        SELECT 
            e.id AS extend_id,
            e.peminjaman_id,
            e.user_id,
            e.tanggal_kembali_sekarang,
            e.tanggal_perpanjang,
            e.alasan,
            e.status AS extend_status,
            e.approved_by,
            e.approved_at,
            e.created_at,
            p.kode_peminjaman,
            p.nama_peminjam,
            p.nrp,
            p.tanggal_pinjam,
            p.rencana_kembali,
            p.status AS status_peminjaman,
            u_approver.nama AS approved_by_nama
        FROM extend_peminjaman e
        JOIN peminjaman p ON e.peminjaman_id = p.id
        LEFT JOIN users u_approver ON e.approved_by = u_approver.id
    ";

    $params = [];
    $types = "";

    if ($filter_status) {
        $query .= " WHERE e.status = ?";
        $params[] = $filter_status;
        $types = "s";
    }

    $query .= " ORDER BY e.created_at DESC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'extend_id' => (int)$row['extend_id'],
            'peminjaman_id' => (int)$row['peminjaman_id'],
            'kode_peminjaman' => $row['kode_peminjaman'],
            'nama_peminjam' => $row['nama_peminjam'],
            'nrp' => $row['nrp'],
            'tanggal_pinjam' => $row['tanggal_pinjam'] ? date('d/m/Y', strtotime($row['tanggal_pinjam'])) : '-',
            'tanggal_kembali_sekarang' => $row['tanggal_kembali_sekarang'] ? date('d/m/Y', strtotime($row['tanggal_kembali_sekarang'])) : '-',
            'tanggal_perpanjang' => $row['tanggal_perpanjang'] ? date('d/m/Y', strtotime($row['tanggal_perpanjang'])) : '-',
            'tanggal_perpanjang_raw' => $row['tanggal_perpanjang'],
            'alasan' => $row['alasan'],
            'extend_status' => $row['extend_status'],
            'status_peminjaman' => $row['status_peminjaman'],
            'approved_by_nama' => $row['approved_by_nama'],
            'approved_at' => $row['approved_at'] ? date('d/m/Y H:i', strtotime($row['approved_at'])) : null,
            'created_at' => $row['created_at'] ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-'
        ];
    }

    echo json_encode(['status' => true, 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
