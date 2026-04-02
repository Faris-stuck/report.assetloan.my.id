<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session-helper.php';
require_once __DIR__ . '/context-helper.php';
require_once __DIR__ . '/config-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'error' => 'Method not allowed',
    ]);
    exit;
}

SessionValidator::requireRole(['user', 'manager', 'admin', 'pic_barang']);

$config = aiAgentLoadConfig([
    __DIR__ . '/../../config/ai_agent.example.php',
    __DIR__ . '/../../config/ai_agent.php',
]);
$agentName = trim((string) ($config['agent_name'] ?? 'Hermes Agent'));
$agentBaseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
$agentApiKey = trim((string) ($config['api_key'] ?? ''));
$agentModel = trim((string) ($config['model'] ?? 'seed-2-0-pro-free'));
$agentTemperature = (float) ($config['temperature'] ?? 0.15);
$agentMaxTokens = (int) ($config['max_tokens'] ?? 900);
$agentTimeout = (int) ($config['timeout'] ?? 45);
$systemPrompt = trim((string) ($config['system_prompt'] ?? ''));
$sensitiveAccessPassword = (string) ($config['sensitive_access_password'] ?? '');
$sensitiveAccessDurationMinutes = max(1, (int) ($config['sensitive_access_duration_minutes'] ?? 30));

if ($agentBaseUrl === '' || $agentApiKey === '' || $agentModel === '') {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'AI agent configuration is incomplete.',
    ]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => 'Invalid JSON payload.',
    ]);
    exit;
}

$message = trim((string) ($payload['message'] ?? ''));
if ($message === '') {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'error' => 'Message is required.',
    ]);
    exit;
}

if (mb_strlen($message) > 2000) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'error' => 'Message is too long. Maximum 2000 characters.',
    ]);
    exit;
}

$pageContext = [];
if (isset($payload['page_context']) && is_array($payload['page_context'])) {
    $pageContext = $payload['page_context'];
}

$history = [];
if (isset($payload['history']) && is_array($payload['history'])) {
    $history = $payload['history'];
}

$sessionRole = SessionValidator::getRole();
$sessionUserId = (int) SessionValidator::getUserId();
$canUnlockSensitiveAccess = $sessionRole === 'admin';
$userMessageDisplay = $message;
$passwordWasSubmitted = false;

if (!$canUnlockSensitiveAccess) {
    aiAgentRevokeSensitiveAccess();
}

if ($sensitiveAccessPassword !== '' && aiAgentMessageContainsPassword($message, $sensitiveAccessPassword)) {
    $passwordWasSubmitted = true;
    $userMessageDisplay = aiAgentMaskSensitiveMessage($message, $sensitiveAccessPassword);

    if ($canUnlockSensitiveAccess) {
        aiAgentGrantSensitiveAccess($sensitiveAccessDurationMinutes);
    } else {
        aiAgentRevokeSensitiveAccess();
    }
}

$sensitiveAccessExpiresAt = $canUnlockSensitiveAccess ? aiAgentGetSensitiveAccessExpiresAt() : 0;
$hasSensitiveAccess = $canUnlockSensitiveAccess && $sensitiveAccessExpiresAt > time();
$effectiveMessage = aiAgentStripSensitivePassword($message, $sensitiveAccessPassword);
if ($effectiveMessage === '') {
    $effectiveMessage = $passwordWasSubmitted ? '' : $message;
}

