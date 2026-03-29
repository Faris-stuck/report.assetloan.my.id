<?php
/**
 * ============================================================
 * EMAIL: Extension Rejected (Extend → Rejected)
 * ============================================================
 * 
 * Email sent to ALL related parties:
 *   - USER (loan owner)
 *   - ADMIN (all admins)
 *   - PIC_BARANG (all PICs)
 *   - ACTOR (from SESSION)
 * 
 * File   : /PROJECT/api/email/send-extend-rejected.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Send extension rejected notification email to ALL related parties
 *
 * @param mysqli $conn        Database connection
 * @param int    $extendId    Extension ID
 * @return bool               true if successful, false if failed
 */
function sendExtendRejectedEmail($conn, $extendId) {
    // Get extend + loan + user data
    $stmt = $conn->prepare("
        SELECT 
            e.id AS extend_id,
            e.tanggal_perpanjang,
            e.alasan AS alasan_extend,
            e.status AS status_extend,
            p.id AS peminjaman_id,
            p.kode_peminjaman,
            p.nama_peminjam,
            p.nrp,
            p.tanggal_pinjam,
            p.rencana_kembali,
            u.email,
            u.nama AS nama_user,
            u.nrp AS nrp_user
        FROM extend_peminjaman e
        JOIN peminjaman p ON e.peminjaman_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE e.id = ?
    ");

    if (!$stmt) {
        error_log("[EMAIL] send-extend-rejected: Prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param('i', $extendId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("[EMAIL] send-extend-rejected: Extend data #{$extendId} not found.");
        return false;
    }

    $data = $result->fetch_assoc();

    $borrower = getBorrowerIdentity($data);
    $nama   = $borrower['nama'];
    $email  = $borrower['email'];
    $kode   = $data['kode_peminjaman'];
    $tglPinjam         = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembaliSaatIni = date('d F Y', strtotime($data['rencana_kembali']));
    $tglPerpanjang     = !empty($data['tanggal_perpanjang']) ? date('d F Y', strtotime($data['tanggal_perpanjang'])) : '-';
    $alasanExtend      = $data['alasan_extend'] ?: '-';

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
        error_log("[EMAIL] send-extend-rejected: No valid recipients for extend #{$extendId}");
        return false;
    }

    // Build email body
    $bodyHtml = '
        <p>Hello,</p>
        
        <div class="warning-box">
            <strong>❌ Extension Rejected</strong><br>
            Extension request from <strong>' . htmlspecialchars($nama) . '</strong> has been rejected.
        </div>
        
        <p>Details of the rejected extension:</p>
        
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
                <td>Current Return Date</td>
                <td>' . htmlspecialchars($tglKembaliSaatIni) . '</td>
            </tr>
            <tr>
                <td>Requested Extension Date</td>
                <td>' . htmlspecialchars($tglPerpanjang) . '</td>
            </tr>
            <tr>
                <td>Request Reason</td>
                <td>' . htmlspecialchars($alasanExtend) . '</td>
            </tr>
        </table>
        
        <p>Items must be returned by the current applicable return date.</p>
        
        <p>Thank you.</p>';

    $subject  = 'Extension Rejected - ' . $kode;
    $fullHtml = buildEmailTemplate('❌ Extension Rejected', $bodyHtml);

    // ============================================================
    // SEND EMAIL USING LOOP TO ALL RECIPIENTS
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-extend-rejected: EMAIL SENT TO: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-extend-rejected: EMAIL FAILED TO: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-extend-rejected: Total sent {$totalSent}/" . count($recipients) . " for extend #{$extendId}");
    return $totalSent > 0;
}

// ============================================================
// TEST MODE
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-extend-rejected.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);

    if (!$id) {
        echo "Usage: send-extend-rejected.php?id=EXTEND_ID\n";
        exit;
    }

    echo "Sending extend rejected email for extend #{$id} to ALL parties...\n";
    $result = sendExtendRejectedEmail($conn, (int)$id);
    echo $result ? "✅ Email successfully sent to all parties!\n" : "❌ Email failed to send.\n";
}
