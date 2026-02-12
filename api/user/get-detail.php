<?php
require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

// Validate user role
try {
    SessionValidator::requireRole(['user']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status" => false,
        "message" => "Unauthorized: " . $e->getMessage()
    ]);
    exit;
}

$peminjaman_id = $_GET['peminjaman_id'] ?? $_GET['id'];

if (!$peminjaman_id) {
    echo json_encode([
        "status" => false,
        "message" => "peminjaman_id tidak ditemukan"
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT p.id, p.kode_peminjaman, p.user_id, p.tanggal_pinjam, p.rencana_kembali, 
           p.status, p.catatan, u.nama, u.nrp, u.nama as nama_peminjam
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");

$stmt->bind_param("i", $peminjaman_id);
$stmt->execute();
$result = $stmt->get_result();
$peminjaman = $result->fetch_assoc();

if (!$peminjaman) {
    echo json_encode([
        "status" => false,
        "message" => "Peminjaman tidak ditemukan"
    ]);
    exit;
}

// Ambil detail barang
$stmt_detail = $conn->prepare("
    SELECT b.id AS barang_id, b.nama_barang, dp.jumlah, dp.lokasi
    FROM detail_peminjaman dp
    JOIN barang b ON dp.barang_id = b.id
    WHERE dp.peminjaman_id = ?
");

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

// Attach pengembalian inspection details if any
$q = $conn->prepare("SELECT id FROM pengembalian WHERE peminjaman_id = ? ORDER BY id DESC LIMIT 1");
$q->bind_param("i", $peminjaman_id);
$q->execute();
$qh = $q->get_result()->fetch_assoc();
$display_status = $peminjaman['status'];
$display_status_en = $peminjaman['status'];
if ($qh) {
    $peng_id = (int)$qh['id'];
    $sd = $conn->prepare("SELECT barang_id, jumlah_kembali, kondisi_kembali, jumlah_rusak FROM detail_pengembalian WHERE pengembalian_id = ?");
    $sd->bind_param("i", $peng_id);
    $sd->execute();
    $rd = $sd->get_result();
    $map = [];
    $total_items = 0;
    $total_rusak = 0;
    while ($r = $rd->fetch_assoc()) {
        $map[(int)$r['barang_id']] = $r;
    }
    foreach ($detail_barang as &$dbi) {
        $bid = (int)$dbi['barang_id'];
        $total_items += $dbi['jumlah'];
        if (isset($map[$bid])) {
            $dbi['jumlah_kembali'] = (int)$map[$bid]['jumlah_kembali'];
            $dbi['jumlah_rusak'] = (int)$map[$bid]['jumlah_rusak'];
            $dbi['kondisi_kembali'] = $map[$bid]['kondisi_kembali'];
            $total_rusak += (int)$map[$bid]['jumlah_rusak'];
        }
    }
    // compute damage status
    if ($total_rusak > 0) {
        if ($total_rusak < $total_items) {
            $display_status = 'Sebagian Rusak';
            $display_status_en = 'Partially Damaged';
        } else {
            $display_status = 'Semua Rusak';
            $display_status_en = 'Fully Damaged';
        }
    }
}

echo json_encode([
    "status" => true,
    "data" => [
        'id' => $peminjaman['id'],
        'kode_peminjaman' => $peminjaman['kode_peminjaman'],
        'nama' => $peminjaman['nama'],
        'nrp' => $peminjaman['nrp'],
        'tanggal_pinjam' => $peminjaman['tanggal_pinjam'],
        'rencana_kembali' => $peminjaman['rencana_kembali'],
        'status' => $display_status,
        'status_en' => $display_status_en,
        'catatan' => $peminjaman['catatan'],
        'detail_barang' => $detail_barang
    ]
]);
?>
