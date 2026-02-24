<?php
/**
 * API: User - Ajukan Pengembalian (membuat record pengembalian yang akan dicek admin/PIC)
 * Endpoint: /api/peminjaman/return.php
 *
 * Input (POST):
 * - peminjaman_id (int) required
 * - catatan_user (string) optional
 * - items (JSON string) optional - array of {barang_id, qty_return, good_condition, damaged}
 *
 * Output:
 * - { status: true, message, pengembalian_id }
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

// Start session before using SessionValidator
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    SessionValidator::requireRole(['user']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit;
}

$user_id = (int) (SessionValidator::getUserId() ?? 0);
$peminjaman_id = (int)($_POST['peminjaman_id'] ?? 0);
$catatan_user = trim((string)($_POST['catatan_user'] ?? ''));
$items_json = trim((string)($_POST['items'] ?? ''));
$items_array = [];

// Debug logging
error_log("DEBUG return.php: user_id={$user_id}, peminjaman_id={$peminjaman_id}, items_json={$items_json}");

if (!$user_id) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "User tidak terdeteksi. Silahkan login kembali.", "debug" => "user_id is empty"]);
    exit;
}

if (!$peminjaman_id) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "peminjaman_id wajib", "debug" => "peminjaman_id is empty"]);
    exit;
}

// Pastikan peminjaman milik user dan sedang dipinjam atau sebagian dikembalikan
    $stmt = $conn->prepare("SELECT id, status, kode_peminjaman FROM peminjaman WHERE id = ? AND user_id = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["status" => false, "message" => "Database error: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("ii", $peminjaman_id, $user_id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();

    if (!$p) {
        http_response_code(404);
        echo json_encode(["status" => false, "message" => "Peminjaman ID $peminjaman_id tidak ditemukan atau bukan milik user ini"]);
        exit;
    }
    
    // Validasi: Hitung total item yang masih belum dikembalikan FIRST (before status checks)
    // Bandingkan aggregate pengembalian dengan detail_peminjaman asli
    $count_total = $conn->prepare("
        SELECT COALESCE(SUM(jumlah), 0) as total_items
        FROM detail_peminjaman
        WHERE peminjaman_id = ?
    ");
    $count_total->bind_param("i", $peminjaman_id);
    $count_total->execute();
    $total_result = $count_total->get_result()->fetch_assoc();
    $total_items = (int)$total_result['total_items'];

    // Hitung total yang sudah dikembalikan dari FINALIZED pengembalian records (status='Selesai')
    // Only count approved/finalized ones, not pending submissions
    $count_returned = $conn->prepare("
        SELECT COALESCE(SUM(jumlah_kembali), 0) as total_returned
        FROM detail_pengembalian
        WHERE pengembalian_id IN (
            SELECT id FROM pengembalian WHERE peminjaman_id = ? AND status = 'Selesai'
        )
    ");
    $count_returned->bind_param("i", $peminjaman_id);
    $count_returned->execute();
    $returned_result = $count_returned->get_result()->fetch_assoc();
    $total_returned = (int)$returned_result['total_returned'];

    // Hitung sisa yang belum dikembalikan
    $sisa_dikembalikan = $total_items - $total_returned;

    // CHECK: Prevent multiple pending submissions (only ONE Diajukan/Dicek allowed at a time)
    // This prevents duplicate submissions and allows sequential returns
    $check_pending = $conn->prepare("
        SELECT COUNT(*) as pending_count
        FROM pengembalian
        WHERE peminjaman_id = ? AND status IN ('Diajukan', 'Dicek')
    ");
    $check_pending->bind_param("i", $peminjaman_id);
    $check_pending->execute();
    $pending_result = $check_pending->get_result()->fetch_assoc();
    $pending_count = (int)$pending_result['pending_count'];
    
    if ($pending_count > 0) {
        http_response_code(400);
        echo json_encode([
            "status" => false, 
            "message" => "Anda sudah memiliki pengajuan pengembalian yang menunggu persetujuan. Silakan menunggu PIC/Admin memeriksa pengajuan sebelumnya.",
            "debug" => [
                "peminjaman_id" => $peminjaman_id,
                "pending_count" => $pending_count
            ]
        ]);
        exit;
    }

    // KEY VALIDATION: Only block if aggregate shows EVERYTHING already returned
    // This is the source of truth - NOT the status field
    if ($sisa_dikembalikan <= 0 && $total_items > 0) {
        http_response_code(400);
        echo json_encode([
            "status" => false, 
            "message" => "Semua barang sudah dikembalikan. Total: $total_items, Sudah dikembalikan: $total_returned",
            "debug" => [
                "peminjaman_id" => $peminjaman_id,
                "total_items" => $total_items,
                "total_returned" => $total_returned,
                "sisa" => $sisa_dikembalikan,
                "peminjaman_status" => $p['status']
            ]
        ]);
        exit;
    }
    
    // Secondary check: If items remain pending, allow return submission regardless of status
    // This allows users to continue returns even if system marked it complete
    if ($sisa_dikembalikan > 0) {
        // Items remain - ALLOW submission
        // No further status checks needed
    }

// NOTE: Allow multiple return submissions for the same peminjaman
    // The aggregate validation below will ensure user can't exceed what was borrowed
    // This allows users to submit returns in batches if needed
    // (e.g., return 4/5 items first, then return remaining 1 later)

$conn->begin_transaction();
try {
    $kode_pengembalian = "KMB-" . time();

    $ins = $conn->prepare("
        INSERT INTO pengembalian
        (kode_pengembalian, peminjaman_id, user_id, status, catatan_user)
        VALUES (?, ?, ?, 'Diajukan', ?)
    ");
    $ins->bind_param("siis", $kode_pengembalian, $peminjaman_id, $user_id, $catatan_user);
    if (!$ins->execute()) {
        throw new Exception("Gagal membuat pengajuan pengembalian: " . $ins->error);
    }
    $pengembalian_id = (int)$conn->insert_id;

    // Parse items from user submission if provided
    if (!empty($items_json)) {
        $items_array = json_decode($items_json, true);
        if (!is_array($items_array)) {
            throw new Exception("Items JSON tidak valid: " . json_last_error_msg());
        }
    }

    // Only create detail_pengembalian for items actually being returned
    // This allows partial returns - items not submitted remain as "still borrowed"
    $insd = $conn->prepare("
        INSERT INTO detail_pengembalian
        (pengembalian_id, barang_id, jumlah_kembali, kondisi_kembali, jumlah_rusak, sisa_dikembalikan, biaya_ganti_rugi, catatan)
        VALUES (?, ?, ?, ?, ?, ?, 0.00, '')
    ");

    $count = 0;
    // Only process items that user is actually returning
    foreach ($items_array as $item) {
        $barang_id = (int)($item['barang_id'] ?? 0);
        $jumlah_kembali = (int)($item['qty_return'] ?? 0);
        $jumlah_rusak = (int)($item['damaged'] ?? 0);
        $sisa_dikembalikan = (int)($item['remain_item'] ?? 0);
        $kondisi_kembali = 'Baik';

        // If has damaged items, set kondisi to Rusak
        if ($jumlah_rusak > 0) {
            $kondisi_kembali = 'Rusak';
        }

        // Only insert if there's actually something being returned
        if ($jumlah_kembali > 0) {
            $insd->bind_param("iiisii", $pengembalian_id, $barang_id, $jumlah_kembali, $kondisi_kembali, $jumlah_rusak, $sisa_dikembalikan);
            if (!$insd->execute()) {
                throw new Exception("Gagal membuat detail pengembalian: " . $insd->error);
            }
            $count++;
        }
    }

    if ($count === 0) {
        throw new Exception("Belum ada item yang diajukan untuk pengembalian. Silahkan isi qty return minimal 1 item.");
    }
    
    // Set peminjaman status to 'Proses Return' to reflect return process in DB
    $upd_status = $conn->prepare("UPDATE peminjaman SET status = 'Proses Return' WHERE id = ?");
    $upd_status->bind_param("i", $peminjaman_id);
    if (!$upd_status->execute()) {
        throw new Exception("Gagal update status peminjaman: " . $upd_status->error);
    }

    $conn->commit();

    // Kirim email notifikasi ke admin tentang permintaan pengembalian
    try {
        require_once __DIR__ . '/../email/send-return-request.php';
        sendReturnRequestEmail($conn, $peminjaman_id);
    } catch (Exception $emailEx) {
        error_log("[EMAIL ERROR] peminjaman/return: " . $emailEx->getMessage());
    }

    echo json_encode([
        "status" => true,
        "message" => "Pengembalian berhasil diajukan. Menunggu pengecekan Admin/PIC.",
        "pengembalian_id" => $pengembalian_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode([
        "status" => false, 
        "message" => $e->getMessage(),
        "debug" => [
            "user_id" => $user_id,
            "peminjaman_id" => $peminjaman_id,
            "items_submitted" => count($items_array),
            "error_type" => "exception"
        ]
    ]);
}

