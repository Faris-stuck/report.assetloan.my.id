<?php
/**
 * ============================================================
 * EMAIL: Return Request (Status → Process Return)
 * ============================================================
 * 
 * Email sent to ALL related parties:
 *   - USER (loan owner, actor)
 *   - ADMIN (all admins)
 *   - PIC_BARANG (all PICs)
 *   - ACTOR (from SESSION)
 * 
 * File   : /PROJECT/api/email/send-return-request.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Send return request notification email to ALL related parties
 *
 * @param mysqli $conn            Database connection
 * @param int    $peminjamanId    Loan ID
 * @return bool                   true if successful, false if failed
 */
function sendReturnRequestEmail($conn, $peminjamanId) {
    // Get loan + user data from DATABASE
    $data = getPeminjamanWithUser($conn, $peminjamanId);

    if (!$data) {
        error_log("[EMAIL] send-return-request: Loan data #{$peminjamanId} not found.");
        return false;
    }

    $borrower   = getBorrowerIdentity($data);
    $namaUser   = $borrower['nama'];
    $emailUser  = $borrower['email'];
    $kode       = $data['kode_peminjaman'];
    $tglPinjam  = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembali = date('d F Y', strtotime($data['rencana_kembali']));

    // ============================================================
    // COLLECT ALL RECIPIENTS IN ARRAY
    // ============================================================
    $recipients = [];

    // 1. USER (loan owner)
    $recipients[] = ['email' => $emailUser, 'nama' => $namaUser];

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
        error_log("[EMAIL] send-return-request: No valid recipients for loan #{$peminjamanId}");
        return false;
    }

    $subject = 'Item Return Request - ' . $kode;

    // Build email body
    $bodyHtml = '
        <p>Hello,</p>
        
        <div class="warning-box">
            <strong>📦 Item Return Request</strong><br>
            <strong>' . htmlspecialchars($namaUser) . '</strong> has submitted an item return request.
        </div>
        
        <p>Details of the loan to be returned:</p>
        
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
                <td>Planned Return Date</td>
                <td>' . htmlspecialchars($tglKembali) . '</td>
            </tr>
        </table>
        
        <p>Please check and confirm the item return promptly.</p>
        
        <p>Thank you.</p>';

    $fullHtml = buildEmailTemplate('📦 Item Return Request', $bodyHtml);

    // ============================================================
    // SEND EMAIL USING LOOP TO ALL RECIPIENTS
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'], '', ['noSyncFallback' => true])) {
            error_log("[EMAIL] send-return-request: EMAIL SENT TO: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-return-request: EMAIL FAILED TO: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-return-request: Total sent {$totalSent}/" . count($recipients) . " for borrowing #{$peminjamanId}");
    return $totalSent > 0;
}

// ============================================================
// TEST MODE
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-return-request.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);

    if (!$id) {
        echo "Usage: send-return-request.php?id=PEMINJAMAN_ID\n";
        exit;
    }

    echo "Sending return request email for loan #{$id} to ALL parties...\n";
    $result = sendReturnRequestEmail($conn, (int)$id);
    echo $result ? "✅ Email successfully sent to all parties!\n" : "❌ Email failed to send.\n";
}
