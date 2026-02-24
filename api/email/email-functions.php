<?php
/**
 * ============================================================
 * EMAIL FUNCTIONS - Fungsi Reusable untuk Pengiriman Email
 * ============================================================
 * 
 * File   : /PROJECT/api/email/email-functions.php
 * 
 * Cara pakai:
 *   require_once __DIR__ . '/email-functions.php';
 *   $result = sendEmail($emailFromDB, 'Subject', '<h1>Body HTML</h1>');
 * 
 * ============================================================
 */

// Load konfigurasi email
require_once __DIR__ . '/../../config/email.php';

// Load PHPMailer
require_once __DIR__ . '/../../phpmailer/src/Exception.php';
require_once __DIR__ . '/../../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Kirim email menggunakan PHPMailer + SMTP Gmail
 *
 * @param string $to         Email penerima
 * @param string $subject    Subject email
 * @param string $htmlBody   Isi email dalam format HTML
 * @param string $toName     Nama penerima (opsional)
 * @param string $plainBody  Isi email plain text fallback (opsional)
 * @return bool              true jika berhasil, false jika gagal
 */
function sendEmail($to, $subject, $htmlBody, $toName = '', $plainBody = '') {
    global $smtpConfig;

    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = $smtpConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpConfig['username'];
        $mail->Password   = $smtpConfig['password'];
        $mail->SMTPSecure = $smtpConfig['secure'];
        $mail->Port       = $smtpConfig['port'];
        $mail->CharSet    = 'UTF-8';

        // Sender
        $mail->setFrom($smtpConfig['username'], $smtpConfig['fromName']);

        // Recipient
        $mail->addAddress($to, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("[EMAIL ERROR] Gagal kirim ke {$to}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Buat template email HTML standar
 *
 * @param string $title      Judul di header email
 * @param string $bodyHtml   Konten body (HTML)
 * @return string            Full HTML email
 */
function buildEmailTemplate($title, $bodyHtml) {
    $year = date('Y');
    return '<!DOCTYPE html>
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
            .success-box {
                background: #d1fae5;
                border-left: 4px solid #10b981;
                padding: 14px 18px;
                border-radius: 6px;
                margin: 20px 0;
                font-size: 14px;
                color: #065f46;
            }
            .info-box {
                background: #dbeafe;
                border-left: 4px solid #3b82f6;
                padding: 14px 18px;
                border-radius: 6px;
                margin: 20px 0;
                font-size: 14px;
                color: #1e3a5f;
            }
            .warning-box {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 14px 18px;
                border-radius: 6px;
                margin: 20px 0;
                font-size: 14px;
                color: #92400e;
            }
            .footer { 
                background: #f9fafb; 
                padding: 18px 32px; 
                text-align: center; 
                font-size: 12px; 
                color: #9ca3af; 
                border-top: 1px solid #e5e7eb;
            }
            .auto-note {
                margin-top: 24px; 
                color: #6b7280; 
                font-size: 13px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . htmlspecialchars($title) . '</h1>
                <p>Komatsu Indonesia - Sistem Peminjaman</p>
            </div>
            <div class="body">
                ' . $bodyHtml . '
                <p class="auto-note">
                    <em>Email ini dikirim secara otomatis oleh sistem. Mohon tidak membalas email ini.</em>
                </p>
            </div>
            <div class="footer">
                &copy; ' . $year . ' ICT Komatsu Indonesia — Sistem Peminjaman Barang
            </div>
        </div>
    </body>
    </html>';
}

/**
 * ============================================================
 * HELPER FUNCTIONS: Ambil email DINAMIS dari database
 * Tidak ada hardcode email — semua dari tabel users
 * ============================================================
 */

/**
 * Ambil semua admin dari database (role = 'admin')
 *
 * @param mysqli $conn   Koneksi database
 * @return array         Array of ['nama' => ..., 'email' => ...]
 */
function getAdminEmails($conn) {
    $result = $conn->query("SELECT nama, email FROM users WHERE role = 'admin'");
    $admins = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $admins[] = $row;
            }
        }
    }
    return $admins;
}

/**
 * Ambil semua manager dari database (role = 'manager')
 *
 * @param mysqli $conn   Koneksi database
 * @return array         Array of ['nama' => ..., 'email' => ...]
 */
function getManagerEmails($conn) {
    $result = $conn->query("SELECT nama, email FROM users WHERE role = 'manager'");
    $managers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $managers[] = $row;
            }
        }
    }
    return $managers;
}

/**
 * Ambil semua PIC Barang dari database (role = 'pic_barang')
 *
 * @param mysqli $conn   Koneksi database
 * @return array         Array of ['nama' => ..., 'email' => ...]
 */
function getPicBarangEmails($conn) {
    $result = $conn->query("SELECT nama, email FROM users WHERE role = 'pic_barang'");
    $pics = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $pics[] = $row;
            }
        }
    }
    return $pics;
}

/**
 * Ambil email berdasarkan role tertentu
 *
 * @param mysqli $conn   Koneksi database
 * @param string $role   Role: 'admin', 'manager', 'pic_barang', 'user'
 * @return array         Array of ['nama' => ..., 'email' => ...]
 */
function getEmailsByRole($conn, $role) {
    $stmt = $conn->prepare("SELECT nama, email FROM users WHERE role = ?");
    if (!$stmt) return [];
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $users[] = $row;
        }
    }
    return $users;
}

/**
 * Kirim email ke semua user dengan role tertentu
 *
 * @param mysqli $conn      Koneksi database
 * @param string $role      Role target
 * @param string $subject   Subject email
 * @param string $htmlBody  Body HTML
 * @return int              Jumlah email berhasil dikirim
 */
function sendEmailToRole($conn, $role, $subject, $htmlBody) {
    $users = getEmailsByRole($conn, $role);
    $sent = 0;
    foreach ($users as $user) {
        if (sendEmail($user['email'], $subject, $htmlBody, $user['nama'])) {
            $sent++;
        }
    }
    return $sent;
}

/**
 * Helper: Ambil data peminjaman + user dari database
 *
 * @param mysqli $conn           Koneksi database
 * @param int    $peminjamanId   ID peminjaman
 * @return array|null            Data peminjaman + user, atau null jika tidak ditemukan
 */
function getPeminjamanWithUser($conn, $peminjamanId) {
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.kode_peminjaman,
            p.nama_peminjam,
            p.tanggal_pinjam,
            p.rencana_kembali,
            p.tanggal_kembali,
            p.status,
            p.catatan,
            u.email,
            u.nama AS nama_user
        FROM peminjaman p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");

    if (!$stmt) {
        error_log("[EMAIL ERROR] Prepare gagal: " . $conn->error);
        return null;
    }

    $stmt->bind_param('i', $peminjamanId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}
