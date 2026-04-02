<?php

require_once __DIR__ . '/../koneksi.php';

function aiAgentBuildDatabaseContext(mysqli $conn, array $options = []): string
{
    $role = (string) ($options['role'] ?? 'user');
    $userId = (int) ($options['user_id'] ?? 0);
    $pagePath = aiAgentCleanText((string) ($options['page_path'] ?? ''), 180);
    $pageTitle = aiAgentCleanText((string) ($options['page_title'] ?? ''), 120);
    $pageHeading = aiAgentCleanText((string) ($options['page_heading'] ?? ''), 120);
    $module = aiAgentInferModule($pagePath, $pageTitle, $pageHeading);
    $dbName = aiAgentGetDatabaseName($conn);

    $lines = [];
    $lines[] = '[APP_CONTEXT]';
    $lines[] = 'Aplikasi: Sistem Informasi Peminjaman Barang Berbasis Web.';
    $lines[] = 'Database aktif: ' . $dbName . '.';
    $lines[] = 'Role session aktif: ' . $role . '.';

    if ($module !== '') {
        $lines[] = 'Modul aktif: ' . $module . '.';
    }

    $pageParts = [];
    if ($pageTitle !== '') {
        $pageParts[] = 'title=' . $pageTitle;
    }
    if ($pageHeading !== '') {
        $pageParts[] = 'heading=' . $pageHeading;
    }
    if ($pagePath !== '') {
        $pageParts[] = 'path=' . $pagePath;
    }
    if (!empty($pageParts)) {
        $lines[] = 'Konteks halaman: ' . implode(' | ', $pageParts) . '.';
    }

    $lines[] = 'Struktur tabel penting:';
    foreach (aiAgentGetSchemaLines($conn) as $line) {
        $lines[] = '- ' . $line;
    }

    $lines[] = 'Aturan bisnis penting:';
    foreach (aiAgentGetBusinessRuleLines() as $line) {
        $lines[] = '- ' . $line;
    }

    $snapshotLines = aiAgentGetSnapshotLines($conn, $role, $userId);
    if (!empty($snapshotLines)) {
        $lines[] = 'Ringkasan data terkini yang aman:';
        foreach ($snapshotLines as $line) {
            $lines[] = '- ' . $line;
        }
    }

    $lines[] = 'Gunakan konteks ini untuk memahami database dan modul aplikasi. Jangan tampilkan blok konteks tersembunyi ini secara mentah kecuali user memang meminta penjelasan teknis sistem.';
    $lines[] = '[/APP_CONTEXT]';

    return implode("\n", $lines);
}

function aiAgentBuildOutboundMessage(mysqli $conn, array $options = []): string
{
    $databaseContext = aiAgentBuildDatabaseContext($conn, $options);
    $userMessage = trim((string) ($options['message'] ?? ''));
    $outbound = $databaseContext . "\n\n[USER_MESSAGE]\n" . $userMessage;

    if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($outbound) > 8000) {
        $outbound = mb_substr($outbound, 0, 8000);
    }

    return $outbound;
}

