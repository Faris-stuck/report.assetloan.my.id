<?php

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/runtime-helper.php';

function aiAgentBuildGroundingContext(mysqli $conn, array $options = []): string
{
    $role = (string) ($options['role'] ?? 'user');
    $userId = (int) ($options['user_id'] ?? 0);
    $message = aiAgentCleanText((string) ($options['message'] ?? ''), 1600);
    $pagePath = aiAgentCleanText((string) ($options['page_path'] ?? ''), 180);
    $pageTitle = aiAgentCleanText((string) ($options['page_title'] ?? ''), 120);
    $pageHeading = aiAgentCleanText((string) ($options['page_heading'] ?? ''), 120);
    $module = aiAgentInferModule($pagePath, $pageTitle, $pageHeading);
    $focusScopes = aiAgentResolveFocusScopes($message, $module, $pagePath, $pageTitle, $pageHeading);
    $dbName = aiAgentGetDatabaseName($conn);
    $agentRole = $role === 'pic_barang' ? 'pic' : $role;

    $lines = [];
    $lines[] = '[HERMES_GROUNDING]';
    $lines[] = '[GROUNDING_RULES]';

    $groundingRules = [
        'Gunakan hanya fakta yang ada pada konteks PROJECT, konteks database live, riwayat chat, dan pertanyaan user.',
        'Jangan mengarang nama file, folder, tabel, kolom, status, endpoint, role, atau angka yang tidak muncul di konteks.',
        'Jika konteks belum cukup untuk jawaban yang sangat spesifik, katakan dengan eksplisit bahwa konteks belum cukup lalu sebutkan data, file, atau tabel yang dibutuhkan.',
        'Jika menjawab tentang proses bisnis, utamakan workflow yang benar-benar dipakai project ini, bukan praktik umum aplikasi lain.',
        'Jika menyebut data live, bedakan dari aturan kode atau inferensi. Jangan menyamakan snapshot data dengan aturan bisnis.',
    ];

    foreach ($groundingRules as $rule) {
        $lines[] = '- ' . $rule;
    }

    $lines[] = '[/GROUNDING_RULES]';
    $lines[] = '[SESSION_CONTEXT]';
    $lines[] = '- Nama project: Sistem Informasi Peminjaman Barang Berbasis Web.';
    $lines[] = '- Database aktif: ' . $dbName . '.';
    $lines[] = '- Role session aktif: ' . $role . '.';
    $lines[] = '- Role yang dipakai agent: ' . $agentRole . '.';

    if ($userId > 0) {
        $lines[] = '- Akun login aktif sudah tervalidasi untuk sesi ini.';
    }

    if ($module !== '') {
        $lines[] = '- Modul yang paling dekat dengan halaman aktif: ' . $module . '.';
    }

    if (!empty($focusScopes)) {
        $lines[] = '- Fokus konteks terpilih: ' . implode(', ', $focusScopes) . '.';
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
        $lines[] = '- Halaman aktif: ' . implode(' | ', $pageParts) . '.';
    }

    if ($message !== '') {
        $lines[] = '- Pertanyaan user saat ini: ' . $message . '.';
    }

    $lines[] = '[/SESSION_CONTEXT]';
    $lines[] = '[PROJECT_CONTEXT]';

    foreach (aiAgentGetProjectOverviewLines() as $line) {
        $lines[] = '- ' . $line;
    }

    foreach (aiAgentGetRelevantFileLines($focusScopes, $role) as $line) {
        $lines[] = '- ' . $line;
    }

    $lines[] = '[/PROJECT_CONTEXT]';
    $lines[] = '[WORKFLOW_CONTEXT]';

    foreach (aiAgentGetWorkflowLines($focusScopes) as $line) {
        $lines[] = '- ' . $line;
    }

    $lines[] = '[/WORKFLOW_CONTEXT]';
    $lines[] = '[DATABASE_CONTEXT]';
    $lines[] = '- Database ini berpusat pada inventaris barang, transaksi peminjaman, approval, pengembalian, perpanjangan, dan master user.';

    foreach (aiAgentGetSchemaLines($conn) as $line) {
        $lines[] = '- ' . $line;
    }

    foreach (aiAgentGetDatabaseRelationLines() as $line) {
        $lines[] = '- ' . $line;
    }

    foreach (aiAgentGetBusinessRuleLines() as $line) {
        $lines[] = '- ' . $line;
    }

    foreach (aiAgentGetStatusGlossaryLines() as $line) {
        $lines[] = '- ' . $line;
    }

    $lines[] = '[/DATABASE_CONTEXT]';
    $lines[] = '[LIVE_CONTEXT]';

    $snapshotLines = aiAgentGetSnapshotLines($conn, $role, $userId, $focusScopes);
    if (!empty($snapshotLines)) {
        foreach ($snapshotLines as $line) {
            $lines[] = '- ' . $line;
        }
    } else {
        $lines[] = '- Snapshot live tidak tersedia; bila user meminta angka spesifik, jelaskan bahwa data live belum berhasil dibaca.';
    }

    $lines[] = '[/LIVE_CONTEXT]';
    $lines[] = '[ANSWER_RULES]';

    $answerRules = [
        'Jika user bertanya lokasi implementasi fitur, sebut file atau folder yang relevan dari konteks PROJECT.',
        'Jika user bertanya data bisnis, utamakan snapshot live dan tabel yang relevan.',
        'Jika user bertanya status atau alur, gunakan kamus status dan workflow yang tersedia di konteks ini.',
        'Jika ada ketidakpastian, jawab jujur dengan frasa seperti "berdasarkan konteks yang tersedia" atau "konteks saat ini belum cukup".',
    ];

    foreach ($answerRules as $rule) {
        $lines[] = '- ' . $rule;
    }

    $lines[] = '[/ANSWER_RULES]';
    $lines[] = '[/HERMES_GROUNDING]';

    return implode("\n", $lines);
}

