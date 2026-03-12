<?php
/**
 * API: Inspeksi pengembalian (Admin/PIC Barang)
 * Endpoint: /api/pengembalian/inspect.php
 *
 * Input (POST):
 * - pengembalian_id (int) required
 * - catatan_petugas (string) optional
 * - items (json string) required:
 *   [
 *     { "barang_id": 1, "kondisi_kembali": "Good|Damaged", "jumlah_rusak": 0, "biaya_ganti_rugi": 0, "catatan": "" }
 *   ]
 *
 * Behavior:
 * - Update detail_pengembalian sesuai inspeksi
 * - If damaged: set barang.kondisi='Damaged' (simple, barang level)
 * - Update peminjaman.status='Returned' and tanggal_kembali=CURDATE()
 * - Return stok_tersedia only for good qty (jumlah_kembali - jumlah_rusak)
 * - Set pengembalian.status='Completed' + total_ganti_rugi
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    SessionValidator::requireRole(['admin', 'pic_barang']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit;
}

$role = SessionValidator::getRole();
$checker_user_id = (int)(SessionValidator::getUserId() ?? 0);

$pengembalian_id = (int)($_POST['pengembalian_id'] ?? 0);
$catatan_petugas = trim((string)($_POST['catatan_petugas'] ?? ''));
$items_json = (string)($_POST['items'] ?? '');

if (!$pengembalian_id || $items_json === '') {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "pengembalian_id and items are required"]);
    exit;
}

$items = json_decode($items_json, true);
if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Invalid items format"]);
    exit;
}

$conn->begin_transaction();
try {
    // Lock header
    $h = $conn->prepare("SELECT id, peminjaman_id, status FROM pengembalian WHERE id = ? FOR UPDATE");
    $h->bind_param("i", $pengembalian_id);
    $h->execute();
    $header = $h->get_result()->fetch_assoc();
    if (!$header) {
        throw new Exception("Return not found");
    }
    // If already finished, nothing to do
    if ($header['status'] === 'Completed') {
        echo json_encode(["status" => true, "message" => "Return already processed"]);
        $conn->commit();
        exit;
    }

    // Mark as 'Being Inspected' (being processed) when admin/pic starts inspecting
    if ($header['status'] === 'Submitted') {
        $updStatus = $conn->prepare("UPDATE pengembalian SET status = 'Being Inspected', dicek_at = NOW() WHERE id = ?");
        $updStatus->bind_param("i", $pengembalian_id);
        $updStatus->execute();
        // reflect change in local header var
        $header['status'] = 'Being Inspected';
    }

    $has_rusak = 0;
    $total_ganti_rugi = 0.00;

    $upd = $conn->prepare("
        UPDATE detail_pengembalian
        SET jumlah_kembali = ?, kondisi_kembali = ?, jumlah_rusak = ?, biaya_ganti_rugi = ?, catatan = ?
        WHERE pengembalian_id = ? AND barang_id = ?
    ");

    // Ensure barang has stok_rusak column (check once outside loop)
    $col = $conn->query("SHOW COLUMNS FROM barang LIKE 'stok_rusak'");
    if ($col->num_rows === 0) {
        $conn->query("ALTER TABLE barang ADD COLUMN stok_rusak INT NOT NULL DEFAULT 0");
    }

    // Process each item in the inspection
    foreach ($items as $it) {
        $barang_id = (int)($it['barang_id'] ?? 0);
        $jumlah_kembali = max(0, (int)($it['jumlah_kembali'] ?? 0));
        $kondisi = ($it['kondisi_kembali'] ?? 'Good') === 'Damaged' ? 'Damaged' : 'Good';
        $jumlah_rusak = max(0, (int)($it['jumlah_rusak'] ?? 0));
        $biaya = (float)($it['biaya_ganti_rugi'] ?? 0);
        $catatan = trim((string)($it['catatan'] ?? ''));

        if (!$barang_id) continue;

        // Ensure jumlah_rusak does not exceed jumlah_kembali
        if ($jumlah_rusak > $jumlah_kembali) {
            $jumlah_rusak = $jumlah_kembali;
        }

        if ($kondisi === 'Damaged') {
            $has_rusak = 1;
            $total_ganti_rugi += $biaya;
            // Increment stok_rusak by jumlah_rusak for this barang
            if ($jumlah_rusak > 0) {
                $stmtRusak = $conn->prepare("UPDATE barang SET stok_rusak = stok_rusak + ? WHERE id = ?");
                $stmtRusak->bind_param("ii", $jumlah_rusak, $barang_id);
                $stmtRusak->execute();
            }
        }

        $upd->bind_param("isidsii", $jumlah_kembali, $kondisi, $jumlah_rusak, $biaya, $catatan, $pengembalian_id, $barang_id);
        if (!$upd->execute()) {
            throw new Exception("Failed to update return detail: " . $upd->error);
        }
    }

    // Return stock based on good qty
    $dq = $conn->prepare("SELECT barang_id, jumlah_kembali, jumlah_rusak FROM detail_pengembalian WHERE pengembalian_id = ?");
    $dq->bind_param("i", $pengembalian_id);
    $dq->execute();
    $dr = $dq->get_result();
    while ($d = $dr->fetch_assoc()) {
        $barang_id = (int)$d['barang_id'];
        $jumlah_kembali = (int)$d['jumlah_kembali'];
        $jumlah_rusak = (int)$d['jumlah_rusak'];
        if ($jumlah_rusak > $jumlah_kembali) $jumlah_rusak = $jumlah_kembali;
        $jumlah_baik = $jumlah_kembali - $jumlah_rusak;
        if ($jumlah_baik > 0) {
            $conn->query("UPDATE barang SET stok_tersedia = LEAST(stok_total, stok_tersedia + $jumlah_baik) WHERE id = $barang_id");
        }
    }

    // Update peminjaman status based on AGGREGATE totals across ALL pengembalian records (including current one)
    $peminjaman_id = (int)$header['peminjaman_id'];
    
    // Get total items borrowed (approved units from peminjaman_units)
    $tq = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman_units WHERE peminjaman_id = ? AND approval_status = 'Approved'");
    $tq->bind_param("i", $peminjaman_id);
    $tq->execute();
    $tq_result = $tq->get_result()->fetch_assoc();
    $total_items = (int)($tq_result['total'] ?? 0);
    
    // Get AGGREGATE total returned and damaged from ALL pengembalian records (incl. current one being finalized)
    // This counts pengembalian with status IN ('Being Inspected', 'Completed') to include the current inspection
    $agg = $conn->prepare("
        SELECT 
            COALESCE(SUM(dp.jumlah_kembali), 0) as total_kembali,
            COALESCE(SUM(dp.jumlah_rusak), 0) as total_rusak
        FROM detail_pengembalian dp
        JOIN pengembalian p ON dp.pengembalian_id = p.id
        WHERE p.peminjaman_id = ? AND (p.status = 'Completed' OR p.id = ?)
    ");
    $agg->bind_param("ii", $peminjaman_id, $pengembalian_id);
    $agg->execute();
    $agg_result = $agg->get_result()->fetch_assoc();
    $total_returned = (int)($agg_result['total_kembali'] ?? 0);
    $total_damaged = (int)($agg_result['total_rusak'] ?? 0);
    
    $sisa = $total_items - $total_returned;
    
    // Determine status based on how many items are returned vs total
    if ($sisa <= 0 && $total_items > 0) {
        // All items returned - regardless of damage status, mark as 'Returned'
        $final_status = 'Returned';
        // Set tanggal_kembali only when ALL items returned
        $upd_peminjaman = $conn->prepare("UPDATE peminjaman SET status = ?, tanggal_kembali = CURDATE() WHERE id = ?");
        $upd_peminjaman->bind_param("si", $final_status, $peminjaman_id);

        // Sync peminjaman_units: mark all unreturned units as 'Returned'
        $upd_units = $conn->prepare("UPDATE peminjaman_units SET return_status = 'Returned' WHERE peminjaman_id = ? AND return_status = 'Not Yet Returned'");
        $upd_units->bind_param("i", $peminjaman_id);
        $upd_units->execute();
    } else if ($total_returned > 0) {
        // Partial return - some items still out but this inspection batch is finalized
        // Check if there are still PENDING return requests for this peminjaman
        $chkPending = $conn->prepare("SELECT COUNT(*) as cnt FROM pengembalian WHERE peminjaman_id = ? AND status IN ('Submitted', 'Being Inspected') AND id != ?");
        $chkPending->bind_param("ii", $peminjaman_id, $pengembalian_id);
        $chkPending->execute();
        $pendingCount = (int)($chkPending->get_result()->fetch_assoc()['cnt'] ?? 0);
        
        if ($pendingCount > 0) {
            // There are still pending return requests → keep as 'Return in Process'
            $final_status = 'Return in Process';
        } else {
            // All returns finalized, but items remain → 'Partially Returned'
            $final_status = 'Partially Returned';
        }
        $upd_peminjaman = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id = ?");
        $upd_peminjaman->bind_param("si", $final_status, $peminjaman_id);
    } else {
        // Nothing returned (edge case: PIC set all qty to 0)
        // Keep as 'Borrowed' since no return yet
        $final_status = 'Borrowed';
        $upd_peminjaman = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id = ?");
        $upd_peminjaman->bind_param("si", $final_status, $peminjaman_id);
    }
    
    if (!$upd_peminjaman->execute()) {
        throw new Exception("Failed to update borrowing status: " . $upd_peminjaman->error);
    }

    // Update header pengembalian
    $u = $conn->prepare("
        UPDATE pengembalian
        SET status = 'Completed',
            catatan_petugas = ?,
            checked_by_role = ?,
            checked_by_user_id = ?,
            has_rusak = ?,
            total_ganti_rugi = ?,
            dicek_at = COALESCE(dicek_at, NOW()),
            selesai_at = NOW()
        WHERE id = ?
    ");
    $u->bind_param("ssiidi", $catatan_petugas, $role, $checker_user_id, $has_rusak, $total_ganti_rugi, $pengembalian_id);
    if (!$u->execute()) {
        throw new Exception("Failed to update return header: " . $u->error);
    }

    $conn->commit();

    // Send email notification to user when all items are returned
    if ($final_status === 'Returned') {
        try {
            require_once __DIR__ . '/../email/send-return-confirmed.php';
            sendReturnConfirmedEmail($conn, $peminjaman_id);
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] pengembalian/inspect: " . $emailEx->getMessage());
        }
    }

    echo json_encode([
        "status" => true,
        "message" => $has_rusak ? "Complete. Damaged items found, user must pay compensation." : "Complete. All returned items are in good condition.",
        "has_rusak" => (int)$has_rusak,
        "total_ganti_rugi" => $total_ganti_rugi
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}

