<?php
/**
 * Database Connection Bridge
 * Requires centralized config/database.php
 */

// Include centralized database configuration
require_once __DIR__ . '/../config/database.php';

// ============================================================
// HELPER: Calculate due status in REAL-TIME from expected return date
// ============================================================
/**
 * Calculate loan due status in real-time.
 * Only override status if current status is "active borrowing"
 * (Borrowed / Due% / Overdue). Non-active statuses are NOT changed.
 *
 * @param string $dbStatus        Status from database
 * @param string $rencanaKembali  Expected return date (Y-m-d, Y-m-d H:i:s, or d/m/Y)
 * @return string Real-time status based on day difference
 */
function computeDueStatus($dbStatus, $rencanaKembali) {
    if (empty($rencanaKembali) || $rencanaKembali === '-') {
        return $dbStatus;
    }

    // Only final/inactive statuses are NOT overridden by due-date proximity.
    // All other statuses (Partial Approved, Approved, Partially Returned,
    // Borrowed, Return in Process, existing Due*, Overdue) ARE overridden.
    $isNotOverridable = in_array($dbStatus, [
        'Waiting for Approval', 'Rejected', 'Returned',
        'Fully Damaged', 'Partially Damaged', 'Completed'
    ]);
    if ($isNotOverridable) {
        return $dbStatus;
    }

    // Parse date — support Y-m-d, Y-m-d H:i:s, and d/m/Y
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

    // Calculate days_remaining = expected_return - today (in days, without time)
    $diff = $today->diff($returnDate);
    $daysRemaining = (int) $diff->format('%r%a');

    // PRIORITY LOGIC: Due status overrides all non-final statuses.
    // Cap at 7 days — beyond 7 days, keep original DB status.
    if ($daysRemaining < 0) {
        return 'Overdue';
    } elseif ($daysRemaining === 0) {
        return 'Due Today';
    } elseif ($daysRemaining === 1) {
        return 'Due In 1 Day';
    } elseif ($daysRemaining >= 2 && $daysRemaining <= 7) {
        return 'Due In ' . $daysRemaining . ' Days';
    }

    // > 7 days: keep original DB status (e.g. Partial Approved, Approved, etc.)
    return $dbStatus;
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
    // Only for units NOT yet returned (return_status NOT IN Returned/Damaged/Rejected)
    $stmt = $conn->prepare("
        SELECT MIN(pu.expected_return) AS nearest_return
        FROM peminjaman_units pu
        WHERE pu.peminjaman_id = ?
          AND pu.expected_return IS NOT NULL
          AND pu.return_status NOT IN ('Returned', 'Damaged', 'Rejected')
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
 * Priority: Overdue > Due Today > Due In X Days (smallest X) > Partially Returned > Borrowed > Returned
 *
 * @param mysqli $conn
 * @param int    $peminjaman_id
 * @param string $dbStatus  Current database status from peminjaman table
 * @return string  Computed dynamic status
 */
function computeStatusFromUnits(&$conn, $peminjaman_id, $dbStatus) {
    // Final statuses that must NEVER be overridden
    if (in_array($dbStatus, ['Rejected', 'Waiting for Approval', 'Returned'])) {
        return $dbStatus;
    }

    // Get all units for this peminjaman from database
    $stmt = $conn->prepare("
        SELECT pu.return_status, pu.expected_return
        FROM peminjaman_units pu
        WHERE pu.peminjaman_id = ?
          AND pu.return_status != 'Rejected'
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
        if (in_array($rs, ['Returned', 'Damaged'])) {
            $total_returned++;
            continue;
        }

        // Track return process
        if ($rs === 'Return in Process') {
            $has_proses_return = true;
        }

        // For active units, compute due proximity from per-unit expected_return
        if ($expectedRaw && !in_array($rs, ['Rejected', 'Waiting for Approval', 'Approved'])) {
            $retDate = false;
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $expectedRaw)) {
                $retDate = DateTime::createFromFormat('Y-m-d', substr($expectedRaw, 0, 10), $tz);
            }
            if ($retDate) {
                $retDate->setTime(0, 0, 0);
                $diffDays = (int)$today->diff($retDate)->format('%r%a');
                if ($diffDays < 0) {
                    $has_overdue = true;
                } elseif ($diffDays >= 0) {
                    if ($diffDays < $min_due_days) {
                        $min_due_days = $diffDays;
                    }
                }
            }
        }
    }

    // PRIORITY 6: All units returned
    if ($total_returned >= $total_units && $total_units > 0) {
        return 'Returned';
    }

    // PRIORITY 1: Overdue
    if ($has_overdue) {
        return 'Overdue';
    }

    // PRIORITY 2: Due Today
    if ($min_due_days === 0) {
        return 'Due Today';
    }

    // PRIORITY 3: Due In X Days (capped at 7 days)
    if ($min_due_days !== PHP_INT_MAX && $min_due_days >= 1 && $min_due_days <= 7) {
        if ($min_due_days === 1) {
            return 'Due In 1 Day';
        }
        return 'Due In ' . $min_due_days . ' Days';
    }

    // > 7 days: keep original DB status (e.g. Partial Approved, Approved, etc.)
    if ($min_due_days > 7) {
        return $dbStatus;
    }

    // PRIORITY 4: Partially Returned
    if ($total_returned > 0 && $total_returned < $total_units) {
        return 'Partially Returned';
    }

    // PRIORITY 5: Borrowed or Return in Process
    if ($has_proses_return) {
        return 'Return in Process';
    }

    return 'Borrowed';
}

/**
 * Auto-update due status for all active borrowings.
 * Silent — no output. Called on every koneksi.php include.
 * Uses computeDueStatus() and getNearestExpectedReturn() already defined above.
 *
 * @param mysqli $conn
 */
function autoUpdateDueStatus(&$conn) {
    try {
        $sql = "
            SELECT id, status, rencana_kembali
            FROM peminjaman
            WHERE status = 'Borrowed'
               OR status LIKE 'Due%'
               OR status = 'Overdue'
               OR status = 'Partially Returned'
               OR status = 'Return in Process'
               OR status = 'Partial Approved'
               OR status = 'Approved'
        ";
        $result = $conn->query($sql);
        if (!$result || $result->num_rows === 0) return;

        $stmt = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id = ?");
        if (!$stmt) return;

        while ($row = $result->fetch_assoc()) {
            $nearest = getNearestExpectedReturn($conn, $row['id']);
            $effectiveDate = $nearest ?? $row['rencana_kembali'];
            $newStatus = computeDueStatus($row['status'], $effectiveDate);

            if ($newStatus !== $row['status']) {
                $stmt->bind_param('si', $newStatus, $row['id']);
                $stmt->execute();
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Silent — no output
    }
}

// Auto-update due statuses on every page load
autoUpdateDueStatus($conn);