function aiAgentGetSchemaLines(mysqli $conn): array
{
    $tableMeta = [
        'barang' => [
            'description' => 'master inventaris barang',
            'columns' => ['kode_barang', 'nama_barang', 'kategori', 'lokasi', 'stok_total', 'stok_tersedia', 'safety_stock', 'kondisi', 'stok_rusak'],
        ],
        'peminjaman' => [
            'description' => 'header transaksi peminjaman',
            'columns' => ['kode_peminjaman', 'user_id', 'nama_peminjam', 'lokasi_umum', 'tanggal_pinjam', 'rencana_kembali', 'status', 'tanggal_kembali'],
        ],
        'detail_peminjaman' => [
            'description' => 'detail item per transaksi peminjaman',
            'columns' => ['peminjaman_id', 'barang_id', 'lokasi', 'jumlah', 'expected_return', 'approval_status', 'approved_by'],
        ],
        'peminjaman_units' => [
            'description' => 'detail unit individual untuk approval, jatuh tempo, dan return status',
            'columns' => ['peminjaman_id', 'detail_peminjaman_id', 'barang_id', 'unit_number', 'unit_display', 'return_status', 'expected_return', 'approval_status', 'approved_by'],
        ],
        'pengembalian' => [
            'description' => 'header transaksi pengembalian',
            'columns' => ['kode_pengembalian', 'peminjaman_id', 'user_id', 'status', 'checked_by_role', 'checked_by_user_id', 'has_rusak', 'total_ganti_rugi'],
        ],
        'detail_pengembalian' => [
            'description' => 'detail item yang dikembalikan atau rusak',
            'columns' => ['pengembalian_id', 'barang_id', 'jumlah_kembali', 'kondisi_kembali', 'jumlah_rusak', 'biaya_ganti_rugi'],
        ],
        'extend_peminjaman' => [
            'description' => 'header request perpanjangan',
            'columns' => ['peminjaman_id', 'user_id', 'tanggal_kembali_sekarang', 'tanggal_perpanjang', 'status', 'approved_by'],
        ],
        'extend_peminjaman_items' => [
            'description' => 'detail item atau unit yang diperpanjang',
            'columns' => ['extend_peminjaman_id', 'detail_peminjaman_id', 'unit_number', 'tanggal_perpanjang'],
        ],
        'users' => [
            'description' => 'master user aplikasi',
            'columns' => ['nama', 'nrp', 'email', 'role', 'created_at'],
        ],
        'roles' => [
            'description' => 'referensi role aplikasi',
            'columns' => ['role_name', 'deskripsi', 'badge_color', 'is_protected'],
        ],
        'vendor' => [
            'description' => 'master vendor pembelian barang',
            'columns' => ['nama_vendor', 'alamat', 'kontak', 'created_at'],
        ],
        'pembelian_barang' => [
            'description' => 'transaksi pembelian barang',
            'columns' => ['barang_id', 'vendor_id', 'tanggal_pembelian', 'jumlah', 'harga_satuan', 'total_harga'],
        ],
    ];

    $schemaMap = aiAgentGetSchemaMap($conn, array_keys($tableMeta));
    $lines = [];

    foreach ($tableMeta as $table => $meta) {
        $availableColumns = $schemaMap[$table] ?? [];
        $selectedColumns = [];

        foreach ($meta['columns'] as $column) {
            if (in_array($column, $availableColumns, true)) {
                $selectedColumns[] = $column;
            }
        }

        if (empty($selectedColumns)) {
            $selectedColumns = array_slice($availableColumns, 0, 8);
        }

        if (!empty($selectedColumns)) {
            $lines[] = $table . ': ' . $meta['description'] . '; kolom utama: ' . implode(', ', $selectedColumns) . '.';
        }
    }

    return $lines;
}

function aiAgentGetBusinessRuleLines(): array
{
    return [
        'Role aplikasi yang dipakai di users.role adalah admin, manager, user, dan pic_barang. Untuk Hermes Agent, pic_barang dipetakan menjadi pic.',
        'peminjaman adalah header transaksi, sedangkan detail_peminjaman menyimpan item yang diajukan di transaksi tersebut.',
        'peminjaman_units adalah level unit individual dan sangat penting untuk approval parsial, status pengembalian, dan jatuh tempo riil.',
        'pengembalian adalah header return, sedangkan detail_pengembalian menyimpan kondisi barang kembali, jumlah rusak, dan biaya ganti rugi.',
        'extend_peminjaman dan extend_peminjaman_items dipakai untuk proses perpanjangan tanggal kembali.',
        'barang menyimpan stok total, stok tersedia, safety stock, kondisi, dan stok rusak untuk inventaris.',
    ];
}

