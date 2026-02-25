<?php
/**
 * Direct test for get_detail_units logic without HTTP session requirement.
 * Run: php test-detail-units-direct.php
 */

// Bootstrap DB connection directly
$host = "localhost";
$user = "root";
$password = "";
$database = "peminjaman";
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) { die("DB ERROR: " . $conn->connect_error . "\n"); }

// ---------------------------------------------------------------
// Inline copy of the core logic from get_detail_units.php
// (not the whole file, just unit building + return_status logic)
// ---------------------------------------------------------------

function computeUnits($conn, $peminjaman_id) {
    // Get peminjaman header
    $stmt = $conn->prepare("
        SELECT p.id, p.rencana_kembali, p.status
        FROM peminjaman p WHERE p.id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    $peminjaman = $stmt->get_result()->fetch_assoc();
    if (!$peminjaman) return null;

    // Get detail_peminjaman
    $stmt = $conn->prepare("
        SELECT d.id AS detail_id, d.barang_id, d.jumlah AS qty_total,
               b.kode_barang, b.nama_barang
        FROM detail_peminjaman d JOIN barang b ON b.id = d.barang_id
        WHERE d.peminjaman_id = ? ORDER BY d.id
    ");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    $detail_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get extends
    $stmt = $conn->prepare("
        SELECT ep.id AS extend_id, ep.status AS extend_status,
               ep.tanggal_perpanjang, epi.detail_peminjaman_id,
               epi.unit_number, epi.tanggal_perpanjang AS unit_tanggal_perpanjang
        FROM extend_peminjaman ep
        LEFT JOIN extend_peminjaman_items epi ON epi.extend_peminjaman_id = ep.id
        WHERE ep.peminjaman_id = ?
        ORDER BY ep.id DESC, COALESCE(epi.unit_number, 0)
    ");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    $extends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Build extend_map
    $extend_map = [];
    foreach ($extends as $ext) {
        $detail_id = $ext['detail_peminjaman_id'];
        $unit_num = $ext['unit_number'];
        $req_status = $ext['extend_status'];
        if (!isset($extend_map[$detail_id])) $extend_map[$detail_id] = [];
        if ($detail_id && $unit_num) {
            if (!isset($extend_map[$detail_id][$unit_num])) {
                $extend_map[$detail_id][$unit_num] = [
                    'is_extended' => ($req_status === 'Approved'),
                    'extend_status' => $req_status,
                    'extend_date' => $ext['unit_tanggal_perpanjang']
                ];
            }
        } else if (!$detail_id && !$unit_num) {
            if (!isset($extend_map[0]['blanket'])) {
                $extend_map[0]['blanket'] = [
                    'is_extended' => ($req_status === 'Approved'),
                    'extend_status' => $req_status,
                    'extend_date' => $ext['tanggal_perpanjang']
                ];
            }
        }
    }

    $master_status = $peminjaman['status'];
    $master_is_final = ($master_status === 'Ditolak' || $master_status === 'Dikembalikan');
    $units = [];

    foreach ($detail_items as $item) {
        $detail_id = (int)$item['detail_id'];
        $qty_total = (int)$item['qty_total'];

        for ($unit_num = 1; $unit_num <= $qty_total; $unit_num++) {
            $is_returned = false; // For this test, assume none returned

            // Determine expected return date
            $expected_return_raw = $peminjaman['rencana_kembali'];
            if (isset($extend_map[$detail_id][$unit_num])) {
                $ext_info = $extend_map[$detail_id][$unit_num];
                if ($ext_info['is_extended'] && $ext_info['extend_date']) {
                    $expected_return_raw = $ext_info['extend_date'];
                }
            } else if (isset($extend_map[0]['blanket'])) {
                $ext_info = $extend_map[0]['blanket'];
                if ($ext_info['is_extended'] && $ext_info['extend_date']) {
                    $expected_return_raw = $ext_info['extend_date'];
                }
            }

            // === THE FIX: detect if latest applicable extend is rejected ===
            $latest_extend_is_rejected = false;
            if (isset($extend_map[$detail_id][$unit_num])) {
                $latest_extend_is_rejected = ($extend_map[$detail_id][$unit_num]['extend_status'] === 'Rejected');
            } elseif (isset($extend_map[0]['blanket'])) {
                $latest_extend_is_rejected = ($extend_map[0]['blanket']['extend_status'] === 'Rejected');
            }

            // Determine return status
            $return_status = 'Belum Dikembalikan';
            if ($master_is_final) {
                $return_status = $master_status;
            } elseif ($is_returned) {
                $return_status = 'Dikembalikan';
            } elseif ($latest_extend_is_rejected) {
                $return_status = 'Belum Dikembalikan'; // === FIXED: never compute due ===
            } elseif ($expected_return_raw) {
                $tz_rt = new DateTimeZone('Asia/Jakarta');
                $today_rt = new DateTime('today', $tz_rt);
                $retDate_rt = false;
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $expected_return_raw)) {
                    $retDate_rt = DateTime::createFromFormat('Y-m-d', substr($expected_return_raw, 0, 10), $tz_rt);
                }
                if ($retDate_rt) {
                    $retDate_rt->setTime(0,0,0); $today_rt->setTime(0,0,0);
                    $diffDays = (int)$today_rt->diff($retDate_rt)->format('%r%a');
                    if ($diffDays < 0) $return_status = 'Overdue';
                    elseif ($diffDays === 0) $return_status = 'Due Today';
                    elseif ($diffDays === 1) $return_status = 'Due In 1 Day';
                    elseif ($diffDays <= 7) $return_status = 'Due In ' . $diffDays . ' Days';
                }
            }

            $ext_status = isset($extend_map[$detail_id][$unit_num])
                ? $extend_map[$detail_id][$unit_num]['extend_status']
                : (isset($extend_map[0]['blanket']) ? 'blanket:' . $extend_map[0]['blanket']['extend_status'] : 'none');

            $units[] = [
                'detail_id' => $detail_id,
                'unit_num' => $unit_num,
                'expected_return' => $expected_return_raw ? date('d/m/Y', strtotime($expected_return_raw)) : '-',
                'return_status' => $return_status,
                'latest_extend' => $ext_status,
                'rejected' => $latest_extend_is_rejected ? 'YES' : 'NO',
            ];
        }
    }
    return ['rencana_kembali' => $peminjaman['rencana_kembali'], 'units' => $units];
}

// Test peminjaman 87 (has rejected extends for units 1 and 2 of detail 119, approved for unit 3)
echo "=== PEMINJAMAN 87 (rencana_kembali should be 2026-03-31) ===\n";
$result = computeUnits($conn, 87);
echo "Base rencana_kembali: " . $result['rencana_kembali'] . "\n\n";
printf("%-12s %-10s %-18s %-22s %-22s %-8s\n",
    "Detail ID", "Unit", "Expected Return", "Return Status", "Latest Extend", "Rejected");
echo str_repeat("-", 100) . "\n";
foreach ($result['units'] as $u) {
    printf("%-12s %-10s %-18s %-22s %-22s %-8s\n",
        $u['detail_id'], $u['unit_num'], $u['expected_return'],
        $u['return_status'], $u['latest_extend'], $u['rejected']);
}

echo "\n=== EXPECTED RESULTS ===\n";
echo "detail_id=119, unit 1: extend=Rejected → Return Status='Belum Dikembalikan', Expected Return='31/03/2026'\n";
echo "detail_id=119, unit 2: extend=Rejected → Return Status='Belum Dikembalikan', Expected Return='31/03/2026'\n";
echo "detail_id=119, unit 3: extend=Approved (2026-03-31) → Return Status='Belum Dikembalikan' (34 days), Expected Return='31/03/2026'\n";
echo "detail_id=119, unit 4: no extend → blanket Approved date or rencana_kembali\n";
echo "detail_id=119, unit 5: no extend → blanket Approved date or rencana_kembali\n";

// Test peminjaman 86 (blanket approved extends)
echo "\n\n=== PEMINJAMAN 86 (blanket + per-unit approved) ===\n";
$result86 = computeUnits($conn, 86);
if ($result86) {
    echo "Base rencana_kembali: " . $result86['rencana_kembali'] . "\n\n";
    printf("%-12s %-10s %-18s %-22s %-22s %-8s\n",
        "Detail ID", "Unit", "Expected Return", "Return Status", "Latest Extend", "Rejected");
    echo str_repeat("-", 100) . "\n";
    foreach ($result86['units'] as $u) {
        printf("%-12s %-10s %-18s %-22s %-22s %-8s\n",
            $u['detail_id'], $u['unit_num'], $u['expected_return'],
            $u['return_status'], $u['latest_extend'], $u['rejected']);
    }
}

// Test peminjaman 85 (blanket rejected then approved)
echo "\n\n=== PEMINJAMAN 85 (blanket rejected then approved) ===\n";
$result85 = computeUnits($conn, 85);
if ($result85) {
    echo "Base rencana_kembali: " . $result85['rencana_kembali'] . "\n\n";
    printf("%-12s %-10s %-18s %-22s %-22s %-8s\n",
        "Detail ID", "Unit", "Expected Return", "Return Status", "Latest Extend", "Rejected");
    echo str_repeat("-", 100) . "\n";
    foreach ($result85['units'] as $u) {
        printf("%-12s %-10s %-18s %-22s %-22s %-8s\n",
            $u['detail_id'], $u['unit_num'], $u['expected_return'],
            $u['return_status'], $u['latest_extend'], $u['rejected']);
    }
}
echo "\n✅ If unit with Rejected shows 'Belum Dikembalikan' → FIX WORKS\n";
echo "❌ If unit with Rejected shows 'Due In X Days' → FIX BROKEN\n";
