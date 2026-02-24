<?php
/**
 * ============================================================
 * CRON: Auto-Update Status Dinamis Pengembalian
 * ============================================================
 * 
 * File   : /PROJECT/api/cron/update-due-status.php
 * Akses  : http://localhost/PROJECT/api/cron/update-due-status.php
 * 
 * Logic:
 *   - Status aktif (Sedang Dipinjam / Due% / Overdue) di-update berdasarkan sisa hari:
 *     > 7 hari  → Sedang Dipinjam
 *     2-7 hari  → Due in X Days
 *     1 hari    → Due Tomorrow
 *     0 hari    → Due Today
 *     < 0 hari  → Overdue
 *   - Status non-aktif (Dikembalikan, Ditolak, dll) TIDAK diubah.
 * 
 * Cron job (setiap hari jam 00:05):
 *   5 0 * * * /opt/lampp/bin/php /opt/lampp/htdocs/PROJECT/api/cron/update-due-status.php >> /opt/lampp/htdocs/PROJECT/api/cron/due-status.log 2>&1
 * 
 * ============================================================
 */

// ============================================================
// 1. OUTPUT HEADER
// ============================================================
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}

echo "============================================================\n";
echo "  CRON: Auto-Update Status Dinamis Pengembalian\n";
echo "  Waktu eksekusi: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n\n";

// ============================================================
// 2. KONEKSI DATABASE
// ============================================================
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

require_once __DIR__ . '/../koneksi.php';

if ($conn->connect_error) {
    echo "[ERROR] Koneksi database gagal: " . $conn->connect_error . "\n";
    exit(1);
}
echo "[OK] Koneksi database berhasil.\n\n";

// ============================================================
// 3. UPDATE DINAMIS: Semua peminjaman aktif
//    Status aktif = 'Sedang Dipinjam' OR LIKE 'Due%' OR 'Overdue'
// ============================================================
echo "--- Update status dinamis berdasarkan sisa hari ---\n";

$sql_update = "
    UPDATE peminjaman 
    SET status = CASE
        WHEN DATEDIFF(rencana_kembali, CURDATE()) > 7
            THEN 'Sedang Dipinjam'
        WHEN DATEDIFF(rencana_kembali, CURDATE()) > 1
            THEN CONCAT('Due in ', DATEDIFF(rencana_kembali, CURDATE()), ' Days')
        WHEN DATEDIFF(rencana_kembali, CURDATE()) = 1
            THEN 'Due Tomorrow'
        WHEN DATEDIFF(rencana_kembali, CURDATE()) = 0
            THEN 'Due Today'
        ELSE 'Overdue'
    END
    WHERE (status = 'Sedang Dipinjam' OR status LIKE 'Due%' OR status = 'Overdue')
";

$result = $conn->query($sql_update);

if ($result) {
    $affected = $conn->affected_rows;
    echo "[OK] {$affected} peminjaman status diperbarui.\n\n";
} else {
    echo "[ERROR] Gagal update: " . $conn->error . "\n\n";
}

// ============================================================
// 4. TAMPILKAN DETAIL STATUS SAAT INI
// ============================================================
echo "--- Detail status peminjaman aktif ---\n";

$sql_detail = "
    SELECT id, kode_peminjaman, status, rencana_kembali, 
           DATEDIFF(rencana_kembali, CURDATE()) as sisa_hari
    FROM peminjaman 
    WHERE status = 'Sedang Dipinjam' OR status LIKE 'Due%' OR status = 'Overdue'
    ORDER BY rencana_kembali ASC
";

$detail = $conn->query($sql_detail);
if ($detail && $detail->num_rows > 0) {
    while ($row = $detail->fetch_assoc()) {
        echo "  [{$row['kode_peminjaman']}] Status: {$row['status']} | Kembali: {$row['rencana_kembali']} | Sisa: {$row['sisa_hari']} hari\n";
    }
} else {
    echo "  Tidak ada peminjaman aktif.\n";
}

// ============================================================
// 5. SUMMARY
// ============================================================
echo "\n============================================================\n";
echo "  SUMMARY:\n";
echo "  - Total status diperbarui: " . ($affected ?? 0) . "\n";
echo "  - Selesai: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n";

$conn->close();
