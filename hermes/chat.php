<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../api/session-helper.php';
require_once __DIR__ . '/config/hermes-schema.php';
require_once __DIR__ . '/config/hermes-roles.php';
require_once __DIR__ . '/config/hermes-keywords.php';
require_once __DIR__ . '/config/hermes-limits.php';
require_once __DIR__ . '/config/hermes-strings.php';
require_once __DIR__ . '/engine/context-helper.php';
require_once __DIR__ . '/engine/codebase-helper.php';
require_once __DIR__ . '/engine/index-helper.php';
require_once __DIR__ . '/engine/tool-helper.php';
require_once __DIR__ . '/model/config-helper.php';
require_once __DIR__ . '/engine/runtime-helper.php';
require_once __DIR__ . '/memory/conversation-helper.php';
require_once __DIR__ . '/memory/memory-helper.php';
require_once __DIR__ . '/database/integrated-memory-helper.php';
require_once __DIR__ . '/engine/skills-helper.php';
require_once __DIR__ . '/engine/self-improve-helper.php';
require_once __DIR__ . '/engine/summarization-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'error' => 'Method not allowed',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

SessionValidator::requireRole(['user', 'manager', 'admin', 'pic_barang']);

$config = aiAgentLoadConfig([
    __DIR__ . '/config/ai_agent.php',
]);
aiAgentBootstrapRuntimeConfig($config);
$memoryEnabled = !isset($config['memory_enabled']) ? true : (bool) $config['memory_enabled'];
$memoryDatabaseEnabled = !isset($config['memory_database_enabled']) ? false : (bool) $config['memory_database_enabled'];

// Use existing peminjaman database connection for memory storage
require_once __DIR__ . '/../config/database.php';
if ($memoryEnabled && $memoryDatabaseEnabled && isset($conn) && $conn instanceof mysqli && $conn->ping()) {
    aiAgentInitializeIntegratedMemoryTables($conn);
}

