<?php
/**
 * ============================================================
 * EMAIL: Peminjaman Disetujui (Status → Disetujui)
 * ============================================================
 * 
 * File   : /PROJECT/api/email/send-approved.php
 * 
 * Cara panggil setelah update status:
 *   require_once __DIR__ . '/../email/send-approved.php';
 *   sendApprovedEmail($conn, $peminjaman_id);
 * 
 * Atau via browser untuk test:
 *   http://localhost/PROJECT/api/email/send-approved.php?id=123
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi peminjaman disetujui ke user
 *
 * @param mysqli $conn            Koneksi database
 * @param int    $peminjamanId    ID peminjaman
 * @return bool                   true jika berhasil, false jika gagal
 */
function sendApprovedEmail($conn, $peminjamanId) {
    // Ambil data peminjaman + user
    $data = getPeminjamanWithUser($conn, $peminjamanId);

    if (!$data) {
        error_log("[EMAIL] send-approved: Data peminjaman #{$peminjamanId} tidak ditemukan.");
        return false;
    }

    $nama  = $data['nama_user'] ?: $data['nama_peminjam'];
    $email = $data['email'];
    $kode  = $data['kode_peminjaman'];
    $tglPinjam  = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembali = date('d F Y', strtotime($data['rencana_kembali']));

    // Validasi email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("[EMAIL] send-approved: Email tidak valid untuk peminjaman #{$peminjamanId}: '{$email}'");
        return false;
    }

    // Buat body email
    $bodyHtml = '
        <p>Halo <strong>' . htmlspecialchars($nama) . '</strong>,</p>
        
        <div class="success-box">
            <strong>✅ Peminjaman Disetujui!</strong><br>
            Permintaan peminjaman Anda telah disetujui.
        </div>
        
        <p>Berikut detail peminjaman Anda:</p>
        
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
        </table>
        
        <p>Silakan mengambil barang sesuai jadwal yang telah ditentukan.</p>
        
        <p>Terima kasih.</p>';

    $subject  = 'Peminjaman Disetujui - ' . $kode;
    $fullHtml = buildEmailTemplate('✅ Peminjaman Disetujui', $bodyHtml);

    $result = sendEmail($email, $subject, $fullHtml, $nama);

    if ($result) {
        error_log("[EMAIL] send-approved: Berhasil kirim ke {$email} untuk peminjaman #{$peminjamanId}");
    }

    return $result;
}

// ============================================================
// TEST MODE: Jalankan langsung via browser/CLI dengan ?id=xxx
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-approved.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);

    if (!$id) {
        echo "Usage: send-approved.php?id=PEMINJAMAN_ID\n";
        exit;
    }

    echo "Mengirim email approved untuk peminjaman #{$id}...\n";
    $result = sendApprovedEmail($conn, (int)$id);
    echo $result ? "✅ Email berhasil dikirim!\n" : "❌ Email gagal dikirim.\n";
}
