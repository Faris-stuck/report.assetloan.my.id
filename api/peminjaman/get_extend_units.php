<?php
/**
 * API: Get per-unit items for extend modal display
 * Endpoint: /api/peminjaman/get_extend_units.php
 */
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
$peminjamanId = (int) ($_GET['peminjaman_id'] ?? 0);

if ($peminjamanId <= 0) {
    apiBusinessError('peminjaman_id is required', 400);
}

try {
    $stmt = $conn->prepare("
        SELECT id, rencana_kembali, status
        FROM peminjaman
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException('Failed to prepare borrowing query');
    }

    $stmt->bind_param("ii", $peminjamanId, $userId);
    $stmt->execute();
    $peminjaman = $stmt->get_result()->fetch_assoc();

    if (!$peminjaman) {
        apiBusinessError('Borrowing not found', 404);
    }

    $stmt = $conn->prepare("
        SELECT 
            pu.id as pu_id,
            pu.detail_peminjaman_id,
            pu.barang_id,
            pu.unit_number,
            pu.return_status,
            pu.expected_return as pu_expected_return,
            pu.approval_status,
            b.kode_barang,
            b.nama_barang,
            dp.kondisi_pinjam
        FROM peminjaman_units pu
        JOIN barang b ON b.id = pu.barang_id
        JOIN detail_peminjaman dp ON dp.id = pu.detail_peminjaman_id
        WHERE pu.peminjaman_id = ?
          AND pu.approval_status = 'Approved'
        ORDER BY pu.detail_peminjaman_id, pu.unit_number
    ");

    if (!$stmt) {
        throw new RuntimeException('Failed to prepare approved unit query');
    }

    $stmt->bind_param("i", $peminjamanId);
    $stmt->execute();
    $approvedUnits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $approvedCounts = [];
    foreach ($approvedUnits as $approvedUnit) {
        $detailId = (int) $approvedUnit['detail_peminjaman_id'];
        if (!isset($approvedCounts[$detailId])) {
            $approvedCounts[$detailId] = 0;
        }
        $approvedCounts[$detailId]++;
    }

    $stmt = $conn->prepare("
        SELECT 
            ep.id as extend_id,
            ep.status as extend_req_status,
            ep.tanggal_perpanjang,
            epi.detail_peminjaman_id,
            epi.unit_number,
            epi.tanggal_perpanjang as unit_tanggal_perpanjang
        FROM extend_peminjaman ep
        LEFT JOIN extend_peminjaman_items epi ON epi.extend_peminjaman_id = ep.id
        WHERE ep.peminjaman_id = ?
        ORDER BY ep.id DESC, COALESCE(epi.unit_number, 0)
    ");

    if (!$stmt) {
        throw new RuntimeException('Failed to prepare extend item query');
    }

    $stmt->bind_param("i", $peminjamanId);
    $stmt->execute();
    $extends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $extendMap = [];
    foreach ($extends as $extend) {
        $detailId = $extend['detail_peminjaman_id'];
        $unitNum = $extend['unit_number'];
        $requestStatus = $extend['extend_req_status'];

        if (!isset($extendMap[$detailId])) {
            $extendMap[$detailId] = [];
        }

        if ($detailId && $unitNum) {
            if (!isset($extendMap[$detailId][$unitNum])) {
                $extendMap[$detailId][$unitNum] = [
                    'is_extended' => ($requestStatus === 'Approved'),
                    'extend_status' => $requestStatus,
                    'extend_date' => $extend['unit_tanggal_perpanjang']
                ];
            }
        } elseif (!$detailId && !$unitNum && !isset($extendMap[0]['blanket'])) {
            $extendMap[0]['blanket'] = [
                'is_extended' => ($requestStatus === 'Approved'),
                'extend_status' => $requestStatus,
                'extend_date' => $extend['tanggal_perpanjang']
            ];
        }
    }

    $latestBorrowingExtendStatus = $extends[0]['extend_req_status'] ?? null;
    $nearestReturn = getNearestExpectedReturn($conn, $peminjamanId);
    $borrowingExtendState = getBorrowingExtendState(
        $conn,
        $peminjamanId,
        (string) $peminjaman['status'],
        $peminjaman['rencana_kembali'],
        $latestBorrowingExtendStatus,
        $nearestReturn
    );

    $units = [];
    $detailSequence = [];

    foreach ($approvedUnits as $approvedUnit) {
        $detailId = (int) $approvedUnit['detail_peminjaman_id'];
        $barangId = (int) $approvedUnit['barang_id'];
        $unitNum = (int) $approvedUnit['unit_number'];

        if (!isset($detailSequence[$detailId])) {
            $detailSequence[$detailId] = 0;
        }
        $detailSequence[$detailId]++;

        $returnStatus = $approvedUnit['return_status'] ?? 'Not Yet Returned';
        $isReturned = in_array($returnStatus, ['Returned', 'Damaged'], true);
        $expectedReturn = $approvedUnit['pu_expected_return'] ?? $peminjaman['rencana_kembali'];

        $isExtended = false;
        $extendStatusForUnit = null;
        $extendDate = null;

        if (isset($extendMap[$detailId][$unitNum])) {
            $extendInfo = $extendMap[$detailId][$unitNum];
            $isExtended = $extendInfo['is_extended'];
            $extendStatusForUnit = $extendInfo['extend_status'];
            $extendDate = $extendInfo['extend_date'];
            if ($isExtended) {
                $expectedReturn = $extendDate;
            }
        } elseif (isset($extendMap[0]['blanket'])) {
            $extendInfo = $extendMap[0]['blanket'];
            $isExtended = $extendInfo['is_extended'];
            $extendStatusForUnit = $extendInfo['extend_status'];
            $extendDate = $extendInfo['extend_date'];
            if ($isExtended) {
                $expectedReturn = $extendDate;
            }
        }

        $effectiveLatestExtendStatus = $extendStatusForUnit === 'Pending'
            ? 'Pending'
            : $latestBorrowingExtendStatus;

        $canExtend = canBorrowingUnitBeExtended(
            $conn,
            $peminjamanId,
            (string) $peminjaman['status'],
            $returnStatus,
            $peminjaman['rencana_kembali'],
            $effectiveLatestExtendStatus,
            $nearestReturn
        );

        $units[] = [
            'unit_id' => "detail_{$detailId}_unit_{$unitNum}",
            'detail_peminjaman_id' => $detailId,
            'barang_id' => $barangId,
            'kode_barang' => $approvedUnit['kode_barang'],
            'nama_barang' => $approvedUnit['nama_barang'],
            'unit_number' => $unitNum,
            'qty_dipinjam' => 1,
            'kondisi_pinjam' => $approvedUnit['kondisi_pinjam'],
            'expected_return' => $expectedReturn ? date('d/m/Y', strtotime($expectedReturn)) : '-',
            'sudah_dikembalikan' => $isReturned,
            'is_extended' => $isExtended,
            'extend_status' => $extendStatusForUnit,
            'extend_date' => $extendDate ? date('d/m/Y', strtotime($extendDate)) : null,
            'can_extend' => $canExtend,
            'unit_display' => $detailSequence[$detailId] . '/' . $approvedCounts[$detailId]
        ];
    }

    apiJsonResponse(200, [
        'status' => true,
        'data' => [
            'peminjaman_id' => $peminjamanId,
            'peminjaman_rencana_kembali' => $peminjaman['rencana_kembali'] ? date('d/m/Y', strtotime($peminjaman['rencana_kembali'])) : '-',
            'peminjaman_status' => $borrowingExtendState['status'],
            'units' => $units
        ]
    ]);
} catch (Throwable $e) {
    apiServerError($e, 'api/peminjaman/get_extend_units.php');
}
