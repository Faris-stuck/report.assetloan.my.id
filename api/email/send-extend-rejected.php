<?php
/**
 * ============================================================
 * EMAIL: Perpanjangan Ditolak (Extend → Rejected)
 * ============================================================
 * 
 * File   : /PROJECT/api/email/send-extend-rejected.php
 * 
 * Cara panggil setelah update status extend:
 *   require_once __DIR__ . '/../email/send-extend-rejected.php';
 *   sendExtendRejectedEmail($conn, $extend_id);
 * 
 * Atau via browser untuk test:
 *   http://localhost/PROJECT/api/email/send-extend-rejected.php?id=123
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi perpanjangan ditolak ke user
 *
 * @param mysqli $conn        Koneksi database
 * @param int    $extendId    ID extend_peminjaman
 * @return bool               true jika berhasil, false jika gagal
 */
function sendExtendRejectedEmail($conn, $extendId) {
    // Ambil data extend + peminjaman + user
    $stmt = $conn->prepare("
        SELECT 
            e.id AS extend_id,
            e.tanggal_perpanjang,
            e.alasan AS alasan_extend,
            e.status AS status_extend,
            p.id AS peminjaman_id,
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
        error_log("[EMAIL] send-extend-rejected: Prepare gagal: " . $conn->error);
        return false;
    }

    $stmt->bind_param('i', $extendId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("[EMAIL] send-extend-rejected: Data extend #{$extendId} tidak ditemukan.");
        return false;
    }

    $data = $result->fetch_assoc();

    $nama   = $data['nama_user'] ?: $data['nama_peminjam'];
    $email  = $data['email'];
    $kode   = $data['kode_peminjaman'];
    $tglPinjam         = date('d F Y', strtotime($data['tanggal_pinjam']));
    $tglKembaliSaatIni = date('d F Y', strtotime($data['rencana_kembali']));
    $tglPerpanjang     = !empty($data['tanggal_perpanjang']) ? date('d F Y', strtotime($data['tanggal_perpanjang'])) : '-';
    $alasanExtend      = $data['alasan_extend'] ?: '-';

    // Validasi email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("[EMAIL] send-extend-rejected: Email tidak valid untuk extend #{$extendId}: '{$email}'");
        return false;
    }

    // Buat body email
    $bodyHtml = '
        <p>Halo <strong>' . htmlspecialchars($nama) . '</strong>,</p>
        
        <div class="warning-box">
            <strong>❌ Perpanjangan Ditolak</strong><br>
            Mohon maaf, permintaan perpanjangan masa pinjam Anda telah ditolak.
        </div>
        
        <p>Detail perpanjangan yang ditolak:</p>
        
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
                <td>Tanggal Kembali Saat Ini</td>
                <td>' . htmlspecialchars($tglKembaliSaatIni) . '</td>
            </tr>
            <tr>
                <td>Tanggal Perpanjangan Diminta</td>
                <td>' . htmlspecialchars($tglPerpanjang) . '</td>
            </tr>
            <tr>
                <td>Alasan Pengajuan</td>
                <td>' . htmlspecialchars($alasanExtend) . '</td>
            </tr>
        </table>
        
        <p>Barang wajib dikembalikan sesuai tanggal kembali yang berlaku saat ini.</p>
        <p>Silakan hubungi admin untuk informasi lebih lanjut.</p>
        
        <p>Terima kasih.</p>';

    $subject  = 'Perpanjangan Ditolak - ' . $kode;
    $fullHtml = buildEmailTemplate('❌ Perpanjangan Ditolak', $bodyHtml);

    $result = sendEmail($email, $subject, $fullHtml, $nama);

    if ($result) {
        error_log("[EMAIL] send-extend-rejected: Berhasil kirim ke {$email} untuk extend #{$extendId}");
        echo "EMAIL PENOLAKAN TERKIRIM";
    } else {
        error_log("[EMAIL] send-extend-rejected: Gagal kirim ke {$email} untuk extend #{$extendId}");
    }

    return $result;
}

// ============================================================
// TEST MODE: Jalankan langsung via browser/CLI dengan ?id=xxx
// ============================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'send-extend-rejected.php') {
    if (php_sapi_name() === 'cli') {
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    require_once __DIR__ . '/../koneksi.php';

    $id = $_GET['id'] ?? ($argv[1] ?? null);

    if (!$id) {
        echo "Usage: send-extend-rejected.php?id=EXTEND_ID\n";
        exit;
    }

    echo "Mengirim email extend rejected untuk extend #{$id}...\n";
    $result = sendExtendRejectedEmail($conn, (int)$id);
    echo $result ? "\n✅ Email berhasil dikirim!\n" : "\n❌ Email gagal dikirim.\n";
}
