<?php
/**
 * ============================================================
 * EMAIL: Loan Extension Request
 * ============================================================
 * 
 * Email sent to ALL related parties:
 *   - USER (loan owner)
 *   - ADMIN (all admins)
 *   - PIC_BARANG (all PICs)
 *   - ACTOR (from SESSION)
 * 
 * File   : /PROJECT/api/email/send-extend-request.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Helper: Get extend + loan + user data from database
 */
function getExtendWithPeminjamanAndUser($conn, $extendId) {
    $stmt = $conn->prepare("
        SELECT 
            e.id as extend_id,
            e.peminjaman_id,
            e.tanggal_perpanjang,
            e.status as extend_status,
            e.catatan,
            p.kode_peminjaman,
            p.nama_peminjam,
            p.tanggal_pinjam,
            p.rencana_kembali,
            u.email,
            u.nama AS nama_user
        FROM extend_peminjaman e
        JOIN peminjaman p ON e.peminjaman_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE e.id = ?
    ");

    if (!$stmt) {
        error_log("[EMAIL ERROR] getExtendWithPeminjamanAndUser prepare failed: " . $conn->error);
        return null;
    }

    $stmt->bind_param('i', $extendId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}

/**
 * Send extension request notification email to ALL related parties
 *
 * @param mysqli $conn      Database connection
 * @param int    $extendId  Extension ID
 * @return bool             true if successful, false if failed
 */
function sendExtendRequestEmail($conn, $extendId) {
    // Get extend + loan + user data from DATABASE
    $data = getExtendWithPeminjamanAndUser($conn, $extendId);

    if (!$data) {
        error_log("[EMAIL] send-extend-request: Extend data #{$extendId} not found.");
        return false;
    }

    $namaUser    = $data['nama_user'] ?: $data['nama_peminjam'];
    $emailUser   = $data['email'];
    $kode        = $data['kode_peminjaman'];
    $tglPinjam   = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglPerpanjang = date('d F Y', strtotime($data['tanggal_perpanjang']));

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
        error_log("[EMAIL] send-extend-request: No valid recipients for extend #{$extendId}");
        return false;
    }

    $subject = 'Extension Request - ' . $kode;

    // Build email body
    $bodyHtml = '
        <p>Hello,</p>
        
        <div class="warning-box">
            <strong>⏱️ New Extension Request</strong><br>
            <strong>' . htmlspecialchars($namaUser) . '</strong> has submitted a loan extension request.
        </div>
        
        <p>Extension request details:</p>
        
        <table class="info-table">
            <tr>
                <td>Loan Code</td>
                <td><strong>' . htmlspecialchars($kode) . '</strong></td>
            </tr>
            <tr>
                <td>Borrower Name</td>
                <td>' . htmlspecialchars($namaUser) . '</td>
            </tr>
            <tr>
                <td>Borrower Email</td>
                <td>' . htmlspecialchars($emailUser) . '</td>
            </tr>
            <tr>
                <td>Borrow Date</td>
                <td>' . htmlspecialchars($tglPinjam) . '</td>
            </tr>
            <tr>
                <td>Extended Until</td>
                <td><strong>' . htmlspecialchars($tglPerpanjang) . '</strong></td>
            </tr>
        </table>
        
        <p>Please review and approve this extension request promptly.</p>
        
        <p>Thank you.</p>';

    $fullHtml = buildEmailTemplate('⏱️ New Extension Request', $bodyHtml);

    // ============================================================
    // SEND EMAIL USING LOOP TO ALL RECIPIENTS
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'], '', ['noSyncFallback' => true])) {
            error_log("[EMAIL] send-extend-request: EMAIL SENT TO: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-extend-request: EMAIL FAILED TO: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-extend-request: Total sent {$totalSent}/" . count($recipients) . " for extend #{$extendId}");
    return $totalSent > 0;
}

// ============================================================
// TEST MODE
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-extend-request.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);

    if (!$id) {
        echo "Usage: send-extend-request.php?id=EXTEND_ID\n";
        exit;
    }

    echo "Sending extension request email for extend #{$id} to ALL parties...\n";
    $result = sendExtendRequestEmail($conn, (int)$id);
    echo $result ? "✅ Email successfully sent to all parties!\n" : "❌ Email failed to send.\n";
}