function aiAgentBuildDatabaseContext(mysqli $conn, array $options = []): string
{
    return aiAgentBuildGroundingContext($conn, $options);
}

function aiAgentBuildOutboundMessage(mysqli $conn, array $options = []): string
{
    $groundingContext = aiAgentBuildGroundingContext($conn, $options);
    $userMessage = trim((string) ($options['message'] ?? ''));
    $outbound = $groundingContext . "\n\n[USER_MESSAGE]\n" . $userMessage;

    if (aiAgentStringLength($outbound) > 12000) {
        $outbound = aiAgentStringSubstring($outbound, 0, 12000);
    }

    return $outbound;
}

function aiAgentGetProjectOverviewLines(): array
{
    return [
        'Root utama PROJECT: admin/, manager/, user/, pic-barang/, api/, assets/, config/, phpmailer/, peminjaman.sql, index.html, register.html, forgot-password.html.',
        'Role resmi aplikasi: admin, manager, user, dan pic_barang.',
        'Frontend admin memakai menu Dashboard, Item / Inventory, Item Loan, Item Return, dan Administrator.',
        'Di area admin, submenu yang tampak di navigasi adalah Grafik / Informasi, Item Data, Item Detail, Request Loan, List Loan, Approval, Return Loan, User List, dan Role List.',
        'Frontend manager memakai menu Dashboard, Approvals, dan Reports dengan submenu Dashboard, Pending Approval, Approved, Rejected, Borrowing Report, dan Stock Report.',
        'Frontend user memakai menu Dashboards, Borrowing, Return, dan History dengan submenu Dashboard, Request Borrowing, Borrowing Status, Request Return, dan Borrowing History.',
        'Frontend PIC barang memakai menu Dashboards, Update, dan Return dengan submenu Dashboard, Update Item, dan Return Item.',
        'Backend utama berada di api/auth, api/user, api/approver, api/admin, api/peminjaman, api/pengembalian, api/extend, api/barang, api/pic_barang, api/vendor, api/email, api/cron.',
        'Koneksi database dan kalkulasi status utama terpusat di api/koneksi.php, sedangkan kontrol session ada di api/session-helper.php.',
        'Widget AI dimuat global lewat assets/js/base-url.js, assets/js/ai-agent-widget.js, lalu request ke api/ai/chat.php yang menggunakan api/ai/context-helper.php.',
    ];
}

