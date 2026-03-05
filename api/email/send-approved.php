<?php
/**
 * ============================================================
 * EMAIL: Loan Approved (Status → Approved)
 * ============================================================
 * 
 * Email sent to ALL related parties:
 *   - USER (loan owner)
 *   - ADMIN (all admins)
 *   - PIC_BARANG (all PICs)
 *   - ACTOR (from SESSION)
 * 
 * File   : /PROJECT/api/email/send-approved.php
 * 
 * How to call after status update:
 *   require_once __DIR__ . '/../email/send-approved.php';
 *   sendApprovedEmail($conn, $peminjaman_id);
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Send loan approved notification email to ALL related parties
 *
 * @param mysqli $conn            Database connection
 * @param int    $peminjamanId    Loan ID
 * @return bool                   true if successful, false if failed
 */
function sendApprovedEmail($conn, $peminjamanId) {
    // Get loan + user data
    $data = getPeminjamanWithUser($conn, $peminjamanId);

    if (!$data) {
        error_log("[EMAIL] send-approved: Loan data #{$peminjamanId} not found.");
        return false;
    }

    $nama  = $data['nama_user'] ?: $data['nama_peminjam'];
    $email = $data['email'];
    $kode  = $data['kode_peminjaman'];
    $tglPinjam  = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembali = date('d F Y', strtotime($data['rencana_kembali']));

    // ============================================================
    // COLLECT ALL RECIPIENTS IN ARRAY
    // ============================================================
    $recipients = [];

    // 1. USER (loan owner) — from database
    $recipients[] = ['email' => $email, 'nama' => $nama];

    // 2. ALL ADMINS — from database
    $admins = getAdminEmails($conn);
    foreach ($admins as $admin) {
        $recipients[] = ['email' => $admin['email'], 'nama' => $admin['nama']];
    }

    // 3. ALL PIC_BARANG — from database
    $pics = getPicBarangEmails($conn);
    foreach ($pics as $pic) {
        $recipients[] = ['email' => $pic['email'], 'nama' => $pic['nama']];
    }

    // 4. ACTOR — from SESSION (user who approved)
    $actor = getActorEmail($conn);
    if ($actor) {
        $recipients[] = ['email' => $actor['email'], 'nama' => $actor['nama']];
    }

    // DEDUPLICATION — remove duplicate emails
    $recipients = buildUniqueRecipients(...array_map(fn($r) => $r, $recipients));

    if (empty($recipients)) {
        error_log("[EMAIL] send-approved: No valid recipients for loan #{$peminjamanId}");
        return false;
    }

    // Build email body
    $bodyHtml = '
        <p>Hello,</p>
        
        <div class="success-box">
            <strong>✅ Loan Approved!</strong><br>
            Loan request from <strong>' . htmlspecialchars($nama) . '</strong> has been approved.
        </div>
        
        <p>Loan details:</p>
        
        <table class="info-table">
            <tr>
                <td>Loan Code</td>
                <td><strong>' . htmlspecialchars($kode) . '</strong></td>
            </tr>
            <tr>
                <td>Borrower Name</td>
                <td>' . htmlspecialchars($nama) . '</td>
            </tr>
            <tr>
                <td>Borrow Date</td>
                <td>' . htmlspecialchars($tglPinjam) . '</td>
            </tr>
            <tr>
                <td>Planned Return Date</td>
                <td>' . htmlspecialchars($tglKembali) . '</td>
            </tr>
        </table>
        
        <p>Thank you.</p>';

    $subject  = 'Loan Approved - ' . $kode;
    $fullHtml = buildEmailTemplate('✅ Loan Approved', $bodyHtml);

    // ============================================================
    // SEND EMAIL USING LOOP TO ALL RECIPIENTS
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-approved: EMAIL SENT TO: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-approved: EMAIL FAILED TO: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-approved: Total sent {$totalSent}/" . count($recipients) . " for borrowing #{$peminjamanId}");
    return $totalSent > 0;
}

// ============================================================
// TEST MODE: Run directly via browser/CLI with ?id=xxx
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-approved.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);

    if (!$id) {
        echo "Usage: send-approved.php?id=PEMINJAMAN_ID\n";
        exit;
    }

    echo "Sending approved email for loan #{$id} to ALL parties...\n";
    $result = sendApprovedEmail($conn, (int)$id);
    echo $result ? "✅ Email successfully sent to all parties!\n" : "❌ Email failed to send.\n";
}
