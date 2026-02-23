<?php
/**
 * API: Request Extend (Perpanjang Masa Peminjaman)
 * Method: POST
 * Params: peminjaman_id, tanggal_perpanjang, alasan
 * Role: user
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

$user_id = $session->getUserId();
$peminjaman_id = isset($_POST['peminjaman_id']) ? (int)$_POST['peminjaman_id'] : 0;
$tanggal_perpanjang = trim($_POST['tanggal_perpanjang'] ?? '');
$alasan = trim($_POST['alasan'] ?? '');

// Validation
if ($peminjaman_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Peminjaman ID tidak valid']);
    exit;
}

if (empty($tanggal_perpanjang)) {
    echo json_encode(['status' => false, 'message' => 'Tanggal perpanjangan harus diisi']);
    exit;
}

if (empty($alasan)) {
    echo json_encode(['status' => false, 'message' => 'Alasan perpanjangan harus diisi']);
    exit;
}

try {
    // Verify peminjaman belongs to user and is active
    $stmt = $conn->prepare("SELECT id, rencana_kembali, status FROM peminjaman WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $peminjaman_id, $user_id);
    $stmt->execute();
    $peminjaman = $stmt->get_result()->fetch_assoc();

    if (!$peminjaman) {
        echo json_encode(['status' => false, 'message' => 'Peminjaman tidak ditemukan atau bukan milik Anda']);
        exit;
    }

    // Only allow extend for active borrowings
    $allowed_statuses = ['Sedang Dipinjam', 'Disetujui', 'Sebagian Dikembalikan'];
    if (!in_array($peminjaman['status'], $allowed_statuses)) {
        echo json_encode(['status' => false, 'message' => 'Peminjaman dengan status "' . $peminjaman['status'] . '" tidak dapat diperpanjang']);
        exit;
    }

    // Check if there's already a pending extend request for this peminjaman
    $stmt = $conn->prepare("SELECT id FROM extend_peminjaman WHERE peminjaman_id = ? AND status = 'Pending'");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => false, 'message' => 'Sudah ada permintaan perpanjangan yang sedang menunggu persetujuan']);
        exit;
    }

    // Validate new date is after current return date
    $current_return = $peminjaman['rencana_kembali'];
    if ($tanggal_perpanjang <= $current_return) {
        echo json_encode(['status' => false, 'message' => 'Tanggal perpanjangan harus setelah tanggal kembali saat ini (' . date('d/m/Y', strtotime($current_return)) . ')']);
        exit;
    }

    // Insert extend request
    $stmt = $conn->prepare("INSERT INTO extend_peminjaman (peminjaman_id, user_id, tanggal_kembali_sekarang, tanggal_perpanjang, alasan, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("iisss", $peminjaman_id, $user_id, $current_return, $tanggal_perpanjang, $alasan);

    if ($stmt->execute()) {
        echo json_encode(['status' => true, 'message' => 'Permintaan perpanjangan berhasil diajukan']);
    } else {
        echo json_encode(['status' => false, 'message' => 'Gagal menyimpan permintaan: ' . $conn->error]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
