<?php
/**
 * Database Connection — Auto-detect localhost vs VPS
 * Timezone: Asia/Jakarta (WAJIB untuk konsistensi due-status)
 */

// ============================================================
// TIMEZONE: Wajib Asia/Jakarta untuk seluruh sistem
// ============================================================
date_default_timezone_set('Asia/Jakarta');

$host = "localhost";
$database = "peminjaman";

if ($_SERVER['HTTP_HOST'] == "localhost" || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    // Localhost / XAMPP
    $user = "root";
    $password = "";
} else {
    // VPS / Production — sesuaikan credentials di sini
    $user = "root";
    $password = "";
}

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Koneksi DB gagal"]);
    exit;
}

// Set MySQL session timezone ke Asia/Jakarta (+07:00)
// KRITIS: agar CURDATE(), NOW(), DATEDIFF() konsisten dengan PHP
$conn->query("SET time_zone = '+07:00'");

// ============================================================
// HELPER: Hitung status due secara REAL-TIME dari rencana_kembali
// ============================================================
/**
 * Menghitung status due peminjaman secara real-time.
 * Hanya override status jika status saat ini bersifat "active borrowing"
 * (Sedang Dipinjam / Due% / Overdue). Status non-aktif TIDAK diubah.
 *
 * @param string $dbStatus        Status dari database
 * @param string $rencanaKembali  Tanggal expected return (Y-m-d, Y-m-d H:i:s, atau d/m/Y)
 * @return string Status real-time berdasarkan selisih hari
 */
function computeDueStatus($dbStatus, $rencanaKembali) {
    if (empty($rencanaKembali) || $rencanaKembali === '-') {
        return $dbStatus;
    }

    // Determine if we should check due status.
    //
    // PRIORITY RULE (WAJIB):
    //   "Sebagian Dikembalikan" is intentionally EXCLUDED from $isActive.
    //   When some items have already been returned, the TABLE aggregate status
    //   MUST remain "Sebagian Dikembalikan" regardless of how close the remaining
    //   items are to their due date.  The per-unit MODAL will still display the
    //   individual "Due In X Days" for each unreturned unit via get_detail_units.php.
    //
    //   All other active-borrow statuses ("Sedang Dipinjam", "Proses Return",
    //   existing "Due*" or "Overdue") CAN be overridden by due-proximity logic.
    $isActive = ($dbStatus === 'Sedang Dipinjam' || $dbStatus === 'Overdue'
                 || $dbStatus === 'Proses Return'
                 || strpos($dbStatus, 'Due') === 0);
    if (!$isActive) {
        return $dbStatus;
    }

    // Parse tanggal — support Y-m-d, Y-m-d H:i:s, dan d/m/Y
    $tz = new DateTimeZone('Asia/Jakarta');
    $today = new DateTime('today', $tz);

    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $rencanaKembali)) {
        $returnDate = DateTime::createFromFormat('Y-m-d', substr($rencanaKembali, 0, 10), $tz);
    } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $rencanaKembali)) {
        $returnDate = DateTime::createFromFormat('d/m/Y', $rencanaKembali, $tz);
    } else {
        return $dbStatus;
    }

    if (!$returnDate) {
        return $dbStatus;
    }

    $returnDate->setTime(0, 0, 0);
    $today->setTime(0, 0, 0);

    // Hitung days_remaining = expected_return - today (dalam hari, tanpa jam)
    $diff = $today->diff($returnDate);
    $daysRemaining = (int) $diff->format('%r%a');

    // ==========================================
    // PRIORITY LOGIC: Due status overrides active-borrow statuses
    // NOTE: "Sebagian Dikembalikan" is NOT in $isActive so it will never reach
    //       here.  Only "Sedang Dipinjam", "Proses Return", and existing "Due*"
    //       statuses are resolved by remaining-days proximity below.
    // ==========================================
    
    if ($daysRemaining < 0) {
        return 'Overdue';
    } elseif ($daysRemaining === 0) {
        return 'Due Today';
    } elseif ($daysRemaining === 1) {
        return 'Due In 1 Day';
    } elseif ($daysRemaining >= 2 && $daysRemaining <= 7) {
        return 'Due In ' . $daysRemaining . ' Days';
    } else {
        // Only if daysRemaining > 7, keep original DB status
        return $dbStatus;
    }
}

/**
 * Get the nearest expected_return for a peminjaman.
 *
 * Uses peminjaman_units table (per-unit, database-only) as the single source of truth.
 * Returns the minimum expected_return among units that are NOT yet returned.
 *
 * @param mysqli $conn
 * @param int    $peminjaman_id
 * @return string|null  Date in Y-m-d format, or null when everything returned.
 */
