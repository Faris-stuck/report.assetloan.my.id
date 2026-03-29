<?php
/**
 * ============================================================
 * EMAIL: Extension Approved
 * ============================================================
 * 
 * Email sent to ALL related parties:
 *   - USER (loan owner)
 *   - ADMIN (all admins)
 *   - PIC_BARANG (all PICs)
 *   - ACTOR (from SESSION)
 * 
 * File   : /PROJECT/api/email/send-extend-approved.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Send extension approved notification email to ALL related parties
 *
 * @param mysqli      $conn            Database connection
 * @param int         $peminjamanId    Loan ID
 * @param string|null $newDate         New return date (Y-m-d), optional
 * @return bool                        true if successful, false if failed
 */
function sendExtendApprovedEmail($conn, $peminjamanId, $newDate = null) {
    // Get loan + user data
    $data = getPeminjamanWithUser($conn, $peminjamanId);

    if (!$data) {
        error_log("[EMAIL] send-extend-approved: Loan data #{$peminjamanId} not found.");
        return false;
    }

    $borrower = getBorrowerIdentity($data);
    $nama  = $borrower['nama'];
    $email = $borrower['email'];
    $kode  = $data['kode_peminjaman'];
    $tglPinjam       = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembaliBaru  = $newDate 
        ? date('d F Y', strtotime($newDate)) 
        : date('d F Y', strtotime($data['rencana_kembali']));

    // ============================================================
    // COLLECT ALL RECIPIENTS IN ARRAY
    // ============================================================
    $recipients = [];

    // 1. USER (loan owner)
    $recipients[] = ['email' => $email, 'nama' => $nama];

    // 2. ALL ADMINS
    $admins = getAdminEmails($conn);
    foreach ($admins as $admin) {
        $recipients[] = ['email' => $admin['email'], 'nama' => $admin['nama']];
    }

    // 3. ALL PIC_BARANG
    $pics = getPicBarangEmails($conn);
    foreach ($pics as $pic) {
        $recipients[] = ['email' => $pic['email'], 'nama' => $pic['nama']];
    }

    // 4. ACTOR (from SESSION)
    $actor = getActorEmail($conn);
    if ($actor) {
        $recipients[] = ['email' => $actor['email'], 'nama' => $actor['nama']];
    }

    // DEDUPLICATION
    $recipients = buildUniqueRecipients(...array_map(fn($r) => $r, $recipients));

    if (empty($recipients)) {
        error_log("[EMAIL] send-extend-approved: No valid recipients for loan #{$peminjamanId}");
        return false;
    }

    // Build email body
    $bodyHtml = '
        <p>Hello,</p>
        
        <div class="success-box">
            <strong>✅ Extension Approved!</strong><br>
            Extension request from <strong>' . htmlspecialchars($nama) . '</strong> has been approved.
        </div>
        
        <p>Extension details:</p>
        
        <table class="info-table">
            <tr>
                <td>Loan Code</td>
                <td><strong>' . htmlspecialchars($kode) . '</strong></td>
            </tr>
            ' . buildBorrowerIdentityRows($borrower) . '
            <tr>
                <td>Borrow Date</td>
                <td>' . htmlspecialchars($tglPinjam) . '</td>
            </tr>
            <tr>
                <td>New Return Date</td>
                <td><strong style="color: #2563eb;">' . htmlspecialchars($tglKembaliBaru) . '</strong></td>
            </tr>
        </table>
        
        <p>Please ensure items are returned before the new return date.</p>
        
        <p>Thank you.</p>';

    $subject  = 'Extension Approved - ' . $kode;
    $fullHtml = buildEmailTemplate('✅ Extension Approved', $bodyHtml);

    // ============================================================
    // SEND EMAIL USING LOOP TO ALL RECIPIENTS
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-extend-approved: EMAIL SENT TO: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-extend-approved: EMAIL FAILED TO: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-extend-approved: Total sent {$totalSent}/" . count($recipients) . " for borrowing #{$peminjamanId}");
    return $totalSent > 0;
}

// ============================================================
// TEST MODE
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-extend-approved.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);

    if (!$id) {
        echo "Usage: send-extend-approved.php?id=PEMINJAMAN_ID\n";
        exit;
    }

    echo "Sending extend approved email for loan #{$id} to ALL parties...\n";
    $result = sendExtendApprovedEmail($conn, (int)$id);
    echo $result ? "✅ Email successfully sent to all parties!\n" : "❌ Email failed to send.\n";
}
