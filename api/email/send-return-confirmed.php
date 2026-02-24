<?php
/**
 * ============================================================
 * EMAIL: Pengembalian Dikonfirmasi (Status → Dikembalikan)
 * ============================================================
 * 
 * Email dikirim ke USER setelah admin mengkonfirmasi
 * pengembalian barang berhasil.
 * 
 * File   : /PROJECT/api/email/send-return-confirmed.php
 * 
 * Cara panggil setelah update status:
 *   require_once __DIR__ . '/../email/send-return-confirmed.php';
 *   sendReturnConfirmedEmail($conn, $peminjaman_id);
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi pengembalian dikonfirmasi ke user
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

    // Validasi email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("[EMAIL] send-return-confirmed: Email tidak valid untuk peminjaman #{$peminjamanId}: '{$email}'");
        return false;
    }

    // Buat body email
    $bodyHtml = '
        <p>Halo <strong>' . htmlspecialchars($nama) . '</strong>,</p>
        
        <div class="success-box">
            <strong>✅ Pengembalian Dikonfirmasi!</strong><br>
            Pengembalian barang Anda telah dikonfirmasi oleh admin.
        </div>
        
        <p>Detail peminjaman:</p>
        
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
                <td>Tanggal Dikembalikan</td>
                <td><strong style="color: #059669;">' . htmlspecialchars($tglKembali) . '</strong></td>
            </tr>
        </table>
        
        <p>Barang telah diterima dengan baik. Terima kasih atas kerjasama Anda.</p>
        
        <p>Terima kasih.</p>';

    $subject  = 'Pengembalian Dikonfirmasi - ' . $kode;
    $fullHtml = buildEmailTemplate('✅ Pengembalian Dikonfirmasi', $bodyHtml);

    $result = sendEmail($email, $subject, $fullHtml, $nama);

    if ($result) {
        error_log("[EMAIL] send-return-confirmed: Berhasil kirim ke {$email} untuk peminjaman #{$peminjamanId}");
    }

    return $result;
}

// ============================================================
// TEST MODE: Jalankan langsung via browser/CLI dengan ?id=xxx
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

    echo "Mengirim email return confirmed untuk peminjaman #{$id}...\n";
    $result = sendReturnConfirmedEmail($conn, (int)$id);
    echo $result ? "✅ Email berhasil dikirim!\n" : "❌ Email gagal dikirim.\n";
}