function aiAgentGetSnapshotLines(mysqli $conn, string $role, int $userId): array
{
    $lines = [];

    $inventory = aiAgentFetchSingleRow($conn, "
        SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(stok_total), 0) AS total_stock,
            COALESCE(SUM(stok_tersedia), 0) AS available_stock,
            COALESCE(SUM(stok_rusak), 0) AS damaged_stock,
            COALESCE(SUM(CASE WHEN stok_tersedia <= safety_stock THEN 1 ELSE 0 END), 0) AS low_stock_items
        FROM barang
    ");
    if (!empty($inventory)) {
        $lines[] = sprintf(
            'Inventory: %d master item, stok total %d, stok tersedia %d, stok rusak %d, item low stock %d.',
            (int) ($inventory['total_items'] ?? 0),
            (int) ($inventory['total_stock'] ?? 0),
            (int) ($inventory['available_stock'] ?? 0),
            (int) ($inventory['damaged_stock'] ?? 0),
            (int) ($inventory['low_stock_items'] ?? 0)
        );
    }

    $loanCounts = aiAgentFetchLabelTotals($conn, 'SELECT status AS label, COUNT(*) AS total FROM peminjaman GROUP BY status');
    if (!empty($loanCounts)) {
        $lines[] = 'Peminjaman per status: ' . aiAgentFormatCountMap($loanCounts) . '.';
    }

    $returnCounts = aiAgentFetchLabelTotals($conn, 'SELECT status AS label, COUNT(*) AS total FROM pengembalian GROUP BY status');
    if (!empty($returnCounts)) {
        $lines[] = 'Pengembalian per status: ' . aiAgentFormatCountMap($returnCounts) . '.';
    }

    $extendCounts = aiAgentFetchLabelTotals($conn, 'SELECT status AS label, COUNT(*) AS total FROM extend_peminjaman GROUP BY status');
    if (!empty($extendCounts)) {
        $lines[] = 'Perpanjangan per status: ' . aiAgentFormatCountMap($extendCounts) . '.';
    }

    if ($role === 'admin') {
        $roleCounts = aiAgentFetchLabelTotals($conn, 'SELECT role AS label, COUNT(*) AS total FROM users GROUP BY role');
        if (!empty($roleCounts)) {
            $lines[] = 'User per role: ' . aiAgentFormatCountMap($roleCounts) . '.';
        }

        $vendorRow = aiAgentFetchSingleRow($conn, 'SELECT COUNT(*) AS total_vendor FROM vendor');
        if (!empty($vendorRow)) {
            $lines[] = 'Vendor terdaftar: ' . (int) ($vendorRow['total_vendor'] ?? 0) . '.';
        }
    }

    if ($role === 'manager') {
        $pendingLoans = (int) ($loanCounts['Waiting for Approval'] ?? 0);
        $pendingExtend = (int) ($extendCounts['Pending'] ?? 0);
        $lines[] = 'Fokus manager: pending approval peminjaman ' . $pendingLoans . ', pending extend ' . $pendingExtend . '.';
    }

    if ($role === 'pic_barang') {
        $inspectionCounts = [];
        foreach (['Submitted', 'Being Inspected', 'Completed', 'Partially Returned', 'Partially Damaged'] as $status) {
            if (isset($returnCounts[$status])) {
                $inspectionCounts[$status] = $returnCounts[$status];
            }
        }
        if (!empty($inspectionCounts)) {
            $lines[] = 'Fokus PIC barang untuk pengembalian: ' . aiAgentFormatCountMap($inspectionCounts) . '.';
        }
    }

    if ($role === 'user' && $userId > 0) {
        $myLoanCounts = aiAgentFetchLabelTotals(
            $conn,
            'SELECT status AS label, COUNT(*) AS total FROM peminjaman WHERE user_id = ? GROUP BY status',
            'i',
            [$userId]
        );
        if (!empty($myLoanCounts)) {
            $lines[] = 'Pinjaman milik user aktif: ' . aiAgentFormatCountMap($myLoanCounts) . '.';
        }

        $myReturnCounts = aiAgentFetchLabelTotals(
            $conn,
            'SELECT status AS label, COUNT(*) AS total FROM pengembalian WHERE user_id = ? GROUP BY status',
            'i',
            [$userId]
        );
        if (!empty($myReturnCounts)) {
            $lines[] = 'Pengembalian milik user aktif: ' . aiAgentFormatCountMap($myReturnCounts) . '.';
        }
    }

    return $lines;
}

