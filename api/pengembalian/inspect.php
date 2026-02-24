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
 *     { "barang_id": 1, "kondisi_kembali": "Baik|Rusak", "jumlah_rusak": 0, "biaya_ganti_rugi": 0, "catatan": "" }
 *   ]
 *
 * Behavior:
 * - Update detail_pengembalian sesuai inspeksi
 * - Jika ada rusak: set barang.kondisi='Rusak' (sederhana, level barang)
 * - Update peminjaman.status='Dikembalikan' dan tanggal_kembali=CURDATE()
 * - Kembalikan stok_tersedia hanya untuk jumlah baik (jumlah_kembali - jumlah_rusak)
 * - Set pengembalian.status='Selesai' + total_ganti_rugi
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
    echo json_encode(["status" => false, "message" => "pengembalian_id dan items wajib"]);
    exit;
}

$items = json_decode($items_json, true);
if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Format items tidak valid"]);
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
        throw new Exception("Pengembalian tidak ditemukan");
    }
    // If already finished, nothing to do
    if ($header['status'] === 'Selesai') {
        echo json_encode(["status" => true, "message" => "Pengembalian sudah selesai diproses"]);
        $conn->commit();
        exit;
    }

    // Mark as 'Dicek' (being processed) when admin/pic starts inspecting
    if ($header['status'] === 'Diajukan') {
        $updStatus = $conn->prepare("UPDATE pengembalian SET status = 'Dicek', dicek_at = NOW() WHERE id = ?");
        $updStatus->bind_param("i", $pengembalian_id);
        $updStatus->execute();
        // reflect change in local header var
        $header['status'] = 'Dicek';
    }

    $has_rusak = 0;
    $total_ganti_rugi = 0.00;

    $upd = $conn->prepare("
        UPDATE detail_pengembalian
        SET jumlah_kembali = ?, kondisi_kembali = ?, jumlah_rusak = ?, biaya_ganti_rugi = ?, catatan = ?
        WHERE pengembalian_id = ? AND barang_id = ?
    ");

    // Reset stok return effect first by not supporting re-inspect after completion.
    foreach ($items as $it) {
        $barang_id = (int)($it['barang_id'] ?? 0);
        $jumlah_kembali = max(0, (int)($it['jumlah_kembali'] ?? 0));
        $kondisi = ($it['kondisi_kembali'] ?? 'Baik') === 'Rusak' ? 'Rusak' : 'Baik';
        $jumlah_rusak = max(0, (int)($it['jumlah_rusak'] ?? 0));
        $biaya = (float)($it['biaya_ganti_rugi'] ?? 0);
        $catatan = trim((string)($it['catatan'] ?? ''));

        if (!$barang_id) continue;

        // Ensure jumlah_rusak does not exceed jumlah_kembali
        if ($jumlah_rusak > $jumlah_kembali) {
            $jumlah_rusak = $jumlah_kembali;
        }

        if ($kondisi === 'Rusak') {
            $has_rusak = 1;
            $total_ganti_rugi += $biaya;
            // Ensure barang has stok_rusak column
            $col = $conn->query("SHOW COLUMNS FROM barang LIKE 'stok_rusak'");
            if ($col->num_rows === 0) {
                $conn->query("ALTER TABLE barang ADD COLUMN stok_rusak INT NOT NULL DEFAULT 0");
            }
            // Increment stok_rusak by jumlah_rusak for this barang
            if ($jumlah_rusak > 0) {
                $conn->query("UPDATE barang SET stok_rusak = stok_rusak + $jumlah_rusak WHERE id = " . (int)$barang_id);
            }
        }

        $upd->bind_param("isidsii", $jumlah_kembali, $kondisi, $jumlah_rusak, $biaya, $catatan, $pengembalian_id, $barang_id);
        if (!$upd->execute()) {
            throw new Exception("Gagal update detail pengembalian: " . $upd->error);
        }
    }

    // Kembalikan stok berdasarkan jumlah baik
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
    
    // Get total items borrowed
    $tq = $conn->prepare("SELECT SUM(jumlah) as total FROM detail_peminjaman WHERE peminjaman_id = ?");
    $tq->bind_param("i", $peminjaman_id);
    $tq->execute();
    $tq_result = $tq->get_result()->fetch_assoc();
    $total_items = (int)($tq_result['total'] ?? 0);
    
    // Get AGGREGATE total returned and damaged from ALL pengembalian records (incl. current one being finalized)
    // This counts pengembalian with status IN ('Dicek', 'Selesai') to include the current inspection
    $agg = $conn->prepare("
        SELECT 
            COALESCE(SUM(dp.jumlah_kembali), 0) as total_kembali,
            COALESCE(SUM(dp.jumlah_rusak), 0) as total_rusak
        FROM detail_pengembalian dp
        JOIN pengembalian p ON dp.pengembalian_id = p.id
        WHERE p.peminjaman_id = ? AND (p.status = 'Selesai' OR p.id = ?)
    ");
    $agg->bind_param("ii", $peminjaman_id, $pengembalian_id);
    $agg->execute();
    $agg_result = $agg->get_result()->fetch_assoc();
    $total_returned = (int)($agg_result['total_kembali'] ?? 0);
    $total_damaged = (int)($agg_result['total_rusak'] ?? 0);
    
    $sisa = $total_items - $total_returned;
    
    // Determine status based on how many items are returned vs total
    if ($sisa <= 0 && $total_items > 0) {
        // All items returned - regardless of damage status, mark as 'Dikembalikan'
        $final_status = 'Dikembalikan';
        // Set tanggal_kembali only when ALL items returned
        $upd_peminjaman = $conn->prepare("UPDATE peminjaman SET status = ?, tanggal_kembali = CURDATE() WHERE id = ?");
        $upd_peminjaman->bind_param("si", $final_status, $peminjaman_id);
    } else if ($total_returned > 0) {
        // Partial return - some items still out
        // Use 'Proses Return' to indicate inspection/return process is ongoing
        $final_status = 'Proses Return';
        $upd_peminjaman = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id = ?");
        $upd_peminjaman->bind_param("si", $final_status, $peminjaman_id);
    } else {
        // Nothing returned (edge case: PIC set all qty to 0)
        // Keep as 'Sedang Dipinjam' since no return yet
        $final_status = 'Sedang Dipinjam';
        $upd_peminjaman = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id = ?");
        $upd_peminjaman->bind_param("si", $final_status, $peminjaman_id);
    }
    
    if (!$upd_peminjaman->execute()) {
        throw new Exception("Gagal update peminjaman status: " . $upd_peminjaman->error);
    }

    // Update header pengembalian
    $u = $conn->prepare("
        UPDATE pengembalian
        SET status = 'Selesai',
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
        throw new Exception("Gagal update header pengembalian: " . $u->error);
    }

    $conn->commit();

    // Kirim email notifikasi ke user saat semua barang dikembalikan
    if ($final_status === 'Dikembalikan') {
        try {
            require_once __DIR__ . '/../email/send-return-confirmed.php';
            sendReturnConfirmedEmail($conn, $peminjaman_id);
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] pengembalian/inspect: " . $emailEx->getMessage());
        }
    }

    echo json_encode([
        "status" => true,
        "message" => $has_rusak ? "Selesai. Ada barang rusak, user wajib ganti rugi." : "Selesai. Pengembalian dalam kondisi baik.",
        "has_rusak" => (int)$has_rusak,
        "total_ganti_rugi" => $total_ganti_rugi
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}

