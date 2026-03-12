<?php
/**
 * API: Process partial approval for borrowing items (per-unit)
 * 
 * POST params:
 *   peminjaman_id (int) - required
 *   items (JSON string) - array of {detail_peminjaman_id, unit_number, status}
 *     status: "approved" or "rejected"
 *   rejection_reason (string) - required if any item is rejected
 *
 * Logic:
 *   - Creates peminjaman_units rows if they don't exist yet (pending borrowing)
 *   - Sets approval_status per unit
 *   - Determines overall peminjaman status:
 *       All approved => Borrowed
 *       All rejected => Rejected
 *       Mixed => Partial Approved
 *   - Restores stock for rejected units
 *   - Stores rejection_reason on peminjaman if any rejected
 */
require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['manager', 'admin']);
    
    $peminjaman_id = intval($_POST['peminjaman_id'] ?? 0);
    $items_json = $_POST['items'] ?? '[]';
    $reject_reason = trim($_POST['rejection_reason'] ?? ($_POST['reject_reason'] ?? ''));
    
    if (!$peminjaman_id) {
        throw new Exception('Borrowing ID is required.');
    }
    
    $items_decisions = json_decode($items_json, true);
    if (!is_array($items_decisions) || empty($items_decisions)) {
        throw new Exception('Invalid or empty items data.');
    }
    
    // Validate all items have required fields and valid status
    foreach ($items_decisions as $idx => $item) {
        if (!isset($item['detail_peminjaman_id']) || !isset($item['unit_number']) || !isset($item['status'])) {
            throw new Exception("Item at index $idx missing required fields (detail_peminjaman_id, unit_number, status).");
        }
        if (!in_array($item['status'], ['approved', 'rejected', 'Approved', 'Rejected'])) {
            throw new Exception("Item at index $idx has invalid status: " . $item['status']);
        }
    }
    
    $conn->begin_transaction();
    
    // 1. Verify peminjaman exists and is pending
    $stmt_check = $conn->prepare("SELECT id, status, rencana_kembali FROM peminjaman WHERE id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $peminjaman_id);
    $stmt_check->execute();
    $peminjaman = $stmt_check->get_result()->fetch_assoc();
    
    if (!$peminjaman) {
        throw new Exception("Borrowing not found.");
    }
    
    if ($peminjaman['status'] !== 'Waiting for Approval') {
        throw new Exception("Borrowing status is '{$peminjaman['status']}', can only process 'Waiting for Approval'.");
    }
    
    // 2. Get detail_peminjaman rows
    $stmt_detail = $conn->prepare("
        SELECT dp.id, dp.barang_id, dp.jumlah, dp.lokasi, dp.expected_return, dp.kondisi_pinjam,
               b.nama_barang
        FROM detail_peminjaman dp
        JOIN barang b ON b.id = dp.barang_id
        WHERE dp.peminjaman_id = ?
    ");
    $stmt_detail->bind_param("i", $peminjaman_id);
    $stmt_detail->execute();
    $detail_rows = $stmt_detail->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($detail_rows)) {
        throw new Exception("No detail items found for this borrowing.");
    }
    
    // Build a map of detail_peminjaman rows by id
    $detail_map = [];
    foreach ($detail_rows as $row) {
        $detail_map[$row['id']] = $row;
    }
    
    // 3. Check if peminjaman_units already exist
    $stmt_units_check = $conn->prepare("SELECT COUNT(*) as cnt FROM peminjaman_units WHERE peminjaman_id = ?");
    $stmt_units_check->bind_param("i", $peminjaman_id);
    $stmt_units_check->execute();
    $units_exist = $stmt_units_check->get_result()->fetch_assoc()['cnt'] > 0;
    
    // 4. If units don't exist, create them from detail_peminjaman
    if (!$units_exist) {
        $stmt_insert_unit = $conn->prepare("
            INSERT INTO peminjaman_units 
            (peminjaman_id, detail_peminjaman_id, barang_id, unit_number, unit_display, return_status, expected_return, created_at) 
            VALUES (?, ?, ?, ?, ?, 'Waiting for Approval', ?, NOW())
        ");
        
        foreach ($detail_rows as $detail) {
            $qty = (int)$detail['jumlah'];
            $expected = $detail['expected_return'] ?: $peminjaman['rencana_kembali'];
            for ($i = 1; $i <= $qty; $i++) {
                $display = "Unit $i of $qty";
                $stmt_insert_unit->bind_param("iiiiss", 
                    $peminjaman_id, 
                    $detail['id'], 
                    $detail['barang_id'], 
                    $i, 
                    $display,
                    $expected
                );
                if (!$stmt_insert_unit->execute()) {
                    throw new Exception("Failed to create unit row: " . $stmt_insert_unit->error);
                }
            }
        }
    }
    
    // 5. Process each decision - update peminjaman_units
    $manager_id = SessionValidator::getUserId();
    $approved_count = 0;
    $rejected_count = 0;
    $total_count = count($items_decisions);
    
    // Track rejected qty per barang_id for stock restore
    $rejected_stock = []; // barang_id => qty to restore
    
    $stmt_update_unit = $conn->prepare("
        UPDATE peminjaman_units 
        SET approval_status = ?, 
            approved_by = ?, 
            approval_time = NOW(),
            return_status = ?
        WHERE peminjaman_id = ? AND detail_peminjaman_id = ? AND unit_number = ?
    ");
    
    foreach ($items_decisions as $item) {
        $detail_id = intval($item['detail_peminjaman_id']);
        $unit_num = intval($item['unit_number']);
        $decision = $item['status']; // 'approved'/'Approved' or 'rejected'/'Rejected'
        
        if ($decision === 'approved' || $decision === 'Approved') {
            $approval_status = 'Approved';
            $return_status = 'Not Yet Returned'; // Approved = ready to be borrowed
            $approved_count++;
        } else {
            $approval_status = 'Rejected';
            $return_status = 'Rejected'; // Rejected
            $rejected_count++;
            
            // Track stock to restore
            if (isset($detail_map[$detail_id])) {
                $bid = $detail_map[$detail_id]['barang_id'];
                if (!isset($rejected_stock[$bid])) $rejected_stock[$bid] = 0;
                $rejected_stock[$bid]++;
            }
        }
        
        $stmt_update_unit->bind_param("sisiii", 
            $approval_status, 
            $manager_id, 
            $return_status, 
            $peminjaman_id, 
            $detail_id, 
            $unit_num
        );
        
        if (!$stmt_update_unit->execute()) {
            throw new Exception("Failed to update unit: " . $stmt_update_unit->error);
        }
    }
    
    // 6. Determine overall status
    if ($approved_count === $total_count) {
        $overall_status = 'Borrowed';
    } elseif ($rejected_count === $total_count) {
        $overall_status = 'Rejected';
    } else {
        $overall_status = 'Partial Approved';
    }
    
    // 7. Update peminjaman record
    $tanggal_disetujui = ($approved_count > 0) ? date('Y-m-d') : null;
    
    if ($tanggal_disetujui) {
        $stmt_update_peminjaman = $conn->prepare("
            UPDATE peminjaman 
            SET status = ?, tanggal_disetujui = ?, rejection_reason = ?
            WHERE id = ?
        ");
        $stmt_update_peminjaman->bind_param("sssi", $overall_status, $tanggal_disetujui, $reject_reason, $peminjaman_id);
    } else {
        $stmt_update_peminjaman = $conn->prepare("
            UPDATE peminjaman 
            SET status = ?, rejection_reason = ?
            WHERE id = ?
        ");
        $stmt_update_peminjaman->bind_param("ssi", $overall_status, $reject_reason, $peminjaman_id);
    }
    
    if (!$stmt_update_peminjaman->execute()) {
        throw new Exception("Failed to update borrowing status: " . $stmt_update_peminjaman->error);
    }
    
    // 8. Restore stock for rejected units (cap at stok_total)
    if (!empty($rejected_stock)) {
        $stmt_restore = $conn->prepare("
            UPDATE barang SET stok_tersedia = LEAST(stok_total, stok_tersedia + ?) WHERE id = ?
        ");
        foreach ($rejected_stock as $barang_id => $qty) {
            $stmt_restore->bind_param("ii", $qty, $barang_id);
            $stmt_restore->execute();
        }
    }
    
    $conn->commit();
    
    // 9. Send email notifications
    if ($overall_status === 'Borrowed' || $overall_status === 'Partial Approved') {
        // Some items approved - send approval email
        try {
            if (file_exists(__DIR__ . '/../email/send-approved.php')) {
                require_once __DIR__ . '/../email/send-approved.php';
                sendApprovedEmail($conn, $peminjaman_id);
            }
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] approve-items approved: " . $emailEx->getMessage());
        }
    }
    
    if ($overall_status === 'Rejected') {
        // All rejected - send rejection email
        try {
            if (file_exists(__DIR__ . '/../email/send-rejected.php')) {
                require_once __DIR__ . '/../email/send-rejected.php';
                sendRejectedEmail($conn, $peminjaman_id, 'Loan');
            }
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] approve-items rejected: " . $emailEx->getMessage());
        }
    }
    
    echo json_encode([
        "status" => true,
        "message" => "Borrowing successfully processed.",
        "peminjaman_id" => $peminjaman_id,
        "overall_status" => $overall_status,
        "approved_count" => $approved_count,
        "rejected_count" => $rejected_count,
        "items_processed" => $total_count
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->errno) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>
