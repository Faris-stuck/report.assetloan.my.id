<?php
/**
 * ============================================================
 * EMAIL: Perpanjangan Disetujui
 * ============================================================
 * 
 * Email dikirim ke USER setelah manager/admin menyetujui
 * permintaan perpanjangan masa pinjam.
 * 
 * File   : /PROJECT/api/email/send-extend-approved.php
 * 
 * Cara panggil setelah update status:
 *   require_once __DIR__ . '/../email/send-extend-approved.php';
 *   sendExtendApprovedEmail($conn, $peminjaman_id, $tanggal_baru);
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi perpanjangan disetujui ke user
 *
 * @param mysqli      $conn            Koneksi database
 * @param int         $peminjamanId    ID peminjaman
 * @param string|null $newDate         Tanggal kembali baru (Y-m-d), opsional
 * @return bool                        true jika berhasil, false jika gagal
 */
function sendExtendApprovedEmail($conn, $peminjamanId, $newDate = null) {
    // Ambil data peminjaman + user
    $data = getPeminjamanWithUser($conn, $peminjamanId);

    if (!$data) {
        error_log("[EMAIL] send-extend-approved: Data peminjaman #{$peminjamanId} tidak ditemukan.");
        return false;
    }

    $nama  = $data['nama_user'] ?: $data['nama_peminjam'];
    $email = $data['email'];
    $kode  = $data['kode_peminjaman'];
    $tglPinjam       = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembaliBaru  = $newDate 
        ? date('d F Y', strtotime($newDate)) 
        : date('d F Y', strtotime($data['rencana_kembali']));

    // Validasi email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("[EMAIL] send-extend-approved: Email tidak valid untuk peminjaman #{$peminjamanId}: '{$email}'");
        return false;
    }

    // Buat body email
    $bodyHtml = '
        <p>Halo <strong>' . htmlspecialchars($nama) . '</strong>,</p>
        
        <div class="success-box">
            <strong>✅ Perpanjangan Disetujui!</strong><br>
            Permintaan perpanjangan masa pinjam Anda telah disetujui.
        </div>
        
        <p>Detail perpanjangan:</p>
        
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
                <td>Tanggal Kembali Baru</td>
                <td><strong style="color: #2563eb;">' . htmlspecialchars($tglKembaliBaru) . '</strong></td>
            </tr>
        </table>
        
        <p>Mohon pastikan barang dikembalikan sebelum tanggal kembali yang baru.</p>
        
        <p>Terima kasih.</p>';

    $subject  = 'Perpanjangan Disetujui - ' . $kode;
    $fullHtml = buildEmailTemplate('✅ Perpanjangan Disetujui', $bodyHtml);

    $result = sendEmail($email, $subject, $fullHtml, $nama);

    if ($result) {
        error_log("[EMAIL] send-extend-approved: Berhasil kirim ke {$email} untuk peminjaman #{$peminjamanId}");
    }

    return $result;
}

// ============================================================
// TEST MODE: Jalankan langsung via browser/CLI dengan ?id=xxx
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-extend-approved.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);

    if (!$id) {
        echo "Usage: send-extend-approved.php?id=PEMINJAMAN_ID\n";
        exit;
    }

    echo "Mengirim email extend approved untuk peminjaman #{$id}...\n";
    $result = sendExtendApprovedEmail($conn, (int)$id);
    echo $result ? "✅ Email berhasil dikirim!\n" : "❌ Email gagal dikirim.\n";
}
