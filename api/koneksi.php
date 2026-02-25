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
 * Uses detail_peminjaman.expected_return as the single source of truth.
 * Returns the minimum expected_return among detail rows that still have
 * items not yet fully returned.
 *
 * @param mysqli $conn
 * @param int    $peminjaman_id
 * @return string|null  Date in Y-m-d format, or null when everything returned.
 */
function getNearestExpectedReturn(&$conn, $peminjaman_id) {
    // Query the minimum expected_return from detail_peminjaman, considering
    // only rows where not all units have been returned yet.
    $stmt = $conn->prepare("
        SELECT MIN(d.expected_return) AS nearest_return
        FROM detail_peminjaman d
        WHERE d.peminjaman_id = ?
          AND d.expected_return IS NOT NULL
          AND d.jumlah > COALESCE((
              SELECT SUM(dp.jumlah_kembali)
              FROM detail_pengembalian dp
              JOIN pengembalian pen ON dp.pengembalian_id = pen.id
              WHERE pen.peminjaman_id = ?
                AND dp.barang_id = d.barang_id
                AND pen.status = 'Selesai'
          ), 0)
    ");
    $stmt->bind_param("ii", $peminjaman_id, $peminjaman_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && $row['nearest_return']) {
        return $row['nearest_return'];
    }

    // Fall back: if no detail rows have expected_return yet (old rows before migration),
    // use peminjaman.rencana_kembali.
    $stmt = $conn->prepare("SELECT rencana_kembali FROM peminjaman WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $peminjaman_id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    return $p['rencana_kembali'] ?? null;
}
