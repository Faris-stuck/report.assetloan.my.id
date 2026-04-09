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

$sessionUserId = (int) (SessionValidator::getUserId() ?? 0);
$peminjamanId = isset($_GET['peminjaman_id']) ? (int) $_GET['peminjaman_id'] : (int) ($_GET['id'] ?? 0);

if ($peminjamanId <= 0) {
    apiBusinessError('peminjaman_id not found', 400);
}

try {
    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.kode_peminjaman,
            p.user_id,
            p.tanggal_pinjam,
            p.rencana_kembali,
            p.status,
            p.catatan,
            u.nama,
            u.nrp,
            u.nama as nama_peminjam
        FROM peminjaman p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.user_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException('Failed to prepare borrowing detail query');
    }

    $stmt->bind_param("ii", $peminjamanId, $sessionUserId);
    $stmt->execute();
    $peminjaman = $stmt->get_result()->fetch_assoc();

    if (!$peminjaman) {
        apiBusinessError('Borrowing not found', 404);
    }

    $stmtDetail = $conn->prepare("
        SELECT
            b.id AS barang_id,
            b.nama_barang,
            CASE
                WHEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.peminjaman_id = dp.peminjaman_id) > 0
                THEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.detail_peminjaman_id = dp.id AND pu.approval_status = 'Approved')
                ELSE dp.jumlah
            END as jumlah,
            dp.lokasi
        FROM detail_peminjaman dp
        JOIN barang b ON dp.barang_id = b.id
        WHERE dp.peminjaman_id = ?
        HAVING jumlah > 0
    ");

    if (!$stmtDetail) {
        throw new RuntimeException('Failed to prepare borrowing item query');
    }

    $stmtDetail->bind_param("i", $peminjamanId);
    $stmtDetail->execute();
    $resultDetail = $stmtDetail->get_result();

    $detailBarang = [];
    while ($row = $resultDetail->fetch_assoc()) {
        $detailBarang[] = [
            'barang_id' => (int) $row['barang_id'],
            'nama_barang' => $row['nama_barang'],
            'jumlah' => (int) $row['jumlah'],
            'lokasi' => $row['lokasi'],
            'jumlah_kembali' => 0,
            'jumlah_rusak' => 0,
            'kondisi_kembali' => ''
        ];
    }

    $aggQuery = $conn->prepare("
        SELECT 
            SUM(dp.jumlah_kembali) as total_kembali,
            SUM(dp.jumlah_rusak) as total_rusak
        FROM detail_pengembalian dp
        JOIN pengembalian p ON dp.pengembalian_id = p.id
        WHERE p.peminjaman_id = ? AND p.status = 'Completed'
    ");

    if (!$aggQuery) {
        throw new RuntimeException('Failed to prepare return aggregate query');
    }

    $aggQuery->bind_param("i", $peminjamanId);
    $aggQuery->execute();
    $aggResult = $aggQuery->get_result()->fetch_assoc();

    $displayStatus = $peminjaman['status'];
    $displayStatusEn = $peminjaman['status'];
    $totalItems = 0;

    foreach ($detailBarang as $item) {
        $totalItems += (int) $item['jumlah'];
    }

    $perBarang = $conn->prepare("
        SELECT 
            barang_id,
            SUM(jumlah_kembali) as total_kembali,
            SUM(jumlah_rusak) as total_rusak,
            MAX(kondisi_kembali) as kondisi_kembali
        FROM detail_pengembalian
        WHERE pengembalian_id IN (
            SELECT id FROM pengembalian WHERE peminjaman_id = ? AND status = 'Completed'
        )
        GROUP BY barang_id
    ");

    if (!$perBarang) {
        throw new RuntimeException('Failed to prepare per-item return query');
    }

    $perBarang->bind_param("i", $peminjamanId);
    $perBarang->execute();
    $pbResult = $perBarang->get_result();
    $perBarangMap = [];

    while ($row = $pbResult->fetch_assoc()) {
        $perBarangMap[(int) $row['barang_id']] = [
            'jumlah_kembali' => (int) $row['total_kembali'],
            'jumlah_rusak' => (int) $row['total_rusak'],
            'kondisi_kembali' => $row['kondisi_kembali']
        ];
    }

    foreach ($detailBarang as &$item) {
        $barangId = (int) $item['barang_id'];
        if (isset($perBarangMap[$barangId])) {
            $item['jumlah_kembali'] = $perBarangMap[$barangId]['jumlah_kembali'];
            $item['jumlah_rusak'] = $perBarangMap[$barangId]['jumlah_rusak'];
            $item['kondisi_kembali'] = $perBarangMap[$barangId]['kondisi_kembali'];
        }
    }
    unset($item);

    if ($aggResult) {
        $totalDikembalikan = (int) ($aggResult['total_kembali'] ?? 0);
        $totalRusak = (int) ($aggResult['total_rusak'] ?? 0);

        if ($totalDikembalikan >= $totalItems && $totalItems > 0) {
            if ($totalRusak > 0) {
                if ($totalRusak >= $totalItems) {
                    $displayStatus = 'Fully Damaged';
                    $displayStatusEn = 'Fully Damaged';
                } else {
                    $displayStatus = 'Partially Damaged';
                    $displayStatusEn = 'Partially Damaged';
                }
            } else {
                $displayStatus = 'Returned';
                $displayStatusEn = 'Returned';
            }
        } elseif ($totalDikembalikan > 0 && $totalDikembalikan < $totalItems) {
            $displayStatus = 'Partially Returned';
            $displayStatusEn = 'Partially Returned';
        }
    }

    $nearestExpected = getNearestExpectedReturn($conn, (int) $peminjaman['id']);
    $displayStatus = computeDueStatus($displayStatus, $nearestExpected ?? $peminjaman['rencana_kembali']);
    $displayStatusEn = $displayStatus;

    apiJsonResponse(200, [
        'status' => true,
        'data' => [
            'id' => (int) $peminjaman['id'],
            'kode_peminjaman' => $peminjaman['kode_peminjaman'],
            'nama' => $peminjaman['nama'],
            'nrp' => $peminjaman['nrp'],
            'tanggal_pinjam' => $peminjaman['tanggal_pinjam'] ? date('d/m/Y', strtotime($peminjaman['tanggal_pinjam'])) : '-',
            'rencana_kembali' => $peminjaman['rencana_kembali'] ? date('d/m/Y', strtotime($peminjaman['rencana_kembali'])) : '-',
            'expected_return_nearest' => $nearestExpected ? date('d/m/Y', strtotime($nearestExpected)) : ($peminjaman['rencana_kembali'] ? date('d/m/Y', strtotime($peminjaman['rencana_kembali'])) : '-'),
            'status' => $displayStatus,
            'status_en' => $displayStatusEn,
            'catatan' => $peminjaman['catatan'],
            'detail_barang' => $detailBarang
        ]
    ]);
} catch (Throwable $e) {
    apiServerError($e, 'api/user/get-detail.php');
}
