<?php
/**
 * ============================================================
 * EMAIL: Perpanjangan Ditolak (Extend → Rejected)
 * ============================================================
 * 
 * Email dikirim ke SEMUA pihak terkait:
 *   - USER (pemilik peminjaman)
 *   - ADMIN (semua admin)
 *   - PIC_BARANG (semua PIC)
 *   - PELAKU AKSI (dari SESSION)
 * 
 * File   : /PROJECT/api/email/send-extend-rejected.php
 * 
 * ============================================================
 */

require_once __DIR__ . '/email-functions.php';

/**
 * Kirim email notifikasi perpanjangan ditolak ke SEMUA pihak terkait
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
        error_log("[EMAIL] send-extend-rejected: Tidak ada penerima valid untuk extend #{$extendId}");
        return false;
    }

    // Buat body email
    $bodyHtml = '
        <p>Halo,</p>
        
        <div class="warning-box">
            <strong>❌ Perpanjangan Ditolak</strong><br>
            Permintaan perpanjangan dari <strong>' . htmlspecialchars($nama) . '</strong> telah ditolak.
        </div>
        
        <p>Detail perpanjangan yang ditolak:</p>
        
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
        
        <p>Terima kasih.</p>';

    $subject  = 'Perpanjangan Ditolak - ' . $kode;
    $fullHtml = buildEmailTemplate('❌ Perpanjangan Ditolak', $bodyHtml);

    // ============================================================
    // KIRIM EMAIL MENGGUNAKAN LOOP KE SEMUA PENERIMA
    // ============================================================
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $fullHtml, $r['nama'])) {
            error_log("[EMAIL] send-extend-rejected: EMAIL TERKIRIM KE: " . $r['email']);
            $totalSent++;
        } else {
            error_log("[EMAIL] send-extend-rejected: EMAIL GAGAL KE: " . $r['email']);
        }
    }

    error_log("[EMAIL] send-extend-rejected: Total terkirim {$totalSent}/" . count($recipients) . " untuk extend #{$extendId}");
    return $totalSent > 0;
}

// ============================================================
// TEST MODE
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

    echo "Mengirim email extend rejected untuk extend #{$id} ke SEMUA pihak...\n";
    $result = sendExtendRejectedEmail($conn, (int)$id);
    echo $result ? "✅ Email berhasil dikirim ke semua pihak!\n" : "❌ Email gagal dikirim.\n";
}