if ($passwordWasSubmitted && $effectiveMessage === '') {
    session_write_close();
    $replyMessage = $canUnlockSensitiveAccess
        ? 'Akses detail teknis internal berhasil dibuka selama ' . $sensitiveAccessDurationMinutes . ' menit. Selama periode ini saya bisa menjelaskan nama file, path, database, tabel, dan detail teknis lain jika Anda memang memintanya.'
        : 'Akses detail teknis internal hanya tersedia untuk admin. Password Anda sudah disensor, tetapi mode sensitif tidak dibuka untuk role Anda saat ini.';
    echo json_encode([
        'status' => 'ok',
        'reply' => $replyMessage,
        'processing_time_ms' => 0,
        'timestamp' => time(),
        'user_message_display' => $userMessageDisplay,
        'sensitive_access_active' => $hasSensitiveAccess,
        'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
        'reply_contains_sensitive' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($effectiveMessage === '') {
    $effectiveMessage = $message;
}

$isSensitiveRequest = aiAgentMessageRequestsSensitiveInfo($effectiveMessage);
$useSensitiveGrounding = $hasSensitiveAccess && $isSensitiveRequest;
$groundingContext = $useSensitiveGrounding
    ? aiAgentBuildGroundingContext($conn, [
        'role' => $sessionRole,
        'user_id' => $sessionUserId,
        'message' => $effectiveMessage,
        'page_path' => (string) ($pageContext['path'] ?? ''),
        'page_title' => (string) ($pageContext['title'] ?? ''),
        'page_heading' => (string) ($pageContext['heading'] ?? ''),
    ])
    : aiAgentBuildPublicGroundingContext($conn, [
        'role' => $sessionRole,
        'user_id' => $sessionUserId,
        'message' => $effectiveMessage,
        'page_path' => (string) ($pageContext['path'] ?? ''),
        'page_title' => (string) ($pageContext['title'] ?? ''),
        'page_heading' => (string) ($pageContext['heading'] ?? ''),
    ]);

$modePrompt = aiAgentBuildModePrompt([
    'agent_name' => $agentName,
    'has_sensitive_access' => $hasSensitiveAccess,
    'can_unlock_sensitive_access' => $canUnlockSensitiveAccess,
    'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
    'is_sensitive_request' => $isSensitiveRequest,
    'use_sensitive_grounding' => $useSensitiveGrounding,
]);

$messages = [
    [
        'role' => 'system',
        'content' => $systemPrompt !== ''
            ? $systemPrompt
            : 'Anda adalah ' . $agentName . '. Jangan mengarang, dan dalam mode normal jangan ungkap detail teknis internal.',
    ],
    [
        'role' => 'system',
        'content' => $modePrompt,
    ],
    [
        'role' => 'system',
        'content' => $groundingContext,
    ],
];

foreach (aiAgentSanitizeHistoryMessages($history, $sensitiveAccessPassword, $useSensitiveGrounding) as $historyMessage) {
    $messages[] = $historyMessage;
}

$messages[] = [
    'role' => 'user',
    'content' => $effectiveMessage,
];

$providerPayload = [
    'model' => $agentModel,
    'messages' => $messages,
    'max_tokens' => max(100, $agentMaxTokens),
    'temperature' => max(0, min(2, $agentTemperature)),
];

session_write_close();

$startedAt = microtime(true);
$curl = curl_init($agentBaseUrl . '/chat/completions');
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => json_encode($providerPayload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $agentApiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => max(5, $agentTimeout),
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$result = curl_exec($curl);
$curlError = curl_error($curl);
$httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($result === false) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'error' => 'Failed to connect to AI provider.',
        'details' => $curlError,
        'user_message_display' => $userMessageDisplay,
        'sensitive_access_active' => $hasSensitiveAccess,
        'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
        'reply_contains_sensitive' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($result, true);
if (!is_array($decoded)) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'error' => 'Invalid response from AI provider.',
        'user_message_display' => $userMessageDisplay,
        'sensitive_access_active' => $hasSensitiveAccess,
        'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
        'reply_contains_sensitive' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode >= 400) {
    $providerError = aiAgentExtractProviderError($decoded);
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'error' => $providerError !== '' ? $providerError : 'AI provider returned an error.',
        'user_message_display' => $userMessageDisplay,
        'sensitive_access_active' => $hasSensitiveAccess,
        'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
        'reply_contains_sensitive' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$reply = aiAgentExtractProviderReply($decoded);
if ($reply === '') {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'error' => 'AI provider did not return a valid reply.',
        'user_message_display' => $userMessageDisplay,
        'sensitive_access_active' => $hasSensitiveAccess,
        'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
        'reply_contains_sensitive' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$processingTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

echo json_encode([
    'status' => 'ok',
    'reply' => $reply,
    'processing_time_ms' => $processingTimeMs,
    'timestamp' => time(),
    'user_message_display' => $userMessageDisplay,
    'sensitive_access_active' => $hasSensitiveAccess,
    'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
    'reply_contains_sensitive' => $useSensitiveGrounding,
], JSON_UNESCAPED_UNICODE);

function aiAgentBuildModePrompt(array $options = []): string
{
    $agentName = trim((string) ($options['agent_name'] ?? 'Hermes Agent'));
    $hasSensitiveAccess = !empty($options['has_sensitive_access']);
    $canUnlockSensitiveAccess = !empty($options['can_unlock_sensitive_access']);
    $sensitiveAccessExpiresAt = (int) ($options['sensitive_access_expires_at'] ?? 0);
    $isSensitiveRequest = !empty($options['is_sensitive_request']);
    $useSensitiveGrounding = !empty($options['use_sensitive_grounding']);

    if ($useSensitiveGrounding) {
        return $agentName . ' sedang berada pada mode sensitif terotorisasi sampai sekitar ' . aiAgentFormatAccessExpiryLabel($sensitiveAccessExpiresAt) . '. Anda boleh menyebut nama file, folder, path, endpoint, database, tabel, kolom, dan detail backend internal hanya jika user memang memintanya secara jelas. Untuk pertanyaan biasa, tetap prioritaskan jawaban berbasis menu, submenu, card, halaman, tombol, dan alur penggunaan.';
    }

    if ($isSensitiveRequest && !$hasSensitiveAccess) {
        if ($canUnlockSensitiveAccess) {
            return 'Mode publik aktif. Pertanyaan user menyentuh detail teknis internal. Jangan ungkap nama file, folder, path, endpoint, database, tabel, kolom, query, atau detail backend internal. Tetap bantu dengan jawaban aman berbasis menu, submenu, card, halaman, tombol, dan langkah penggunaan. Tutup dengan catatan singkat bahwa detail teknis internal dikunci dan hanya admin yang bisa membukanya setelah password akses diberikan.';
        }

        return 'Mode publik aktif. Pertanyaan user menyentuh detail teknis internal. Jangan ungkap nama file, folder, path, endpoint, database, tabel, kolom, query, atau detail backend internal. Tetap bantu dengan jawaban aman berbasis menu, submenu, card, halaman, tombol, dan langkah penggunaan. Tutup dengan catatan singkat bahwa detail teknis internal dikunci dan hanya admin yang bisa membukanya setelah memasukkan password akses yang benar.';
    }

    return 'Mode publik aktif. Prioritaskan jawaban berbasis menu, submenu, card, halaman, tombol, langkah penggunaan, dan status bisnis. Jangan menyebut nama file, folder, path, endpoint, database, tabel, kolom, query, atau detail backend internal kecuali sistem secara eksplisit mengaktifkan mode sensitif untuk admin yang sudah memasukkan password.';
}

function aiAgentBuildPublicGroundingContext(mysqli $conn, array $options = []): string
{
    $role = (string) ($options['role'] ?? 'user');
    $userId = (int) ($options['user_id'] ?? 0);
    $message = aiAgentCleanText((string) ($options['message'] ?? ''), 1600);
    $pagePath = aiAgentCleanText((string) ($options['page_path'] ?? ''), 180);
    $pageTitle = aiAgentCleanText((string) ($options['page_title'] ?? ''), 120);
    $pageHeading = aiAgentCleanText((string) ($options['page_heading'] ?? ''), 120);
    $module = aiAgentInferModule($pagePath, $pageTitle, $pageHeading);
    $focusScopes = aiAgentResolveFocusScopes($message, $module, $pagePath, $pageTitle, $pageHeading);

    $lines = [];
    $lines[] = '[PUBLIC_GROUNDING]';
    $lines[] = '[PUBLIC_RULES]';
    $publicRules = [
        'Jawab dengan istilah menu, submenu, card, halaman, tombol, langkah penggunaan, dan status bisnis.',
        'Jangan menyebut nama file, folder, path, endpoint, database, tabel, kolom, query, atau detail backend internal.',
        'Jika user meminta detail teknis internal, bantu dulu dengan versi aman lalu jelaskan bahwa detail internal butuh password akses.',
        'Tetap akurat terhadap struktur role, menu, dan alur bisnis aplikasi ini.',
    ];
    foreach ($publicRules as $rule) {
        $lines[] = '- ' . $rule;
    }
    $lines[] = '[/PUBLIC_RULES]';
    $lines[] = '[PUBLIC_SESSION]';
    $lines[] = '- Role session aktif: ' . $role . '.';
    if ($module !== '') {
        $lines[] = '- Modul atau area yang paling dekat dengan halaman aktif: ' . $module . '.';
    }
    if ($pageTitle !== '' || $pageHeading !== '') {
        $pageBits = [];
        if ($pageTitle !== '') {
            $pageBits[] = 'title=' . $pageTitle;
        }
        if ($pageHeading !== '') {
            $pageBits[] = 'heading=' . $pageHeading;
        }
        $lines[] = '- Halaman aktif: ' . implode(' | ', $pageBits) . '.';
    }
    if ($message !== '') {
        $lines[] = '- Pertanyaan user saat ini: ' . $message . '.';
    }
    $lines[] = '[/PUBLIC_SESSION]';
    $lines[] = '[PUBLIC_PROJECT]';
    foreach (aiAgentGetPublicProjectLines($role) as $line) {
        $lines[] = '- ' . $line;
    }
    $lines[] = '[/PUBLIC_PROJECT]';
    $lines[] = '[PUBLIC_WORKFLOWS]';
    foreach (aiAgentGetPublicWorkflowLines($focusScopes, $role) as $line) {
        $lines[] = '- ' . $line;
    }
    $lines[] = '[/PUBLIC_WORKFLOWS]';
    $lines[] = '[PUBLIC_DATA]';
    foreach (aiAgentGetPublicDataModelLines() as $line) {
        $lines[] = '- ' . $line;
    }
    foreach (aiAgentGetPublicSnapshotLines($conn, $role, $userId) as $line) {
        $lines[] = '- ' . $line;
    }
    $lines[] = '[/PUBLIC_DATA]';
    $lines[] = '[/PUBLIC_GROUNDING]';

    return implode("\n", $lines);
}

function aiAgentGetPublicProjectLines(string $role): array
{
    $lines = [
        'Aplikasi ini adalah sistem peminjaman barang berbasis web dengan role admin, manager, user, dan PIC barang.',
        'Area Admin memiliki menu Dashboard, Pengaturan, User, Barang, Peminjaman, Peminjam, Pengembalian, dan Laporan.',
        'Area Manager memiliki menu Dashboard, Persetujuan, dan Laporan.',
        'Area User memiliki menu Dashboard, Profil, Riwayat, Peminjaman, dan Pengembalian.',
        'Area PIC Barang memiliki menu Dashboard, Profil, Update Barang, dan Pengembalian.',
    ];

    $roleHints = [
        'admin' => 'Untuk role admin, fokus bantuan biasanya berada pada user, barang, approval peminjaman, pengembalian, dan laporan.',
        'manager' => 'Untuk role manager, fokus bantuan biasanya berada pada persetujuan pengajuan dan monitoring laporan.',
        'user' => 'Untuk role user, fokus bantuan biasanya berada pada pengajuan pinjam, melihat status, riwayat, dan pengembalian.',
        'pic_barang' => 'Untuk role PIC barang, fokus bantuan biasanya berada pada update barang, stok, dan proses pengembalian.',
    ];

    if (isset($roleHints[$role])) {
        $lines[] = $roleHints[$role];
    }

    return $lines;
}

function aiAgentGetPublicWorkflowLines(array $focusScopes, string $role): array
{
    $workflowMap = [
        'users' => 'Penambahan user dilakukan dari area Admin pada menu User. Admin membuka form pembuatan user, mengisi identitas seperti nama, NRP, email, memilih role, lalu menyimpan.',
        'peminjaman' => 'Pengajuan pinjam dilakukan dari area User pada menu Peminjaman. User memilih barang, mengisi kebutuhan peminjaman dan rencana kembali, lalu mengirim pengajuan untuk approval.',
        'approval' => 'Approval dilakukan dari area Manager atau Admin pada menu Persetujuan atau daftar pengajuan menunggu persetujuan. Approver meninjau item lalu menyetujui atau menolak.',
        'pengembalian' => 'Pengembalian diajukan dari area User pada menu Pengembalian, lalu diperiksa oleh Admin atau PIC Barang sampai proses selesai.',
        'extend' => 'Perpanjangan dilakukan dari alur pinjaman aktif. User mengajukan tanggal kembali baru lalu menunggu persetujuan.',
        'barang' => 'Pengelolaan inventaris dilakukan dari menu Barang untuk admin atau Update Barang untuk PIC Barang.',
        'laporan' => 'Laporan tersedia pada menu Laporan untuk admin dan manager.',
        'auth' => 'Profil dan perubahan data akun dilakukan dari area Profil sesuai role masing-masing.',
        'dashboard' => 'Dashboard tiap role menampilkan ringkasan operasional yang relevan dengan tugas role tersebut.',
    ];

    $selected = [];
    foreach ($focusScopes as $scope) {
        if (isset($workflowMap[$scope])) {
            $selected[] = $workflowMap[$scope];
        }
    }

    if (empty($selected)) {
        $fallbackByRole = [
            'admin' => ['users', 'barang', 'approval', 'pengembalian'],
            'manager' => ['approval', 'laporan', 'dashboard'],
            'user' => ['peminjaman', 'pengembalian', 'auth'],
            'pic_barang' => ['barang', 'pengembalian', 'dashboard'],
        ];
        foreach ($fallbackByRole[$role] ?? ['dashboard'] as $scope) {
            if (isset($workflowMap[$scope])) {
                $selected[] = $workflowMap[$scope];
            }
        }
    }

    return array_values(array_unique($selected));
}

function aiAgentGetPublicDataModelLines(): array
{
    return [
        'Data yang dikelola aplikasi ini mencakup inventaris barang, transaksi peminjaman, item yang diajukan, unit barang yang disetujui atau dikembalikan, pengembalian, perpanjangan, akun user, role, vendor, dan pembelian barang.',
        'Status bisnis penting mencakup menunggu approval, dipinjam, parsial disetujui, ditolak, proses pengembalian, dikembalikan, jatuh tempo, overdue, rusak, dan selesai.',
        'Inventaris memperhatikan stok total, stok tersedia, stok rusak, dan batas aman stok agar operasional tetap terkontrol.',
    ];
}

function aiAgentGetPublicSnapshotLines(mysqli $conn, string $role, int $userId): array
{
    $lines = [];

    $inventory = aiAgentFetchSingleRow($conn, '
        SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(stok_tersedia), 0) AS available_stock,
            COALESCE(SUM(CASE WHEN stok_tersedia <= safety_stock THEN 1 ELSE 0 END), 0) AS low_stock_items
        FROM barang
    ');
    if (!empty($inventory)) {
        $lines[] = sprintf(
            'Ringkasan inventaris saat ini: %d item master, stok tersedia %d, item low stock %d.',
            (int) ($inventory['total_items'] ?? 0),
            (int) ($inventory['available_stock'] ?? 0),
            (int) ($inventory['low_stock_items'] ?? 0)
        );
    }

    $loanCounts = aiAgentFetchLabelTotals($conn, 'SELECT status AS label, COUNT(*) AS total FROM peminjaman GROUP BY status');
    if (!empty($loanCounts)) {
        $lines[] = 'Ringkasan status peminjaman saat ini: ' . aiAgentFormatCountMap($loanCounts) . '.';
    }

    $returnCounts = aiAgentFetchLabelTotals($conn, 'SELECT status AS label, COUNT(*) AS total FROM pengembalian GROUP BY status');
    if (!empty($returnCounts)) {
        $lines[] = 'Ringkasan status pengembalian saat ini: ' . aiAgentFormatCountMap($returnCounts) . '.';
    }

    if ($role === 'manager') {
        $lines[] = 'Untuk manager, fokus utama adalah item yang menunggu persetujuan dan permintaan perpanjangan yang masih pending.';
    }

    if ($role === 'user' && $userId > 0) {
        $myLoanCounts = aiAgentFetchLabelTotals(
            $conn,
            'SELECT status AS label, COUNT(*) AS total FROM peminjaman WHERE user_id = ? GROUP BY status',
            'i',
            [$userId]
        );
        if (!empty($myLoanCounts)) {
            $lines[] = 'Ringkasan peminjaman milik user aktif: ' . aiAgentFormatCountMap($myLoanCounts) . '.';
        }
    }

    return $lines;
}

function aiAgentGetSensitiveAccessExpiresAt(): int
{
    $expiresAt = (int) ($_SESSION['ai_sensitive_access_expires_at'] ?? 0);
    if ($expiresAt <= time()) {
        unset($_SESSION['ai_sensitive_access_expires_at']);
        return 0;
    }

    return $expiresAt;
}

function aiAgentGrantSensitiveAccess(int $durationMinutes): void
{
    $_SESSION['ai_sensitive_access_expires_at'] = time() + max(1, $durationMinutes) * 60;
}

function aiAgentRevokeSensitiveAccess(): void
{
    unset($_SESSION['ai_sensitive_access_expires_at']);
}

function aiAgentFormatAccessExpiryLabel(int $timestamp): string
{
    if ($timestamp <= 0) {
        return 'kurang dari 30 menit ke depan';
    }

    return date('d-m-Y H:i', $timestamp) . ' WIB';
}

function aiAgentMessageContainsPassword(string $message, string $password): bool
{
    return $password !== '' && strpos($message, $password) !== false;
}

function aiAgentMaskSensitiveMessage(string $message, string $password): string
{
    if ($password === '') {
        return $message;
    }

    $masked = str_replace($password, '[password disensor]', $message);
    return trim($masked) !== '' ? $masked : '[password disensor]';
}

function aiAgentStripSensitivePassword(string $message, string $password): string
{
    if ($password === '') {
        return trim($message);
    }

    $cleaned = str_replace($password, ' ', $message);
    $cleaned = preg_replace('/\b(password|pass|pwd|kata\s*sandi|sandi)\b\s*[:=\-]?\s*/iu', ' ', (string) $cleaned);
    $cleaned = preg_replace('/\s+/', ' ', $cleaned);
    $cleaned = trim((string) $cleaned, " \t\n\r\0\x0B,.:;|-_");

    return trim($cleaned);
}

function aiAgentMessageRequestsSensitiveInfo(string $message): bool
{
    $source = strtolower(trim($message));
    if ($source === '') {
        return false;
    }

    $keywords = [
        'nama file',
        'file php',
        'file html',
        'folder',
        'path',
        'direktori',
        'database',
        'db ',
        'schema',
        'sql',
        'tabel',
        'table',
        'kolom',
        'query',
        'endpoint',
        'api ',
        'source code',
        'kode backend',
        'lokasi file',
        'struktur folder',
    ];

    foreach ($keywords as $keyword) {
        if ($keyword !== '' && strpos($source, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

function aiAgentSanitizeHistoryMessages(array $history, string $sensitiveAccessPassword = '', bool $allowSensitiveHistory = false): array
{
    $messages = [];
    $history = array_slice($history, -8);

    foreach ($history as $item) {
        if (!is_array($item)) {
            continue;
        }

        $role = (string) ($item['role'] ?? '');
        if ($role !== 'user' && $role !== 'assistant') {
            continue;
        }

        $content = trim((string) ($item['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        if ($sensitiveAccessPassword !== '' && strpos($content, $sensitiveAccessPassword) !== false) {
            $content = aiAgentMaskSensitiveMessage($content, $sensitiveAccessPassword);
        }

        if (!$allowSensitiveHistory && aiAgentContentLooksSensitive($content)) {
            continue;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($content) > 1200) {
            $content = mb_substr($content, 0, 1200);
        } elseif (strlen($content) > 1200) {
            $content = substr($content, 0, 1200);
        }

        $messages[] = [
            'role' => $role,
            'content' => $content,
        ];
    }

    return $messages;
}

function aiAgentContentLooksSensitive(string $content): bool
{
    if (aiAgentMessageRequestsSensitiveInfo($content)) {
        return true;
    }

    if (preg_match('/\b[a-z0-9_-]+\.(php|html|js|css|sql)\b/i', $content)) {
        return true;
    }

    if (preg_match('/\b[a-z0-9_-]+\/[a-z0-9_\/.-]+\b/i', $content)) {
        return true;
    }

    return false;
}

function aiAgentExtractProviderReply(array $decoded): string
{
    $content = $decoded['choices'][0]['message']['content'] ?? '';

    if (is_string($content)) {
        return trim($content);
    }

    if (is_array($content)) {
        $parts = [];
        foreach ($content as $item) {
            if (is_array($item) && isset($item['text']) && is_string($item['text'])) {
                $parts[] = trim($item['text']);
            }
        }
        return trim(implode("\n", array_filter($parts)));
    }

    return '';
}

function aiAgentExtractProviderError(array $decoded): string
{
    $error = $decoded['error'] ?? null;
    if (is_string($error)) {
        return trim($error);
    }

    if (is_array($error)) {
        if (isset($error['message']) && is_string($error['message'])) {
            return trim($error['message']);
        }
        if (isset($error['code']) && is_string($error['code'])) {
            return trim($error['code']);
        }
    }

    if (isset($decoded['message']) && is_string($decoded['message'])) {
        return trim($decoded['message']);
    }

    return '';
}
