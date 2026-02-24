<?php
/**
 * ============================================================
 * EMAIL: Peminjaman Ditolak (Status → Ditolak)
 * ============================================================
 * 
 * File   : /PROJECT/api/email/send-rejected.php
 * 
 * Cara panggil setelah update status:
 *   require_once __DIR__ . '/../email/send-rejected.php';
 *   sendRejectedEmail($conn, $peminjaman_id);
 * 
 * Atau via browser untuk test:
 *   http://localhost/PROJECT/api/email/send-rejected.php?id=123
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi peminjaman ditolak ke user
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

    // Validasi email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("[EMAIL] send-rejected: Email tidak valid untuk peminjaman #{$peminjamanId}: '{$email}'");
        return false;
    }

    // Buat body email
    $bodyHtml = '
        <p>Halo <strong>' . htmlspecialchars($nama) . '</strong>,</p>
        
        <div class="warning-box">
            <strong>❌ ' . htmlspecialchars($konteks) . ' Ditolak</strong><br>
            Mohon maaf, permintaan ' . htmlspecialchars(strtolower($konteks)) . ' Anda telah ditolak.
        </div>
        
        <p>Berikut detail ' . htmlspecialchars(strtolower($konteks)) . ' Anda:</p>
        
        <table class="info-table">
            <tr>
                <td>Kode Peminjaman</td>
                <td><strong>' . htmlspecialchars($kode) . '</strong></td>
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
        
        <p>Silakan hubungi admin untuk informasi lebih lanjut.</p>
        
        <p>Terima kasih.</p>';

    $subject  = $konteks . ' Ditolak - ' . $kode;
    $fullHtml = buildEmailTemplate('❌ ' . $konteks . ' Ditolak', $bodyHtml);

    $result = sendEmail($email, $subject, $fullHtml, $nama);

    if ($result) {
        error_log("[EMAIL] send-rejected: Berhasil kirim ke {$email} untuk peminjaman #{$peminjamanId} ({$konteks})");
        echo "EMAIL PENOLAKAN TERKIRIM";
    } else {
        error_log("[EMAIL] send-rejected: Gagal kirim ke {$email} untuk peminjaman #{$peminjamanId} ({$konteks})");
    }

    return $result;
}

// ============================================================
// TEST MODE: Jalankan langsung via browser/CLI dengan ?id=xxx
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

    echo "Mengirim email rejected untuk peminjaman #{$id} ({$konteks})...\n";
    $result = sendRejectedEmail($conn, (int)$id, $konteks);
    echo $result ? "\n✅ Email berhasil dikirim!\n" : "\n❌ Email gagal dikirim.\n";
}
