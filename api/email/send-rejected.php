<?php
/**
 * ============================================================
 * EMAIL: Loan Rejected (Status → Rejected)
 * ============================================================
 * 
 * Email sent to ALL related parties:
 *   - USER (loan owner)
 *   - ADMIN (all admins)
 *   - PIC_BARANG (all PICs)
 *   - ACTOR (from SESSION)
 * 
 * File   : /PROJECT/api/email/send-rejected.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Send loan rejected notification email to ALL related parties
 *
 * @param mysqli $conn            Database connection
 * @param int    $peminjamanId    Loan ID
 * @param string $konteks         Rejection context (Loan/Return)
 * @return bool                   true if successful, false if failed
 */
function sendRejectedEmail($conn, $peminjamanId, $konteks = 'Loan') {
    // Get loan + user data
    $data = getPeminjamanWithUser($conn, $peminjamanId);

    if (!$data) {
        error_log("[EMAIL] send-rejected: Loan data #{$peminjamanId} not found.");
        return false;
    }

    $borrower = getBorrowerIdentity($data);
    $nama   = $borrower['nama'];
    $email  = $borrower['email'];
    $kode   = $data['kode_peminjaman'];
    $catatan = $data['catatan'] ?: '-';
    $tglPinjam   = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembali  = !empty($data['rencana_kembali']) ? date('d F Y', strtotime($data['rencana_kembali'])) : '-';

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
        error_log("[EMAIL] send-rejected: No valid recipients for loan #{$peminjamanId}");
        return false;
    }

    // Build email body
    $bodyHtml = '
        <p>Hello,</p>
        
        <div class="warning-box">
            <strong>❌ ' . htmlspecialchars($konteks) . ' Rejected</strong><br>
            ' . htmlspecialchars($konteks) . ' request from <strong>' . htmlspecialchars($nama) . '</strong> has been rejected.
        </div>
        
        <p>' . htmlspecialchars($konteks) . ' details:</p>
        
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
                <td>Rejection Reason</td>
                <td><strong style="color: #dc2626;">' . htmlspecialchars($catatan) . '</strong></td>
            </tr>
        </table>
        
        <p>Thank you.</p>';

    $subject  = $konteks . ' Rejected - ' . $kode;
    $fullHtml = buildEmailTemplate('❌ ' . $konteks . ' Rejected', $bodyHtml);

    // ============================================================
    // QUEUE EMAIL TO ALL RECIPIENTS
    // ============================================================
    $totalQueued = 0;
    foreach ($recipients as $r) {
        if (queueEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-rejected: EMAIL QUEUED TO: " . $r['email']);
            $totalQueued++;
        } else {
            error_log("[EMAIL] send-rejected: EMAIL QUEUE FAILED TO: " . $r['email']);
        }
    }

    if ($totalQueued > 0) {
        dispatchEmailQueueWorker();
    }

    error_log("[EMAIL] send-rejected: Total queued {$totalQueued}/" . count($recipients) . " for borrowing #{$peminjamanId} ({$konteks})");
    return $totalQueued > 0;
}

// ============================================================
// TEST MODE
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-rejected.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);
    $konteks = $_GET['konteks'] ?? 'Loan';

    if (!$id) {
        echo "Usage: send-rejected.php?id=PEMINJAMAN_ID&konteks=Peminjaman\n";
        exit;
    }

    echo "Sending rejected email for loan #{$id} ({$konteks}) to ALL parties...\n";
    $result = sendRejectedEmail($conn, (int)$id, $konteks);
    echo $result ? "✅ Email successfully sent to all parties!\n" : "❌ Email failed to send.\n";
}
