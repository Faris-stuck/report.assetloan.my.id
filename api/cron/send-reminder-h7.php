<?php
/**
 * ============================================================
 * CRON: Kirim Email Pengingat H-7 Sebelum Tanggal Kembali
 * ============================================================
 * 
 * File   : /PROJECT/api/cron/send-reminder-h7.php
 * Akses  : http://localhost/PROJECT/api/cron/send-reminder-h7.php
 * 
 * Cron job (setiap hari jam 08:00):
 *   0 8 * * * /opt/lampp/bin/php /opt/lampp/htdocs/PROJECT/api/cron/send-reminder-h7.php >> /opt/lampp/htdocs/PROJECT/api/cron/reminder.log 2>&1
 * 
 * ============================================================
 */

// ============================================================
// 1. KONFIGURASI SMTP — dari config/email.php (TIDAK HARDCODE)
//    Semua email penerima diambil dari database
// ============================================================
require_once __DIR__ . '/../../config/email.php';

// ============================================================
// 2. OUTPUT HEADER (untuk akses via browser)
// ============================================================
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
}

echo "============================================================\n";
echo "  CRON: Email Pengingat H-7 Pengembalian Barang\n";
echo "  Waktu eksekusi: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n\n";

// ============================================================
// 3. KONEKSI DATABASE
// ============================================================

// Deteksi environment: CLI tidak punya HTTP_HOST
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
// 4. LOAD PHPMAILER & EMAIL FUNCTIONS
// ============================================================
require_once __DIR__ . '/../email/email-functions.php';

// ============================================================
// 5. QUERY: Ambil peminjaman H-7 sebelum tanggal kembali
//    - rencana_kembali = tanggal rencana kembali
//    - status = 'Sedang Dipinjam' (masih aktif dipinjam)
//    - JOIN ke tabel users untuk mendapatkan email & nama
// ============================================================
$sql = "
    SELECT 
        p.id,
        p.kode_peminjaman,
        p.nama_peminjam,
        p.rencana_kembali,
        p.tanggal_pinjam,
        u.email,
        u.nama AS nama_user
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    WHERE p.status = 'Sedang Dipinjam'
      AND DATEDIFF(p.rencana_kembali, CURDATE()) = 7
";

$result = $conn->query($sql);

if (!$result) {
    echo "[ERROR] Query gagal: " . $conn->error . "\n";
    exit(1);
}

$totalRows = $result->num_rows;
echo "[INFO] Ditemukan {$totalRows} peminjaman yang H-7 hari ini.\n\n";

if ($totalRows === 0) {
    echo "[INFO] Tidak ada email yang perlu dikirim hari ini.\n";
    echo "============================================================\n";
    exit(0);
}

// ============================================================
// 6. LOOP & KIRIM EMAIL
// ============================================================
$berhasil = 0;
$gagal    = 0;

while ($row = $result->fetch_assoc()) {
    $namaUser       = $row['nama_user'] ?: $row['nama_peminjam'];
    $emailUser      = $row['email'];
    $kodePeminjaman = $row['kode_peminjaman'];
    $tanggalPinjam  = date('d F Y', strtotime($row['tanggal_pinjam']));
    $tanggalKembali = date('d F Y', strtotime($row['rencana_kembali']));

    echo "-----------------------------------------------------------\n";
    echo "[PROSES] Kode: {$kodePeminjaman} | {$namaUser} | {$emailUser}\n";
    echo "         Pinjam: {$tanggalPinjam} → Kembali: {$tanggalKembali}\n";

    // Validasi email
    if (empty($emailUser) || !filter_var($emailUser, FILTER_VALIDATE_EMAIL)) {
        echo "[SKIP]   Email tidak valid: '{$emailUser}'\n\n";
        $gagal++;
        continue;
    }

    // ---------------------------------------------------------
    // Kirim menggunakan fungsi sendEmail() dari email-functions.php
    // (email penerima dari database, bukan hardcode)
    // ---------------------------------------------------------
    $subject  = 'Peringatan Pengembalian Barang - ' . $kodePeminjaman;
    $htmlBody = buildEmailBody($namaUser, $kodePeminjaman, $tanggalPinjam, $tanggalKembali);

    if (sendEmail($emailUser, $subject, $htmlBody, $namaUser, buildEmailPlainText($namaUser, $kodePeminjaman, $tanggalPinjam, $tanggalKembali))) {
        echo "[OK]     Email berhasil dikirim ke: {$emailUser}\n\n";
        $berhasil++;
    } else {
        echo "[GAGAL]  Email gagal dikirim ke: {$emailUser}\n\n";
        $gagal++;
    }
}

// ============================================================
// 7. SUMMARY
// ============================================================
echo "============================================================\n";
echo "  HASIL PENGIRIMAN\n";
echo "  Total peminjaman  : {$totalRows}\n";
echo "  Berhasil dikirim  : {$berhasil}\n";
echo "  Gagal / Dilewati  : {$gagal}\n";
echo "  Waktu selesai     : " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n";

$conn->close();
exit(0);


// ============================================================
// FUNGSI: Template Email HTML
// ============================================================
function buildEmailBody($nama, $kode, $tglPinjam, $tglKembali) {
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
                background: linear-gradient(135deg, #1e3a8a, #2563eb); 
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
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 14px 18px;
                border-radius: 6px;
                margin: 20px 0;
                font-size: 14px;
                color: #92400e;
            }
            .alert-box strong {
                color: #78350f;
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
                <h1>⚠️ Peringatan Pengembalian Barang</h1>
                <p>Komatsu Indonesia - Sistem Peminjaman</p>
            </div>
            <div class="body">
                <p>Halo <strong>' . htmlspecialchars($nama) . '</strong>,</p>
                
                <div class="alert-box">
                    <strong>Perhatian!</strong> Masa peminjaman barang Anda akan berakhir dalam <strong>7 hari</strong>.
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
// FUNGSI: Template Email Plain Text (fallback)
// ============================================================
function buildEmailPlainText($nama, $kode, $tglPinjam, $tglKembali) {
    return "Halo {$nama},

Masa peminjaman barang Anda akan berakhir pada tanggal {$tglKembali}.

Detail Peminjaman:
- Kode Peminjaman : {$kode}
- Tanggal Pinjam  : {$tglPinjam}
- Batas Kembali   : {$tglKembali}

Mohon segera melakukan pengembalian sebelum tanggal tersebut.

Terima kasih.

---
Email ini dikirim otomatis oleh Sistem Peminjaman Komatsu Indonesia.";
}
