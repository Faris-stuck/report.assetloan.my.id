<?php
/**
 * ============================================================
 * EMAIL REMINDER: Kirim Pengingat Harian H-7 sampai H-0
 * ============================================================
 * 
 * File   : /PROJECT/api/cron/send-reminder-h7.php
 * Akses  : http://localhost/PROJECT/api/cron/send-reminder-h7.php
 * 
 * Cara kerja:
 *   - Dibuka via browser (bukan cron job)
 *   - Cek peminjaman dengan rencana_kembali antara H-7 s/d H-0
 *   - Kirim email reminder 1x per hari per peminjaman
 *   - Tidak kirim ulang jika halaman di-refresh di hari yang sama
 *   - Menggunakan kolom last_reminder_date untuk tracking
 * 
 * ============================================================
 */

// ============================================================
// 1. KONFIGURASI SMTP — dari config/email.php (TIDAK HARDCODE)
// ============================================================
require_once __DIR__ . '/../../config/email.php';

// ============================================================
// 2. OUTPUT HEADER (untuk akses via browser)
// ============================================================
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font-family: Consolas, monospace; font-size: 14px; background: #1e1e2e; color: #cdd6f4; padding: 24px; border-radius: 8px; max-width: 900px; margin: 20px auto; line-height: 1.6;">';
}

echo "============================================================\n";
echo "  REMINDER: Email Pengingat Pengembalian Barang (H-7 s/d H-0)\n";
echo "  Waktu eksekusi: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n\n";

// ============================================================
// 3. KONEKSI DATABASE
// ============================================================
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

require_once __DIR__ . '/../koneksi.php';

if ($conn->connect_error) {
    echo "[ERROR] Koneksi database gagal: " . $conn->connect_error . "\n";
    exit(1);
}
echo "[OK] Koneksi database berhasil.\n";
echo "[INFO] Tanggal hari ini: " . date('Y-m-d') . "\n\n";

// ============================================================
// 4. LOAD PHPMAILER & EMAIL FUNCTIONS
// ============================================================
require_once __DIR__ . '/../email/email-functions.php';

// ============================================================
// 5. QUERY: Ambil peminjaman H-7 sampai H-0
//    - DATEDIFF(rencana_kembali, CURDATE()) BETWEEN 0 AND 7
//    - last_reminder_date IS NULL OR != CURDATE() (anti duplikasi)
//    - Status aktif (Sedang Dipinjam / Due* / Overdue)
//    - JOIN users untuk email & nama
// ============================================================
$sql = "
    SELECT 
        p.id,
        p.kode_peminjaman,
        p.nama_peminjam,
        p.rencana_kembali,
        p.tanggal_pinjam,
        p.status,
        p.last_reminder_date,
        DATEDIFF(p.rencana_kembali, CURDATE()) AS sisa_hari,
        u.email,
        u.nama AS nama_user
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    WHERE (p.status = 'Sedang Dipinjam' OR p.status LIKE 'Due%' OR p.status = 'Overdue')
      AND DATEDIFF(p.rencana_kembali, CURDATE()) BETWEEN 0 AND 7
      AND (p.last_reminder_date IS NULL OR p.last_reminder_date != CURDATE())
    ORDER BY p.rencana_kembali ASC
";

$result = $conn->query($sql);

if (!$result) {
    echo "[ERROR] Query gagal: " . $conn->error . "\n";
    exit(1);
}

$totalRows = $result->num_rows;
echo "[INFO] Ditemukan {$totalRows} peminjaman yang perlu diingatkan.\n\n";

// ============================================================
// Cek juga berapa yang sudah dikirim hari ini (info saja)
// ============================================================
$sqlSudah = "
    SELECT COUNT(*) AS cnt FROM peminjaman
    WHERE (status = 'Sedang Dipinjam' OR status LIKE 'Due%' OR status = 'Overdue')
      AND DATEDIFF(rencana_kembali, CURDATE()) BETWEEN 0 AND 7
      AND last_reminder_date = CURDATE()
";
$resSudah = $conn->query($sqlSudah);
$sudahDikirim = 0;
if ($resSudah && $rowSudah = $resSudah->fetch_assoc()) {
    $sudahDikirim = (int) $rowSudah['cnt'];
}
if ($sudahDikirim > 0) {
    echo "[INFO] {$sudahDikirim} peminjaman sudah dikirim reminder hari ini (dilewati).\n\n";
}

if ($totalRows === 0) {
    echo "[INFO] Tidak ada email yang perlu dikirim saat ini.\n";
    if (php_sapi_name() !== 'cli') echo '</pre>';
    echo "\n============================================================\n";
    $conn->close();
    exit(0);
}

// ============================================================
// 6. LOOP & KIRIM EMAIL
// ============================================================
$berhasil = 0;
$gagal    = 0;

