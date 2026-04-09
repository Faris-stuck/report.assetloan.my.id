<?php
require_once "../koneksi.php";
require_once "../session-helper.php";
require_once "../response-helper.php";

header('Content-Type: application/json');

if (!SessionValidator::isLoggedIn()) {
    apiBusinessError('Unauthorized', 401);
}

if (SessionValidator::getRole() !== 'user') {
    apiBusinessError('Access denied', 403);
}

$userId = (int) (SessionValidator::getUserId() ?? 0);
$lokasiUmum = trim((string) ($_POST['lokasi_umum'] ?? ''));
$rencanaPinjam = trim((string) ($_POST['rencana_pinjam'] ?? ''));
$rencanaKembali = trim((string) ($_POST['rencana_kembali'] ?? ''));
$catatan = trim((string) ($_POST['catatan'] ?? ''));
$kode = "PMJ-" . time();

$barang = [];
$lokasi = [];

if (isset($_POST['barang']) && is_array($_POST['barang'])) {
    foreach ($_POST['barang'] as $id => $val) {
        $barang[(int) $id] = (int) $val;
    }
}

if (isset($_POST['lokasi']) && is_array($_POST['lokasi'])) {
    foreach ($_POST['lokasi'] as $id => $val) {
        $lokasi[(int) $id] = (string) $val;
    }
}

foreach ($_POST as $key => $value) {
    if (!is_string($key)) {
        continue;
    }

    if (preg_match('/^barang\[(\d+)\]$/', $key, $matches)) {
        $barangId = (int) $matches[1];
        if (!isset($barang[$barangId])) {
            $barang[$barangId] = (int) $value;
        }
    } elseif (preg_match('/^lokasi\[(\d+)\]$/', $key, $matches)) {
        $barangId = (int) $matches[1];
        if (!isset($lokasi[$barangId])) {
            $lokasi[$barangId] = (string) $value;
        }
    }
}

if ($userId <= 0 || $rencanaPinjam === '' || $rencanaKembali === '' || $lokasiUmum === '') {
    apiBusinessError('Required data is incomplete. Please ensure all fields are filled.');
}

$today = date('Y-m-d');
if (strtotime($rencanaPinjam) < strtotime($today)) {
    apiBusinessError("Borrowing date cannot be earlier than today ({$today})");
}

if (strtotime($rencanaKembali) < strtotime($rencanaPinjam)) {
    apiBusinessError('Return date cannot be earlier than the borrowing date');
}

if (empty($barang) || array_sum($barang) === 0) {
    apiBusinessError('Please select at least 1 item to borrow.');
}

try {
    $userStmt = $conn->prepare("SELECT nama, nrp FROM users WHERE id = ? LIMIT 1");
    if (!$userStmt) {
        throw new RuntimeException('Failed to prepare user lookup');
    }

    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();

    if (!$user) {
        apiBusinessError('User account not found', 404);
    }

    $namaPeminjam = trim((string) ($user['nama'] ?? ''));
    $nrp = trim((string) ($user['nrp'] ?? ''));

    if ($namaPeminjam === '' || $nrp === '') {
        apiBusinessError('User profile is incomplete. Please contact administrator.');
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("
            INSERT INTO peminjaman
            (kode_peminjaman, user_id, nama_peminjam, nrp, lokasi_umum, tanggal_pinjam, rencana_kembali, status, catatan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException('Failed to prepare borrowing insert');
        }

        $status = "Waiting for Approval";
        $stmt->bind_param("sisssssss", $kode, $userId, $namaPeminjam, $nrp, $lokasiUmum, $rencanaPinjam, $rencanaKembali, $status, $catatan);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to create borrowing request');
        }

        $peminjamanId = $conn->insert_id;

        foreach ($barang as $barangId => $jumlah) {
            $lokasiItem = $lokasi[$barangId] ?? '';

            if ($jumlah <= 0) {
                continue;
            }

            $stmtCheck = $conn->prepare("SELECT nama_barang, stok_tersedia FROM barang WHERE id = ?");
            if (!$stmtCheck) {
                throw new RuntimeException('Failed to prepare stock lookup');
            }

            $stmtCheck->bind_param("i", $barangId);
            $stmtCheck->execute();
            $barangData = $stmtCheck->get_result()->fetch_assoc();

            if (!$barangData) {
                throw new DomainException("Item with ID {$barangId} not found");
            }

            $stokTersedia = (int) $barangData['stok_tersedia'];
            $namaBarang = $barangData['nama_barang'];

            if ($jumlah > $stokTersedia) {
                throw new DomainException("Borrow quantity ({$jumlah}) for '{$namaBarang}' exceeds available stock ({$stokTersedia})");
            }

            if (($stokTersedia - $jumlah) < 0) {
                throw new DomainException("Stock for '{$namaBarang}' is insufficient. Available stock: {$stokTersedia}, requested: {$jumlah}");
            }

            $stmtDetail = $conn->prepare("
                INSERT INTO detail_peminjaman
                (peminjaman_id, barang_id, lokasi, jumlah, kondisi_pinjam)
                VALUES (?, ?, ?, ?, ?)
            ");
            if (!$stmtDetail) {
                throw new RuntimeException('Failed to prepare borrowing detail insert');
            }

            $kondisiPinjam = 'Good';
            $stmtDetail->bind_param("iisis", $peminjamanId, $barangId, $lokasiItem, $jumlah, $kondisiPinjam);
            if (!$stmtDetail->execute()) {
                throw new RuntimeException('Failed to save borrowing detail');
            }

            $stmtUpdate = $conn->prepare("
                UPDATE barang
                SET stok_tersedia = stok_tersedia - ?
                WHERE id = ? AND stok_tersedia >= ?
            ");
            if (!$stmtUpdate) {
                throw new RuntimeException('Failed to prepare stock update');
            }

            $stmtUpdate->bind_param("iii", $jumlah, $barangId, $jumlah);
            if (!$stmtUpdate->execute()) {
                throw new RuntimeException('Failed to update stock');
            }

            if ($stmtUpdate->affected_rows === 0) {
                throw new DomainException("Failed to reduce stock for '{$namaBarang}'. Stock may have changed.");
            }
        }

        $conn->commit();

        try {
            require_once __DIR__ . '/../email/send-pinjam-request.php';
            sendPinjamRequestEmail($conn, $peminjamanId);
        } catch (Throwable $emailEx) {
            error_log("[EMAIL ERROR] request-peminjaman: " . $emailEx->getMessage());
        }

        apiJsonResponse(200, ['status' => true]);
    } catch (DomainException $e) {
        $conn->rollback();
        apiBusinessError($e->getMessage(), 400);
    } catch (Throwable $e) {
        $conn->rollback();
        apiServerError($e, 'api/user/request-peminjaman.php', 'Failed to submit borrowing request');
    }
} catch (Throwable $e) {
    apiServerError($e, 'api/user/request-peminjaman.php', 'Failed to submit borrowing request');
}