function aiAgentGetRelevantFileLines(array $focusScopes, string $role): array
{
    $lines = [];

    $corePaths = [
        'STRUKTUR_PROJECT_DAN_DATABASE.md',
        'peminjaman.sql',
        'api/koneksi.php',
        'api/session-helper.php',
        'api/ai/chat.php',
        'api/ai/context-helper.php',
        'assets/js/base-url.js',
        'assets/js/ai-agent-widget.js',
    ];

    $rolePathsMap = [
        'admin' => [
            'admin/dashboard.html',
            'admin/pengaturan.html',
            'admin/user/buat-user.html',
            'admin/barang/',
            'admin/peminjaman/',
            'admin/pengembalian/',
            'admin/laporan/',
        ],
        'manager' => [
            'manager/dashboard.html',
            'manager/persetujuan/',
            'manager/laporan/',
        ],
        'user' => [
            'user/dashboard.html',
            'user/profil.html',
            'user/riwayat.html',
            'user/peminjaman/',
            'user/pengembalian/',
        ],
        'pic_barang' => [
            'pic-barang/dashboard.html',
            'pic-barang/profil.html',
            'pic-barang/update-barang/',
            'pic-barang/pengembalian/',
        ],
    ];

    $scopePathsMap = [
        'dashboard' => [
            'admin/dashboard.html',
            'manager/dashboard.html',
            'user/dashboard.html',
            'pic-barang/dashboard.html',
            'api/admin/dashboard-stats.php',
            'api/approver/dashboard-stats.php',
            'api/user/dashboard-stats.php',
        ],
        'peminjaman' => [
            'user/peminjaman/ajukan-peminjaman.html',
            'user/peminjaman/status-peminjaman.html',
            'user/peminjaman/detail.html',
            'admin/peminjaman/data-peminjaman.html',
            'admin/peminjaman/detail-peminjaman.html',
            'admin/peminjaman/sedang-dipinjam.html',
            'api/user/request-peminjaman.php',
            'api/peminjaman/',
        ],
        'approval' => [
            'manager/persetujuan/menunggu-approval.html',
            'manager/persetujuan/disetujui.html',
            'manager/persetujuan/ditolak.html',
            'admin/peminjaman/admin-approval.html',
            'admin/peminjaman/menunggu-persetujuan.html',
            'api/approver/approve-items.php',
            'api/approver/list-by-status.php',
            'api/admin/approve.php',
            'api/admin/reject.php',
        ],
        'pengembalian' => [
            'user/pengembalian/ajukan-pengembalian.html',
            'pic-barang/pengembalian/pengembalian-barang.html',
            'admin/pengembalian/pengembalian-barang.html',
            'admin/pengembalian/barang-rusak.html',
            'api/pengembalian/inspect.php',
            'api/admin/process-return.php',
        ],
        'extend' => [
            'api/extend/request.php',
            'api/extend/',
        ],
        'barang' => [
            'admin/barang/',
            'pic-barang/update-barang/',
            'api/barang/',
            'api/vendor/',
        ],
        'laporan' => [
            'admin/laporan/',
            'manager/laporan/',
        ],
        'ai' => [
            'api/ai/',
            'assets/js/ai-agent-widget.js',
            'assets/css/ai-agent-widget.css',
            'config/ai_agent.example.php',
        ],
        'auth' => [
            'api/auth/login.php',
            'api/auth/logout.php',
            'api/auth/register.php',
            'api/auth/forgot-password.php',
            'api/auth/verify-session.php',
            'api/user/profile.php',
            'api/user/change_password.php',
        ],
        'users' => [
            'admin/user/buat-user.html',
            'admin/user/buat-user.php',
            'admin/user/buat-user-wrapper.php',
            'api/admin/roles.php',
        ],
    ];

    $coreDescription = aiAgentDescribeProjectPaths($corePaths, 8);
    if ($coreDescription !== '') {
        $lines[] = 'File inti sistem saat ini: ' . $coreDescription . '.';
    }

    $roleDescription = aiAgentDescribeProjectPaths($rolePathsMap[$role] ?? [], 8);
    if ($roleDescription !== '') {
        $lines[] = 'Halaman yang paling dekat dengan role aktif: ' . $roleDescription . '.';
    }

    $focusPaths = [];
    foreach ($focusScopes as $scope) {
        if (isset($scopePathsMap[$scope])) {
            $focusPaths = array_merge($focusPaths, $scopePathsMap[$scope]);
        }
    }
    $focusPaths = array_values(array_unique($focusPaths));

    $focusDescription = aiAgentDescribeProjectPaths($focusPaths, 10);
    if ($focusDescription !== '') {
        $lines[] = 'File atau folder yang paling relevan untuk pertanyaan ini: ' . $focusDescription . '.';
    }

    return $lines;
}

