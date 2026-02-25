<?php
require_once "../koneksi.php";
header('Content-Type: application/json');
require_once "../session-helper.php";

// Validate user role - coba dari session dulu, jika tidak ada cek dari header
$user_role = $_SESSION['user_role'] ?? null;

// Jika tidak ada session, cek dari Authorization header (token/role dari client)
if (!$user_role && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
    if (preg_match('/Bearer\s+(\w+)/', $auth, $m)) {
        // Decode atau validate token jika ada
        $user_role = $m[1]; 
    }
}

// Jika masih tidak ada, accept 'user' dari POST untuk flexibility
if (!$user_role) {
    $user_role = $_POST['role'] ?? 'user';
}

if ($user_role !== 'user') {
    http_response_code(401);
    echo json_encode([
        "status" => false,
        "message" => "Unauthorized: Role harus 'user'. Role saat ini: $user_role"
    ]);
    exit;
}

$user_id = $_POST['user_id'] ?? null;
$nama_peminjam = $_POST['nama_peminjam'] ?? null;
$nrp = $_POST['nrp'] ?? null;
$lokasi_umum = $_POST['lokasi_umum'] ?? '';
$rencana_pinjam = $_POST['rencana_pinjam'] ?? null;
$rencana_kembali = $_POST['rencana_kembali'] ?? null;
$catatan = $_POST['catatan'] ?? '';

// Cast user_id ke integer
$user_id = intval($user_id);

// Ensure string values
$nama_peminjam = strval($nama_peminjam);
$nrp = strval($nrp);
$rencana_pinjam = strval($rencana_pinjam);
$rencana_kembali = strval($rencana_kembali);

// Parse array inputs dengan benar
$barang = [];
$lokasi = [];

// Generate values yang akan digunakan
$kode = "PMJ-" . time();

if (isset($_POST['barang']) && is_array($_POST['barang'])) {
    foreach ($_POST['barang'] as $id => $val) {
        $barang[intval($id)] = intval($val);
    }
}

if (isset($_POST['lokasi']) && is_array($_POST['lokasi'])) {
    foreach ($_POST['lokasi'] as $id => $val) {
        $lokasi[intval($id)] = $val;
    }
}

foreach ($_POST as $key => $value) {
    if (!is_string($key)) continue;
    if (preg_match('/^barang\\[(\\d+)\\]$/', $key, $m)) {
        $id = intval($m[1]);
        if (!isset($barang[$id])) $barang[$id] = intval($value);
    } elseif (preg_match('/^lokasi\\[(\\d+)\\]$/', $key, $m)) {
        $id = intval($m[1]);
        if (!isset($lokasi[$id])) $lokasi[$id] = $value;
    }
}

// Validasi data required
if (empty($user_id) || empty($nama_peminjam) || empty($nrp) || empty($rencana_pinjam) || empty($rencana_kembali)) {
    echo json_encode([
        "status" => false,
        "message" => "Data required tidak lengkap. Pastikan semua field terisi."
    ]);
    exit;
}

// Validasi: tanggal peminjaman tidak boleh kurang dari hari ini
$today = date('Y-m-d');
if (strtotime($rencana_pinjam) < strtotime($today)) {
    echo json_encode([
        "status" => false,
        "message" => "Tanggal peminjaman tidak boleh kurang dari tanggal hari ini ($today)"
    ]);
    exit;
}

// Validasi: tanggal pengembalian tidak boleh kurang dari tanggal peminjaman
if (strtotime($rencana_kembali) < strtotime($rencana_pinjam)) {
    echo json_encode([
        "status" => false,
        "message" => "Tanggal pengembalian tidak boleh kurang dari tanggal peminjaman"
    ]);
    exit;
}

