<?php
/**
 * ============================================================
 * EMAIL: Perpanjangan Disetujui
 * ============================================================
 * 
 * Email dikirim ke SEMUA pihak terkait:
 *   - USER (pemilik peminjaman)
 *   - ADMIN (semua admin)
 *   - PIC_BARANG (semua PIC)
 *   - PELAKU AKSI (dari SESSION)
 * 
 * File   : /PROJECT/api/email/send-extend-approved.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi perpanjangan disetujui ke SEMUA pihak terkait
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
        error_log("[EMAIL] send-extend-approved: Tidak ada penerima valid untuk peminjaman #{$peminjamanId}");
        return false;
    }

    // Buat body email
    $bodyHtml = '
        <p>Halo,</p>
        
        <div class="success-box">
            <strong>✅ Perpanjangan Disetujui!</strong><br>
            Permintaan perpanjangan dari <strong>' . htmlspecialchars($nama) . '</strong> telah disetujui.
        </div>
        
        <p>Detail perpanjangan:</p>
        
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
                <td>Tanggal Kembali Baru</td>
                <td><strong style="color: #2563eb;">' . htmlspecialchars($tglKembaliBaru) . '</strong></td>
            </tr>
        </table>
        
        <p>Mohon pastikan barang dikembalikan sebelum tanggal kembali yang baru.</p>
        
        <p>Terima kasih.</p>';

    $subject  = 'Perpanjangan Disetujui - ' . $kode;
    $fullHtml = buildEmailTemplate('✅ Perpanjangan Disetujui', $bodyHtml);

    // ============================================================
    // KIRIM EMAIL MENGGUNAKAN LOOP KE SEMUA PENERIMA
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-extend-approved: EMAIL TERKIRIM KE: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-extend-approved: EMAIL GAGAL KE: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-extend-approved: Total terkirim {$totalSent}/" . count($recipients) . " untuk peminjaman #{$peminjamanId}");
    return $totalSent > 0;
}

// ============================================================
// TEST MODE
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

    echo "Mengirim email extend approved untuk peminjaman #{$id} ke SEMUA pihak...\n";
    $result = sendExtendApprovedEmail($conn, (int)$id);
    echo $result ? "✅ Email berhasil dikirim ke semua pihak!\n" : "❌ Email gagal dikirim.\n";
}