while ($row = $result->fetch_assoc()) {
    $peminjaman_id  = $row['id'];
    $namaUser       = $row['nama_user'] ?: $row['nama_peminjam'];
    $emailUser      = $row['email'];
    $kodePeminjaman = $row['kode_peminjaman'];
    $tanggalPinjam  = date('d F Y', strtotime($row['tanggal_pinjam']));
    $tanggalKembali = date('d F Y', strtotime($row['rencana_kembali']));
    $sisaHari       = (int) $row['sisa_hari'];
    $statusPinjaman = $row['status'];

    echo "-----------------------------------------------------------\n";
    echo "[PROSES] Kode: {$kodePeminjaman} | {$namaUser} | {$emailUser}\n";
    echo "         Status: {$statusPinjaman} | Sisa: {$sisaHari} hari\n";
    echo "         Pinjam: {$tanggalPinjam} → Kembali: {$tanggalKembali}\n";

    // Validasi email
    if (empty($emailUser) || !filter_var($emailUser, FILTER_VALIDATE_EMAIL)) {
        echo "[SKIP]   Email tidak valid: '{$emailUser}'\n\n";
        $gagal++;
        continue;
    }

    // ---------------------------------------------------------
    // Kirim email menggunakan sendEmail() dari email-functions.php
    // Email penerima dari database, bukan hardcode
    // ---------------------------------------------------------
    $subject  = 'Pengingat Pengembalian Barang - ' . $kodePeminjaman;
    $htmlBody = buildReminderEmailBody($namaUser, $kodePeminjaman, $tanggalPinjam, $tanggalKembali, $sisaHari);
    $plainBody = buildReminderEmailPlainText($namaUser, $kodePeminjaman, $tanggalPinjam, $tanggalKembali, $sisaHari);

    if (sendEmail($emailUser, $subject, $htmlBody, $namaUser, $plainBody)) {
        echo "<span style='color: #a6e3a1;'>[OK]     Reminder terkirim ke: {$emailUser}</span>\n";
        $berhasil++;

        // Update last_reminder_date agar tidak kirim ulang hari ini
        $stmtUpdate = $conn->prepare("UPDATE peminjaman SET last_reminder_date = CURDATE() WHERE id = ?");
        $stmtUpdate->bind_param("i", $peminjaman_id);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        echo "         last_reminder_date diupdate ke: " . date('Y-m-d') . "\n\n";
    } else {
        echo "<span style='color: #f38ba8;'>[GAGAL]  Email gagal dikirim ke: {$emailUser}</span>\n\n";
        $gagal++;
    }
}

// ============================================================
// 7. SUMMARY
// ============================================================
echo "============================================================\n";
echo "  HASIL PENGIRIMAN REMINDER\n";
echo "  Total perlu dikirim  : {$totalRows}\n";
echo "  Berhasil dikirim     : {$berhasil}\n";
echo "  Gagal / Dilewati     : {$gagal}\n";
echo "  Sudah dikirim hari ini (sebelumnya): {$sudahDikirim}\n";
echo "  Waktu selesai        : " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n";

if (php_sapi_name() !== 'cli') echo '</pre>';

$conn->close();
exit(0);