$missingPrimaryAiConfig = aiAgentGetMissingPrimaryAiRuntimeConfigKeys($config);
if (!empty($missingPrimaryAiConfig)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'AI agent configuration is incomplete.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$agentName = trim((string) ($config['agent_name'] ?? ''));
$agentBaseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
$agentApiKey = trim((string) ($config['api_key'] ?? ''));
$agentModel = trim((string) ($config['model'] ?? ''));
$agentTemperature = (float) ($config['temperature'] ?? 0);
$agentMaxTokens = (int) ($config['max_tokens'] ?? 0);
$agentTimeout = (int) ($config['timeout'] ?? 0);
$systemPrompt = trim((string) ($config['system_prompt'] ?? ''));
$sensitiveAccessPassword = (string) ($config['sensitive_access_password'] ?? '');
$sensitiveAccessDurationMinutes = max(1, (int) ($config['sensitive_access_duration_minutes'] ?? 30));
$sensitiveAccessUnlimited = !isset($config['sensitive_access_unlimited'])
    ? true
    : (bool) $config['sensitive_access_unlimited'];

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => 'Invalid JSON payload.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$message = trim((string) ($payload['message'] ?? ''));
if ($message === '') {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'error' => 'Message is required.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (aiAgentStringLength($message) > 2000) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'error' => 'Message is too long. Maximum 2000 characters.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$pageContext = isset($payload['page_context']) && is_array($payload['page_context']) ? $payload['page_context'] : [];
$pageSnapshot = isset($pageContext['ui_snapshot']) && is_array($pageContext['ui_snapshot']) ? $pageContext['ui_snapshot'] : [];
$clientHistory = isset($payload['history']) && is_array($payload['history']) ? $payload['history'] : [];
$conversationId = trim((string) ($payload['conversation_id'] ?? 'default'));
$conversationId = preg_replace('/[^a-z0-9._:-]+/i', '-', strtolower($conversationId));
$conversationId = trim((string) $conversationId, '-');
if ($conversationId === '') {
    $conversationId = 'default';
}

$sessionRole = (string) SessionValidator::getRole();
$sessionUserId = (int) SessionValidator::getUserId();
$sessionUserName = (string) SessionValidator::getUserName();
$canUnlockTechnicalAccess = $sessionRole === 'admin';
$canUnlockBusinessOverride = $sessionRole === 'admin';
$userMessageDisplay = $message;
$passwordWasSubmitted = false;

if (!$canUnlockTechnicalAccess) {
    aiAgentRevokeSensitiveAccess();
}

if ($sensitiveAccessPassword !== '' && aiAgentMessageContainsPassword($message, $sensitiveAccessPassword)) {
    $passwordWasSubmitted = true;
    $userMessageDisplay = aiAgentMaskSensitiveMessage($message, $sensitiveAccessPassword);
    $grantDuration = $sensitiveAccessUnlimited ? 0 : $sensitiveAccessDurationMinutes;

    if ($canUnlockBusinessOverride) {
        aiAgentGrantBusinessOverrideAccess($grantDuration);
    }
    if ($canUnlockTechnicalAccess) {
        aiAgentGrantSensitiveAccess($grantDuration);
    } else {
        aiAgentRevokeSensitiveAccess();
        aiAgentRevokeBusinessOverrideAccess();
    }
}

$businessOverrideExpiresAt = $canUnlockBusinessOverride ? aiAgentGetBusinessOverrideExpiresAt() : 0;
$hasBusinessOverrideAccess = $canUnlockBusinessOverride && $businessOverrideExpiresAt > time();
$sensitiveAccessExpiresAt = $canUnlockTechnicalAccess ? aiAgentGetSensitiveAccessExpiresAt() : 0;
$hasSensitiveAccess = $canUnlockTechnicalAccess && $sensitiveAccessExpiresAt > time();

// Calculate remaining duration for display
$sensitiveAccessRemainingMinutes = 0;
if ($hasSensitiveAccess && $sensitiveAccessExpiresAt > 0 && $sensitiveAccessExpiresAt !== PHP_INT_MAX) {
    $sensitiveAccessRemainingMinutes = max(0, ceil(($sensitiveAccessExpiresAt - time()) / 60));
}

$businessOverrideRemainingMinutes = 0;
if ($hasBusinessOverrideAccess && $businessOverrideExpiresAt > 0 && $businessOverrideExpiresAt !== PHP_INT_MAX) {
    $businessOverrideRemainingMinutes = max(0, ceil(($businessOverrideExpiresAt - time()) / 60));
}

$effectiveMessage = aiAgentStripSensitivePassword($message, $sensitiveAccessPassword);
if ($effectiveMessage === '') {
    $effectiveMessage = $passwordWasSubmitted ? '' : $message;
}

if ($passwordWasSubmitted && $effectiveMessage === '') {
    session_write_close();

    $replyMessage = $hasSensitiveAccess
        ? 'Akses teknis internal berhasil dibuka untuk sesi ini.'
        : ($hasBusinessOverrideAccess
            ? 'Akses data lintas-role berhasil dibuka untuk sesi ini.'
            : 'Password sudah disensor, tetapi akses tambahan tidak tersedia untuk role ini.');

    echo json_encode([
        'status' => 'ok',
        'reply' => $replyMessage,
        'processing_time_ms' => 0,
        'timestamp' => time(),
        'conversation_id' => $conversationId,
        'user_message_display' => $userMessageDisplay,
        'sensitive_access_active' => $hasSensitiveAccess,
        'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
        'sensitive_access_remaining_minutes' => $sensitiveAccessRemainingMinutes,
        'business_override_active' => $hasBusinessOverrideAccess,
        'business_override_expires_at' => $businessOverrideExpiresAt,
        'business_override_remaining_minutes' => $businessOverrideRemainingMinutes,
        'reply_contains_sensitive' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($effectiveMessage === '') {
    $effectiveMessage = $message;
}

$memoryConfig = aiAgentGetMemoryConfig($config);
$skillsConfig = aiAgentGetSkillsConfig($config);
$selfImproveConfig = aiAgentGetSelfImproveConfig($config);

$serverConversation = aiAgentLoadConversationMemory($memoryConfig, $sessionRole, $sessionUserId, $conversationId);
$serverMessages = isset($serverConversation['messages']) && is_array($serverConversation['messages'])
    ? $serverConversation['messages']
    : [];
$history = aiAgentMergeConversationHistory($clientHistory, $serverMessages, 12);

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
    'conversation_id' => $conversationId,
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
    $groundingContext = aiAgentBuildGroundingContext($conn, [
        'role' => $sessionRole,
        'user_id' => $sessionUserId,
        'message' => $effectiveMessage,
        'page_path' => (string) ($pageContext['path'] ?? ''),
        'page_title' => (string) ($pageContext['title'] ?? ''),
        'page_heading' => (string) ($pageContext['heading'] ?? ''),
    ]);
}

$memoryContext = aiAgentBuildMemoryContext($memoryConfig, $sessionRole, $sessionUserId, $conversationId, $effectiveMessage);
$skillsContext = aiAgentBuildSkillsContext($effectiveMessage, $pageContext, $skillsConfig);
$modePrompt = aiAgentBuildModePrompt([
    'agent_name' => $agentName,
    'has_sensitive_access' => $hasSensitiveAccess,
    'has_business_override_access' => $hasBusinessOverrideAccess,
    'can_unlock_sensitive_access' => $canUnlockTechnicalAccess,
    'access_decision' => $accessDecision,
]);

$messages = [
    [
        'role' => 'system',
        'content' => $systemPrompt,
    ],
    [
        'role' => 'system',
        'content' => $modePrompt,
    ],
];

if ($skillsContext !== '') {
    $messages[] = [
        'role' => 'system',
        'content' => $skillsContext,
    ];
}

if ($memoryContext !== '') {
    $messages[] = [
        'role' => 'system',
        'content' => $memoryContext,
    ];
}

if ($groundingContext !== '') {
    $messages[] = [
        'role' => 'system',
        'content' => $groundingContext,
    ];
}

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

$fallbackConversationMessages = [];
foreach (aiAgentSanitizeHistoryMessages($history, $sensitiveAccessPassword, $useSensitiveGrounding) as $historyMessage) {
    $fallbackConversationMessages[] = $historyMessage;
}
$fallbackConversationMessages[] = [
    'role' => 'user',
    'content' => $effectiveMessage,
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
$usedFallbackProvider = false;

// Try fallback provider if primary failed
if (($httpCode <= 0 && $result === '') || $httpCode >= 400) {
    $extProviderConfig = aiAgentGetExtendedProviderConfig($config);
    if (!empty($extProviderConfig['enabled']) && !empty($extProviderConfig['fallback_on_error'])) {
        $fallbackResult = aiAgentCallExtendedProvider($extProviderConfig, $systemPrompt, $fallbackConversationMessages);
        if ($fallbackResult['ok']) {
            $reply = $fallbackResult['reply'];
            $usedFallbackProvider = true;
            $httpCode = 200; // Mark as success
            $result = json_encode(['fallback' => true, 'reply' => $reply]);
            // Skip to processing stage
        }
    }
}

if ($httpCode <= 0 && $result === '') {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'error' => 'Failed to connect to AI provider.',
        'details' => $transportError !== '' ? $transportError : 'No HTTP transport available for outbound AI request.',
        'conversation_id' => $conversationId,
        'user_message_display' => $userMessageDisplay,
        'sensitive_access_active' => $hasSensitiveAccess,
        'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
        'business_override_active' => $hasBusinessOverrideAccess,
        'business_override_expires_at' => $businessOverrideExpiresAt,
        'reply_contains_sensitive' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$usedFallbackProvider) {
    $decoded = json_decode($result, true);
    if (!is_array($decoded)) {
        http_response_code(502);
        echo json_encode([
            'status' => 'error',
            'error' => 'Invalid response from AI provider.',
            'conversation_id' => $conversationId,
            'user_message_display' => $userMessageDisplay,
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
            'conversation_id' => $conversationId,
            'user_message_display' => $userMessageDisplay,
            'reply_contains_sensitive' => false,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $reply = aiAgentExtractProviderReply($decoded);
} else {
    $decoded = json_decode($result, true);
}

if ($reply === '') {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'error' => 'AI provider did not return a valid reply.',
        'conversation_id' => $conversationId,
        'user_message_display' => $userMessageDisplay,
        'reply_contains_sensitive' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$reply = aiAgentApplyTruthfulnessGuard($reply, $conn, [
    'message' => $effectiveMessage,
    'role' => $sessionRole,
    'page_context' => $pageContext,
    'page_snapshot' => $pageSnapshot,
]);
$reply = aiAgentRedactPublicReplySensitiveIdentifiers($reply, [
    'session_user_id' => $sessionUserId,
    'allow_sensitive_identifiers' => $useSensitiveGrounding,
]);

aiAgentAppendConversationMemory($memoryConfig, $sessionRole, $sessionUserId, $conversationId, $effectiveMessage, $reply, $pageContext);
aiAgentSetUserActiveConversationId($memoryConfig, $sessionRole, $sessionUserId, $conversationId);
$storedNotes = aiAgentStoreLessonMemory($memoryConfig, $sessionRole, $sessionUserId, $effectiveMessage, $reply);
aiAgentAppendReflectionLog($memoryConfig, [
    'conversation_id' => $conversationId,
    'role' => $sessionRole,
    'user_id' => $sessionUserId,
    'user_name' => $sessionUserName,
    'message' => $effectiveMessage,
    'reply_excerpt' => aiAgentStringSubstring($reply, 0, 240),
    'stored_notes' => $storedNotes,
    'timestamp' => time(),
    'page_path' => (string) ($pageContext['path'] ?? ''),
]);
aiAgentStoreSelfImprovementObservation($selfImproveConfig, [
    'conversation_id' => $conversationId,
    'role' => $sessionRole,
    'user_id' => $sessionUserId,
    'message' => $effectiveMessage,
    'reply_excerpt' => aiAgentStringSubstring($reply, 0, 240),
    'timestamp' => time(),
], $storedNotes);
$selfImprovePipeline = aiAgentProcessSelfImprovementPipeline($selfImproveConfig, $skillsConfig, [
    'conversation_id' => $conversationId,
    'role' => $sessionRole,
    'user_id' => $sessionUserId,
    'message' => $effectiveMessage,
    'reply_excerpt' => aiAgentStringSubstring($reply, 0, 240),
    'timestamp' => time(),
], $storedNotes);

// Prepare conversation state for summarization & memory flush
$conversationMessages = $fallbackConversationMessages;
$conversationMessages[] = [
    'role' => 'assistant',
    'content' => $reply,
];

// Apply conversation summarization if needed
$summaryConfig = aiAgentGetSummarizationConfig($config);
if (aiAgentShouldSummarizeConversation($conversationMessages, $summaryConfig)) {
    $conversationMessages = aiAgentSummarizeConversation($conversationMessages, $summaryConfig);
}

// Load existing memory and refresh the persistent memory snapshot
$previousMemory = aiAgentLoadMemoryMarkdown($memoryConfig, $sessionRole, $sessionUserId);
aiAgentFlushMemoryMarkdown($memoryConfig, $sessionRole, $sessionUserId, $conversationMessages, $previousMemory);

// Priority 3: Verify RAG sources used in tool context
$ragVerification = aiAgentVerifyRAGSourcesUsed($sharedState ?? [], $sections ?? []);
$ragSummary = aiAgentFormatRAGSummary($ragVerification);

// Build RAG provenance report
$ragProvenanceReport = aiAgentBuildRAGProvenanceReport($sections ?? []);

$processingTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

echo json_encode([
    'status' => 'ok',
    'reply' => $reply,
    'processing_time_ms' => $processingTimeMs,
    'timestamp' => time(),
    'conversation_id' => $conversationId,
    'user_message_display' => $userMessageDisplay,
    'sensitive_access_active' => $hasSensitiveAccess,
    'sensitive_access_expires_at' => $sensitiveAccessExpiresAt,
    'business_override_active' => $hasBusinessOverrideAccess,
    'business_override_expires_at' => $businessOverrideExpiresAt,
    'reply_contains_sensitive' => $useSensitiveGrounding,
    'memory_notes_stored' => count($storedNotes),
    'candidate_skill_created' => !empty($selfImprovePipeline['candidate_skill_path']),
    'auto_skills_activated' => count($selfImprovePipeline['activated_skills'] ?? []),
    // Priority 3: RAG verification
    'rag_sources_used' => $ragVerification['active_sources'] ?? [],
    'rag_coverage_percentage' => $ragVerification['coverage_percentage'] ?? 0,
    'rag_fully_utilized' => $ragVerification['rag_fully_utilized'] ?? false,
    'rag_summary' => $ragSummary,
    // Priority 4: Fallback provider indicator
    'fallback_provider_used' => $usedFallbackProvider,
], JSON_UNESCAPED_UNICODE);
