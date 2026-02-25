<?php
/**
 * ============================================================
 * EMAIL: Pengembalian Dikonfirmasi (Status → Dikembalikan)
 * ============================================================
 * 
 * Email dikirim ke SEMUA pihak terkait:
 *   - USER (pemilik peminjaman)
 *   - ADMIN (semua admin)
 *   - PIC_BARANG (semua PIC)
 *   - PELAKU AKSI (dari SESSION)
 * 
 * File   : /PROJECT/api/email/send-return-confirmed.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi pengembalian dikonfirmasi ke SEMUA pihak terkait
 *
 * @param mysqli $conn            Koneksi database
 * @param int    $peminjamanId    ID peminjaman
 * @return bool                   true jika berhasil, false jika gagal
 */
function sendReturnConfirmedEmail($conn, $peminjamanId) {
    // Ambil data peminjaman + user
    $data = getPeminjamanWithUser($conn, $peminjamanId);

    if (!$data) {
        error_log("[EMAIL] send-return-confirmed: Data peminjaman #{$peminjamanId} tidak ditemukan.");
        return false;
    }

    $nama  = $data['nama_user'] ?: $data['nama_peminjam'];
    $email = $data['email'];
    $kode  = $data['kode_peminjaman'];
    $tglPinjam    = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembali   = $data['tanggal_kembali'] ? date('d F Y', strtotime($data['tanggal_kembali'])) : date('d F Y');

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
        error_log("[EMAIL] send-return-confirmed: Tidak ada penerima valid untuk peminjaman #{$peminjamanId}");
        return false;
    }

    // Buat body email
    $bodyHtml = '
        <p>Halo,</p>
        
        <div class="success-box">
            <strong>✅ Pengembalian Dikonfirmasi!</strong><br>
            Pengembalian barang dari <strong>' . htmlspecialchars($nama) . '</strong> telah dikonfirmasi.
        </div>
        
        <p>Detail peminjaman:</p>
        
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
                <td>Tanggal Dikembalikan</td>
                <td><strong style="color: #059669;">' . htmlspecialchars($tglKembali) . '</strong></td>
            </tr>
        </table>
        
        <p>Barang telah diterima dengan baik. Terima kasih.</p>';

    $subject  = 'Pengembalian Dikonfirmasi - ' . $kode;
    $fullHtml = buildEmailTemplate('✅ Pengembalian Dikonfirmasi', $bodyHtml);

    // ============================================================
    // KIRIM EMAIL MENGGUNAKAN LOOP KE SEMUA PENERIMA
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-return-confirmed: EMAIL TERKIRIM KE: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-return-confirmed: EMAIL GAGAL KE: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-return-confirmed: Total terkirim {$totalSent}/" . count($recipients) . " untuk peminjaman #{$peminjamanId}");
    return $totalSent > 0;
}

// ============================================================
// TEST MODE
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-return-confirmed.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);

    if (!$id) {
        echo "Usage: send-return-confirmed.php?id=PEMINJAMAN_ID\n";
        exit;
    }

    echo "Mengirim email return confirmed untuk peminjaman #{$id} ke SEMUA pihak...\n";
    $result = sendReturnConfirmedEmail($conn, (int)$id);
    echo $result ? "✅ Email berhasil dikirim ke semua pihak!\n" : "❌ Email gagal dikirim.\n";
}