// ============================================================
// FUNGSI: Template Email HTML — dinamis berdasarkan sisa hari
// ============================================================
function buildReminderEmailBody($nama, $kode, $tglPinjam, $tglKembali, $sisaHari) {
    // Pesan dinamis berdasarkan sisa hari
    if ($sisaHari <= 0) {
        $pesanAlert = '<strong>⚠️ Perhatian!</strong> Masa peminjaman barang Anda <strong>sudah jatuh tempo hari ini</strong>. Mohon segera kembalikan.';
        $alertBg    = '#fee2e2';
        $alertBorder = '#ef4444';
        $alertColor  = '#991b1b';
        $headerBg    = 'linear-gradient(135deg, #991b1b, #dc2626)';
        $headerTitle = '🚨 Pengembalian Barang Jatuh Tempo!';
    } elseif ($sisaHari === 1) {
        $pesanAlert = '<strong>⚠️ Perhatian!</strong> Masa peminjaman barang Anda akan berakhir <strong>besok</strong>.';
        $alertBg    = '#fef3c7';
        $alertBorder = '#f59e0b';
        $alertColor  = '#92400e';
        $headerBg    = 'linear-gradient(135deg, #92400e, #d97706)';
        $headerTitle = '⏰ Pengembalian Barang Besok!';
    } else {
        $pesanAlert = '<strong>Perhatian!</strong> Masa peminjaman barang Anda akan berakhir dalam <strong>' . $sisaHari . ' hari</strong>.';
        $alertBg    = '#fef3c7';
        $alertBorder = '#f59e0b';
        $alertColor  = '#92400e';
        $headerBg    = 'linear-gradient(135deg, #1e3a8a, #2563eb)';
        $headerTitle = '⚠️ Pengingat Pengembalian Barang';
    }

    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { 
                font-family: "Segoe UI", Arial, sans-serif; 
                background: #f4f6f8; 
                margin: 0; 
                padding: 0; 
            }
            .container { 
                max-width: 600px; 
                margin: 30px auto; 
                background: #ffffff; 
                border-radius: 10px; 
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            }
            .header { 
                background: ' . $headerBg . '; 
                color: #ffffff; 
                padding: 28px 32px; 
                text-align: center;
            }
            .header h1 { 
                margin: 0; 
                font-size: 20px; 
                font-weight: 600; 
            }
            .header p {
                margin: 6px 0 0 0;
                font-size: 13px;
                opacity: 0.85;
            }
            .body { 
                padding: 32px; 
                color: #1f2937; 
                line-height: 1.7; 
                font-size: 15px;
            }
            .alert-box {
                background: ' . $alertBg . ';
                border-left: 4px solid ' . $alertBorder . ';
                padding: 14px 18px;
                border-radius: 6px;
                margin: 20px 0;
                font-size: 14px;
                color: ' . $alertColor . ';
            }
            .info-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .info-table td {
                padding: 10px 14px;
                font-size: 14px;
                border-bottom: 1px solid #e5e7eb;
            }
            .info-table td:first-child {
                font-weight: 600;
                color: #374151;
                width: 45%;
            }
            .info-table td:last-child {
                color: #1f2937;
            }
            .sisa-hari {
                text-align: center;
                padding: 16px;
                margin: 16px 0;
                border-radius: 8px;
                background: ' . ($sisaHari <= 1 ? '#fee2e2' : '#dbeafe') . ';
                color: ' . ($sisaHari <= 1 ? '#991b1b' : '#1e3a8a') . ';
                font-size: 24px;
                font-weight: 700;
            }
            .footer { 
                background: #f9fafb; 
                padding: 18px 32px; 
                text-align: center; 
                font-size: 12px; 
                color: #9ca3af; 
                border-top: 1px solid #e5e7eb;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . $headerTitle . '</h1>
                <p>Komatsu Indonesia - Sistem Peminjaman</p>
            </div>
            <div class="body">
                <p>Halo <strong>' . htmlspecialchars($nama) . '</strong>,</p>
                
                <div class="alert-box">
                    ' . $pesanAlert . '
                </div>

                <div class="sisa-hari">
                    ' . ($sisaHari <= 0 ? 'JATUH TEMPO HARI INI' : 'Sisa ' . $sisaHari . ' Hari') . '
                </div>
                
                <p>Berikut adalah detail peminjaman Anda:</p>
                
                <table class="info-table">
                    <tr>
                        <td>Kode Peminjaman</td>
                        <td><strong>' . htmlspecialchars($kode) . '</strong></td>
                    </tr>
                    <tr>
                        <td>Tanggal Pinjam</td>
                        <td>' . htmlspecialchars($tglPinjam) . '</td>
                    </tr>
                    <tr>
                        <td>Batas Pengembalian</td>
                        <td><strong style="color: #dc2626;">' . htmlspecialchars($tglKembali) . '</strong></td>
                    </tr>
                    <tr>
                        <td>Sisa Hari</td>
                        <td><strong style="color: ' . ($sisaHari <= 1 ? '#dc2626' : '#2563eb') . ';">' . ($sisaHari <= 0 ? 'Hari ini!' : $sisaHari . ' hari') . '</strong></td>
                    </tr>
                </table>
                
                <p>Mohon segera melakukan pengembalian barang sebelum tanggal tersebut untuk menghindari keterlambatan.</p>
                
                <p>Terima kasih atas perhatian dan kerjasamanya.</p>
                
                <p style="margin-top: 24px; color: #6b7280; font-size: 13px;">
                    <em>Email ini dikirim secara otomatis oleh sistem. Jika Anda sudah mengembalikan barang, mohon abaikan email ini.</em>
                </p>
            </div>
            <div class="footer">
                &copy; ' . date('Y') . ' ICT Komatsu Indonesia — Sistem Peminjaman Barang
            </div>
        </div>
    </body>
    </html>';
}


// ============================================================
// FUNGSI: Template Email Plain Text — dinamis berdasarkan sisa hari
// ============================================================
function buildReminderEmailPlainText($nama, $kode, $tglPinjam, $tglKembali, $sisaHari) {
    $pesanSisa = $sisaHari <= 0 
        ? "Masa peminjaman barang Anda SUDAH JATUH TEMPO hari ini." 
        : "Masa peminjaman barang Anda akan berakhir dalam {$sisaHari} hari (tanggal {$tglKembali}).";

    return "Halo {$nama},

{$pesanSisa}

Detail Peminjaman:
- Kode Peminjaman : {$kode}
- Tanggal Pinjam  : {$tglPinjam}
- Batas Kembali   : {$tglKembali}
- Sisa Hari       : " . ($sisaHari <= 0 ? 'HARI INI!' : "{$sisaHari} hari") . "

Mohon segera melakukan pengembalian sebelum tanggal tersebut.

Terima kasih.

---
Email ini dikirim otomatis oleh Sistem Peminjaman Komatsu Indonesia.";
}
