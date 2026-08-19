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
require_once __DIR__ . '/../response-helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiBusinessError('Method not allowed', 405);
}

if (!SessionValidator::isLoggedIn()) {
    apiBusinessError('Unauthorized', 401);
}

if (SessionValidator::getRole() !== 'user') {
    apiBusinessError('Access denied', 403);
}

$user_id = (int) (SessionValidator::getUserId() ?? 0);
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
        echo json_encode(['status' => false, 'message' => 'Invalid units format']);
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
    apiBusinessError('Select at least 1 item/unit to extend', 400);
}

try {
    // Verify peminjaman belongs to user and is active
    $stmt = $conn->prepare("SELECT id, rencana_kembali, status FROM peminjaman WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $peminjaman_id, $user_id);
    $stmt->execute();
    $peminjaman = $stmt->get_result()->fetch_assoc();

    if (!$peminjaman) {
        echo json_encode(['status' => false, 'message' => 'Borrowing not found or does not belong to you']);
        exit;
    }

    // ============================================================
    // KEY FIX: Check for ACTUAL return requests instead of status field
    // Status "Return in Process" is only valid if there's a pending return request
    // ============================================================
    $chkReturn = $conn->prepare("
        SELECT id, status FROM pengembalian 
        WHERE peminjaman_id = ? AND status IN ('Submitted', 'Being Inspected', 'Partially Returned', 'Partially Damaged')
        LIMIT 1
    ");
    $chkReturn->bind_param("i", $peminjaman_id);
    $chkReturn->execute();
    $hasActiveReturn = $chkReturn->get_result()->num_rows > 0;

    // Check if all approved units have been fully returned (via peminjaman_units.return_status)
    $chkFullReturn = $conn->prepare("
        SELECT 
            COUNT(*) as total_approved,
            SUM(CASE WHEN return_status IN ('Returned', 'Damaged') THEN 1 ELSE 0 END) as total_returned
        FROM peminjaman_units
        WHERE peminjaman_id = ?
          AND approval_status = 'Approved'
    ");
    $chkFullReturn->bind_param("i", $peminjaman_id);
    $chkFullReturn->execute();
    $returnStats = $chkFullReturn->get_result()->fetch_assoc();
    $totalItems = (int)($returnStats['total_approved'] ?? 0);
    $totalReturned = (int)($returnStats['total_returned'] ?? 0);
    $itemsRemaining = $totalItems - $totalReturned;

    // Allow extend if:
    // 1. There are items still out (not fully returned)
    // 2. AND either NO active return request exists OR there are items not included in the pending return
    if ($itemsRemaining <= 0) {
        echo json_encode(['status' => false, 'message' => 'All items already returned, cannot extend']);
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
            WHERE p.peminjaman_id = ? AND p.status IN ('Submitted', 'Being Inspected', 'Partially Returned', 'Partially Damaged')
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
            echo json_encode(['status' => false, 'message' => 'Borrowing is in return process. Wait until the process is complete to extend.']);
            exit;
        }
    }

    $latestExtendStatus = getLatestBorrowingExtendStatus($conn, $peminjaman_id);
    if ($latestExtendStatus === 'Pending') {
        echo json_encode(['status' => false, 'message' => 'There is already a pending extension request awaiting approval']);
        exit;
    }

    $nearestReturn = getNearestExpectedReturn($conn, $peminjaman_id);
    $extendState = getBorrowingExtendState(
        $conn,
        $peminjaman_id,
        (string) $peminjaman['status'],
        $peminjaman['rencana_kembali'],
        $latestExtendStatus,
        $nearestReturn
    );
    $currentStatus = $extendState['status'];

    if (!$extendState['can_extend']) {
        echo json_encode(['status' => false, 'message' => 'Borrowing with status "' . $currentStatus . '" cannot be extended']);
        exit;
    }

    // Validate new date is after current return date
    $current_return = $peminjaman['rencana_kembali'];
    if ($tanggal_perpanjang <= $current_return) {
        echo json_encode(['status' => false, 'message' => 'Extension date must be after the current return date (' . date('d/m/Y', strtotime($current_return)) . ')']);
        exit;
    }

    // Validate items: check that qty_extend doesn't exceed sisa dikembalikan (for legacy format)
    // For per-unit format, just verify detail_peminjaman_id exists
    $errorItems = [];
    
    if ($is_per_unit) {
        // Per-unit format validation - verify each unit exists in peminjaman_units and is approved
        foreach ($items as $item) {
            $detail_id = (int)($item['detail_peminjaman_id'] ?? 0);
            $unit_num = (int)($item['unit_number'] ?? 0);
            
            if ($detail_id <= 0 || $unit_num <= 0) {
                $errorItems[] = "Invalid unit format";
                continue;
            }
            
            // Verify unit exists in peminjaman_units, is approved, and not returned
            $chk = $conn->prepare("
                SELECT pu.id 
                FROM peminjaman_units pu
                WHERE pu.detail_peminjaman_id = ? 
                  AND pu.unit_number = ?
                  AND pu.peminjaman_id = ?
                  AND pu.approval_status = 'Approved'
                  AND pu.return_status NOT IN ('Returned', 'Damaged')
            ");
            $chk->bind_param("iii", $detail_id, $unit_num, $peminjaman_id);
            $chk->execute();
            $unitRow = $chk->get_result()->fetch_assoc();
            
            if (!$unitRow) {
                $errorItems[] = "Unit {$unit_num} of detail ID {$detail_id} not found, not approved, or already returned";
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
                        WHERE peminjaman_id = ? AND status = 'Completed'
                    )
                WHERE d.peminjaman_id = ? AND d.barang_id = ?
                GROUP BY d.id
            ");
            $stmtCheck->bind_param("iii", $peminjaman_id, $peminjaman_id, $barang_id);
            $stmtCheck->execute();
            $detailRow = $stmtCheck->get_result()->fetch_assoc();

            if (!$detailRow) {
                $errorItems[] = "Item ID {$barang_id} not found in this borrowing";
                continue;
            }

            $original_qty = (int)$detailRow['jumlah'];
            $returned_qty = (int)$detailRow['sudah_dikembalikan'];
            $sisa_untuk_extend = $original_qty - $returned_qty;

            // QTY extend cannot exceed sisa yang belum dikembalikan
            if ($qty_extend > $sisa_untuk_extend) {
                $errorItems[] = "Extension qty for item ID {$barang_id} ({$qty_extend}) exceeds remaining extendable qty ({$sisa_untuk_extend})";
            }
        }
    }

    if (!empty($errorItems)) {
        echo json_encode(['status' => false, 'message' => 'Item validation failed: ' . implode('; ', $errorItems)]);
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert extend request
        $stmt = $conn->prepare("INSERT INTO extend_peminjaman (peminjaman_id, user_id, tanggal_kembali_sekarang, tanggal_perpanjang, alasan, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("iisss", $peminjaman_id, $user_id, $current_return, $tanggal_perpanjang, $alasan);

        if (!$stmt->execute()) {
            throw new Exception('Failed to save request: ' . $conn->error);
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
                        throw new Exception('Failed to save extension item: ' . $conn->error);
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
                                throw new Exception('Failed to save extension item: ' . $conn->error);
                            }
                        }
                    }
                }
            }
        }

        $conn->commit();
        
        // ============================================================
        // Send email notification after extension request is successfully created
        // ============================================================
        try {
            require_once __DIR__ . '/../email/send-extend-request.php';
            sendExtendRequestEmail($conn, $extend_id);
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] extend/request: " . $emailEx->getMessage());
            // Email error tidak perlu menggagalkan response, hanya log saja
        }
        
        echo json_encode(['status' => true, 'message' => 'Extension request submitted successfully']);

    } catch (Throwable $e) {
        $conn->rollback();
        apiServerError($e, 'api/extend/request.php', 'Failed to save extension request');
    }

} catch (Throwable $e) {
    apiServerError($e, 'api/extend/request.php');
}
