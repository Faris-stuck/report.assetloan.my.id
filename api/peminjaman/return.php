<?php
/**
 * API: User - Ajukan Pengembalian (membuat record pengembalian yang akan dicek admin/PIC)
 * Endpoint: /api/peminjaman/return.php
 *
 * Input (POST):
 * - peminjaman_id (int) required
 * - catatan_user (string) optional
 *
 * Output:
 * - { status: true, message, pengembalian_id }
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

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

if (!$user_id || !$peminjaman_id) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "peminjaman_id wajib"]);
    exit;
}

// Pastikan peminjaman milik user dan sedang dipinjam
$stmt = $conn->prepare("SELECT id, status FROM peminjaman WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $peminjaman_id, $user_id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();

if (!$p) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Data peminjaman tidak ditemukan"]);
    exit;
}
if ($p['status'] !== 'Sedang Dipinjam') {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Pengembalian hanya bisa diajukan saat status 'Sedang Dipinjam'"]);
    exit;
}

// Cegah double submit
$cek = $conn->prepare("SELECT id, status FROM pengembalian WHERE peminjaman_id = ? LIMIT 1");
$cek->bind_param("i", $peminjaman_id);
$cek->execute();
$existing = $cek->get_result()->fetch_assoc();
if ($existing) {
    echo json_encode([
        "status" => true,
        "message" => "Pengembalian sudah pernah diajukan",
        "pengembalian_id" => (int)$existing['id'],
        "pengembalian_status" => $existing['status']
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

    // Copy item dari detail_peminjaman menjadi detail_pengembalian (default kondisi Baik)
    $q = $conn->prepare("SELECT barang_id, jumlah FROM detail_peminjaman WHERE peminjaman_id = ?");
    $q->bind_param("i", $peminjaman_id);
    $q->execute();
    $res = $q->get_result();

    $insd = $conn->prepare("
        INSERT INTO detail_pengembalian
        (pengembalian_id, barang_id, jumlah_kembali, kondisi_kembali, jumlah_rusak, biaya_ganti_rugi, catatan)
        VALUES (?, ?, ?, 'Baik', 0, 0.00, '')
    ");

    $count = 0;
    while ($row = $res->fetch_assoc()) {
        $barang_id = (int)$row['barang_id'];
        $jumlah = (int)$row['jumlah'];
        $insd->bind_param("iii", $pengembalian_id, $barang_id, $jumlah);
        if (!$insd->execute()) {
            throw new Exception("Gagal membuat detail pengembalian: " . $insd->error);
        }
        $count++;
    }

    if ($count === 0) {
        throw new Exception("Detail peminjaman kosong, tidak bisa ajukan pengembalian");
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
    http_response_code(500);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}

