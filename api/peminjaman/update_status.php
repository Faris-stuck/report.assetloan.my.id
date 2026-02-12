<?php
require_once "../koneksi.php";
header('Content-Type: application/json');
// Server-side session validation
require_once "../session-helper.php";

// Validate user role
try {
    SessionValidator::requireRole(['manager', 'admin']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status" => false,
        "message" => "Unauthorized: " . $e->getMessage()
    ]);
    exit;
}



$id = (int) ($_POST['id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');

if (!$id || !$new_status) {
    echo json_encode([
        "status" => false,
        "message" => "ID dan status diperlukan"
    ]);
    exit;
}

// Ambil status saat ini dari database
$stmt_cur = $conn->prepare("SELECT status FROM peminjaman WHERE id = ?");
$stmt_cur->bind_param("i", $id);
$stmt_cur->execute();
$res_cur = $stmt_cur->get_result();
if (!$res_cur || $res_cur->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "Peminjaman tidak ditemukan"]);
    exit;
}
$current = $res_cur->fetch_assoc()['status'];
$role = SessionValidator::getRole();

// Validasi alur: Requester -> Approver (manager) -> Admin
// Menunggu Approver -> hanya Approver/Admin boleh set ke "Menunggu Admin" atau "Ditolak"
// Menunggu Admin -> hanya Admin boleh set ke "Sedang Dipinjam" atau "Ditolak"
if ($current === 'Menunggu Approver') {
    if (!in_array($new_status, ['Menunggu Admin', 'Ditolak'], true)) {
        echo json_encode(["status" => false, "message" => "Dari Menunggu Approver hanya bisa diubah ke Menunggu Admin atau Ditolak"]);
        exit;
    }
    if (!in_array($role, ['manager', 'admin'], true)) {
        echo json_encode(["status" => false, "message" => "Hanya Approver atau Admin yang boleh menyetujui/menolak tahap ini"]);
        exit;
    }
} elseif ($current === 'Menunggu Admin') {
    if (!in_array($new_status, ['Sedang Dipinjam', 'Ditolak'], true)) {
        echo json_encode(["status" => false, "message" => "Dari Menunggu Admin hanya bisa diubah ke Sedang Dipinjam atau Ditolak"]);
        exit;
    }
    if ($role !== 'admin') {
        echo json_encode(["status" => false, "message" => "Hanya Admin yang boleh menyetujui/menolak tahap Admin Approval"]);
        exit;
    }
} else {
    echo json_encode(["status" => false, "message" => "Status saat ini tidak dapat diubah dari sini"]);
    exit;
}

$conn->begin_transaction();

try {
    // Update status peminjaman
    $stmt = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();

    // Jika status disetujui jadi Sedang Dipinjam, update tanggal persetujuan
    if ($new_status === 'Sedang Dipinjam') {
        $stmt_approve = $conn->prepare("UPDATE peminjaman SET tanggal_disetujui = CURDATE() WHERE id = ?");
        $stmt_approve->bind_param("i", $id);
        $stmt_approve->execute();
    }

    // Jika status ditolak, kembalikan stok barang (cap at stok_total)
    if ($new_status === 'Ditolak') {
        // Ambil detail peminjaman untuk mengembalikan stok
        $stmt_detail = $conn->prepare("
            SELECT barang_id, jumlah FROM detail_peminjaman WHERE peminjaman_id = ?
        ");
        $stmt_detail->bind_param("i", $id);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();

        while ($row = $result_detail->fetch_assoc()) {
            $stmt_stok = $conn->prepare("
                UPDATE barang SET stok_tersedia = LEAST(stok_total, stok_tersedia + ?) WHERE id = ?
            ");
            $stmt_stok->bind_param("ii", $row['jumlah'], $row['barang_id']);
            $stmt_stok->execute();
        }
    }

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Status berhasil diperbarui"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>