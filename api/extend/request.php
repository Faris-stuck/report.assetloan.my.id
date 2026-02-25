<?php
/**
 * API: Request Extend (Perpanjang Masa Peminjaman) - with per-unit support
 * Method: POST
 * Params: 
 *   peminjaman_id: int
 *   tanggal_perpanjang: date (YYYY-MM-DD)
 *   alasan: string
 *   units: JSON array of unit_ids to extend or items with barang_id/qty_extend for backward compat
 *     - Format: ["detail_1_unit_1", "detail_1_unit_2", ...] or legacy [{barang_id, qty_extend}]
 * Role: user
 * 
 * If 'units' param provided: per-unit extend (new behavior)
 * If 'items' param provided: backward compatible extend by qty
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

// Support both new per-unit format and legacy format
$units_json = trim($_POST['units'] ?? '[]');
$items_json = trim($_POST['items'] ?? '[]');

// Determine if using new per-unit format or legacy format
$is_per_unit = !empty($units_json) && $units_json !== '[]';
$items = [];

if ($is_per_unit) {
    // New per-unit format: array of unit_ids like ["detail_1_unit_1", "detail_1_unit_2"]
    $units = json_decode($units_json, true);
    if (!is_array($units)) {
        echo json_encode(['status' => false, 'message' => 'Format units tidak valid']);
        exit;
    }
    // Convert unit_ids to {detail_peminjaman_id, unit_number} format
    foreach ($units as $unit_id) {
        // Parse "detail_{id}_unit_{num}" format
        if (preg_match('/^detail_(\d+)_unit_(\d+)$/', $unit_id, $matches)) {
            $items[] = [
                'detail_peminjaman_id' => (int)$matches[1],
                'unit_number' => (int)$matches[2]
            ];
        }
    }
} else {
    // Legacy format: array of {barang_id, qty_extend}
    $items = json_decode($items_json, true);
    if (!is_array($items)) {
        $items = [];
    }
}

// Validate that at least one item/unit is selected
if (count($items) === 0) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Pilih minimal 1 barang/unit untuk diperpanjang']);
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

    // ============================================================
    // KEY FIX: Check for ACTUAL return requests instead of status field
    // Status "Proses Return" is only valid if there's a pending return request
    // ============================================================
    $chkReturn = $conn->prepare("
        SELECT id, status FROM pengembalian 
        WHERE peminjaman_id = ? AND status IN ('Diajukan', 'Dicek', 'Sebagian Dikembalikan', 'Sebagian Rusak')
        LIMIT 1
    ");
    $chkReturn->bind_param("i", $peminjaman_id);
    $chkReturn->execute();
    $hasActiveReturn = $chkReturn->get_result()->num_rows > 0;

    // Check if all items have been fully returned (via finalized pengembalian records)
    $chkFullReturn = $conn->prepare("
        SELECT 
            COALESCE(SUM(dp.jumlah), 0) as total_items,
            COALESCE(SUM(dr.jumlah_kembali), 0) as total_returned
        FROM detail_peminjaman dp
        LEFT JOIN detail_pengembalian dr ON dr.barang_id = dp.barang_id 
            AND dr.pengembalian_id IN (
                SELECT id FROM pengembalian 
                WHERE peminjaman_id = ? AND status = 'Selesai'
            )
        WHERE dp.peminjaman_id = ?
    ");
    $chkFullReturn->bind_param("ii", $peminjaman_id, $peminjaman_id);
    $chkFullReturn->execute();
    $returnStats = $chkFullReturn->get_result()->fetch_assoc();
    $totalItems = (int)($returnStats['total_items'] ?? 0);
    $totalReturned = (int)($returnStats['total_returned'] ?? 0);
    $itemsRemaining = $totalItems - $totalReturned;

    // Allow extend if:
    // 1. There are items still out (not fully returned)
    // 2. AND either NO active return request exists OR there are items not included in the pending return
    if ($itemsRemaining <= 0) {
        echo json_encode(['status' => false, 'message' => 'Semua barang sudah dikembalikan, tidak dapat diperpanjang']);
        exit;
    }

    if ($hasActiveReturn) {
        // There's a pending return request - only allow extend if not ALL items are in the pending return
        // Get items in the active return request
        $chkReturnItems = $conn->prepare("
            SELECT COUNT(DISTINCT dp.barang_id) as items_in_return
            FROM detail_pengembalian dr
            JOIN pengembalian p ON dr.pengembalian_id = p.id
            JOIN detail_peminjaman dp ON dr.barang_id = dp.barang_id
            WHERE p.peminjaman_id = ? AND p.status IN ('Diajukan', 'Dicek', 'Sebagian Dikembalikan', 'Sebagian Rusak')
            AND dp.peminjaman_id = ?
        ");
        $chkReturnItems->bind_param("ii", $peminjaman_id, $peminjaman_id);
        $chkReturnItems->execute();
        $returnItemsResult = $chkReturnItems->get_result()->fetch_assoc();
        $itemsInReturn = (int)($returnItemsResult['items_in_return'] ?? 0);

        // Get total borrowed items
        $chkTotalItems = $conn->prepare("SELECT COUNT(*) as cnt FROM detail_peminjaman WHERE peminjaman_id = ?");
        $chkTotalItems->bind_param("i", $peminjaman_id);
        $chkTotalItems->execute();
        $totalItemsResult = $chkTotalItems->get_result()->fetch_assoc();
        $totalBorrowedItems = (int)($totalItemsResult['cnt'] ?? 0);

        // Reject only if ALL items are in an active return request
        if ($itemsInReturn >= $totalBorrowedItems && $totalBorrowedItems > 0) {
            echo json_encode(['status' => false, 'message' => 'Peminjaman dalam proses pengembalian. Tunggu hingga proses selesai untuk memperpanjang.']);
            exit;
        }
    }

    // Original status validation - allow active statuses
    $currentStatus = $peminjaman['status'];
    $isActive = in_array($currentStatus, ['Sedang Dipinjam', 'Disetujui', 'Sebagian Dikembalikan', 'Overdue'])
                || strpos($currentStatus, 'Due') === 0
                || $currentStatus === 'Proses Return';  // Allow if there are items remaining
    
    if (!$isActive) {
        echo json_encode(['status' => false, 'message' => 'Peminjaman dengan status "' . $currentStatus . '" tidak dapat diperpanjang']);
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

    // Validate items: check that qty_extend doesn't exceed sisa dikembalikan (for legacy format)
    // For per-unit format, just verify detail_peminjaman_id exists
    $errorItems = [];
    
    if ($is_per_unit) {
        // Per-unit format validation
        foreach ($items as $item) {
            $detail_id = (int)($item['detail_peminjaman_id'] ?? 0);
            $unit_num = (int)($item['unit_number'] ?? 0);
            
            if ($detail_id <= 0 || $unit_num <= 0) {
                $errorItems[] = "Format unit tidak valid";
                continue;
            }
            
            // Verify detail_peminjaman exists in this peminjaman
            $chk = $conn->prepare("SELECT id, jumlah FROM detail_peminjaman WHERE id = ? AND peminjaman_id = ?");
            $chk->bind_param("ii", $detail_id, $peminjaman_id);
            $chk->execute();
            $detail = $chk->get_result()->fetch_assoc();
            
            if (!$detail) {
                $errorItems[] = "Detail item ID {$detail_id} tidak ditemukan";
                continue;
            }
            
            // Verify unit_number is within qty range
            if ($unit_num > (int)$detail['jumlah']) {
                $errorItems[] = "Unit {$unit_num} melebihi jumlah dipinjam (" . (int)$detail['jumlah'] . ")";
            }
        }
    } else {
        // Legacy format validation
        foreach ($items as $item) {
            $barang_id = (int)($item['barang_id'] ?? 0);
            $qty_extend = (int)($item['qty_extend'] ?? 0);

            if ($barang_id <= 0 || $qty_extend <= 0) {
                continue;  // Skip invalid or zero quantities
            }

            // Get original qty and returned qty for this barang in this peminjaman
            $stmtCheck = $conn->prepare("
                SELECT 
                    d.id,
                    d.jumlah,
                    COALESCE(SUM(dr.jumlah_kembali), 0) as sudah_dikembalikan
                FROM detail_peminjaman d
                LEFT JOIN detail_pengembalian dr ON dr.barang_id = d.barang_id 
                    AND dr.pengembalian_id IN (
                        SELECT id FROM pengembalian 
                        WHERE peminjaman_id = ? AND status = 'Selesai'
                    )
                WHERE d.peminjaman_id = ? AND d.barang_id = ?
                GROUP BY d.id
            ");
            $stmtCheck->bind_param("iii", $peminjaman_id, $peminjaman_id, $barang_id);
            $stmtCheck->execute();
            $detailRow = $stmtCheck->get_result()->fetch_assoc();

            if (!$detailRow) {
                $errorItems[] = "Barang ID {$barang_id} tidak ditemukan dalam peminjaman ini";
                continue;
            }

            $original_qty = (int)$detailRow['jumlah'];
            $returned_qty = (int)$detailRow['sudah_dikembalikan'];
            $sisa_untuk_extend = $original_qty - $returned_qty;

            // QTY extend cannot exceed sisa yang belum dikembalikan
            if ($qty_extend > $sisa_untuk_extend) {
                $errorItems[] = "Qty perpanjangan barang ID {$barang_id} ({$qty_extend}) melebihi sisa yang dapat diperpanjang ({$sisa_untuk_extend})";
            }
        }
    }

    if (!empty($errorItems)) {
        echo json_encode(['status' => false, 'message' => 'Validasi item gagal: ' . implode('; ', $errorItems)]);
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert extend request
        $stmt = $conn->prepare("INSERT INTO extend_peminjaman (peminjaman_id, user_id, tanggal_kembali_sekarang, tanggal_perpanjang, alasan, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("iisss", $peminjaman_id, $user_id, $current_return, $tanggal_perpanjang, $alasan);

        if (!$stmt->execute()) {
            throw new Exception('Gagal menyimpan permintaan: ' . $conn->error);
        }

        $extend_id = $conn->insert_id;

        // Insert extended items based on format (per-unit or legacy)
        if ($is_per_unit) {
            // Per-unit format: insert each unit with detail_peminjaman_id and unit_number
            $stmtItem = $conn->prepare("
                INSERT INTO extend_peminjaman_items 
                (extend_peminjaman_id, detail_peminjaman_id, unit_number, tanggal_perpanjang) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($items as $item) {
                $detail_id = (int)($item['detail_peminjaman_id'] ?? 0);
                $unit_num = (int)($item['unit_number'] ?? 0);
                
                if ($detail_id > 0 && $unit_num > 0) {
                    $stmtItem->bind_param("iiis", $extend_id, $detail_id, $unit_num, $tanggal_perpanjang);
                    if (!$stmtItem->execute()) {
                        throw new Exception('Gagal menyimpan item perpanjangan: ' . $conn->error);
                    }
                }
            }
        } else {
            // Legacy format: keep old structure for backward compatibility
            // Note: This branch is deprecated and included only for backward compat
            $stmtItem = $conn->prepare("INSERT INTO extend_peminjaman_items (extend_peminjaman_id, detail_peminjaman_id, unit_number, tanggal_perpanjang) VALUES (?, ?, ?, ?)");
            
            foreach ($items as $item) {
                $barang_id = (int)($item['barang_id'] ?? 0);
                $qty_extend = (int)($item['qty_extend'] ?? 0);

                if ($barang_id > 0 && $qty_extend > 0) {
                    // For legacy, create entries for the first qty_extend units
                    // This is a best-effort mapping since original data is qty-based
                    // Get the detail_peminjaman_id for this barang
                    $detChk = $conn->prepare("SELECT id, jumlah FROM detail_peminjaman WHERE peminjaman_id = ? AND barang_id = ?");
                    $detChk->bind_param("ii", $peminjaman_id, $barang_id);
                    $detChk->execute();
                    $detRow = $detChk->get_result()->fetch_assoc();
                    
                    if ($detRow) {
                        $detail_id = (int)$detRow['id'];
                        // Mark first qty_extend units as extended
                        for ($u = 1; $u <= min($qty_extend, (int)$detRow['jumlah']); $u++) {
                            $stmtItem->bind_param("iiis", $extend_id, $detail_id, $u, $tanggal_perpanjang);
                            if (!$stmtItem->execute()) {
                                throw new Exception('Gagal menyimpan item perpanjangan: ' . $conn->error);
                            }
                        }
                    }
                }
            }
        }

        $conn->commit();
        
        // ============================================================
        // Kirim email notifikasi setelah permintaan perpanjangan berhasil dibuat
        // ============================================================
        try {
            require_once __DIR__ . '/../email/send-extend-request.php';
            sendExtendRequestEmail($conn, $extend_id);
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] extend/request: " . $emailEx->getMessage());
            // Email error tidak perlu menggagalkan response, hanya log saja
        }
        
        echo json_encode(['status' => true, 'message' => 'Permintaan perpanjangan berhasil diajukan']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
