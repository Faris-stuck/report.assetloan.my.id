<?php
/**
 * ============================================================
 * EMAIL REMINDER: Send Daily Reminder D-7 to D-0
 * ============================================================
 * 
 * File   : /PROJECT/api/cron/send-reminder-h7.php
 * Access : http://localhost/PROJECT/api/cron/send-reminder-h7.php
 * 
 * How it works:
 *   - Opened via browser (not a cron job)
 *   - Check borrowings with rencana_kembali between D-7 to D-0
 *   - Send email reminder once per day per borrowing
 *   - Do not resend if page is refreshed on the same day
 *   - Uses the last_reminder_date column for tracking
 * 
 * ============================================================
 */

// ============================================================
// 1. SMTP CONFIGURATION — from config/email.php (NOT HARDCODED)
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
echo "  REMINDER: Item Return Reminder Email (D-7 to D-0)\n";
echo "  Execution time: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n\n";

// ============================================================
// 3. DATABASE CONNECTION
// ============================================================
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

require_once __DIR__ . '/../koneksi.php';

if ($conn->connect_error) {
    echo "[ERROR] Database connection failed: " . $conn->connect_error . "\n";
    exit(1);
}
echo "[OK] Database connection successful.\n";
echo "[INFO] Today's date: " . date('Y-m-d') . "\n\n";

// ============================================================
// 4. LOAD PHPMAILER & EMAIL FUNCTIONS
// ============================================================
require_once __DIR__ . '/../email/email-functions.php';

// ============================================================
// 5. QUERY: Fetch borrowings D-7 to D-0
//    - DATEDIFF(rencana_kembali, CURDATE()) BETWEEN 0 AND 7
//    - last_reminder_date IS NULL OR != CURDATE() (prevent duplicates)
//    - Active status (Sedang Dipinjam / Due* / Overdue)
//    - JOIN users for email & name
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
    echo "[ERROR] Query failed: " . $conn->error . "\n";
    exit(1);
}

$totalRows = $result->num_rows;
echo "[INFO] Found {$totalRows} borrowings that need reminders.\n\n";

// ============================================================
// Also check how many were already sent today (info only)
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
    echo "[INFO] {$sudahDikirim} borrowings already sent reminders today (skipped).\n\n";
}

if ($totalRows === 0) {
    echo "[INFO] No emails need to be sent at this time.\n";
    if (php_sapi_name() !== 'cli') echo '</pre>';
    echo "\n============================================================\n";
    $conn->close();
    exit(0);
}

// ============================================================
// 6. FETCH ADMIN + PIC_BARANG LIST (once only, used for all borrowings)
// ============================================================
$adminList = getAdminEmails($conn);
$picList   = getPicBarangEmails($conn);

echo "[INFO] Admins found: " . count($adminList) . " people\n";
echo "[INFO] PIC Items found: " . count($picList) . " people\n\n";

// ============================================================
// 7. LOOP & SEND EMAIL TO ALL PARTIES (USER + ADMIN + PIC)
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

    // ============================================================
    // COLLECT ALL RECIPIENTS INTO ARRAY
    // ============================================================
    $recipients = [];

    // 1. USER (borrowing owner)
    if (!empty($emailUser) && filter_var($emailUser, FILTER_VALIDATE_EMAIL)) {
        $recipients[] = ['email' => $emailUser, 'nama' => $namaUser];
    }

    // 2. ALL ADMINS
    foreach ($adminList as $admin) {
        $recipients[] = ['email' => $admin['email'], 'nama' => $admin['nama']];
    }

    // 3. ALL PIC_BARANG
    foreach ($picList as $pic) {
        $recipients[] = ['email' => $pic['email'], 'nama' => $pic['nama']];
    }

    // DEDUPLICATION
    $recipients = buildUniqueRecipients(...array_map(fn($r) => $r, $recipients));

    if (empty($recipients)) {
        echo "[SKIP]   No valid recipients for borrowing #{$peminjaman_id}\n\n";
        $gagal++;
        continue;
    }

    echo "         Recipients: " . count($recipients) . " people (user + admin + PIC)\n";

    // ---------------------------------------------------------
    // Send email to ALL recipients using LOOP
    // ---------------------------------------------------------
    $subject   = 'Item Return Reminder - ' . $kodePeminjaman;
    $htmlBody  = buildReminderEmailBody($namaUser, $kodePeminjaman, $tanggalPinjam, $tanggalKembali, $sisaHari);
    $plainBody = buildReminderEmailPlainText($namaUser, $kodePeminjaman, $tanggalPinjam, $tanggalKembali, $sisaHari);

    $sentCount = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $htmlBody, $r['nama'], $plainBody)) {
            error_log("[EMAIL] send-reminder-h7: EMAIL SENT TO: " . $r['email'] . " for {$kodePeminjaman}");
            echo "<span style='color: #a6e3a1;'>[OK]     Reminder sent to: {$r['email']}</span>\n";
            $sentCount++;
        } else {
            error_log("[EMAIL] send-reminder-h7: EMAIL FAILED TO: " . $r['email'] . " for {$kodePeminjaman}");
            echo "<span style='color: #f38ba8;'>[FAILED] Email failed to send to: {$r['email']}</span>\n";
        }
    }

    if ($sentCount > 0) {
        $berhasil++;

        // Update last_reminder_date to prevent resending today
        $stmtUpdate = $conn->prepare("UPDATE peminjaman SET last_reminder_date = CURDATE() WHERE id = ?");
        $stmtUpdate->bind_param("i", $peminjaman_id);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        echo "         last_reminder_date updated to: " . date('Y-m-d') . "\n";
        echo "         Total sent: {$sentCount}/" . count($recipients) . " recipients\n\n";
    } else {
        echo "<span style='color: #f38ba8;'>[FAILED] All emails failed for: {$kodePeminjaman}</span>\n\n";
        $gagal++;
    }
}

