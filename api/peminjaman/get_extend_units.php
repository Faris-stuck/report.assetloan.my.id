<?php
/**
 * API: Get per-unit items for extend modal display
 * Endpoint: /api/peminjaman/get_extend_units.php
 * 
 * Purpose: Returns barang as individual units (not qty summary) for extend modal
 * Each row = 1 unit with its own expected return date based on extend status
 * 
 * Query params:
 * - peminjaman_id (int) required
 * 
 * Returns:
 * {
 *   status: bool,
 *   message?: string,
 *   data?: {
 *     peminjaman_id: int,
 *     peminjaman_rencana_kembali: date,
 *     units: [
 *       {
 *         unit_id: string (unique identifier for this unit),
 *         detail_peminjaman_id: int,
 *         barang_id: int,
 *         kode_barang: string,
 *         nama_barang: string,
 *         unit_number: int (1, 2, 3...),
 *         qty_dipinjam: 1,
 *         kondisi_pinjam: string,
 *         expected_return: date,
 *         sudah_dikembalikan: bool,
 *         is_extended: bool,
 *         extend_date: date|null,
 *         can_extend: bool
 *       }
 *     ]
 *   }
 * }
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

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
$peminjaman_id = (int)($_GET['peminjaman_id'] ?? 0);

if (!$peminjaman_id) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "peminjaman_id is required"]);
    exit;
}

try {
    // Verify peminjaman belongs to current user and get rencana_kembali
    $stmt = $conn->prepare("
        SELECT id, rencana_kembali, status, tanggal_kembali
        FROM peminjaman
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $peminjaman_id, $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    $peminjaman = $stmt->get_result()->fetch_assoc();

    if (!$peminjaman) {
        http_response_code(404);
        echo json_encode(["status" => false, "message" => "Borrowing not found"]);
        exit;
    }

    // ═══════════════════════════════════════════════════════════════
    // Get APPROVED units from peminjaman_units (not detail_peminjaman.jumlah)
    // This ensures only manager-approved units appear in the extend modal.
    // ═══════════════════════════════════════════════════════════════
    $stmt = $conn->prepare("
        SELECT 
            pu.id as pu_id,
            pu.detail_peminjaman_id,
            pu.barang_id,
            pu.unit_number,
            pu.unit_display as pu_unit_display,
            pu.return_status,
            pu.expected_return as pu_expected_return,
            pu.kondisi_kembali,
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
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $peminjaman_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    $approved_units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Count approved units per detail_peminjaman_id for unit_display "X/Y"
    $approved_counts = [];
    foreach ($approved_units as $au) {
        $did = (int)$au['detail_peminjaman_id'];
        if (!isset($approved_counts[$did])) {
            $approved_counts[$did] = 0;
        }
        $approved_counts[$did]++;
    }

    // Get latest extend per peminjaman and its items
    // Use ORDER BY ep.id DESC to get latest extend reliably
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
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $peminjaman_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    $extends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Build map of extends by detail_peminjaman_id and unit_number
    // Results are ORDER BY ep.id DESC - first occurrence = latest extend for each unit
    $extend_map = [];
    foreach ($extends as $ext) {
        $detail_id = $ext['detail_peminjaman_id'];
        $unit_num = $ext['unit_number'];
        $req_status = $ext['extend_req_status'];
        
        if (!isset($extend_map[$detail_id])) {
            $extend_map[$detail_id] = [];
        }
        
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

    // Determine if peminjaman is completed/inactive
    $peminjaman_status = $peminjaman['status'];
    $is_completed = in_array($peminjaman_status, [
        'Returned', 'Completed', 'Closed',
        'Rejected', 'Cancelled'
    ]);

    // Build per-unit array directly from peminjaman_units (approved only)
    $units = [];
    // Track sequential number per detail_peminjaman_id for display
    $detail_seq = [];

    foreach ($approved_units as $au) {
        $pu_id = (int)$au['pu_id'];
        $detail_id = (int)$au['detail_peminjaman_id'];
        $barang_id = (int)$au['barang_id'];
        $unit_num = (int)$au['unit_number'];
        $kode_barang = $au['kode_barang'];
        $nama_barang = $au['nama_barang'];
        $kondisi_pinjam = $au['kondisi_pinjam'];

        // Track sequential number within this item for display
        if (!isset($detail_seq[$detail_id])) {
            $detail_seq[$detail_id] = 0;
        }
        $detail_seq[$detail_id]++;
        $seq = $detail_seq[$detail_id];
        $total_approved = $approved_counts[$detail_id];

        // Determine returned status from peminjaman_units.return_status
        $return_status = $au['return_status'] ?? 'Not Yet Returned';
        $is_returned = in_array($return_status, ['Returned', 'Damaged']);

        // Expected return date: use peminjaman_units.expected_return as source of truth
        $expected_return = $au['pu_expected_return'] ?? $peminjaman['rencana_kembali'];

        $is_extended = false;
        $extend_status_for_unit = null;
        $extend_date = null;

        // Check if this specific unit has an extend (latest = first in ORDER BY id DESC)
        if (isset($extend_map[$detail_id][$unit_num])) {
            $extend_info = $extend_map[$detail_id][$unit_num];
            $is_extended = $extend_info['is_extended'];
            $extend_status_for_unit = $extend_info['extend_status'];
            $extend_date = $extend_info['extend_date'];
            if ($is_extended) {
                $expected_return = $extend_date;
            }
        } else if (isset($extend_map[0]['blanket'])) {
            $extend_info = $extend_map[0]['blanket'];
            $is_extended = $extend_info['is_extended'];
            $extend_status_for_unit = $extend_info['extend_status'];
            $extend_date = $extend_info['extend_date'];
            if ($is_extended) {
                $expected_return = $extend_date;
            }
        }

        // Can extend: not yet returned AND peminjaman still active
        $can_extend = !$is_returned && !$is_completed;

        $units[] = [
            'unit_id' => "detail_{$detail_id}_unit_{$unit_num}",
            'detail_peminjaman_id' => $detail_id,
            'barang_id' => $barang_id,
            'kode_barang' => $kode_barang,
            'nama_barang' => $nama_barang,
            'unit_number' => $unit_num,
            'qty_dipinjam' => 1,
            'kondisi_pinjam' => $kondisi_pinjam,
            'expected_return' => $expected_return ? date('d/m/Y', strtotime($expected_return)) : '-',
            'sudah_dikembalikan' => $is_returned,
            'is_extended' => $is_extended,
            'extend_status' => $extend_status_for_unit,
            'extend_date' => $extend_date ? date('d/m/Y', strtotime($extend_date)) : null,
            'can_extend' => $can_extend,
            'unit_display' => "{$seq}/{$total_approved}"
        ];
    }

    echo json_encode([
        "status" => true,
        "data" => [
            "peminjaman_id" => $peminjaman_id,
            "peminjaman_rencana_kembali" => $peminjaman['rencana_kembali'] ? date('d/m/Y', strtotime($peminjaman['rencana_kembali'])) : '-',
            "peminjaman_status" => computeDueStatus($peminjaman_status, getNearestExpectedReturn($conn, $peminjaman_id) ?? $peminjaman['rencana_kembali']),
            "units" => $units
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>
