<?php
/**
 * ============================================================
 * EMAIL: Permintaan Peminjaman (Status → Menunggu Persetujuan)
 * ============================================================
 * 
 * Email dikirim ke SEMUA pihak terkait:
 *   - USER (pemilik peminjaman, pelaku aksi)
 *   - ADMIN (semua admin)
 *   - PIC_BARANG (semua PIC)
 *   - PELAKU AKSI (dari SESSION)
 * 
 * File   : /PROJECT/api/email/send-pinjam-request.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi peminjaman baru ke SEMUA pihak terkait
 *
 * @param mysqli $conn            Koneksi database
 * @param int    $peminjamanId    ID peminjaman
 * @return bool                   true jika berhasil, false jika gagal
 */
function sendPinjamRequestEmail($conn, $peminjamanId) {
    // Ambil data peminjaman + user dari DATABASE
    $data = getPeminjamanWithUser($conn, $peminjamanId);

    if (!$data) {
        error_log("[EMAIL] send-pinjam-request: Data peminjaman #{$peminjamanId} tidak ditemukan.");
        return false;
    }

    $namaUser   = $data['nama_user'] ?: $data['nama_peminjam'];
    $emailUser  = $data['email'];
    $kode       = $data['kode_peminjaman'];
    $tglPinjam  = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembali = date('d F Y', strtotime($data['rencana_kembali']));

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
        error_log("[EMAIL] send-pinjam-request: Tidak ada penerima valid untuk peminjaman #{$peminjamanId}");
        return false;
    }

    $subject = 'Permintaan Peminjaman Baru - ' . $kode;

    // Buat body email
    $bodyHtml = '
        <p>Halo,</p>
        
        <div class="info-box">
            <strong>📋 Permintaan Peminjaman Baru</strong><br>
            <strong>' . htmlspecialchars($namaUser) . '</strong> telah mengajukan permintaan peminjaman barang.
        </div>
        
        <p>Detail permintaan peminjaman:</p>
        
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
                <td>Rencana Kembali</td>
                <td>' . htmlspecialchars($tglKembali) . '</td>
            </tr>
            <tr>
                <td>Status</td>
                <td><strong>Menunggu Persetujuan</strong></td>
            </tr>
        </table>
        
        <p>Mohon segera melakukan review dan persetujuan permintaan peminjaman ini.</p>
        
        <p>Terima kasih.</p>';

    $fullHtml = buildEmailTemplate('📋 Permintaan Peminjaman Baru', $bodyHtml);

    // ============================================================
    // KIRIM EMAIL MENGGUNAKAN LOOP KE SEMUA PENERIMA
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-pinjam-request: EMAIL TERKIRIM KE: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-pinjam-request: EMAIL GAGAL KE: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-pinjam-request: Total terkirim {$totalSent}/" . count($recipients) . " untuk peminjaman #{$peminjamanId}");
    return $totalSent > 0;
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

    echo "Mengirim email permintaan peminjaman untuk peminjaman #{$id} ke SEMUA pihak...\n";
    $result = sendPinjamRequestEmail($conn, (int)$id);
    echo $result ? "✅ Email berhasil dikirim ke semua pihak!\n" : "❌ Email gagal dikirim.\n";
}
