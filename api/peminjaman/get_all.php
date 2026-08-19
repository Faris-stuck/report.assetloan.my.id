<?php
require_once "../koneksi.php";
require_once "../session-helper.php";
require_once "../response-helper.php";

header('Content-Type: application/json');

$allowedRoles = ['admin', 'manager', 'pic_barang', 'user'];
$currentRole = SessionValidator::getRole();
$sessionUserId = (int) (SessionValidator::getUserId() ?? 0);

if (!SessionValidator::isLoggedIn()) {
    apiBusinessError('Unauthorized', 401);
}

if (!in_array($currentRole, $allowedRoles, true)) {
    apiBusinessError('Unauthorized', 403);
}

$requestedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$peminjamanId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$kodePeminjaman = trim((string) ($_GET['kode'] ?? ''));
$startDate = trim((string) ($_GET['start_date'] ?? ''));
$endDate = trim((string) ($_GET['end_date'] ?? ''));

try {
    $whereParts = [];
    $params = [];
    $types = '';

    if ($peminjamanId > 0) {
        $whereParts[] = "p.id = ?";
        $params[] = $peminjamanId;
        $types .= "i";
    }

    if ($kodePeminjaman !== '') {
        $whereParts[] = "p.kode_peminjaman = ?";
        $params[] = $kodePeminjaman;
        $types .= "s";
    }

    if ($currentRole === 'user') {
        if ($sessionUserId <= 0) {
            apiBusinessError('Unauthorized', 401);
        }

        $whereParts[] = "p.user_id = ?";
        $params[] = $sessionUserId;
        $types .= "i";
    } elseif ($requestedUserId > 0) {
        $whereParts[] = "p.user_id = ?";
        $params[] = $requestedUserId;
        $types .= "i";
    }

    if ($startDate !== '' && $endDate !== '') {
        $whereParts[] = "DATE(p.tanggal_pinjam) >= ? AND DATE(p.tanggal_pinjam) <= ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= "ss";
    }

    $whereClause = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.kode_peminjaman,
            p.user_id,
            p.nama_peminjam,
            p.nrp,
            p.tanggal_pinjam,
            p.rencana_kembali,
            p.status,
            p.catatan,
            latest_ext.extend_status AS latest_extend_status,
            latest_ext.tanggal_perpanjang AS latest_extend_date
        FROM peminjaman p
        LEFT JOIN (
            SELECT e1.peminjaman_id, e1.status AS extend_status, e1.tanggal_perpanjang
            FROM extend_peminjaman e1
            WHERE e1.id = (
                SELECT MAX(e2.id)
                FROM extend_peminjaman e2
                WHERE e2.peminjaman_id = e1.peminjaman_id
            )
        ) latest_ext ON latest_ext.peminjaman_id = p.id
        {$whereClause}
        ORDER BY p.tanggal_pinjam DESC
    ");

    if (!$stmt) {
        throw new RuntimeException('Failed to prepare borrowing query');
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $borrowingId = (int) $row['id'];

        $stmtDetail = $conn->prepare("
            SELECT
                dp.barang_id as barang_id,
                b.nama_barang,
                CASE
                    WHEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.peminjaman_id = dp.peminjaman_id) > 0
                    THEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.detail_peminjaman_id = dp.id AND pu.approval_status = 'Approved')
                    ELSE dp.jumlah
                END as jumlah,
                dp.lokasi
            FROM detail_peminjaman dp
            LEFT JOIN barang b ON dp.barang_id = b.id
            WHERE dp.peminjaman_id = ?
            HAVING jumlah > 0
        ");

        if (!$stmtDetail) {
            throw new RuntimeException('Failed to prepare borrowing detail query');
        }

        $stmtDetail->bind_param("i", $borrowingId);
        $stmtDetail->execute();
        $resultDetail = $stmtDetail->get_result();

        $barangList = [];
        while ($detailRow = $resultDetail->fetch_assoc()) {
            $barangList[] = [
                'barang_id' => (int) $detailRow['barang_id'],
                'nama' => $detailRow['nama_barang'],
                'jumlah' => (int) $detailRow['jumlah'],
                'lokasi' => $detailRow['lokasi'],
                'jumlah_kembali' => 0,
                'jumlah_rusak' => 0,
                'kondisi_kembali' => ''
            ];
        }

        $agg = $conn->prepare("
            SELECT 
                SUM(dp.jumlah_kembali) as total_kembali,
                SUM(dp.jumlah_rusak) as total_rusak
            FROM detail_pengembalian dp
            JOIN pengembalian p ON dp.pengembalian_id = p.id
            WHERE p.peminjaman_id = ? AND p.status = 'Completed'
        ");

        if (!$agg) {
            throw new RuntimeException('Failed to prepare return aggregate query');
        }

        $agg->bind_param("i", $borrowingId);
        $agg->execute();
        $aggResult = $agg->get_result()->fetch_assoc();

        $qk = $conn->prepare("SELECT id, status FROM pengembalian WHERE peminjaman_id = ? ORDER BY id DESC LIMIT 1");
        if (!$qk) {
            throw new RuntimeException('Failed to prepare latest return query');
        }

        $qk->bind_param("i", $borrowingId);
        $qk->execute();
        $hk = $qk->get_result()->fetch_assoc();

        $pengembalianStatus = null;
        $hasPengembalian = false;

        if ($hk) {
            $hasPengembalian = true;
            $pengId = (int) $hk['id'];
            $pengembalianStatus = $hk['status'];

            $sd = $conn->prepare("
                SELECT barang_id, jumlah_kembali, kondisi_kembali, jumlah_rusak
                FROM detail_pengembalian
                WHERE pengembalian_id = ?
            ");

            if (!$sd) {
                throw new RuntimeException('Failed to prepare return detail query');
            }

            $sd->bind_param("i", $pengId);
            $sd->execute();
            $rd = $sd->get_result();
            $map = [];
            while ($r = $rd->fetch_assoc()) {
                $map[(int) $r['barang_id']] = $r;
            }

            foreach ($barangList as &$barangItem) {
                $barangId = (int) $barangItem['barang_id'];
                if (isset($map[$barangId])) {
                    $barangItem['jumlah_kembali'] = (int) $map[$barangId]['jumlah_kembali'];
                    $barangItem['jumlah_rusak'] = (int) $map[$barangId]['jumlah_rusak'];
                    $barangItem['kondisi_kembali'] = $map[$barangId]['kondisi_kembali'];
                }
            }
            unset($barangItem);
        }

        $nearestReturn = getNearestExpectedReturn($conn, $borrowingId);
        $extendState = getBorrowingExtendState(
            $conn,
            $borrowingId,
            (string) $row['status'],
            $row['rencana_kembali'],
            $row['latest_extend_status'] ?? null,
            $nearestReturn
        );

        $computedStatus = $extendState['status'];
        $statusEnMap = [
            'Returned' => 'Returned',
            'Partially Returned' => 'Partially Returned',
            'Borrowed' => 'Borrowed',
            'Return in Process' => 'Return In Progress',
            'Overdue' => 'Overdue',
            'Due Today' => 'Due Today',
            'Rejected' => 'Rejected',
            'Waiting for Approval' => 'Pending Approval',
            'Approved' => 'Approved',
            'Partial Approved' => 'Partial Approved',
        ];

        if (isset($statusEnMap[$computedStatus])) {
            $statusEn = $statusEnMap[$computedStatus];
        } elseif (strpos($computedStatus, 'Due In') === 0) {
            $statusEn = $computedStatus;
        } else {
            $statusEn = $computedStatus;
        }

        $expectedReturnNearest = $nearestReturn
            ? date('d/m/Y', strtotime($nearestReturn))
            : ($row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-');

        $record = [
            'id' => $borrowingId,
            'kode' => $row['kode_peminjaman'],
            'user_id' => (int) $row['user_id'],
            'nama' => $row['nama_peminjam'] ?: '-',
            'nrp' => $row['nrp'] ?: '-',
            'tanggal' => $row['tanggal_pinjam'] ? date('d/m/Y', strtotime($row['tanggal_pinjam'])) : '-',
            'rencana_kembali' => $row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-',
            'expected_return_nearest' => $expectedReturnNearest,
            'status' => $computedStatus,
            'status_en' => $statusEn,
            'barang' => implode(', ', array_map(function ($item) {
                return $item['jumlah'] . 'x ' . $item['nama'] . ' (' . $item['lokasi'] . ')';
            }, $barangList)),
            'catatan' => $row['catatan'] ?: '',
            'can_extend' => $extendState['can_extend'],
            'latest_extend_status' => $extendState['latest_extend_status'],
            'latest_extend_date' => $row['latest_extend_date'] ? date('d/m/Y', strtotime($row['latest_extend_date'])) : null,
            'has_pengembalian' => $hasPengembalian,
        ];

        if ($pengembalianStatus !== null) {
            $record['pengembalian_status'] = $pengembalianStatus;
        }

        if ($aggResult) {
            $record['total_kembali'] = (int) ($aggResult['total_kembali'] ?? 0);
            $record['total_rusak'] = (int) ($aggResult['total_rusak'] ?? 0);
        }

        $data[] = $record;
    }

    apiJsonResponse(200, [
        'status' => true,
        'data' => $data
    ]);
} catch (Throwable $e) {
    apiServerError($e, 'api/peminjaman/get_all.php');
}
