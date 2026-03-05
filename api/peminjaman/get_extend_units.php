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

    // Get all detail_peminjaman records with their item info
    // d.expected_return is the single source of truth for the current due date.
    $stmt = $conn->prepare("
        SELECT 
            d.id as detail_id,
            d.barang_id,
            d.jumlah as qty_total,
            d.kondisi_pinjam,
            d.expected_return AS detail_expected_return,
            b.kode_barang,
            b.nama_barang
        FROM detail_peminjaman d
        JOIN barang b ON b.id = d.barang_id
        WHERE d.peminjaman_id = ?
        ORDER BY d.id
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $peminjaman_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    $detail_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
            // Per-unit extend: only set from the first (latest) record for this slot
            if (!isset($extend_map[$detail_id][$unit_num])) {
                $extend_map[$detail_id][$unit_num] = [
                    'is_extended' => ($req_status === 'Approved'),
                    'extend_status' => $req_status,
                    'extend_date' => $ext['unit_tanggal_perpanjang']
                ];
            }
        } else if (!$detail_id && !$unit_num) {
            // Blanket extend (no items linked): covers the whole peminjaman
            // Key by '0' to separate from unit-specific entries
            if (!isset($extend_map[0]['blanket'])) {
                $extend_map[0]['blanket'] = [
                    'is_extended' => ($req_status === 'Approved'),
                    'extend_status' => $req_status,
                    'extend_date' => $ext['tanggal_perpanjang']
                ];
            }
        }
    }

    // Get return history grouped by barang_id to compute sudah_dikembalikan
    $stmt = $conn->prepare("
        SELECT 
            dr.barang_id,
            COALESCE(SUM(dr.jumlah_kembali), 0) as total_returned
        FROM detail_pengembalian dr
        WHERE dr.pengembalian_id IN (
            SELECT id FROM pengembalian 
            WHERE peminjaman_id = ? AND status = 'Selesai'
        )
        GROUP BY dr.barang_id
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $peminjaman_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    $return_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Build return map
    $return_map = [];
    foreach ($return_history as $ret) {
        $return_map[$ret['barang_id']] = (int)$ret['total_returned'];
    }

    // Generate per-unit array
    $units = [];
    $unit_counter = 1;

    foreach ($detail_items as $item) {
        $detail_id = (int)$item['detail_id'];
        $barang_id = (int)$item['barang_id'];
        $qty_total = (int)$item['qty_total'];
        $kode_barang = $item['kode_barang'];
        $nama_barang = $item['nama_barang'];
        $kondisi_pinjam = $item['kondisi_pinjam'];
        
        $total_returned = $return_map[$barang_id] ?? 0;
        
        // Determine status of this peminjaman
        $peminjaman_status = $peminjaman['status'];
        $is_completed = in_array($peminjaman_status, [
            'Dikembalikan', 'Returned', 'Completed', 'Closed',
            'Ditolak', 'Rejected', 'Batal', 'Cancelled'
        ]);
        
        // Generate per-unit rows
        for ($unit_num = 1; $unit_num <= $qty_total; $unit_num++) {
            // Determine if this unit was returned
            // A unit is returned if total_returned >= unit_num
            $is_returned = ($total_returned >= $unit_num);
            
            // Determine expected return date for this unit.
            // Use detail_peminjaman.expected_return (single source of truth) as the base.
            // Per-unit extends from extend_peminjaman_items can further override for granularity.
            $expected_return = $item['detail_expected_return'] ?? $peminjaman['rencana_kembali'];
            $is_extended = false;
            $extend_status_for_unit = null;
            $extend_date = null;
            
            // Check if this specific unit has an extend (latest = first in ORDER BY id DESC)
            if (isset($extend_map[$detail_id][$unit_num])) {
                $extend_info = $extend_map[$detail_id][$unit_num];
                $is_extended = $extend_info['is_extended'];
                $extend_status_for_unit = $extend_info['extend_status'];
                $extend_date = $extend_info['extend_date'];
                // Only update expected_return if Approved (Pending doesn't change the date yet)
                if ($is_extended) {
                    $expected_return = $extend_date;
                }
            } else if (isset($extend_map[0]['blanket'])) {
                // Fall back to blanket (whole-peminjaman) extend if unit-specific not found
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
                'unit_display' => "{$unit_num}/{$qty_total}"
            ];
        }
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
