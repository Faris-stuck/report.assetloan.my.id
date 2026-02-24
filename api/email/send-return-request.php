<?php
/**
 * ============================================================
 * EMAIL: Permintaan Return (Status → Proses Return)
 * ============================================================
 * 
 * Email dikirim ke ADMIN untuk memberitahu ada permintaan
 * pengembalian barang dari user.
 * 
 * File   : /PROJECT/api/email/send-return-request.php
 * 
 * Cara panggil setelah update status:
 *   require_once __DIR__ . '/../email/send-return-request.php';
 *   sendReturnRequestEmail($conn, $peminjaman_id);
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi return request ke ADMIN
 *
 * @param mysqli $conn            Koneksi database
 * @param int    $peminjamanId    ID peminjaman
 * @return bool                   true jika berhasil, false jika gagal
 */
function sendReturnRequestEmail($conn, $peminjamanId) {
    // Ambil data peminjaman + user dari DATABASE
    $data = getPeminjamanWithUser($conn, $peminjamanId);

    if (!$data) {
        error_log("[EMAIL] send-return-request: Data peminjaman #{$peminjamanId} tidak ditemukan.");
        return false;
    }

    $namaUser   = $data['nama_user'] ?: $data['nama_peminjam'];
    $emailUser  = $data['email'];
    $kode       = $data['kode_peminjaman'];
    $tglPinjam  = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembali = date('d F Y', strtotime($data['rencana_kembali']));

    // Ambil semua admin dari DATABASE (TIDAK HARDCODE)
    $admins = getAdminEmails($conn);

    if (empty($admins)) {
        error_log("[EMAIL] send-return-request: Tidak ada admin ditemukan di database.");
        return false;
    }

    $subject = 'Permintaan Pengembalian Barang - ' . $kode;
    $totalSent = 0;

    // Kirim ke SEMUA admin yang ada di database
    foreach ($admins as $admin) {
        $adminName  = $admin['nama'];
        $adminEmail = $admin['email'];

        // Buat body email untuk admin
        $bodyHtml = '
            <p>Halo <strong>' . htmlspecialchars($adminName) . '</strong>,</p>
            
            <div class="warning-box">
                <strong>📦 Permintaan Pengembalian Barang</strong><br>
                Seorang user telah mengajukan pengembalian barang.
            </div>
            
            <p>Detail peminjaman yang akan dikembalikan:</p>
            
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
            </table>
            
            <p>Mohon segera melakukan pengecekan dan konfirmasi pengembalian barang.</p>
            
            <p>Terima kasih.</p>';

        $fullHtml = buildEmailTemplate('📦 Permintaan Pengembalian Barang', $bodyHtml);

        if (sendEmail($adminEmail, $subject, $fullHtml, $adminName)) {
            error_log("[EMAIL] send-return-request: Berhasil kirim ke admin {$adminEmail} untuk peminjaman #{$peminjamanId}");
            $totalSent++;
        }
    }

    return $totalSent > 0;
}

// ============================================================
// TEST MODE: Jalankan langsung via browser/CLI dengan ?id=xxx
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-return-request.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);

    if (!$id) {
        echo "Usage: send-return-request.php?id=PEMINJAMAN_ID\n";
        exit;
    }

    echo "Mengirim email return request ke admin untuk peminjaman #{$id}...\n";
    $result = sendReturnRequestEmail($conn, (int)$id);
    echo $result ? "✅ Email berhasil dikirim ke admin!\n" : "❌ Email gagal dikirim.\n";
}
