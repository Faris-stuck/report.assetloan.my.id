<?php
require_once "../koneksi.php";
header('Content-Type: application/json');
require_once "../session-helper.php";

// Validate user role
try {
    SessionValidator::requireRole(['admin', 'manager']);
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
        "message" => "ID and status are required"
    ]);
    exit;
}

// Ambil status saat ini dari database
$stmt_cur = $conn->prepare("SELECT status FROM peminjaman WHERE id = ?");
$stmt_cur->bind_param("i", $id);
$stmt_cur->execute();
$res_cur = $stmt_cur->get_result();
if (!$res_cur || $res_cur->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "Borrowing not found"]);
    exit;
}
$current = $res_cur->fetch_assoc()['status'];

// Validasi alur untuk Admin
if ($current === 'Disetujui') {
    if (!in_array($new_status, ['Sedang Dipinjam', 'Ditolak'], true)) {
        echo json_encode(["status" => false, "message" => "From Approved, can only change to Active Borrowing or Rejected"]);
        exit;
    }
} else {
    echo json_encode(["status" => false, "message" => "Current status cannot be changed from here"]);
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

    // Kirim email notifikasi setelah berhasil
    if ($new_status === 'Sedang Dipinjam') {
        try {
            require_once __DIR__ . '/../email/send-approved.php';
            sendApprovedEmail($conn, $id);
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] admin/update-status: " . $emailEx->getMessage());
        }
    }

    // Kirim email notifikasi penolakan ke user
    if ($new_status === 'Ditolak') {
        try {
            require_once __DIR__ . '/../email/send-rejected.php';
            sendRejectedEmail($conn, $id, 'Loan');
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] admin/update-status-reject: " . $emailEx->getMessage());
        }
    }

    echo json_encode([
        "status" => true,
        "message" => "Status updated successfully"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>