// ============================================================
// 7. SUMMARY
// ============================================================
echo "============================================================\n";
echo "  REMINDER SEND RESULTS\n";
echo "  Total to send         : {$totalRows}\n";
echo "  Successfully sent     : {$berhasil}\n";
echo "  Failed / Skipped      : {$gagal}\n";
echo "  Already sent today (prior): {$sudahDikirim}\n";
echo "  Completion time       : " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n";

if (php_sapi_name() !== 'cli') echo '</pre>';

$conn->close();
exit(0);


// ============================================================
// FUNCTION: HTML Email Template — dynamic based on remaining days
// ============================================================
function buildReminderEmailBody($nama, $kode, $tglPinjam, $tglKembali, $sisaHari) {
    // Dynamic message based on remaining days
    if ($sisaHari <= 0) {
        $pesanAlert = '<strong>⚠️ Warning!</strong> Your item borrowing period <strong>is due today</strong>. Please return immediately.';
        $alertBg    = '#fee2e2';
        $alertBorder = '#ef4444';
        $alertColor  = '#991b1b';
        $headerBg    = 'linear-gradient(135deg, #991b1b, #dc2626)';
        $headerTitle = '🚨 Item Return Due Today!';
    } elseif ($sisaHari === 1) {
        $pesanAlert = '<strong>⚠️ Warning!</strong> Your item borrowing period will end <strong>tomorrow</strong>.';
        $alertBg    = '#fef3c7';
        $alertBorder = '#f59e0b';
        $alertColor  = '#92400e';
        $headerBg    = 'linear-gradient(135deg, #92400e, #d97706)';
        $headerTitle = '⏰ Item Return Tomorrow!';
    } else {
        $pesanAlert = '<strong>Warning!</strong> Your item borrowing period will end in <strong>' . $sisaHari . ' days</strong>.';
        $alertBg    = '#fef3c7';
        $alertBorder = '#f59e0b';
        $alertColor  = '#92400e';
        $headerBg    = 'linear-gradient(135deg, #1e3a8a, #2563eb)';
        $headerTitle = '⚠️ Item Return Reminder';
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
                <p>Komatsu Indonesia - Borrowing System</p>
            </div>
            <div class="body">
                <p>Hello <strong>' . htmlspecialchars($nama) . '</strong>,</p>
                
                <div class="alert-box">
                    ' . $pesanAlert . '
                </div>

                <div class="sisa-hari">
                    ' . ($sisaHari <= 0 ? 'DUE TODAY' : $sisaHari . ' Days Remaining') . '
                </div>
                
                <p>Here are your borrowing details:</p>
                
                <table class="info-table">
                    <tr>
                        <td>Borrowing Code</td>
                        <td><strong>' . htmlspecialchars($kode) . '</strong></td>
                    </tr>
                    <tr>
                        <td>Borrow Date</td>
                        <td>' . htmlspecialchars($tglPinjam) . '</td>
                    </tr>
                    <tr>
                        <td>Return Deadline</td>
                        <td><strong style="color: #dc2626;">' . htmlspecialchars($tglKembali) . '</strong></td>
                    </tr>
                    <tr>
                        <td>Days Remaining</td>
                        <td><strong style="color: ' . ($sisaHari <= 1 ? '#dc2626' : '#2563eb') . ';">' . ($sisaHari <= 0 ? 'Today!' : $sisaHari . ' days') . '</strong></td>
                    </tr>
                </table>
                
                <p>Please return the items before the above date to avoid late returns.</p>
                
                <p>Thank you for your attention and cooperation.</p>
                
                <p style="margin-top: 24px; color: #6b7280; font-size: 13px;">
                    <em>This email is sent automatically by the system. If you have already returned the items, please ignore this email.</em>
                </p>
            </div>
            <div class="footer">
                &copy; ' . date('Y') . ' ICT Komatsu Indonesia — Item Borrowing System
            </div>
        </div>
    </body>
    </html>';
}


// ============================================================
// FUNCTION: Plain Text Email Template — dynamic based on remaining days
// ============================================================
function buildReminderEmailPlainText($nama, $kode, $tglPinjam, $tglKembali, $sisaHari) {
    $pesanSisa = $sisaHari <= 0 
        ? "Your item borrowing period is DUE TODAY." 
        : "Your item borrowing period will end in {$sisaHari} days (date {$tglKembali}).";

    return "Hello {$nama},

{$pesanSisa}

Borrowing Details:
- Borrowing Code  : {$kode}
- Borrow Date     : {$tglPinjam}
- Return Deadline  : {$tglKembali}
- Days Remaining   : " . ($sisaHari <= 0 ? 'TODAY!' : "{$sisaHari} days") . "

Please return the items before the above date.

Thank you.

---
This email is sent automatically by the Komatsu Indonesia Borrowing System.";
}