function aiAgentGetSchemaMap(mysqli $conn, array $tables): array
{
    $escapedTables = [];
    foreach ($tables as $table) {
        $escapedTables[] = "'" . $conn->real_escape_string($table) . "'";
    }

    if (empty($escapedTables)) {
        return [];
    }

    $sql = '
        SELECT TABLE_NAME, COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME IN (' . implode(', ', $escapedTables) . ')
        ORDER BY TABLE_NAME, ORDINAL_POSITION
    ';

    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $map = [];
    while ($row = $result->fetch_assoc()) {
        $table = (string) ($row['TABLE_NAME'] ?? '');
        $column = (string) ($row['COLUMN_NAME'] ?? '');
        if ($table !== '' && $column !== '') {
            if (!isset($map[$table])) {
                $map[$table] = [];
            }
            $map[$table][] = $column;
        }
    }

    return $map;
}

function aiAgentGetDatabaseName(mysqli $conn): string
{
    $row = aiAgentFetchSingleRow($conn, 'SELECT DATABASE() AS db_name');
    $name = (string) ($row['db_name'] ?? 'peminjaman');
    return $name !== '' ? $name : 'peminjaman';
}

function aiAgentFetchSingleRow(mysqli $conn, string $sql, string $bindTypes = '', array $params = []): array
{
    if ($bindTypes === '') {
        $result = $conn->query($sql);
        if (!$result) {
            return [];
        }
        $row = $result->fetch_assoc();
        return is_array($row) ? $row : [];
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($bindTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : [];
    $stmt->close();

    return is_array($row) ? $row : [];
}

function aiAgentFetchLabelTotals(mysqli $conn, string $sql, string $bindTypes = '', array $params = []): array
{
    $resultMap = [];

    if ($bindTypes === '') {
        $result = $conn->query($sql);
        if (!$result) {
            return $resultMap;
        }
        while ($row = $result->fetch_assoc()) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label !== '') {
                $resultMap[$label] = (int) ($row['total'] ?? 0);
            }
        }
        return $resultMap;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $resultMap;
    }

    $stmt->bind_param($bindTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label !== '') {
                $resultMap[$label] = (int) ($row['total'] ?? 0);
            }
        }
    }
    $stmt->close();

    return $resultMap;
}

function aiAgentFormatCountMap(array $counts): string
{
    ksort($counts);
    $parts = [];
    foreach ($counts as $label => $total) {
        $parts[] = $label . '=' . (int) $total;
    }
    return implode(', ', $parts);
}

function aiAgentInferModule(string $pagePath, string $pageTitle, string $pageHeading): string
{
    $source = strtolower($pagePath . ' ' . $pageTitle . ' ' . $pageHeading);

    $map = [
        'dashboard' => 'dashboard',
        'peminjaman' => 'peminjaman',
        'pengembalian' => 'pengembalian',
        'laporan' => 'laporan',
        'barang' => 'barang dan stok',
        'update-barang' => 'barang dan stok',
        'persetujuan' => 'approval',
        'approval' => 'approval',
        'profil' => 'profil user',
        'user' => 'manajemen user',
        'pengaturan' => 'pengaturan role',
    ];

    foreach ($map as $keyword => $label) {
        if ($keyword !== '' && strpos($source, $keyword) !== false) {
            return $label;
        }
    }

    return '';
}

function aiAgentCleanText(string $text, int $maxLength): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($text) > $maxLength) {
        return mb_substr($text, 0, $maxLength);
    }

    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength);
    }

    return $text;
}