<?php
require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate admin role
SessionValidator::requireRole(['admin']);

$peminjaman_id = $_GET['peminjaman_id'] ?? $_GET['id'];

if (!$peminjaman_id) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "peminjaman_id not found"
    ]);
    exit;
}

// Query peminjaman dengan user info (kode dinamis dari database)
$stmt = $conn->prepare("
    SELECT p.id, p.kode_peminjaman, p.user_id, p.tanggal_pinjam, p.rencana_kembali, 
           p.status, p.catatan, u.nama, u.nrp
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $peminjaman_id);
$stmt->execute();
$result = $stmt->get_result();
$peminjaman = $result->fetch_assoc();

if (!$peminjaman) {
    http_response_code(404);
    echo json_encode([
        "status" => false,
        "message" => "Borrowing not found"
    ]);
    exit;
}

// Ambil detail barang dari database
$stmt_detail = $conn->prepare("
    SELECT b.id AS barang_id, b.nama_barang, dp.jumlah, dp.lokasi
    FROM detail_peminjaman dp
    JOIN barang b ON dp.barang_id = b.id
    WHERE dp.peminjaman_id = ?
");

if (!$stmt_detail) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$stmt_detail->bind_param("i", $peminjaman_id);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();

$detail_barang = [];
while ($row = $result_detail->fetch_assoc()) {
    $detail_barang[] = [
        'barang_id' => (int)$row['barang_id'],
        'nama_barang' => $row['nama_barang'],
        'jumlah' => (int)$row['jumlah'],
        'lokasi' => $row['lokasi'],
        'jumlah_kembali' => 0,
        'jumlah_rusak' => 0,
        'kondisi_kembali' => ''
    ];
}

// Ambil aggregate pengembalian dari database (ONLY finalized/Selesai records)
$agg_q = $conn->prepare("
    SELECT 
        SUM(dp.jumlah_kembali) as total_kembali,
        SUM(dp.jumlah_rusak) as total_rusak
    FROM detail_pengembalian dp
    JOIN pengembalian p ON dp.pengembalian_id = p.id
    WHERE p.peminjaman_id = ? AND p.status = 'Selesai'
");

if (!$agg_q) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$agg_q->bind_param("i", $peminjaman_id);
$agg_q->execute();
$agg_result = $agg_q->get_result()->fetch_assoc();

$display_status = $peminjaman['status'];
$display_status_en = $peminjaman['status'];
$total_items = 0;

// Sum all item quantities
foreach ($detail_barang as $dbi) {
    $total_items += (int)$dbi['jumlah'];
}

// Get aggregate per barang from FINALIZED pengembalian records only (status='Selesai')
$per_barang = $conn->prepare("
    SELECT 
        barang_id,
        SUM(jumlah_kembali) as total_kembali,
        SUM(jumlah_rusak) as total_rusak,
        MAX(kondisi_kembali) as kondisi_kembali
    FROM detail_pengembalian
    WHERE pengembalian_id IN (
        SELECT id FROM pengembalian WHERE peminjaman_id = ? AND status = 'Selesai'
    )
    GROUP BY barang_id
");

if (!$per_barang) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$per_barang->bind_param("i", $peminjaman_id);
$per_barang->execute();
$pb_result = $per_barang->get_result();
$per_barang_map = [];

while ($row = $pb_result->fetch_assoc()) {
    $per_barang_map[(int)$row['barang_id']] = [
        'jumlah_kembali' => (int)$row['total_kembali'],
        'jumlah_rusak' => (int)$row['total_rusak'],
        'kondisi_kembali' => $row['kondisi_kembali']
    ];
}

// Update detail_barang dengan nilai aggregate
foreach ($detail_barang as &$dbi) {
    $bid = (int)$dbi['barang_id'];
    if (isset($per_barang_map[$bid])) {
        $dbi['jumlah_kembali'] = $per_barang_map[$bid]['jumlah_kembali'];
        $dbi['jumlah_rusak'] = $per_barang_map[$bid]['jumlah_rusak'];
        $dbi['kondisi_kembali'] = $per_barang_map[$bid]['kondisi_kembali'];
    }
}

// Determine display status based on aggregate return
if ($agg_result) {
    $total_dikembalikan = (int)($agg_result['total_kembali'] ?? 0);
    $total_rusak = (int)($agg_result['total_rusak'] ?? 0);
    
    if ($total_dikembalikan >= $total_items && $total_items > 0) {
        if ($total_rusak > 0) {
            if ($total_rusak >= $total_items) {
                $display_status = 'Semua Rusak';
                $display_status_en = 'Fully Damaged';
            } else {
                $display_status = 'Sebagian Rusak';
                $display_status_en = 'Partially Damaged';
            }
        } else {
            $display_status = 'Dikembalikan';
            $display_status_en = 'Returned';
        }
    } else if ($total_dikembalikan > 0 && $total_dikembalikan < $total_items) {
        $display_status = 'Sebagian Dikembalikan';
        $display_status_en = 'Partially Returned';
    }
}

// REAL-TIME DUE STATUS (use nearest expected return considering extends)
$nearest_expected = getNearestExpectedReturn($conn, $peminjaman['id']);
$display_status = computeDueStatus($display_status, $nearest_expected ?? $peminjaman['rencana_kembali']);
$display_status_en = $display_status;

echo json_encode([
    "status" => true,
    "data" => [
        'id' => $peminjaman['id'],
        'kode_peminjaman' => $peminjaman['kode_peminjaman'],
        'nama' => $peminjaman['nama'],
        'nrp' => $peminjaman['nrp'],
        'tanggal_pinjam' => $peminjaman['tanggal_pinjam'] ? date('d/m/Y', strtotime($peminjaman['tanggal_pinjam'])) : '-',
        'rencana_kembali' => $peminjaman['rencana_kembali'] ? date('d/m/Y', strtotime($peminjaman['rencana_kembali'])) : '-',
        'expected_return_nearest' => $nearest_expected ? date('d/m/Y', strtotime($nearest_expected)) : ($peminjaman['rencana_kembali'] ? date('d/m/Y', strtotime($peminjaman['rencana_kembali'])) : '-'),
        'status' => $display_status,
        'status_en' => $display_status_en,
        'catatan' => $peminjaman['catatan'],
        'detail_barang' => $detail_barang
    ]
]);
?>
