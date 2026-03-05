<?php
/**
 * API: Get per-unit detail for Borrowing Details modal
 * Endpoint: /api/peminjaman/get_detail_units.php
 * 
 * READS UNITS DIRECTLY FROM peminjaman_units TABLE (database-driven).
 * NO manual loop qty, NO manual status calculation, NO manual unit generation.
 * 
 * Each row in peminjaman_units = 1 physical unit with its own:
 *   - return_status (from database)
 *   - expected_return (from database, per-unit, includes extends)
 *   - unit_display (from database)
 *   - kondisi_kembali (from database)
 *   - tanggal_kembali (from database)
 * 
 * Accessible by all roles: user, admin, manager, pic_barang, teknisi, operator.
 * 
 * Query params:
 * - peminjaman_id (int) required
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    SessionValidator::requireRole(['user', 'admin', 'manager', 'pic_barang', 'teknisi', 'operator']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit;
}

$peminjaman_id = (int)($_GET['peminjaman_id'] ?? 0);
$session_user_id = (int)(SessionValidator::getUserId() ?? 0);
$session_role = SessionValidator::getRole();

if (!$peminjaman_id) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "peminjaman_id is required"]);
    exit;
}

try {
    // ================================================================
    // 1. Get peminjaman header
    // ================================================================
    $stmt = $conn->prepare("
        SELECT p.id, p.kode_peminjaman, p.user_id, p.tanggal_pinjam, p.rencana_kembali,
               p.status, p.catatan, p.rejection_reason AS peminjaman_rejection_reason,
               p.lokasi_umum,
               u.nama AS nama_peminjam, u.nrp
        FROM peminjaman p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    $peminjaman = $stmt->get_result()->fetch_assoc();

    if (!$peminjaman) {
        http_response_code(404);
        echo json_encode(["status" => false, "message" => "Borrowing not found"]);
        exit;
    }

    // Security: if role is user, ensure the peminjaman belongs to the session user.
    if ($session_role === 'user' && (int)$peminjaman['user_id'] !== $session_user_id) {
        http_response_code(403);
        echo json_encode(["status" => false, "message" => "Access denied for this borrowing"]);
        exit;
    }

    // ================================================================
    // 2. FETCH ALL UNITS DIRECTLY FROM peminjaman_units TABLE
    //    NO loop qty. NO manual generation. ALL data from database.
    // ================================================================
    $stmt = $conn->prepare("
        SELECT 
            pu.id AS unit_id,
            pu.detail_peminjaman_id,
            pu.barang_id,
            pu.unit_number,
            pu.unit_display,
            pu.return_status,
            pu.expected_return,
            pu.kondisi_kembali,
            pu.tanggal_kembali,
            pu.approval_status,
            pu.rejection_reason AS unit_rejection_reason,
            b.kode_barang,
            b.nama_barang,
            dp.kondisi_pinjam
        FROM peminjaman_units pu
        JOIN barang b ON b.id = pu.barang_id
        JOIN detail_peminjaman dp ON dp.id = pu.detail_peminjaman_id
        WHERE pu.peminjaman_id = ?
        ORDER BY pu.detail_peminjaman_id, pu.unit_number
    ");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    $unit_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // ================================================================
    // FALLBACK: For Pending Approval - if no units exist yet, get from detail_peminjaman
    // ================================================================
    if (empty($unit_rows) && $peminjaman['status'] === 'Menunggu Persetujuan') {
        $stmt = $conn->prepare("
            SELECT 
                dp.id AS detail_peminjaman_id,
                dp.barang_id,
                dp.jumlah,
                dp.kondisi_pinjam,
                b.kode_barang,
                b.nama_barang,
                p.rencana_kembali
            FROM detail_peminjaman dp
            JOIN barang b ON b.id = dp.barang_id
            JOIN peminjaman p ON p.id = dp.peminjaman_id
            WHERE dp.peminjaman_id = ?
            ORDER BY dp.id
        ");
        $stmt->bind_param("i", $peminjaman_id);
        $stmt->execute();
        $detail_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Convert detail_peminjaman rows to unit-like rows for pending approval display
        $unit_counter = 1;
        foreach ($detail_rows as $detail) {
            $qty = (int)$detail['jumlah'];
            // Generate N rows (one per unit in requested quantity)
            for ($i = 1; $i <= $qty; $i++) {
                $unit_rows[] = [
                    'unit_id' => null,  // No unit_id yet, still pending
                    'detail_peminjaman_id' => (int)$detail['detail_peminjaman_id'],
                    'barang_id' => (int)$detail['barang_id'],
                    'unit_number' => $i,
                    'unit_display' => 'Unit ' . $i . ' of ' . $qty,
                    'return_status' => 'Menunggu Persetujuan',  // Show pending approval status
                    'expected_return' => $detail['rencana_kembali'] ? date('d/m/Y', strtotime($detail['rencana_kembali'])) : '-',
                    'kondisi_kembali' => null,
                    'tanggal_kembali' => null,
                    'kode_barang' => $detail['kode_barang'],
                    'nama_barang' => $detail['nama_barang'],
                    'kondisi_pinjam' => $detail['kondisi_pinjam'],
                    'approval_status' => null,
                    'unit_rejection_reason' => null
                ];
            }
        }
    }

    // ================================================================
    // 3. Build units array from database rows (NO manual calculation)
    //    Only add real-time due-proximity label for active/unreturned units
    //    using the per-unit expected_return FROM DATABASE.
    // ================================================================
    $master_status = $peminjaman['status'];
    $master_is_final = in_array($master_status, ['Ditolak', 'Dikembalikan']);

    $units = [];
    $total_items = 0;
    $total_returned = 0;
    $earliest_return = null;

    $tz = new DateTimeZone('Asia/Jakarta');
    $today = new DateTime('today', $tz);
    $today->setTime(0, 0, 0);

    foreach ($unit_rows as $row) {
        $total_items++;

        // Read return_status directly from database
        $db_return_status = $row['return_status'];
        $expected_return_raw = $row['expected_return'];
        $approval_status = $row['approval_status'] ?? null;
        
        // For Pending Approval, treat as not returned (hasn't been approved yet)
        // For rejected units (approval_status = Ditolak), treat as final
        // For others, check if item was actually returned
        $is_rejected_unit = ($approval_status === 'Ditolak');
        $is_returned = in_array($db_return_status, ['Dikembalikan', 'Rusak']) && $db_return_status !== 'Menunggu Persetujuan';

        if ($is_returned || $is_rejected_unit) {
            $total_returned++;
        }

        // Determine display return_status:
        // - For Menunggu Persetujuan → show as-is (pending approval)
        // - For final statuses (Dikembalikan, Rusak, Ditolak, Proses Return, etc.) → use DB value directly
        // - For active/unreturned units (Dipinjam, Belum Dikembalikan) → compute due-proximity
        //   using per-unit expected_return FROM DATABASE (not global rencana_kembali)
        $display_return_status = $db_return_status;

        // If unit was rejected during partial approval, show as Ditolak regardless
        if ($is_rejected_unit) {
            $display_return_status = 'Ditolak';
        }
        // If status is Menunggu Persetujuan, keep it as-is (don't compute due-proximity)
        elseif ($db_return_status === 'Menunggu Persetujuan') {
            $display_return_status = 'Menunggu Persetujuan';
        } elseif ($master_is_final) {
            // Master peminjaman is Ditolak/Dikembalikan → all units inherit master status
            if (!$is_returned) {
                $display_return_status = $master_status;
            }
        } elseif (!$is_returned && $db_return_status !== 'Proses Return' && $expected_return_raw) {
            // Active unit: compute due-proximity from per-unit expected_return (from DB)
            $retDate = false;
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $expected_return_raw)) {
                $retDate = DateTime::createFromFormat('Y-m-d', substr($expected_return_raw, 0, 10), $tz);
            }
            if ($retDate) {
                $retDate->setTime(0, 0, 0);
                $diffDays = (int)$today->diff($retDate)->format('%r%a');
                if ($diffDays < 0) {
                    $display_return_status = 'Overdue';
                } elseif ($diffDays === 0) {
                    $display_return_status = 'Due Today';
                } elseif ($diffDays === 1) {
                    $display_return_status = 'Due In 1 Day';
                } elseif ($diffDays <= 7) {
                    $display_return_status = 'Due In ' . $diffDays . ' Days';
                } else {
                    $display_return_status = 'Belum Dikembalikan';
                }
            }
        }

        // Track earliest expected return for non-returned units
        if (!$is_returned && $expected_return_raw) {
            $ts = strtotime($expected_return_raw);
            if ($ts && ($earliest_return === null || $ts < $earliest_return)) {
                $earliest_return = $ts;
            }
        }

        $units[] = [
            'detail_peminjaman_id' => (int)$row['detail_peminjaman_id'],
            'barang_id'            => (int)$row['barang_id'],
            'kode_barang'          => $row['kode_barang'],
            'nama_barang'          => $row['nama_barang'],
            'unit_number'          => (int)$row['unit_number'],
            'unit_display'         => $row['unit_display'],      // FROM DATABASE
            'qty'                  => 1,
            'return_status'        => $display_return_status,     // FROM DATABASE + due proximity
            'approval_status'      => $approval_status,           // FROM DATABASE (Disetujui/Ditolak/null)
            'expected_return'      => $expected_return_raw ? date('d/m/Y', strtotime($expected_return_raw)) : '-', // FROM DATABASE
            'kondisi_pinjam'       => $row['kondisi_pinjam'],
            'kondisi_kembali'      => $row['kondisi_kembali'],    // FROM DATABASE
            'tanggal_kembali'      => $row['tanggal_kembali'],    // FROM DATABASE
            'sudah_dikembalikan'   => $is_returned || $is_rejected_unit
        ];
    }

    // ================================================================
    // 4. Determine overall display status from per-unit statuses
    // ================================================================
    $display_status = $master_status;
    if ($master_is_final) {
        // Ditolak or Dikembalikan: keep as-is
    } elseif ($master_status === 'Partial Approved') {
        // For Partial Approved, keep that status unless there are due-proximity concerns
        // Check for overdue among approved (non-rejected) units
        $has_overdue_pa = false;
        $min_due_days_pa = PHP_INT_MAX;
        foreach ($units as $unit) {
            if ($unit['approval_status'] !== 'Ditolak' && !$unit['sudah_dikembalikan']) {
                $rs = $unit['return_status'];
                if ($rs === 'Overdue') $has_overdue_pa = true;
                elseif ($rs === 'Due Today' && 0 < $min_due_days_pa) $min_due_days_pa = 0;
                elseif ($rs === 'Due In 1 Day' && 1 < $min_due_days_pa) $min_due_days_pa = 1;
                elseif (preg_match('/^Due In (\d+) Days$/', $rs, $m) && (int)$m[1] < $min_due_days_pa) $min_due_days_pa = (int)$m[1];
            }
        }
        if ($has_overdue_pa) {
            $display_status = 'Overdue';
        } elseif ($min_due_days_pa !== PHP_INT_MAX) {
            if ($min_due_days_pa === 0) $display_status = 'Due Today';
            elseif ($min_due_days_pa === 1) $display_status = 'Due In 1 Day';
            else $display_status = 'Due In ' . $min_due_days_pa . ' Days';
        } else {
            $display_status = 'Partial Approved';
        }
    } elseif ($total_returned >= $total_items && $total_items > 0) {
        $display_status = 'Dikembalikan';
    } else {
        // Priority: Overdue > Due Today > Due In X Days > Belum Dikembalikan > Sebagian Dikembalikan
        $has_overdue = false;
        $min_due_days = PHP_INT_MAX;
        $has_belum = false;

        foreach ($units as $unit) {
            if (!$unit['sudah_dikembalikan']) {
                $rs = $unit['return_status'];
                if ($rs === 'Overdue') {
                    $has_overdue = true;
                } elseif ($rs === 'Due Today') {
                    if (0 < $min_due_days) $min_due_days = 0;
                } elseif ($rs === 'Due In 1 Day') {
                    if (1 < $min_due_days) $min_due_days = 1;
                } elseif (preg_match('/^Due In (\d+) Days$/', $rs, $m)) {
                    if ((int)$m[1] < $min_due_days) $min_due_days = (int)$m[1];
                } else {
                    $has_belum = true;
                }
            }
        }

        if ($has_overdue) {
            $display_status = 'Overdue';
        } elseif ($min_due_days !== PHP_INT_MAX) {
            if ($min_due_days === 0) {
                $display_status = 'Due Today';
            } elseif ($min_due_days === 1) {
                $display_status = 'Due In 1 Day';
            } else {
                $display_status = 'Due In ' . $min_due_days . ' Days';
            }
        } elseif ($has_belum) {
            $display_status = ($total_returned > 0) ? 'Sebagian Dikembalikan' : $peminjaman['status'];
        }
    }

    // ================================================================
    // 5. Output — same response shape as before for frontend compatibility
    // ================================================================
    echo json_encode([
        "status" => true,
        "data" => [
            "id" => (int)$peminjaman['id'],
            "kode_peminjaman" => $peminjaman['kode_peminjaman'],
            "nama_peminjam" => $peminjaman['nama_peminjam'],
            "nrp" => $peminjaman['nrp'],
            "tanggal_pinjam" => $peminjaman['tanggal_pinjam'] ? date('d/m/Y', strtotime($peminjaman['tanggal_pinjam'])) : '-',
            "rencana_kembali" => $peminjaman['rencana_kembali'] ? date('d/m/Y', strtotime($peminjaman['rencana_kembali'])) : '-',
            "expected_return_nearest" => $earliest_return ? date('d/m/Y', $earliest_return) : ($peminjaman['rencana_kembali'] ? date('d/m/Y', strtotime($peminjaman['rencana_kembali'])) : '-'),
            "status" => $display_status,
            "catatan" => $peminjaman['catatan'],
            "rejection_reason" => $peminjaman['peminjaman_rejection_reason'] ?? null,
            "lokasi_umum" => $peminjaman['lokasi_umum'] ?? null,
            "units" => $units,
            "total_items" => $total_items,
            "total_returned" => min($total_returned, $total_items)
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
