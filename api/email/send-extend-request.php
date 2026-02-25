<?php
/**
 * ============================================================
 * EMAIL: Permintaan Perpanjangan Peminjaman
 * ============================================================
 * 
 * Email dikirim ke SEMUA pihak terkait:
 *   - USER (pemilik peminjaman)
 *   - ADMIN (semua admin)
 *   - PIC_BARANG (semua PIC)
 *   - PELAKU AKSI (dari SESSION)
 * 
 * File   : /PROJECT/api/email/send-extend-request.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Helper: Ambil data extend + peminjaman + user dari database
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
        error_log("[EMAIL ERROR] getExtendWithPeminjamanAndUser prepare gagal: " . $conn->error);
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
 * Kirim email notifikasi permintaan perpanjangan ke SEMUA pihak terkait
 *
 * @param mysqli $conn      Koneksi database
 * @param int    $extendId  ID extend
 * @return bool             true jika berhasil, false jika gagal
 */
function sendExtendRequestEmail($conn, $extendId) {
    // Ambil data extend + peminjaman + user dari DATABASE
    $data = getExtendWithPeminjamanAndUser($conn, $extendId);

    if (!$data) {
        error_log("[EMAIL] send-extend-request: Data extend #{$extendId} tidak ditemukan.");
        return false;
    }

    $namaUser    = $data['nama_user'] ?: $data['nama_peminjam'];
    $emailUser   = $data['email'];
    $kode        = $data['kode_peminjaman'];
    $tglPinjam   = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglPerpanjang = date('d F Y', strtotime($data['tanggal_perpanjang']));

    // ============================================================
    // KUMPULKAN SEMUA PENERIMA DALAM ARRAY
    // ============================================================
    $recipients = [];

    // 1. USER (pemilik peminjaman)
    $recipients[] = ['email' => $emailUser, 'nama' => $namaUser];

    // 2. SEMUA ADMIN
    $admins = getAdminEmails($conn);
    foreach ($admins as $admin) {
        $recipients[] = ['email' => $admin['email'], 'nama' => $admin['nama']];
    }

    // 3. SEMUA PIC_BARANG
    $pics = getPicBarangEmails($conn);
    foreach ($pics as $pic) {
        $recipients[] = ['email' => $pic['email'], 'nama' => $pic['nama']];
    }

    // 4. PELAKU AKSI (dari SESSION)
    $actor = getActorEmail($conn);
    if ($actor) {
        $recipients[] = ['email' => $actor['email'], 'nama' => $actor['nama']];
    }

    // DEDUPLIKASI
    $recipients = buildUniqueRecipients(...array_map(fn($r) => $r, $recipients));

    if (empty($recipients)) {
        error_log("[EMAIL] send-extend-request: Tidak ada penerima valid untuk extend #{$extendId}");
        return false;
    }

    $subject = 'Permintaan Perpanjangan - ' . $kode;

    // Buat body email
    $bodyHtml = '
        <p>Halo,</p>
        
        <div class="warning-box">
            <strong>⏱️ Permintaan Perpanjangan Baru</strong><br>
            <strong>' . htmlspecialchars($namaUser) . '</strong> telah mengajukan permintaan perpanjangan peminjaman.
        </div>
        
        <p>Detail permintaan perpanjangan:</p>
        
        <table class="info-table">
            <tr>
                <td>Kode Peminjaman</td>
                <td><strong>' . htmlspecialchars($kode) . '</strong></td>
            </tr>
            <tr>
                <td>Nama Peminjam</td>
                <td>' . htmlspecialchars($namaUser) . '</td>
            </tr>
            <tr>
                <td>Email Peminjam</td>
                <td>' . htmlspecialchars($emailUser) . '</td>
            </tr>
            <tr>
                <td>Tanggal Pinjam</td>
                <td>' . htmlspecialchars($tglPinjam) . '</td>
            </tr>
            <tr>
                <td>Perpanjangan Sampai</td>
                <td><strong>' . htmlspecialchars($tglPerpanjang) . '</strong></td>
            </tr>
        </table>
        
        <p>Mohon segera melakukan review dan persetujuan permintaan perpanjangan ini.</p>
        
        <p>Terima kasih.</p>';

    $fullHtml = buildEmailTemplate('⏱️ Permintaan Perpanjangan Baru', $bodyHtml);

    // ============================================================
    // KIRIM EMAIL MENGGUNAKAN LOOP KE SEMUA PENERIMA
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-extend-request: EMAIL TERKIRIM KE: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-extend-request: EMAIL GAGAL KE: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-extend-request: Total terkirim {$totalSent}/" . count($recipients) . " untuk extend #{$extendId}");
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

    echo "Mengirim email permintaan perpanjangan untuk extend #{$id} ke SEMUA pihak...\n";
    $result = sendExtendRequestEmail($conn, (int)$id);
    echo $result ? "✅ Email berhasil dikirim ke semua pihak!\n" : "❌ Email gagal dikirim.\n";
}
