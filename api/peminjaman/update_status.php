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
$role = SessionValidator::getRole();

// Validasi alur: Requester -> Approver (manager) -> Admin
// Waiting for Approver -> only Approver/Admin can set to "Waiting for Admin" or "Rejected"
// Waiting for Admin -> only Admin can set to "Borrowed" or "Rejected"
if ($current === 'Waiting for Approver') {
    if (!in_array($new_status, ['Waiting for Admin', 'Rejected'], true)) {
        echo json_encode(["status" => false, "message" => "From Awaiting Approver can only be changed to Awaiting Admin or Rejected"]);
        exit;
    }
    if (!in_array($role, ['manager', 'admin'], true)) {
        echo json_encode(["status" => false, "message" => "Only Approver or Admin can approve/reject at this stage"]);
        exit;
    }
} elseif ($current === 'Waiting for Admin') {
    if (!in_array($new_status, ['Borrowed', 'Rejected'], true)) {
        echo json_encode(["status" => false, "message" => "From Awaiting Admin can only be changed to Currently Borrowed or Rejected"]);
        exit;
    }
    if ($role !== 'admin') {
        echo json_encode(["status" => false, "message" => "Only Admin can approve/reject at Admin Approval stage"]);
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

    // If approved to Borrowed, update approval date
    if ($new_status === 'Borrowed') {
        $stmt_approve = $conn->prepare("UPDATE peminjaman SET tanggal_disetujui = CURDATE() WHERE id = ?");
        $stmt_approve->bind_param("i", $id);
        $stmt_approve->execute();
    }

    // If Rejected, restore item stock (cap at stok_total)
    if ($new_status === 'Rejected') {
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

    // Kirim email notifikasi setelah berhasil
    if ($new_status === 'Borrowed') {
        try {
            require_once __DIR__ . '/../email/send-approved.php';
            sendApprovedEmail($conn, $id);
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] peminjaman/update_status: " . $emailEx->getMessage());
        }
    }

    echo json_encode([
        "status" => true,
        "message" => "Status successfully updated"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>