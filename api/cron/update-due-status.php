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
 *   - Status aktif (Sedang Dipinjam / Due% / Overdue / Sebagian Dikembalikan / Proses Return)
 *     di-update berdasarkan sisa hari dari NEAREST expected return (mempertimbangkan extend):
 *     > 7 hari  → Tetap status asal (Sedang Dipinjam / Sebagian Dikembalikan / Proses Return)
 *     2-7 hari  → Due In X Days
 *     1 hari    → Due In 1 Day
 *     0 hari    → Due Today
 *     < 0 hari  → Overdue
 *   - Status non-aktif (Dikembalikan, Ditolak, dll) TIDAK diubah.
 *   - Menggunakan getNearestExpectedReturn() untuk akurasi dengan extend per-unit.
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
//                   OR 'Sebagian Dikembalikan' OR 'Proses Return'
//    Menggunakan getNearestExpectedReturn() agar extend diperhitungkan
// ============================================================
echo "--- Update status dinamis berdasarkan sisa hari (nearest expected return) ---\n";

$sql_active = "
    SELECT id, kode_peminjaman, status, rencana_kembali
    FROM peminjaman
    WHERE status = 'Sedang Dipinjam' 
       OR status LIKE 'Due%' 
       OR status = 'Overdue'
       OR status = 'Sebagian Dikembalikan'
       OR status = 'Proses Return'
    ORDER BY rencana_kembali ASC
";

$active_result = $conn->query($sql_active);
$affected = 0;

if ($active_result && $active_result->num_rows > 0) {
    $update_stmt = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id = ?");

    while ($row = $active_result->fetch_assoc()) {
        $nearest = getNearestExpectedReturn($conn, $row['id']);
        $effectiveDate = $nearest ?? $row['rencana_kembali'];
        $newStatus = computeDueStatus($row['status'], $effectiveDate);

        if ($newStatus !== $row['status']) {
            $update_stmt->bind_param('si', $newStatus, $row['id']);
            $update_stmt->execute();
            if ($update_stmt->affected_rows > 0) {
                $affected++;
                echo "  [{$row['kode_peminjaman']}] {$row['status']} → {$newStatus} (return: {$effectiveDate})\n";
            }
        }
    }
    $update_stmt->close();
    echo "\n[OK] {$affected} peminjaman status diperbarui.\n\n";
} else {
    echo "[OK] Tidak ada peminjaman aktif untuk diupdate.\n\n";
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
       OR status = 'Sebagian Dikembalikan' OR status = 'Proses Return'
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
