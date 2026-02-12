<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once "../koneksi.php";
header('Content-Type: application/json');
require_once "../session-helper.php";

// Validate user role
try {
    SessionValidator::requireRole(['user']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status" => false,
        "message" => "Unauthorized: " . $e->getMessage()
    ]);
    exit;
}

$user_id = $_GET['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode([
        "status" => false,
        "message" => "User ID diperlukan"
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.kode_peminjaman,
            p.user_id,
            p.nama_peminjam,
            p.nrp,
            p.tanggal_pinjam,
            p.rencana_kembali,
            p.tanggal_kembali,
            p.status,
            p.catatan
        FROM peminjaman p
        WHERE p.user_id = ?
        ORDER BY p.tanggal_pinjam DESC
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $stmt_detail = $conn->prepare("
            SELECT
                b.id AS barang_id,
                b.nama_barang,
                dp.jumlah,
                dp.lokasi,
                dp.kondisi_pinjam
            FROM detail_peminjaman dp
            LEFT JOIN barang b ON dp.barang_id = b.id
            WHERE dp.peminjaman_id = ?
        ");
        $stmt_detail->bind_param("i", $row['id']);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();

        $barang_list = [];
        while ($detail_row = $result_detail->fetch_assoc()) {
            $barang_list[] = [
                'barang_id' => (int)$detail_row['barang_id'],
                'nama' => $detail_row['nama_barang'],
                'jumlah' => (int)$detail_row['jumlah'],
                'lokasi' => $detail_row['lokasi'],
                'kondisi' => $detail_row['kondisi_pinjam'],
                // default return inspection fields
                'jumlah_kembali' => 0,
                'jumlah_rusak' => 0,
                'kondisi_kembali' => ''
            ];
        }

        // Merge pengembalian inspection info if exists
        $qk = $conn->prepare("SELECT id, status FROM pengembalian WHERE peminjaman_id = ? ORDER BY id DESC LIMIT 1");
        $qk->bind_param("i", $row['id']);
        $qk->execute();
        $hk = $qk->get_result()->fetch_assoc();
        $pengembalian_status = null;
        $has_pengembalian = false;
        if ($hk) {
            $has_pengembalian = true;
            $peng_id = (int)$hk['id'];
            $pengembalian_status = $hk['status'];
            $sd = $conn->prepare("SELECT barang_id, jumlah_kembali, kondisi_kembali, jumlah_rusak FROM detail_pengembalian WHERE pengembalian_id = ?");
            $sd->bind_param("i", $peng_id);
            $sd->execute();
            $rd = $sd->get_result();
            $map = [];
            while ($r = $rd->fetch_assoc()) {
                $map[(int)$r['barang_id']] = $r;
            }
            $total_items = 0;
            $total_rusak = 0;
            foreach ($barang_list as &$bi) {
                $bid = (int)$bi['barang_id'];
                $total_items += $bi['jumlah'];
                if (isset($map[$bid])) {
                    $bi['jumlah_kembali'] = (int)$map[$bid]['jumlah_kembali'];
                    $bi['jumlah_rusak'] = (int)$map[$bid]['jumlah_rusak'];
                    $bi['kondisi_kembali'] = $map[$bid]['kondisi_kembali'];
                    $total_rusak += (int)$map[$bid]['jumlah_rusak'];
                }
            }
            // compute a user-friendly status override
            if ($total_rusak > 0) {
                if ($total_rusak < $total_items) {
                    $row['status'] = 'Sebagian Rusak';
                    $row['status_en'] = 'Partially Damaged';
                } else {
                    $row['status'] = 'Semua Rusak';
                    $row['status_en'] = 'Fully Damaged';
                }
            } else {
                $row['status_en'] = $row['status'];
            }
        }

        $data[] = [
            'id' => $row['id'],
            'kode_peminjaman' => $row['kode_peminjaman'],
            'user_id' => $row['user_id'],
            'nama_peminjam' => $row['nama_peminjam'],
            'nrp' => $row['nrp'],
            'tanggal_pinjam' => $row['tanggal_pinjam'],
            'rencana_kembali' => $row['rencana_kembali'],
            'tanggal_kembali' => $row['tanggal_kembali'],
            'status' => $row['status'],
            'catatan' => $row['catatan'],
            'detail_barang' => $barang_list
        ];
        if (isset($pengembalian_status)) $data[count($data)-1]['pengembalian_status'] = $pengembalian_status;
        $data[count($data)-1]['has_pengembalian'] = $has_pengembalian;
    }

    echo json_encode([
        "status" => true,
        "data" => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>