// Validasi ada minimal 1 barang
if (empty($barang) || array_sum($barang) == 0) {
    echo json_encode([
        "status" => false,
        "message" => "Pilih minimal 1 barang untuk dipinjam."
    ]);
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO peminjaman
        (kode_peminjaman, user_id, nama_peminjam, nrp, lokasi_umum, tanggal_pinjam, rencana_kembali, status, catatan)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $status = "Menunggu Persetujuan";
    $stmt->bind_param("sisssssss", $kode, $user_id, $nama_peminjam, $nrp, $lokasi_umum, $rencana_pinjam, $rencana_kembali, $status, $catatan);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $peminjaman_id = $conn->insert_id;

    foreach ($barang as $barang_id => $jumlah) {
        $lokasi_item = $lokasi[$barang_id] ?? '';

        if ($jumlah > 0) {
            // Validasi stok tersedia dan safety stock sebelum mengurangi
            $stmt_cek = $conn->prepare("SELECT nama_barang, stok_tersedia, safety_stock FROM barang WHERE id = ?");
            $stmt_cek->bind_param("i", $barang_id);
            $stmt_cek->execute();
            $result_cek = $stmt_cek->get_result();
            $barang_data = $result_cek->fetch_assoc();

            if (!$barang_data) {
                throw new Exception("Barang dengan ID $barang_id tidak ditemukan");
            }

            $stok_tersedia = intval($barang_data['stok_tersedia']);
            $safety_stock = intval($barang_data['safety_stock']);
            $nama_brg = $barang_data['nama_barang'];

            // Cek apakah jumlah yang diminta melebihi stok tersedia
            if ($jumlah > $stok_tersedia) {
                throw new Exception("Jumlah pinjam ($jumlah) untuk '$nama_brg' melebihi stok tersedia ($stok_tersedia)");
            }

            // Cek apakah stok setelah dipinjam akan di bawah 0
            $stok_setelah = $stok_tersedia - $jumlah;
            if ($stok_setelah < 0) {
                throw new Exception("Stok '$nama_brg' tidak mencukupi. Stok tersedia: $stok_tersedia, diminta: $jumlah");
            }

            $stmt_detail = $conn->prepare("
                INSERT INTO detail_peminjaman
                (peminjaman_id, barang_id, lokasi, jumlah, kondisi_pinjam)
                VALUES (?, ?, ?, ?, ?)
            ");
            $kondisi_pinjam = 'Baik';
            $stmt_detail->bind_param("iisis", $peminjaman_id, $barang_id, $lokasi_item, $jumlah, $kondisi_pinjam);
            if (!$stmt_detail->execute()) {
                throw new Exception("Insert detail failed: " . $stmt_detail->error);
            }

            // Kurangi stok dengan prepared statement (aman dari SQL injection)
            $stmt_update = $conn->prepare("
                UPDATE barang
                SET stok_tersedia = stok_tersedia - ?
                WHERE id = ? AND stok_tersedia >= ?
            ");
            $stmt_update->bind_param("iii", $jumlah, $barang_id, $jumlah);
            if (!$stmt_update->execute()) {
                throw new Exception("Update stok failed: " . $stmt_update->error);
            }
            if ($stmt_update->affected_rows === 0) {
                throw new Exception("Gagal mengurangi stok '$nama_brg'. Stok mungkin sudah berubah.");
            }
        }
    }

    $conn->commit();
    
    // ============================================================
    // Kirim email notifikasi setelah peminjaman berhasil dibuat
    // ============================================================
    try {
        require_once __DIR__ . '/../email/send-pinjam-request.php';
        sendPinjamRequestEmail($conn, $peminjaman_id);
    } catch (Exception $emailEx) {
        error_log("[EMAIL ERROR] request-peminjaman: " . $emailEx->getMessage());
        // Email error tidak perlu menggagalkan response, hanya log saja
    }
    
    echo json_encode(["status" => true]);
} catch (Exception $e) {
    error_log("ERROR in request-peminjaman.php: " . $e->getMessage());
    $conn->rollback();
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>
