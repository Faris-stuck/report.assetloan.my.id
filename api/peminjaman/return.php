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
    
    // Allow submissions ONLY when items are still pending return
    // Prevent submission when all items already returned (final statuses)
    $allowedStatuses = ['Sedang Dipinjam', 'Sebagian Dikembalikan', 'Proses Return'];
    $finalStatuses = ['Dikembalikan', 'Sebagian Rusak', 'Semua Rusak', 'Ditolak', 'Selesai'];
    
    if (in_array($p['status'], $finalStatuses, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => false, 
            "message" => "Pengembalian tidak bisa diajukan lagi. Semua barang sudah dikembalikan dan selesai diproses.",
            "debug" => [
                "peminjaman_id" => $peminjaman_id,
                "kode_peminjaman" => $p['kode_peminjaman'],
                "current_status" => $p['status'],
                "allowed_statuses" => $allowedStatuses
            ]
        ]);
        exit;
    }
    
    if (!in_array($p['status'], $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => false, 
            "message" => "Pengembalian hanya bisa diajukan saat peminjaman berstatus: " . implode(", ", $allowedStatuses) . ". Status saat ini: " . $p['status'],
            "debug" => [
                "peminjaman_id" => $peminjaman_id,
                "kode_peminjaman" => $p['kode_peminjaman'],
                "current_status" => $p['status'],
                "allowed_statuses" => $allowedStatuses
            ]
        ]);
        exit;
    }

    // Validasi tambahan: cek apakah ada item yang belum dikembalikan di detail_peminjaman
    // Hitung item yang belum dikembalikan (yang tidak ada di pengembalian dengan status Selesai)
    $cek_items = $conn->prepare("
        SELECT COUNT(id) as belum_dikembalikan
        FROM detail_peminjaman dp
        WHERE peminjaman_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM detail_pengembalian dr
            LEFT JOIN pengembalian p ON dr.pengembalian_id = p.id
            WHERE dr.barang_id = dp.barang_id
            AND p.peminjaman_id = ?
            AND p.status = 'Selesai'
            AND dr.jumlah_kembali >= dp.jumlah
        )
    ");
    if ($cek_items) {
        $cek_items->bind_param("ii", $peminjaman_id, $peminjaman_id);
        $cek_items->execute();
        $items_result = $cek_items->get_result()->fetch_assoc();
        if ($items_result['belum_dikembalikan'] == 0) {
            http_response_code(400);
            echo json_encode([
                "status" => false, 
                "message" => "Semua barang sudah dikembalikan dan selesai diproses. Tidak ada yang perlu dikembalikan lagi."
            ]);
            exit;
        }
    }

// Cegah double submit untuk return yang masih pending (tidak selesai diproses)
    $cek = $conn->prepare("SELECT id, status, diajukan_at FROM pengembalian WHERE peminjaman_id = ? AND status != 'Selesai' ORDER BY id DESC LIMIT 1");
    if (!$cek) {
        http_response_code(500);
        echo json_encode(["status" => false, "message" => "Database error: " . $conn->error]);
        exit;
    }
    $cek->bind_param("i", $peminjaman_id);
    $cek->execute();
    $existing = $cek->get_result()->fetch_assoc();
    if ($existing) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Pengembalian sudah pernah diajukan dengan status '" . $existing['status'] . "' dan masih dalam proses. Tunggu hingga selesai diperiksa admin.",
            "debug" => [
                "peminjaman_id" => $peminjaman_id,
                "pengembalian_id_existing" => (int)$existing['id'],
                "existing_status" => $existing['status'],
                "diajukan_at" => $existing['diajukan_at']
            ]
        ]);
        exit;
    }

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

