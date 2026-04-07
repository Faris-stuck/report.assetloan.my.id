<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session-helper.php';
require_once __DIR__ . '/context-helper.php';
require_once __DIR__ . '/codebase-helper.php';
require_once __DIR__ . '/index-helper.php';
require_once __DIR__ . '/tool-helper.php';
require_once __DIR__ . '/config-helper.php';
require_once __DIR__ . '/runtime-helper.php';

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
    __DIR__ . '/../../config/ai_agent.php',
    __DIR__ . '/../../config/ai_agent.example.php',
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

if (aiAgentStringLength($message) > 2000) {
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

$pageSnapshot = [];
if (isset($pageContext['ui_snapshot']) && is_array($pageContext['ui_snapshot'])) {
    $pageSnapshot = $pageContext['ui_snapshot'];
}

$history = [];
if (isset($payload['history']) && is_array($payload['history'])) {
    $history = $payload['history'];
}

$sessionRole = SessionValidator::getRole();
$sessionUserId = (int) SessionValidator::getUserId();
$canUnlockTechnicalAccess = $sessionRole === 'admin';
$canUnlockBusinessOverride = true;
$userMessageDisplay = $message;
$passwordWasSubmitted = false;

if (!$canUnlockTechnicalAccess) {
    aiAgentRevokeSensitiveAccess();
}

if ($sensitiveAccessPassword !== '' && aiAgentMessageContainsPassword($message, $sensitiveAccessPassword)) {
    $passwordWasSubmitted = true;
    $userMessageDisplay = aiAgentMaskSensitiveMessage($message, $sensitiveAccessPassword);

    if ($canUnlockBusinessOverride) {
        aiAgentGrantBusinessOverrideAccess($sensitiveAccessDurationMinutes);
    }

    if ($canUnlockTechnicalAccess) {
        aiAgentGrantSensitiveAccess($sensitiveAccessDurationMinutes);
    } else {
        aiAgentRevokeSensitiveAccess();
    }
}

$businessOverrideExpiresAt = $canUnlockBusinessOverride ? aiAgentGetBusinessOverrideExpiresAt() : 0;
$hasBusinessOverrideAccess = $canUnlockBusinessOverride && $businessOverrideExpiresAt > time();
$sensitiveAccessExpiresAt = $canUnlockTechnicalAccess ? aiAgentGetSensitiveAccessExpiresAt() : 0;
$hasSensitiveAccess = $canUnlockTechnicalAccess && $sensitiveAccessExpiresAt > time();
$effectiveMessage = aiAgentStripSensitivePassword($message, $sensitiveAccessPassword);
if ($effectiveMessage === '') {
    $effectiveMessage = $passwordWasSubmitted ? '' : $message;
}

if ($passwordWasSubmitted && $effectiveMessage === '') {
    session_write_close();
    if ($canUnlockTechnicalAccess) {
        $replyMessage = 'Akses data lintas-role dan detail teknis internal berhasil dibuka selama ' . $sensitiveAccessDurationMinutes . ' menit. Dalam periode ini saya bisa memakai data live lintas role, dan untuk admin saya juga bisa menjelaskan file, path, database, tabel, serta detail teknis jika memang diminta.';
    } elseif ($canUnlockBusinessOverride) {
        $replyMessage = 'Akses data lintas-role berhasil dibuka selama ' . $sensitiveAccessDurationMinutes . ' menit. Selama periode ini saya boleh memakai data live di luar scope role normal Anda, tetapi detail teknis internal tetap dikunci karena itu khusus admin.';
    } else {
        $replyMessage = 'Password Anda sudah disensor, tetapi mode override tidak tersedia untuk role Anda saat ini.';
    }
    echo json_encode([
        'status' => 'ok',
        'reply' => $replyMessage,
        'processing_time_ms' => 0,
        'timestamp' => time(),
        'user_message_display' => $userMessageDisplay,
        'sensitive_access_active' => $hasSensitiveAccess,
        'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
        'business_override_active' => $hasBusinessOverrideAccess,
        'business_override_expires_at' => $businessOverrideExpiresAt,
        'reply_contains_sensitive' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($effectiveMessage === '') {
    $effectiveMessage = $message;
}

$accessDecision = aiAgentEvaluateRuntimeAccess([
    'config' => $config,
    'role' => $sessionRole,
    'message' => $effectiveMessage,
    'page_context' => $pageContext,
    'page_snapshot' => $pageSnapshot,
    'has_sensitive_access' => $hasSensitiveAccess,
    'has_business_override_access' => $hasBusinessOverrideAccess,
]);
$isSensitiveRequest = !empty($accessDecision['requires_any_elevated_access']);
$useSensitiveGrounding = !empty($accessDecision['should_use_elevated_grounding']);
$toolRuntimeContext = aiAgentBuildToolRuntimeContext($conn, [
    'config' => $config,
    'role' => $sessionRole,
    'user_id' => $sessionUserId,
    'message' => $effectiveMessage,
    'history' => $history,
    'page_context' => $pageContext,
    'page_snapshot' => $pageSnapshot,
    'has_sensitive_access' => $hasSensitiveAccess,
    'has_business_override_access' => $hasBusinessOverrideAccess,
    'is_sensitive_request' => $isSensitiveRequest,
    'use_sensitive_grounding' => $useSensitiveGrounding,
    'access_decision' => $accessDecision,
]);
$groundingContext = trim((string) ($toolRuntimeContext['grounding'] ?? ''));
if ($groundingContext === '') {
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
}

$modePrompt = aiAgentBuildModePrompt([
    'agent_name' => $agentName,
    'has_sensitive_access' => $hasSensitiveAccess,
    'has_business_override_access' => $hasBusinessOverrideAccess,
    'can_unlock_sensitive_access' => $canUnlockTechnicalAccess,
    'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
    'business_override_expires_at' => $businessOverrideExpiresAt,
    'is_sensitive_request' => $isSensitiveRequest,
    'use_sensitive_grounding' => $useSensitiveGrounding,
    'access_decision' => $accessDecision,
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
$providerResponse = aiAgentHttpRequest('POST', $agentBaseUrl . '/chat/completions', [
    'headers' => [
        'Authorization: Bearer ' . $agentApiKey,
        'Content-Type: application/json',
    ],
    'body' => json_encode($providerPayload, JSON_UNESCAPED_UNICODE),
    'timeout' => max(5, $agentTimeout),
    'connect_timeout' => 10,
]);

$result = (string) ($providerResponse['body'] ?? '');
$transportError = trim((string) ($providerResponse['error'] ?? ''));
$httpCode = (int) ($providerResponse['http_code'] ?? 0);

if ($httpCode <= 0 && $result === '') {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'error' => 'Failed to connect to AI provider.',
        'details' => $transportError !== '' ? $transportError : 'No HTTP transport available for outbound AI request.',
        'user_message_display' => $userMessageDisplay,
        'sensitive_access_active' => $hasSensitiveAccess,
        'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
        'business_override_active' => $hasBusinessOverrideAccess,
        'business_override_expires_at' => $businessOverrideExpiresAt,
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
        'business_override_active' => $hasBusinessOverrideAccess,
        'business_override_expires_at' => $businessOverrideExpiresAt,
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
        'business_override_active' => $hasBusinessOverrideAccess,
        'business_override_expires_at' => $businessOverrideExpiresAt,
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

$reply = aiAgentRedactPublicReplySensitiveIdentifiers($reply, [
    'session_user_id' => $sessionUserId,
    'allow_sensitive_identifiers' => $useSensitiveGrounding,
]);

$processingTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

echo json_encode([
    'status' => 'ok',
    'reply' => $reply,
    'processing_time_ms' => $processingTimeMs,
    'timestamp' => time(),
    'user_message_display' => $userMessageDisplay,
    'sensitive_access_active' => $hasSensitiveAccess,
    'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
    'business_override_active' => $hasBusinessOverrideAccess,
    'business_override_expires_at' => $businessOverrideExpiresAt,
    'reply_contains_sensitive' => $useSensitiveGrounding,
], JSON_UNESCAPED_UNICODE);

function aiAgentBuildModePrompt(array $options = []): string
{
    $agentName = trim((string) ($options['agent_name'] ?? 'Hermes Agent'));
    $hasSensitiveAccess = !empty($options['has_sensitive_access']);
    $hasBusinessOverrideAccess = !empty($options['has_business_override_access']);
    $canUnlockSensitiveAccess = !empty($options['can_unlock_sensitive_access']);
    $sensitiveAccessExpiresAt = (int) ($options['sensitive_access_expires_at'] ?? 0);
    $businessOverrideExpiresAt = (int) ($options['business_override_expires_at'] ?? 0);
    $isSensitiveRequest = !empty($options['is_sensitive_request']);
    $useSensitiveGrounding = !empty($options['use_sensitive_grounding']);
    $accessDecision = isset($options['access_decision']) && is_array($options['access_decision']) ? $options['access_decision'] : [];
    $technicalRequested = !empty($accessDecision['technical_requested']);
    $requiresScopeOverride = !empty($accessDecision['requires_scope_override']);
    $hasScopeOverrideAccess = !empty($accessDecision['has_scope_override_access']);

    if ($technicalRequested && $useSensitiveGrounding && $hasSensitiveAccess) {
        return $agentName . ' sedang berada pada mode sensitif terotorisasi sampai sekitar ' . aiAgentFormatAccessExpiryLabel($sensitiveAccessExpiresAt) . '. Anda menerima tool layer runtime berisi metadata halaman, data live, observasi implementasi, dan context kode frontend/backend yang relevan. Anda boleh menyebut nama file, folder, path, endpoint, database, tabel, kolom, dan detail backend internal hanya jika user memang memintanya secara jelas. Untuk pertanyaan biasa, tetap prioritaskan jawaban berbasis menu, submenu, card, halaman, tombol, dan alur penggunaan.';
    }

    if ($hasScopeOverrideAccess && $useSensitiveGrounding) {
        return $agentName . ' sedang berada pada mode override data lintas-role sampai sekitar ' . aiAgentFormatAccessExpiryLabel($businessOverrideExpiresAt) . '. Anda menerima tool layer runtime berisi metadata halaman, data live, dan observasi implementasi yang boleh melampaui scope role normal sesi ini. Pakai akses ini hanya untuk menjawab kebutuhan data bisnis terbaru. Tetap jangan menyebut nama file, folder, path, endpoint, database, tabel, kolom, query, atau detail backend internal kecuali mode sensitif teknis admin juga aktif.';
    }

    if ($technicalRequested && !$hasSensitiveAccess) {
        if ($canUnlockSensitiveAccess) {
            return 'Mode publik aktif. Pertanyaan user menyentuh detail teknis internal. Anda tetap menerima tool layer runtime berisi metadata halaman, data live, dan observasi implementasi dinamis, tetapi jangan ungkap nama file, folder, path, endpoint, database, tabel, kolom, query, atau detail backend internal. Tetap bantu dengan jawaban aman berbasis menu, submenu, card, halaman, tombol, dan langkah penggunaan. Jangan menganggap struktur atau data tidak tersedia hanya karena user sedang berada di halaman lain; gunakan konteks seluruh aplikasi dan data live yang tersedia. Tutup dengan catatan singkat bahwa detail teknis internal dikunci dan hanya admin yang bisa membukanya setelah password akses diberikan.';
        }

        return 'Mode publik aktif. Pertanyaan user menyentuh detail teknis internal. Anda tetap menerima tool layer runtime berisi metadata halaman, data live, dan observasi implementasi dinamis, tetapi jangan ungkap nama file, folder, path, endpoint, database, tabel, kolom, query, atau detail backend internal. Tetap bantu dengan jawaban aman berbasis menu, submenu, card, halaman, tombol, dan langkah penggunaan. Jangan menganggap struktur atau data tidak tersedia hanya karena user sedang berada di halaman lain; gunakan konteks seluruh aplikasi dan data live yang tersedia. Tutup dengan catatan singkat bahwa detail teknis internal dikunci dan hanya admin yang bisa membukanya setelah memasukkan password akses yang benar.';
    }

    if ($requiresScopeOverride && !$hasScopeOverrideAccess) {
        return 'Mode publik aktif. Pertanyaan user meminta data bisnis di luar scope role saat ini. Anda tetap menerima metadata halaman, data live, dan observasi implementasi dinamis, tetapi data yang boleh dipakai hanya yang masih berada dalam scope role aktif. Jawab dengan jujur berdasarkan akses yang tersedia, jangan mengarang data lintas-role, jangan sebut user_id atau identifier internal mentah, dan tutup dengan catatan singkat bahwa akses lintas-role memerlukan password override. Detail teknis internal tetap terkunci.';
    }

    return 'Mode publik aktif. Prioritaskan jawaban berbasis menu, submenu, card, halaman, tombol, langkah penggunaan, dan status bisnis. Data live dalam scope role aktif boleh dipakai tanpa password. Anda menerima tool layer runtime berisi metadata halaman, data live, dan observasi implementasi dinamis, tetapi jangan menyebut nama file, folder, path, endpoint, database, tabel, kolom, query, user_id, primary key, atau identifier internal mentah kecuali sistem secara eksplisit mengaktifkan mode sensitif teknis untuk admin. Halaman aktif hanya konteks tambahan, bukan batas pengetahuan; jika pertanyaan membahas area lain, gunakan struktur seluruh aplikasi dan data live yang tersedia selama masih sesuai scope role.';
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
        'Jangan menyebut user_id, id akun, primary key, atau identifier internal mentah milik user di jawaban publik.',
        'Jika user meminta detail teknis internal, bantu dulu dengan versi aman lalu jelaskan bahwa detail internal butuh password akses.',
        'Tetap akurat terhadap struktur role, menu, dan alur bisnis aplikasi ini.',
        'Jangan mengatakan data, menu, atau modul tidak tersedia hanya karena halaman aktif berbeda, selama konteks PROJECT atau snapshot live memang tersedia.',
        'Gunakan halaman aktif sebagai konteks tambahan, tetapi untuk pertanyaan lintas menu tetap pakai struktur aplikasi secara keseluruhan.',
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
    foreach (aiAgentGetPublicSnapshotLines($conn, $role, $userId, $focusScopes, $message) as $line) {
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
        'Area Admin di navigasi memakai menu Dashboard, Item / Inventory, Item Loan, Item Return, dan Administrator.',
        'Submenu Admin yang terlihat di UI adalah Grafik / Informasi, Item Data, Item Detail, Request Loan, List Loan, Approval, Return Loan, User List, dan Role List.',
        'Area Manager di navigasi memakai menu Dashboard, Approvals, dan Reports dengan submenu Dashboard, Pending Approval, Approved, Rejected, Borrowing Report, dan Stock Report.',
        'Area User di navigasi memakai menu Dashboards, Borrowing, Return, dan History dengan submenu Dashboard, Request Borrowing, Borrowing Status, Request Return, dan Borrowing History.',
        'Area PIC Barang di navigasi memakai menu Dashboards, Update, dan Return dengan submenu Dashboard, Update Item, dan Return Item.',
        'Pengelolaan vendor admin tidak muncul sebagai submenu Vendor terpisah di navigasi utama; aksesnya berada di Item / Inventory > Item Detail melalui tombol Edit Vendor atau modal Manage Vendors.',
        'Hermes Agent adalah widget AI internal yang tersedia lintas role dan dipakai untuk membantu menjawab pertanyaan berdasarkan konteks aplikasi, snapshot live, dan grounding backend.',
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
        'users' => 'Penambahan user dilakukan dari area Admin pada menu Administrator > User List. Admin membuka form user, mengisi identitas seperti nama, NRP, email, memilih role, lalu menyimpan.',
        'peminjaman' => 'Pengajuan pinjam dilakukan dari area User pada menu Peminjaman. User memilih barang, mengisi kebutuhan peminjaman dan rencana kembali, lalu mengirim pengajuan untuk approval.',
        'approval' => 'Approval dilakukan dari area Manager atau Admin pada menu Persetujuan atau daftar pengajuan menunggu persetujuan. Approver meninjau item lalu menyetujui atau menolak.',
        'pengembalian' => 'Pengembalian diajukan dari area User pada menu Pengembalian, lalu diperiksa oleh Admin atau PIC Barang sampai proses selesai.',
        'extend' => 'Perpanjangan dilakukan dari alur pinjaman aktif. User mengajukan tanggal kembali baru lalu menunggu persetujuan.',
        'barang' => 'Pengelolaan inventaris admin dilakukan dari menu Item / Inventory dengan submenu Item Data dan Item Detail. Pengelolaan vendor dilakukan dari Item / Inventory > Item Detail melalui tombol Edit Vendor atau modal Manage Vendors, bukan dari submenu Vendor terpisah. Untuk PIC Barang, pengelolaan item dilakukan dari menu Update > Update Item.',
        'laporan' => 'Laporan tersedia pada menu Laporan untuk admin dan manager.',
        'auth' => 'Profil dan perubahan data akun dilakukan dari area Profil sesuai role masing-masing.',
        'dashboard' => 'Dashboard tiap role menampilkan ringkasan operasional yang relevan dengan tugas role tersebut.',
        'ai' => 'Hermes Agent membaca pertanyaan, riwayat chat, dan konteks halaman aktif, lalu backend menyiapkan grounding sebelum permintaan dikirim ke model AI.',
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

function aiAgentGetPublicSnapshotLines(mysqli $conn, string $role, int $userId, array $focusScopes = [], string $message = ''): array
{
    $lines = [];
    $messageLower = strtolower($message);

    $inventory = aiAgentFetchSingleRow($conn, '
        SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(CASE WHEN stok_tersedia <= safety_stock THEN 1 ELSE 0 END), 0) AS low_stock_items
        FROM barang
    ');
    $topStockItem = function_exists('aiAgentFetchTopStockItem') ? aiAgentFetchTopStockItem($conn) : [];
    $topStockLine = function_exists('aiAgentFormatTopStockLine') ? aiAgentFormatTopStockLine($topStockItem) : '';
    if (!empty($inventory)) {
        if ($topStockLine !== '') {
            $lines[] = sprintf(
                'Ringkasan inventaris saat ini: %d item master, %s, item low stock %d.',
                (int) ($inventory['total_items'] ?? 0),
                $topStockLine,
                (int) ($inventory['low_stock_items'] ?? 0)
            );
        } else {
            $lines[] = sprintf(
                'Ringkasan inventaris saat ini: %d item master, item low stock %d.',
                (int) ($inventory['total_items'] ?? 0),
                (int) ($inventory['low_stock_items'] ?? 0)
            );
        }
    }

    $loanCounts = aiAgentFetchLabelTotals($conn, 'SELECT status AS label, COUNT(*) AS total FROM peminjaman GROUP BY status');
    if (!empty($loanCounts)) {
        $lines[] = 'Ringkasan status peminjaman saat ini: ' . aiAgentFormatCountMap($loanCounts) . '.';
    }

    $returnCounts = aiAgentFetchLabelTotals($conn, 'SELECT status AS label, COUNT(*) AS total FROM pengembalian GROUP BY status');
    if (!empty($returnCounts)) {
        $lines[] = 'Ringkasan status pengembalian saat ini: ' . aiAgentFormatCountMap($returnCounts) . '.';
    }

    $wantsVendorContext = $role === 'admin'
        && (aiAgentFocusContains($focusScopes, ['barang']) || strpos($messageLower, 'vendor') !== false);
    if ($wantsVendorContext) {
        $vendorCountRow = aiAgentFetchSingleRow($conn, 'SELECT COUNT(*) AS total_vendor FROM vendor');
        $totalVendor = (int) ($vendorCountRow['total_vendor'] ?? 0);
        $lines[] = 'Jumlah vendor terdaftar saat ini: ' . $totalVendor . '.';

        if ($totalVendor > 0) {
            $vendorLimit = $totalVendor > 15 ? 15 : $totalVendor;
            $vendorRows = aiAgentFetchRows(
                $conn,
                'SELECT nama_vendor FROM vendor ORDER BY nama_vendor ASC LIMIT ' . (int) $vendorLimit
            );
            $vendorNames = [];
            foreach ($vendorRows as $vendorRow) {
                $vendorName = trim((string) ($vendorRow['nama_vendor'] ?? ''));
                if ($vendorName !== '') {
                    $vendorNames[] = $vendorName;
                }
            }

            if (!empty($vendorNames)) {
                $label = $totalVendor > $vendorLimit ? 'Contoh vendor terdaftar saat ini' : 'Daftar vendor terdaftar saat ini';
                $lines[] = $label . ': ' . implode(', ', $vendorNames) . '.';
            }
        }
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

function aiAgentGetBusinessOverrideExpiresAt(): int
{
    $expiresAt = (int) ($_SESSION['ai_business_override_expires_at'] ?? 0);
    if ($expiresAt <= time()) {
        unset($_SESSION['ai_business_override_expires_at']);
        return 0;
    }

    return $expiresAt;
}

function aiAgentGrantSensitiveAccess(int $durationMinutes): void
{
    $_SESSION['ai_sensitive_access_expires_at'] = time() + max(1, $durationMinutes) * 60;
}

function aiAgentGrantBusinessOverrideAccess(int $durationMinutes): void
{
    $_SESSION['ai_business_override_expires_at'] = time() + max(1, $durationMinutes) * 60;
}

function aiAgentRevokeSensitiveAccess(): void
{
    unset($_SESSION['ai_sensitive_access_expires_at']);
}

function aiAgentRevokeBusinessOverrideAccess(): void
{
    unset($_SESSION['ai_business_override_expires_at']);
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
    return aiAgentMessageRequestsTechnicalInfo($message, aiAgentGetToolLayerConfig());
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

        if (aiAgentStringLength($content) > 1200) {
            $content = aiAgentStringSubstring($content, 0, 1200);
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

function aiAgentRedactPublicReplySensitiveIdentifiers(string $reply, array $options = []): string
{
    $allowSensitiveIdentifiers = !empty($options['allow_sensitive_identifiers']);
    if ($allowSensitiveIdentifiers) {
        return $reply;
    }

    $sessionUserId = (int) ($options['session_user_id'] ?? 0);
    $redacted = $reply;

    $genericPatterns = [
        '/\buser[_\s-]?id\b\s*[:=]?\s*\d+\b/iu',
        '/\bid\s+akun\b\s*[:=]?\s*\d+\b/iu',
        '/\bprimary\s+key\b\s*[:=]?\s*\d+\b/iu',
    ];
    foreach ($genericPatterns as $pattern) {
        $redacted = preg_replace($pattern, 'akun aktif yang sedang login', $redacted);
    }

    if ($sessionUserId > 0) {
        $specificPatterns = [
            '/\buser[_\s-]?id\b\s*[:=]?\s*' . preg_quote((string) $sessionUserId, '/') . '\b/iu',
            '/\bid\s+akun\b\s*[:=]?\s*' . preg_quote((string) $sessionUserId, '/') . '\b/iu',
            '/\b' . preg_quote((string) $sessionUserId, '/') . '\b(?=\s*(?:yang sedang login|milik akun aktif))/iu',
        ];
        foreach ($specificPatterns as $pattern) {
            $redacted = preg_replace($pattern, 'akun aktif yang sedang login', $redacted);
        }
    }

    $redacted = preg_replace('/\(\s*akun aktif yang sedang login\s*\)/iu', '', (string) $redacted);
    $redacted = preg_replace('/\s{2,}/', ' ', (string) $redacted);

    return trim((string) $redacted);
}