function getNearestExpectedReturn(&$conn, $peminjaman_id) {
    // Query the minimum expected_return from peminjaman_units
    // Only for units NOT yet returned (return_status NOT IN Dikembalikan/Rusak/Ditolak)
    $stmt = $conn->prepare("
        SELECT MIN(pu.expected_return) AS nearest_return
        FROM peminjaman_units pu
        WHERE pu.peminjaman_id = ?
          AND pu.expected_return IS NOT NULL
          AND pu.return_status NOT IN ('Dikembalikan', 'Rusak', 'Ditolak')
    ");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && $row['nearest_return']) {
        return $row['nearest_return'];
    }

    // Fallback: if peminjaman_units has no unreturned rows, try detail_peminjaman
    $stmt = $conn->prepare("
        SELECT MIN(d.expected_return) AS nearest_return
        FROM detail_peminjaman d
        WHERE d.peminjaman_id = ? AND d.expected_return IS NOT NULL
    ");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row && $row['nearest_return']) {
        return $row['nearest_return'];
    }

    // Final fallback: use peminjaman.rencana_kembali
    $stmt = $conn->prepare("SELECT rencana_kembali FROM peminjaman WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    return $p['rencana_kembali'] ?? null;
}

/**
 * Compute per-unit-driven status for a peminjaman from peminjaman_units table.
 * Returns the PRIORITY status based on all unit expected_returns and return_statuses.
 *
 * Priority: Overdue > Due Today > Due In X Days (smallest X) > Sebagian Dikembalikan > Sedang Dipinjam > Dikembalikan
 *
 * @param mysqli $conn
 * @param int    $peminjaman_id
 * @param string $dbStatus  Current database status from peminjaman table
 * @return string  Computed dynamic status
 */
function computeStatusFromUnits(&$conn, $peminjaman_id, $dbStatus) {
    // Final statuses that must NEVER be overridden
    if (in_array($dbStatus, ['Ditolak', 'Menunggu Persetujuan', 'Disetujui'])) {
        return $dbStatus;
    }

    // Get all units for this peminjaman from database
    $stmt = $conn->prepare("
        SELECT pu.return_status, pu.expected_return
        FROM peminjaman_units pu
        WHERE pu.peminjaman_id = ?
    ");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    $units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // If no units in table, fall back to original status
    if (empty($units)) {
        return $dbStatus;
    }

    $tz = new DateTimeZone('Asia/Jakarta');
    $today = new DateTime('today', $tz);
    $today->setTime(0, 0, 0);

    $total_units = count($units);
    $total_returned = 0;
    $has_overdue = false;
    $min_due_days = PHP_INT_MAX;
    $has_proses_return = false;

    foreach ($units as $unit) {
        $rs = $unit['return_status'];
        $expectedRaw = $unit['expected_return'];

        // Count returned units
        if (in_array($rs, ['Dikembalikan', 'Rusak'])) {
            $total_returned++;
            continue;
        }

        // Track proses return
        if ($rs === 'Proses Return') {
            $has_proses_return = true;
        }

        // For active units, compute due proximity from per-unit expected_return
        if ($expectedRaw && !in_array($rs, ['Ditolak', 'Menunggu Persetujuan', 'Disetujui'])) {
            $retDate = false;
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $expectedRaw)) {
                $retDate = DateTime::createFromFormat('Y-m-d', substr($expectedRaw, 0, 10), $tz);
            }
            if ($retDate) {
                $retDate->setTime(0, 0, 0);
                $diffDays = (int)$today->diff($retDate)->format('%r%a');
                if ($diffDays < 0) {
                    $has_overdue = true;
                } elseif ($diffDays >= 0 && $diffDays <= 7) {
                    if ($diffDays < $min_due_days) {
                        $min_due_days = $diffDays;
                    }
                }
            }
        }
    }

    // PRIORITY 6: All units returned
    if ($total_returned >= $total_units && $total_units > 0) {
        return 'Dikembalikan';
    }

    // PRIORITY 1: Overdue
    if ($has_overdue) {
        return 'Overdue';
    }

    // PRIORITY 2: Due Today
    if ($min_due_days === 0) {
        return 'Due Today';
    }

    // PRIORITY 3: Due In X Days
    if ($min_due_days !== PHP_INT_MAX && $min_due_days >= 1 && $min_due_days <= 7) {
        if ($min_due_days === 1) {
            return 'Due In 1 Day';
        }
        return 'Due In ' . $min_due_days . ' Days';
    }

    // PRIORITY 4: Sebagian Dikembalikan
    if ($total_returned > 0 && $total_returned < $total_units) {
        return 'Sebagian Dikembalikan';
    }

    // PRIORITY 5: Sedang Dipinjam or Proses Return
    if ($has_proses_return) {
        return 'Proses Return';
    }

    return 'Sedang Dipinjam';
}