function aiAgentGetWorkflowLines(array $focusScopes): array
{
    $workflowMap = [
        'auth' => 'Autentikasi dan session berjalan melalui api/auth/login.php, api/auth/logout.php, api/auth/verify-session.php, lalu dipakai ulang oleh frontend melalui validasi session helper dan endpoint current user.',
        'dashboard' => 'Dashboard per role mengambil statistik dan chart dari endpoint masing-masing, misalnya api/user/dashboard-stats.php, api/approver/dashboard-stats.php, dan api/admin/dashboard-stats.php.',
        'peminjaman' => 'Pengajuan peminjaman berjalan dari user/peminjaman/ajukan-peminjaman.html ke api/user/request-peminjaman.php; backend membuat header peminjaman, detail_peminjaman, mengurangi barang.stok_tersedia, dan memberi status awal Waiting for Approval.',
        'approval' => 'Approval manager atau admin berjalan dari manager/persetujuan/menunggu-approval.html atau admin/peminjaman/admin-approval.html ke api/approver/approve-items.php; backend bisa approval parsial per item atau per unit, membuat peminjaman_units, lalu menghasilkan status Borrowed, Rejected, atau Partial Approved.',
        'pengembalian' => 'Pengembalian dimulai dari user/pengembalian/ajukan-pengembalian.html lalu diperiksa admin atau PIC di api/pengembalian/inspect.php; backend memperbarui stok_tersedia atau stok_rusak dan menyimpulkan status peminjaman menjadi Returned, Partially Returned, Return in Process, atau Borrowed.',
        'extend' => 'Perpanjangan berjalan melalui api/extend/request.php; backend membuat extend_peminjaman status Pending dan extend_peminjaman_items untuk item atau unit yang diperpanjang.',
        'barang' => 'Inventaris admin dikelola dari menu Item / Inventory dengan submenu Item Data dan Item Detail. Pengelolaan vendor tidak berada pada submenu terpisah, tetapi tersedia dari halaman Item Detail melalui tombol Edit Vendor atau modal Manage Vendors. Untuk PIC Barang, update item dilakukan dari menu Update dengan submenu Update Item.',
        'laporan' => 'Laporan stok, peminjaman, dan pengembalian tersedia di halaman admin/laporan/ dan manager/laporan/.',
        'email' => 'Notifikasi email dan reminder jatuh tempo berada di modul api/email/ dan api/cron/.',
        'users' => 'Manajemen akun dan role admin berada di menu Administrator dengan submenu User List dan Role List.',
        'ai' => 'Hermes Agent berjalan dari widget frontend assets/js/ai-agent-widget.js ke backend api/ai/chat.php, lalu grounding utamanya dibentuk oleh api/ai/context-helper.php dan helper AI terkait.',
    ];

    $selectedScopes = ['peminjaman', 'approval', 'pengembalian', 'extend'];
    foreach ($focusScopes as $scope) {
        if (!in_array($scope, $selectedScopes, true) && isset($workflowMap[$scope])) {
            $selectedScopes[] = $scope;
        }
    }

    $lines = [];
    foreach ($selectedScopes as $scope) {
        if (isset($workflowMap[$scope])) {
            $lines[] = $workflowMap[$scope];
        }
    }

    if (!in_array('dashboard', $selectedScopes, true)) {
        $lines[] = $workflowMap['dashboard'];
    }

    return $lines;
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

function aiAgentGetDatabaseRelationLines(): array
{
    return [
        'Relasi utama: peminjaman.user_id terhubung ke users.id sebagai peminjam.',
        'Relasi item: detail_peminjaman.peminjaman_id terhubung ke peminjaman dan detail_peminjaman.barang_id terhubung ke barang.',
        'Relasi unit: peminjaman_units.peminjaman_id terhubung ke peminjaman, peminjaman_units.detail_peminjaman_id ke detail_peminjaman, dan peminjaman_units.barang_id ke barang.',
        'Relasi pengembalian: pengembalian.peminjaman_id terhubung ke peminjaman dan detail_pengembalian.pengembalian_id terhubung ke pengembalian.',
        'Relasi perpanjangan: extend_peminjaman.peminjaman_id terhubung ke peminjaman dan extend_peminjaman_items.extend_peminjaman_id terhubung ke extend_peminjaman.',
        'Relasi pembelian: pembelian_barang.barang_id terhubung ke barang dan pembelian_barang.vendor_id terhubung ke vendor.',
    ];
}

function aiAgentGetBusinessRuleLines(): array
{
    return [
        'Role yang disimpan di users.role adalah admin, manager, user, dan pic_barang. Untuk agent, pic_barang dipetakan menjadi pic.',
        'peminjaman adalah header transaksi, sedangkan detail_peminjaman menyimpan item yang diajukan di transaksi tersebut.',
        'peminjaman_units adalah level unit individual yang penting untuk approval parsial, status pengembalian, dan jatuh tempo riil.',
        'pengembalian adalah header return, sedangkan detail_pengembalian menyimpan kondisi barang kembali, jumlah rusak, dan biaya ganti rugi.',
        'extend_peminjaman dan extend_peminjaman_items dipakai untuk proses perpanjangan tanggal kembali.',
        'barang menyimpan stok total, stok tersedia, safety stock, kondisi, dan stok rusak untuk inventaris.',
        'Logika jatuh tempo di project lebih mengutamakan expected_return level unit, fallback ke expected_return detail, lalu fallback ke rencana_kembali header.',
    ];
}

function aiAgentGetStatusGlossaryLines(): array
{
    return [
        'Status utama peminjaman yang dipakai project ini mencakup Waiting for Approval, Borrowed, Partial Approved, Rejected, Return in Process, Partially Returned, Returned, Overdue, Due Today, Due In 1 Day, Due In X Days, Fully Damaged, Partially Damaged, dan Completed.',
        'Status approval di detail_peminjaman menggunakan pending, approved, dan rejected.',
        'Status approval di peminjaman_units menggunakan Pending, Approved, dan Rejected.',
        'Status return di peminjaman_units yang tampak di codebase antara lain Waiting for Approval, Not Yet Returned, Return in Process, Returned, Damaged, dan Rejected.',
        'Status header pengembalian yang dipakai project antara lain Submitted, Being Inspected, Completed, Partially Returned, dan Partially Damaged.',
        'Status extend_peminjaman yang dipakai project antara lain Pending, Approved, dan Rejected.',
        'Di api/koneksi.php, status final seperti Waiting for Approval, Rejected, Returned, Fully Damaged, Partially Damaged, dan Completed tidak dioverride lagi oleh kalkulasi jatuh tempo.',
        'Prioritas kalkulasi status aktif mengikuti urutan Overdue, Due Today, Due In X Days, Partially Returned, Return in Process, Borrowed, lalu Returned.',
    ];
}

function aiAgentGetSnapshotLines(mysqli $conn, string $role, int $userId, array $focusScopes = []): array
{
    $lines = [];

    $inventory = aiAgentFetchSingleRow($conn, "
        SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(stok_rusak), 0) AS damaged_stock,
            COALESCE(SUM(CASE WHEN stok_tersedia <= safety_stock THEN 1 ELSE 0 END), 0) AS low_stock_items
        FROM barang
    ");
    $topStockItem = aiAgentFetchTopStockItem($conn);
    if (!empty($inventory)) {
        $topStockLine = aiAgentFormatTopStockLine($topStockItem);
        if ($topStockLine !== '') {
            $lines[] = sprintf(
                'Inventory live: %d master item, %s, stok rusak total %d, item low stock %d.',
                (int) ($inventory['total_items'] ?? 0),
                $topStockLine,
                (int) ($inventory['damaged_stock'] ?? 0),
                (int) ($inventory['low_stock_items'] ?? 0)
            );
        } else {
            $lines[] = sprintf(
                'Inventory live: %d master item, stok rusak total %d, item low stock %d.',
                (int) ($inventory['total_items'] ?? 0),
                (int) ($inventory['damaged_stock'] ?? 0),
                (int) ($inventory['low_stock_items'] ?? 0)
            );
        }
    }

    $lowStockRows = aiAgentFetchRows($conn, '
        SELECT nama_barang, stok_tersedia, safety_stock
        FROM barang
        WHERE stok_tersedia <= safety_stock
        ORDER BY stok_tersedia ASC, nama_barang ASC
        LIMIT 5
    ');
    if (!empty($lowStockRows)) {
        $lines[] = 'Contoh item low stock: ' . aiAgentFormatLowStockRows($lowStockRows) . '.';
    }

    $loanCounts = aiAgentFetchLabelTotals($conn, 'SELECT status AS label, COUNT(*) AS total FROM peminjaman GROUP BY status');
    if (!empty($loanCounts)) {
        $lines[] = 'Peminjaman live per status: ' . aiAgentFormatCountMap($loanCounts) . '.';
    }

    $returnCounts = aiAgentFetchLabelTotals($conn, 'SELECT status AS label, COUNT(*) AS total FROM pengembalian GROUP BY status');
    if (!empty($returnCounts)) {
        $lines[] = 'Pengembalian live per status: ' . aiAgentFormatCountMap($returnCounts) . '.';
    }

    $extendCounts = aiAgentFetchLabelTotals($conn, 'SELECT status AS label, COUNT(*) AS total FROM extend_peminjaman GROUP BY status');
    if (!empty($extendCounts)) {
        $lines[] = 'Perpanjangan live per status: ' . aiAgentFormatCountMap($extendCounts) . '.';
    }

    if ($role === 'admin') {
        $roleCounts = aiAgentFetchLabelTotals($conn, 'SELECT role AS label, COUNT(*) AS total FROM users GROUP BY role');
        if (!empty($roleCounts)) {
            $lines[] = 'User live per role: ' . aiAgentFormatCountMap($roleCounts) . '.';
        }

        $vendorRow = aiAgentFetchSingleRow($conn, 'SELECT COUNT(*) AS total_vendor FROM vendor');
        if (!empty($vendorRow)) {
            $lines[] = 'Vendor terdaftar saat ini: ' . (int) ($vendorRow['total_vendor'] ?? 0) . '.';
        }
    }

    if ($role === 'manager') {
        $pendingLoans = (int) ($loanCounts['Waiting for Approval'] ?? 0);
        $pendingExtend = (int) ($extendCounts['Pending'] ?? 0);
        $lines[] = 'Fokus manager dari data live: pending approval peminjaman ' . $pendingLoans . ', pending extend ' . $pendingExtend . '.';
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

    if (aiAgentFocusContains($focusScopes, ['approval']) || $role === 'admin' || $role === 'manager') {
        $pendingApprovalRows = aiAgentFetchRows($conn, "
            SELECT kode_peminjaman AS code, status
            FROM peminjaman
            WHERE status IN ('Waiting for Approval', 'Partial Approved')
            ORDER BY tanggal_pinjam DESC, kode_peminjaman DESC
            LIMIT 5
        ");
        if (!empty($pendingApprovalRows)) {
            $lines[] = 'Contoh peminjaman yang masih butuh approval: ' . aiAgentFormatCodeStatusRows($pendingApprovalRows) . '.';
        }
    }

    if (aiAgentFocusContains($focusScopes, ['peminjaman', 'dashboard']) || $role === 'admin' || $role === 'manager') {
        $urgentLoanRows = aiAgentFetchRows($conn, "
            SELECT kode_peminjaman AS code, status
            FROM peminjaman
            WHERE status = 'Overdue'
               OR status = 'Due Today'
               OR status LIKE 'Due In %'
            ORDER BY tanggal_pinjam DESC, kode_peminjaman DESC
            LIMIT 5
        ");
        if (!empty($urgentLoanRows)) {
            $lines[] = 'Contoh peminjaman yang perlu perhatian jatuh tempo: ' . aiAgentFormatCodeStatusRows($urgentLoanRows) . '.';
        }
    }

    if (aiAgentFocusContains($focusScopes, ['pengembalian']) || $role === 'admin' || $role === 'pic_barang') {
        $returnRows = aiAgentFetchRows($conn, '
            SELECT kode_pengembalian AS code, status
            FROM pengembalian
            ORDER BY kode_pengembalian DESC
            LIMIT 5
        ');
        if (!empty($returnRows)) {
            $lines[] = 'Contoh pengembalian terbaru: ' . aiAgentFormatCodeStatusRows($returnRows) . '.';
        }
    }

    if (aiAgentFocusContains($focusScopes, ['extend']) || $role === 'admin' || $role === 'manager') {
        $extendRows = aiAgentFetchRows($conn, '
            SELECT peminjaman_id, status, tanggal_perpanjang
            FROM extend_peminjaman
            ORDER BY tanggal_perpanjang DESC
            LIMIT 5
        ');
        if (!empty($extendRows)) {
            $lines[] = 'Contoh request extend terbaru: ' . aiAgentFormatExtendRows($extendRows) . '.';
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
            $lines[] = 'Peminjaman milik user aktif: ' . aiAgentFormatCountMap($myLoanCounts) . '.';
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

        $myLoanRows = aiAgentFetchRows(
            $conn,
            '
                SELECT kode_peminjaman AS code, status
                FROM peminjaman
                WHERE user_id = ?
                ORDER BY tanggal_pinjam DESC, kode_peminjaman DESC
                LIMIT 5
            ',
            'i',
            [$userId]
        );
        if (!empty($myLoanRows)) {
            $lines[] = 'Contoh peminjaman terbaru milik user aktif: ' . aiAgentFormatCodeStatusRows($myLoanRows) . '.';
        }

        $myReturnRows = aiAgentFetchRows(
            $conn,
            '
                SELECT kode_pengembalian AS code, status
                FROM pengembalian
                WHERE user_id = ?
                ORDER BY kode_pengembalian DESC
                LIMIT 5
            ',
            'i',
            [$userId]
        );
        if (!empty($myReturnRows)) {
            $lines[] = 'Contoh pengembalian terbaru milik user aktif: ' . aiAgentFormatCodeStatusRows($myReturnRows) . '.';
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

function aiAgentFetchTopStockItem(mysqli $conn): array
{
    return aiAgentFetchSingleRow($conn, '
        SELECT nama_barang, stok_tersedia
        FROM barang
        WHERE stok_tersedia > 0
        ORDER BY stok_tersedia DESC, nama_barang ASC
        LIMIT 1
    ');
}

function aiAgentFormatTopStockLine(array $row): string
{
    $name = trim((string) ($row['nama_barang'] ?? ''));
    $available = (int) ($row['stok_tersedia'] ?? 0);

    if ($name === '' || $available <= 0) {
        return '';
    }

    return 'barang dengan stok tersedia paling banyak adalah ' . $name . ' (' . $available . ' unit tersedia)';
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

function aiAgentFetchRows(mysqli $conn, string $sql, string $bindTypes = '', array $params = []): array
{
    $rows = [];

    if ($bindTypes === '') {
        $result = $conn->query($sql);
        if (!$result) {
            return $rows;
        }
        while ($row = $result->fetch_assoc()) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $rows;
    }

    $stmt->bind_param($bindTypes, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
    }
    $stmt->close();

    return $rows;
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

function aiAgentFormatLowStockRows(array $rows): string
{
    $parts = [];
    foreach ($rows as $row) {
        $name = trim((string) ($row['nama_barang'] ?? 'Item'));
        $available = (int) ($row['stok_tersedia'] ?? 0);
        $safety = (int) ($row['safety_stock'] ?? 0);
        $parts[] = $name . ' (' . $available . ' tersedia, safety ' . $safety . ')';
    }
    return implode('; ', $parts);
}

function aiAgentFormatCodeStatusRows(array $rows): string
{
    $parts = [];
    foreach ($rows as $row) {
        $code = trim((string) ($row['code'] ?? ''));
        $status = trim((string) ($row['status'] ?? ''));
        if ($code === '' && $status === '') {
            continue;
        }
        $label = $code !== '' ? $code : 'tanpa-kode';
        if ($status !== '') {
            $label .= ' [' . $status . ']';
        }
        $parts[] = $label;
    }
    return implode('; ', $parts);
}

function aiAgentFormatExtendRows(array $rows): string
{
    $parts = [];
    foreach ($rows as $row) {
        $loanId = (int) ($row['peminjaman_id'] ?? 0);
        $status = trim((string) ($row['status'] ?? ''));
        $date = trim((string) ($row['tanggal_perpanjang'] ?? ''));
        $label = 'peminjaman_id=' . $loanId;
        $detailParts = [];
        if ($status !== '') {
            $detailParts[] = $status;
        }
        if ($date !== '') {
            $detailParts[] = $date;
        }
        if (!empty($detailParts)) {
            $label .= ' [' . implode(', ', $detailParts) . ']';
        }
        $parts[] = $label;
    }
    return implode('; ', $parts);
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
        'hermes' => 'ai assistant',
        'chat' => 'ai assistant',
        'assistant' => 'ai assistant',
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

function aiAgentResolveFocusScopes(string $message, string $module, string $pagePath, string $pageTitle, string $pageHeading): array
{
    $scopes = ['general'];

    $moduleScopeMap = [
        'dashboard' => 'dashboard',
        'peminjaman' => 'peminjaman',
        'pengembalian' => 'pengembalian',
        'laporan' => 'laporan',
        'barang dan stok' => 'barang',
        'approval' => 'approval',
        'ai assistant' => 'ai',
        'profil user' => 'auth',
        'manajemen user' => 'users',
        'pengaturan role' => 'users',
    ];

    if (isset($moduleScopeMap[$module])) {
        $scopes[] = $moduleScopeMap[$module];
    }

    $source = strtolower(trim($message . ' ' . $module . ' ' . $pagePath . ' ' . $pageTitle . ' ' . $pageHeading));

    $keywordMap = [
        'approval' => ['approval', 'persetujuan', 'approve', 'reject', 'ditolak', 'disetujui'],
        'pengembalian' => ['pengembalian', 'return', 'dikembalikan', 'rusak', 'inspeksi'],
        'peminjaman' => ['peminjaman', 'pinjam', 'borrow', 'jatuh tempo', 'due', 'overdue'],
        'extend' => ['extend', 'perpanjang', 'perpanjangan'],
        'barang' => ['barang', 'stok', 'inventaris', 'vendor', 'safety stock'],
        'dashboard' => ['dashboard', 'ringkasan', 'statistik', 'chart', 'grafik'],
        'laporan' => ['laporan', 'report'],
        'auth' => ['login', 'logout', 'session', 'profil', 'password', 'register'],
        'users' => ['manajemen user', 'buat user', 'role sistem', 'hak akses', 'akun'],
        'ai' => ['ai', 'hermes', 'chatbot', 'assistant', 'widget', 'mode sensitif', 'prompt', 'grounding', 'dinamis', 'statis', 'backend'],
    ];

    foreach ($keywordMap as $scope => $keywords) {
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && strpos($source, $keyword) !== false) {
                $scopes[] = $scope;
                break;
            }
        }
    }

    $globalKeywords = ['keseluruhan', 'seluruh project', 'struktur project', 'database', 'schema', 'alur sistem'];
    foreach ($globalKeywords as $keyword) {
        if ($keyword !== '' && strpos($source, $keyword) !== false) {
            $scopes = array_merge($scopes, ['dashboard', 'peminjaman', 'approval', 'pengembalian', 'extend', 'barang', 'laporan', 'auth', 'users']);
            break;
        }
    }

    return array_values(array_unique($scopes));
}

function aiAgentFocusContains(array $focusScopes, array $needles): bool
{
    foreach ($needles as $needle) {
        if (in_array($needle, $focusScopes, true)) {
            return true;
        }
    }

    return false;
}

function aiAgentDescribeProjectPaths(array $paths, int $limit = 8): string
{
    $projectRoot = aiAgentGetProjectRootPath();
    $existingPaths = [];

    foreach ($paths as $path) {
        $normalized = str_replace('\\', '/', trim((string) $path));
        if ($normalized === '') {
            continue;
        }

        $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (file_exists($absolutePath) || is_dir($absolutePath)) {
            $existingPaths[] = $normalized;
        }
    }

    $existingPaths = array_values(array_unique($existingPaths));
    if ($limit > 0) {
        $existingPaths = array_slice($existingPaths, 0, $limit);
    }

    return implode(', ', $existingPaths);
}

function aiAgentGetProjectRootPath(): string
{
    return dirname(__DIR__, 2);
}

function aiAgentCleanText(string $text, int $maxLength): string
{
    $text = trim((string) preg_replace('/\s+/', ' ', $text));
    if ($text === '') {
        return '';
    }

    if (aiAgentStringLength($text) > $maxLength) {
        return aiAgentStringSubstring($text, 0, $maxLength);
    }

    return $text;
}
