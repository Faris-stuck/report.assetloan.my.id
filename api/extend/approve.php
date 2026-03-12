<?php
/**
 * API: Approve Extend Request
 * Method: POST
 * Params: extend_id
 * Role: admin, pic_barang
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../session-helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed']);
    exit;
}

$session = new SessionValidator();
if (!$session->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$role = $session->getRole();
if (!in_array($role, ['admin', 'pic_barang'])) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Access denied. Only admin and PIC Item can approve']);
    exit;
}

$extend_id = isset($_POST['extend_id']) ? (int)$_POST['extend_id'] : 0;

if ($extend_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Invalid Extend ID']);
    exit;
}

try {
    $conn->begin_transaction();

    // Get extend request
    $stmt = $conn->prepare("SELECT e.*, p.rencana_kembali FROM extend_peminjaman e JOIN peminjaman p ON e.peminjaman_id = p.id WHERE e.id = ? FOR UPDATE");
    $stmt->bind_param("i", $extend_id);
    $stmt->execute();
    $extend = $stmt->get_result()->fetch_assoc();

    if (!$extend) {
        $conn->rollback();
        echo json_encode(['status' => false, 'message' => 'Extension request not found']);
        exit;
    }

    if ($extend['status'] !== 'Pending') {
        $conn->rollback();
        echo json_encode(['status' => false, 'message' => 'Request already processed (status: ' . $extend['status'] . ')']);
        exit;
    }

    $approver_id = $session->getUserId();
    $now = date('Y-m-d H:i:s');

    // Update extend_peminjaman status to Approved
    $stmt = $conn->prepare("UPDATE extend_peminjaman SET status = 'Approved', approved_by = ?, approved_at = ? WHERE id = ?");
    $stmt->bind_param("isi", $approver_id, $now, $extend_id);
    $stmt->execute();

    // Update peminjaman.rencana_kembali to the new date
    $stmt = $conn->prepare("UPDATE peminjaman SET rencana_kembali = ? WHERE id = ?");
    $stmt->bind_param("si", $extend['tanggal_perpanjang'], $extend['peminjaman_id']);
    $stmt->execute();

    // ─── SINGLE SOURCE OF TRUTH ───────────────────────────────────────────────
    // Update detail_peminjaman.expected_return so all APIs read from one place.
    // If this extend has per-unit items, update only the referenced detail rows.
    // If it is a blanket extend (no items), update all detail rows for this peminjaman.
    $item_stmt = $conn->prepare("
        SELECT DISTINCT detail_peminjaman_id
        FROM extend_peminjaman_items
        WHERE extend_peminjaman_id = ? AND detail_peminjaman_id IS NOT NULL
    ");
    $item_stmt->bind_param("i", $extend_id);
    $item_stmt->execute();
    $affected_details = $item_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!empty($affected_details)) {
        // Per-unit/detail extend: update only the referenced detail_peminjaman rows
        $upd_stmt = $conn->prepare("UPDATE detail_peminjaman SET expected_return = ? WHERE id = ?");
        foreach ($affected_details as $det) {
            $det_id = (int)$det['detail_peminjaman_id'];
            $upd_stmt->bind_param("si", $extend['tanggal_perpanjang'], $det_id);
            $upd_stmt->execute();
        }
    } else {
        // Blanket extend: update all detail_peminjaman rows for this peminjaman
        $upd_stmt = $conn->prepare("UPDATE detail_peminjaman SET expected_return = ? WHERE peminjaman_id = ?");
        $upd_stmt->bind_param("si", $extend['tanggal_perpanjang'], $extend['peminjaman_id']);
        $upd_stmt->execute();
    }
    // ─────────────────────────────────────────────────────────────────────────

    // ─── UPDATE peminjaman_units.expected_return ─────────────────────────────
    // peminjaman_units is the single source of truth for getNearestExpectedReturn()
    // and all dashboard/cron queries. Must stay in sync with detail_peminjaman.
    $unit_items = $conn->prepare("
        SELECT detail_peminjaman_id, unit_number
        FROM extend_peminjaman_items
        WHERE extend_peminjaman_id = ? AND detail_peminjaman_id IS NOT NULL
    ");
    $unit_items->bind_param("i", $extend_id);
    $unit_items->execute();
    $unit_rows = $unit_items->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!empty($unit_rows)) {
        // Per-unit extend: update only the referenced peminjaman_units rows
        $upd_pu = $conn->prepare("UPDATE peminjaman_units SET expected_return = ? WHERE detail_peminjaman_id = ? AND unit_number = ?");
        foreach ($unit_rows as $ur) {
            $dp_id = (int)$ur['detail_peminjaman_id'];
            $unum  = (int)$ur['unit_number'];
            $upd_pu->bind_param("sii", $extend['tanggal_perpanjang'], $dp_id, $unum);
            $upd_pu->execute();
        }
    } else {
        // Blanket extend: update all unreturned peminjaman_units for this peminjaman
        $upd_pu = $conn->prepare("
            UPDATE peminjaman_units
            SET expected_return = ?
            WHERE peminjaman_id = ?
              AND return_status NOT IN ('Returned', 'Damaged', 'Rejected')
        ");
        $upd_pu->bind_param("si", $extend['tanggal_perpanjang'], $extend['peminjaman_id']);
        $upd_pu->execute();
    }
    // ─────────────────────────────────────────────────────────────────────────

    $conn->commit();

    // Kirim email notifikasi ke user bahwa perpanjangan disetujui
    try {
        require_once __DIR__ . '/../email/send-extend-approved.php';
        sendExtendApprovedEmail($conn, $extend['peminjaman_id'], $extend['tanggal_perpanjang']);
    } catch (Exception $emailEx) {
        error_log("[EMAIL ERROR] extend/approve: " . $emailEx->getMessage());
    }

    echo json_encode([
        'status' => true,
        'message' => 'Extension approved. Return date updated to ' . date('d/m/Y', strtotime($extend['tanggal_perpanjang']))
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
