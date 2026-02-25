<?php
/**
 * ============================================================
 * EMAIL: Peminjaman Ditolak (Status → Ditolak)
 * ============================================================
 * 
 * Email dikirim ke SEMUA pihak terkait:
 *   - USER (pemilik peminjaman)
 *   - ADMIN (semua admin)
 *   - PIC_BARANG (semua PIC)
 *   - PELAKU AKSI (dari SESSION)
 * 
 * File   : /PROJECT/api/email/send-rejected.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi peminjaman ditolak ke SEMUA pihak terkait
 *
 * @param mysqli $conn            Koneksi database
 * @param int    $peminjamanId    ID peminjaman
 * @param string $konteks         Konteks penolakan (Peminjaman/Pengembalian)
 * @return bool                   true jika berhasil, false jika gagal
 */
function sendRejectedEmail($conn, $peminjamanId, $konteks = 'Peminjaman') {
    // Ambil data peminjaman + user
    $data = getPeminjamanWithUser($conn, $peminjamanId);

    if (!$data) {
        error_log("[EMAIL] send-rejected: Data peminjaman #{$peminjamanId} tidak ditemukan.");
        return false;
    }

    $nama   = $data['nama_user'] ?: $data['nama_peminjam'];
    $email  = $data['email'];
    $kode   = $data['kode_peminjaman'];
    $catatan = $data['catatan'] ?: '-';
    $tglPinjam   = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembali  = !empty($data['rencana_kembali']) ? date('d F Y', strtotime($data['rencana_kembali'])) : '-';

    // ============================================================
    // KUMPULKAN SEMUA PENERIMA DALAM ARRAY
    // ============================================================
    $recipients = [];

    // 1. USER (pemilik peminjaman)
    $recipients[] = ['email' => $email, 'nama' => $nama];

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
        error_log("[EMAIL] send-rejected: Tidak ada penerima valid untuk peminjaman #{$peminjamanId}");
        return false;
    }

    // Buat body email
    $bodyHtml = '
        <p>Halo,</p>
        
        <div class="warning-box">
            <strong>❌ ' . htmlspecialchars($konteks) . ' Ditolak</strong><br>
            Permintaan ' . htmlspecialchars(strtolower($konteks)) . ' dari <strong>' . htmlspecialchars($nama) . '</strong> telah ditolak.
        </div>
        
        <p>Berikut detail ' . htmlspecialchars(strtolower($konteks)) . ':</p>
        
        <table class="info-table">
            <tr>
                <td>Kode Peminjaman</td>
                <td><strong>' . htmlspecialchars($kode) . '</strong></td>
            </tr>
            <tr>
                <td>Nama Peminjam</td>
                <td>' . htmlspecialchars($nama) . '</td>
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
                <td>Alasan Penolakan</td>
                <td><strong style="color: #dc2626;">' . htmlspecialchars($catatan) . '</strong></td>
            </tr>
        </table>
        
        <p>Terima kasih.</p>';

    $subject  = $konteks . ' Ditolak - ' . $kode;
    $fullHtml = buildEmailTemplate('❌ ' . $konteks . ' Ditolak', $bodyHtml);

    // ============================================================
    // KIRIM EMAIL MENGGUNAKAN LOOP KE SEMUA PENERIMA
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-rejected: EMAIL TERKIRIM KE: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-rejected: EMAIL GAGAL KE: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-rejected: Total terkirim {$totalSent}/" . count($recipients) . " untuk peminjaman #{$peminjamanId} ({$konteks})");
    return $totalSent > 0;
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
    $konteks = $_GET['konteks'] ?? 'Peminjaman';

    if (!$id) {
        echo "Usage: send-rejected.php?id=PEMINJAMAN_ID&konteks=Peminjaman\n";
        exit;
    }

    echo "Mengirim email rejected untuk peminjaman #{$id} ({$konteks}) ke SEMUA pihak...\n";
    $result = sendRejectedEmail($conn, (int)$id, $konteks);
    echo $result ? "✅ Email berhasil dikirim ke semua pihak!\n" : "❌ Email gagal dikirim.\n";
}
