<?php
/**
 * ============================================================
 * EMAIL: Loan Request (Status → Pending Approval)
 * ============================================================
 * 
 * Email sent to ALL related parties:
 *   - USER (loan owner, actor)
 *   - ADMIN (all admins)
 *   - PIC_BARANG (all PICs)
 *   - ACTOR (from SESSION)
 * 
 * File   : /PROJECT/api/email/send-pinjam-request.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Send new loan request notification email to ALL related parties
 *
 * @param mysqli $conn            Database connection
 * @param int    $peminjamanId    Loan ID
 * @return bool                   true if successful, false if failed
 */
function sendPinjamRequestEmail($conn, $peminjamanId) {
    // Get loan + user data from DATABASE
    $data = getPeminjamanWithUser($conn, $peminjamanId);

    if (!$data) {
        error_log("[EMAIL] send-pinjam-request: Loan data #{$peminjamanId} not found.");
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
        error_log("[EMAIL] send-pinjam-request: No valid recipients for loan #{$peminjamanId}");
        return false;
    }

    $subject = 'New Loan Request - ' . $kode;

    // Build email body
    $bodyHtml = '
        <p>Hello,</p>
        
        <div class="info-box">
            <strong>📋 New Loan Request</strong><br>
            <strong>' . htmlspecialchars($namaUser) . '</strong> has submitted a new item loan request.
        </div>
        
        <p>Loan request details:</p>
        
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
            <tr>
                <td>Status</td>
                <td><strong>Pending Approval</strong></td>
            </tr>
        </table>
        
        <p>Please review and approve this loan request promptly.</p>
        
        <p>Thank you.</p>';

    $fullHtml = buildEmailTemplate('📋 New Loan Request', $bodyHtml);

    // ============================================================
    // QUEUE EMAIL TO ALL RECIPIENTS
    // ============================================================
    $totalQueued = 0;
    foreach ($recipients as $r) {
        if (queueEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-pinjam-request: EMAIL QUEUED TO: " . $r['email']);
            $totalQueued++;
        } else {
            error_log("[EMAIL] send-pinjam-request: EMAIL QUEUE FAILED TO: " . $r['email']);
        }
    }

    if ($totalQueued > 0) {
        dispatchEmailQueueWorker();
    }

    error_log("[EMAIL] send-pinjam-request: Total queued {$totalQueued}/" . count($recipients) . " for borrowing #{$peminjamanId}");
    return $totalQueued > 0;
}

// ============================================================
// TEST MODE
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-pinjam-request.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);

    if (!$id) {
        echo "Usage: send-pinjam-request.php?id=PEMINJAMAN_ID\n";
        exit;
    }

    echo "Sending loan request email for loan #{$id} to ALL parties...\n";
    $result = sendPinjamRequestEmail($conn, (int)$id);
    echo $result ? "✅ Email successfully sent to all parties!\n" : "❌ Email failed to send.\n";
}
