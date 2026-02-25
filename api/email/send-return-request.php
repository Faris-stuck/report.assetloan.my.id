<?php
/**
 * ============================================================
 * EMAIL: Permintaan Return (Status → Proses Return)
 * ============================================================
 * 
 * Email dikirim ke SEMUA pihak terkait:
 *   - USER (pemilik peminjaman, pelaku aksi)
 *   - ADMIN (semua admin)
 *   - PIC_BARANG (semua PIC)
 *   - PELAKU AKSI (dari SESSION)
 * 
 * File   : /PROJECT/api/email/send-return-request.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi return request ke SEMUA pihak terkait
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
        error_log("[EMAIL] send-return-request: Tidak ada penerima valid untuk peminjaman #{$peminjamanId}");
        return false;
    }

    $subject = 'Permintaan Pengembalian Barang - ' . $kode;

    // Buat body email
    $bodyHtml = '
        <p>Halo,</p>
        
        <div class="warning-box">
            <strong>📦 Permintaan Pengembalian Barang</strong><br>
            <strong>' . htmlspecialchars($namaUser) . '</strong> telah mengajukan pengembalian barang.
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

    // ============================================================
    // KIRIM EMAIL MENGGUNAKAN LOOP KE SEMUA PENERIMA
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-return-request: EMAIL TERKIRIM KE: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-return-request: EMAIL GAGAL KE: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-return-request: Total terkirim {$totalSent}/" . count($recipients) . " untuk peminjaman #{$peminjamanId}");
    return $totalSent > 0;
}

// ============================================================
// TEST MODE
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

    echo "Mengirim email return request untuk peminjaman #{$id} ke SEMUA pihak...\n";
    $result = sendReturnRequestEmail($conn, (int)$id);
    echo $result ? "✅ Email berhasil dikirim ke semua pihak!\n" : "❌ Email gagal dikirim.\n";
}
