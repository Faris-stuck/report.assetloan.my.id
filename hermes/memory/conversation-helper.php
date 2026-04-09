<?php

function aiAgentBuildModePrompt(array $options = []): string
{
    $agentName = trim((string) ($options['agent_name'] ?? 'Hermes Agent'));
    $hasSensitiveAccess = !empty($options['has_sensitive_access']);
    $hasBusinessOverrideAccess = !empty($options['has_business_override_access']);
    $canUnlockSensitiveAccess = !empty($options['can_unlock_sensitive_access']);
    $accessDecision = isset($options['access_decision']) && is_array($options['access_decision']) ? $options['access_decision'] : [];
    $technicalRequested = !empty($accessDecision['technical_requested']);
    $requiresScopeOverride = !empty($accessDecision['requires_scope_override']);
    $hasScopeOverrideAccess = !empty($accessDecision['has_scope_override_access']);

    if ($technicalRequested && $hasSensitiveAccess) {
        return $agentName . ' sedang berada pada mode teknis. Anda boleh membahas file, path, endpoint, tabel, kolom, query, dan detail implementasi karena akses sensitif sedang aktif.';
    }

    if ($hasBusinessOverrideAccess && $requiresScopeOverride) {
        return $agentName . ' sedang berada pada mode lintas-role. Anda boleh memakai data bisnis lintas role, tetapi detail teknis internal tetap dibatasi kecuali akses sensitif teknis juga aktif.';
    }

    if ($technicalRequested && !$hasSensitiveAccess) {
        return $canUnlockSensitiveAccess
            ? 'Mode publik aktif. User meminta detail teknis internal. Bantu dengan jawaban aman dulu, lalu jelaskan bahwa detail teknis internal penuh memerlukan password akses.'
            : 'Mode publik aktif. User meminta detail teknis internal. Jawab versi aman tanpa menyebut detail implementasi internal secara langsung.';
    }

    if ($requiresScopeOverride && !$hasScopeOverrideAccess) {
        return 'Mode publik aktif. User meminta data lintas-role yang belum diizinkan. Jawab hanya dengan data yang masih sesuai scope role saat ini dan jangan mengarang data lintas-role.';
    }

    return 'Mode publik aktif. Prioritaskan jawaban berbasis menu, langkah penggunaan, status bisnis, dan konteks halaman. Jika perlu menyebut detail teknis, pastikan memang diminta secara jelas dan sesuai izin yang aktif.';
}

function aiAgentBuildTimedAccessState(
    string $expiresKey,
    string $unlimitedKey,
    string $grantedAtKey,
    string $lastActivityKey,
    string $mode
): array {
    $now = time();
    $grantedAt = (int) ($_SESSION[$grantedAtKey] ?? 0);
    $lastActivityAt = (int) ($_SESSION[$lastActivityKey] ?? 0);

    if (!empty($_SESSION[$unlimitedKey])) {
        return [
            'mode' => $mode,
            'active' => true,
            'unlimited' => true,
            'expires_at' => PHP_INT_MAX,
            'granted_at' => $grantedAt,
            'last_activity_at' => $lastActivityAt,
            'remaining_seconds' => null,
            'remaining_minutes' => null,
            'expired' => false,
        ];
    }

    $expiresAt = (int) ($_SESSION[$expiresKey] ?? 0);
    if ($expiresAt <= 0) {
        if ($grantedAt > 0) {
            unset($_SESSION[$grantedAtKey]);
        }
        if ($lastActivityAt > 0) {
            unset($_SESSION[$lastActivityKey]);
        }

        return [
            'mode' => $mode,
            'active' => false,
            'unlimited' => false,
            'expires_at' => 0,
            'granted_at' => 0,
            'last_activity_at' => 0,
            'remaining_seconds' => 0,
            'remaining_minutes' => 0,
            'expired' => false,
        ];
    }

    if ($expiresAt <= $now) {
        unset($_SESSION[$expiresKey]);
        unset($_SESSION[$unlimitedKey]);
        unset($_SESSION[$grantedAtKey]);
        unset($_SESSION[$lastActivityKey]);

        if (function_exists('aiAgentLogSensitiveModeEvent')) {
            aiAgentLogSensitiveModeEvent($mode, 'expired', [
                'reason' => 'duration_elapsed',
                'metadata' => [
                    'expired_at' => $expiresAt,
                    'granted_at' => $grantedAt,
                    'last_activity_at' => $lastActivityAt,
                ],
            ]);
        }

        return [
            'mode' => $mode,
            'active' => false,
            'unlimited' => false,
            'expires_at' => 0,
            'granted_at' => 0,
            'last_activity_at' => 0,
            'remaining_seconds' => 0,
            'remaining_minutes' => 0,
            'expired' => true,
        ];
    }

    $remainingSeconds = max(0, $expiresAt - $now);

    return [
        'mode' => $mode,
        'active' => true,
        'unlimited' => false,
        'expires_at' => $expiresAt,
        'granted_at' => $grantedAt,
        'last_activity_at' => $lastActivityAt,
        'remaining_seconds' => $remainingSeconds,
        'remaining_minutes' => (int) ceil($remainingSeconds / 60),
        'expired' => false,
    ];
}

function aiAgentGetSensitiveAccessState(): array
{
    return aiAgentBuildTimedAccessState(
        'ai_sensitive_access_expires_at',
        'ai_sensitive_access_unlimited',
        'ai_sensitive_access_granted_at',
        'ai_sensitive_access_last_activity_at',
        'sensitive_access'
    );
}

function aiAgentGetBusinessOverrideState(): array
{
    return aiAgentBuildTimedAccessState(
        'ai_business_override_expires_at',
        'ai_business_override_unlimited',
        'ai_business_override_granted_at',
        'ai_business_override_last_activity_at',
        'business_override'
    );
}

function aiAgentEnforceSensitiveUnlimitedPolicy(bool $allowUnlimited): array
{
    $result = [
        'sensitive_access' => false,
        'business_override' => false,
    ];

    if ($allowUnlimited) {
        return $result;
    }

    foreach (
        [
            'sensitive_access' => [
                'expires_key' => 'ai_sensitive_access_expires_at',
                'unlimited_key' => 'ai_sensitive_access_unlimited',
                'granted_at_key' => 'ai_sensitive_access_granted_at',
            ],
            'business_override' => [
                'expires_key' => 'ai_business_override_expires_at',
                'unlimited_key' => 'ai_business_override_unlimited',
                'granted_at_key' => 'ai_business_override_granted_at',
            ],
        ] as $mode => $keys
    ) {
        if (empty($_SESSION[$keys['unlimited_key']])) {
            continue;
        }

        $previousExpiresAt = (int) ($_SESSION[$keys['expires_key']] ?? 0);
        $grantedAt = (int) ($_SESSION[$keys['granted_at_key']] ?? 0);

        unset($_SESSION[$keys['unlimited_key']]);
        if ($previousExpiresAt <= time()) {
            unset($_SESSION[$keys['expires_key']]);
            unset($_SESSION[$keys['granted_at_key']]);
        }

        $result[$mode] = true;

        if (function_exists('aiAgentLogSensitiveModeEvent')) {
            aiAgentLogSensitiveModeEvent($mode, 'policy_cleanup', [
                'reason' => 'unlimited_disabled_by_config',
                'metadata' => [
                    'previous_expires_at' => $previousExpiresAt,
                    'granted_at' => $grantedAt,
                ],
            ]);
        }
    }

    return $result;
}

function aiAgentGetSensitiveAccessExpiresAt(): int
{
    $state = aiAgentGetSensitiveAccessState();
    return (int) ($state['expires_at'] ?? 0);
}

function aiAgentGetBusinessOverrideExpiresAt(): int
{
    $state = aiAgentGetBusinessOverrideState();
    return (int) ($state['expires_at'] ?? 0);
}

function aiAgentRefreshTimedAccessByActivity(
    string $expiresKey,
    string $unlimitedKey,
    string $grantedAtKey,
    string $lastActivityKey,
    string $mode,
    int $durationMinutes,
    string $reason = 'user_activity',
    array $context = []
): bool {
    $state = aiAgentBuildTimedAccessState($expiresKey, $unlimitedKey, $grantedAtKey, $lastActivityKey, $mode);
    if (empty($state['active']) || !empty($state['unlimited'])) {
        return false;
    }

    $now = time();
    $normalizedDurationMinutes = max(1, $durationMinutes);
    $previousExpiresAt = (int) ($state['expires_at'] ?? 0);
    $newExpiresAt = $now + ($normalizedDurationMinutes * 60);

    $_SESSION[$expiresKey] = $newExpiresAt;
    if ((int) ($_SESSION[$grantedAtKey] ?? 0) <= 0) {
        $_SESSION[$grantedAtKey] = $now;
    }
    $_SESSION[$lastActivityKey] = $now;

    if (function_exists('aiAgentLogSensitiveModeEvent')) {
        $metadata = isset($context['metadata']) && is_array($context['metadata']) ? $context['metadata'] : [];
        $metadata['previous_expires_at'] = $previousExpiresAt;
        $metadata['new_expires_at'] = $newExpiresAt;
        $metadata['activity_at'] = $now;
        $metadata['duration_minutes'] = $normalizedDurationMinutes;

        aiAgentLogSensitiveModeEvent($mode, 'refreshed', [
            'reason' => $reason,
            'role' => $context['role'] ?? ($_SESSION['user_role'] ?? ''),
            'user_id' => $context['user_id'] ?? ($_SESSION['user_id'] ?? 0),
            'user_name' => $context['user_name'] ?? ($_SESSION['user_nama'] ?? ''),
            'conversation_id' => $context['conversation_id'] ?? '',
            'metadata' => $metadata,
        ]);
    }

    return $newExpiresAt !== $previousExpiresAt;
}

function aiAgentRefreshSensitiveAccessActivity(
    int $durationMinutes,
    string $reason = 'user_activity',
    array $context = []
): bool {
    return aiAgentRefreshTimedAccessByActivity(
        'ai_sensitive_access_expires_at',
        'ai_sensitive_access_unlimited',
        'ai_sensitive_access_granted_at',
        'ai_sensitive_access_last_activity_at',
        'sensitive_access',
        $durationMinutes,
        $reason,
        $context
    );
}

function aiAgentRefreshBusinessOverrideActivity(
    int $durationMinutes,
    string $reason = 'user_activity',
    array $context = []
): bool {
    return aiAgentRefreshTimedAccessByActivity(
        'ai_business_override_expires_at',
        'ai_business_override_unlimited',
        'ai_business_override_granted_at',
        'ai_business_override_last_activity_at',
        'business_override',
        $durationMinutes,
        $reason,
        $context
    );
}

function aiAgentGrantSensitiveAccess(int $durationMinutes): void
{
    $previousState = aiAgentGetSensitiveAccessState();
    $grantedAt = time();

    if ($durationMinutes <= 0) {
        $_SESSION['ai_sensitive_access_unlimited'] = true;
        unset($_SESSION['ai_sensitive_access_expires_at']);
        $_SESSION['ai_sensitive_access_granted_at'] = $grantedAt;
        $_SESSION['ai_sensitive_access_last_activity_at'] = $grantedAt;

        if (function_exists('aiAgentLogSensitiveModeEvent')) {
            aiAgentLogSensitiveModeEvent('sensitive_access', 'granted', [
                'reason' => 'password_verified',
                'metadata' => [
                    'unlimited' => true,
                    'previous_active' => !empty($previousState['active']),
                    'previous_expires_at' => (int) ($previousState['expires_at'] ?? 0),
                ],
            ]);
        }

        return;
    }

    unset($_SESSION['ai_sensitive_access_unlimited']);
    $_SESSION['ai_sensitive_access_expires_at'] = $grantedAt + max(1, $durationMinutes) * 60;
    $_SESSION['ai_sensitive_access_granted_at'] = $grantedAt;
    $_SESSION['ai_sensitive_access_last_activity_at'] = $grantedAt;

    if (function_exists('aiAgentLogSensitiveModeEvent')) {
        aiAgentLogSensitiveModeEvent('sensitive_access', 'granted', [
            'reason' => 'password_verified',
            'metadata' => [
                'duration_minutes' => max(1, $durationMinutes),
                'expires_at' => (int) $_SESSION['ai_sensitive_access_expires_at'],
                'previous_active' => !empty($previousState['active']),
                'previous_expires_at' => (int) ($previousState['expires_at'] ?? 0),
            ],
        ]);
    }
}

function aiAgentGrantBusinessOverrideAccess(int $durationMinutes): void
{
    $previousState = aiAgentGetBusinessOverrideState();
    $grantedAt = time();

    if ($durationMinutes <= 0) {
        $_SESSION['ai_business_override_unlimited'] = true;
        unset($_SESSION['ai_business_override_expires_at']);
        $_SESSION['ai_business_override_granted_at'] = $grantedAt;
        $_SESSION['ai_business_override_last_activity_at'] = $grantedAt;

        if (function_exists('aiAgentLogSensitiveModeEvent')) {
            aiAgentLogSensitiveModeEvent('business_override', 'granted', [
                'reason' => 'password_verified',
                'metadata' => [
                    'unlimited' => true,
                    'previous_active' => !empty($previousState['active']),
                    'previous_expires_at' => (int) ($previousState['expires_at'] ?? 0),
                ],
            ]);
        }

        return;
    }

    unset($_SESSION['ai_business_override_unlimited']);
    $_SESSION['ai_business_override_expires_at'] = $grantedAt + max(1, $durationMinutes) * 60;
    $_SESSION['ai_business_override_granted_at'] = $grantedAt;
    $_SESSION['ai_business_override_last_activity_at'] = $grantedAt;

    if (function_exists('aiAgentLogSensitiveModeEvent')) {
        aiAgentLogSensitiveModeEvent('business_override', 'granted', [
            'reason' => 'password_verified',
            'metadata' => [
                'duration_minutes' => max(1, $durationMinutes),
                'expires_at' => (int) $_SESSION['ai_business_override_expires_at'],
                'previous_active' => !empty($previousState['active']),
                'previous_expires_at' => (int) ($previousState['expires_at'] ?? 0),
            ],
        ]);
    }
}

function aiAgentRevokeSensitiveAccess(): void
{
    unset($_SESSION['ai_sensitive_access_expires_at']);
    unset($_SESSION['ai_sensitive_access_unlimited']);
    unset($_SESSION['ai_sensitive_access_granted_at']);
    unset($_SESSION['ai_sensitive_access_last_activity_at']);
}

function aiAgentRevokeBusinessOverrideAccess(): void
{
    unset($_SESSION['ai_business_override_expires_at']);
    unset($_SESSION['ai_business_override_unlimited']);
    unset($_SESSION['ai_business_override_granted_at']);
    unset($_SESSION['ai_business_override_last_activity_at']);
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
    $cleaned = preg_replace('/\s+/', ' ', (string) $cleaned);
    return trim((string) $cleaned, " \t\n\r\0\x0B,.:;|-_");
}

function aiAgentSanitizeHistoryMessages(array $history, string $sensitiveAccessPassword = '', bool $allowSensitiveHistory = false): array
{
    $messages = [];
    foreach (array_slice($history, -10) as $item) {
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

        if (function_exists('aiAgentStringLength') && aiAgentStringLength($content) > 1200) {
            $content = aiAgentStringSubstring($content, 0, 1200);
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

    if (is_array($error) && isset($error['message']) && is_string($error['message'])) {
        return trim($error['message']);
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
        return trim($reply);
    }

    $redacted = preg_replace('/\b(user[_\s-]?id|primary\s+key|id\s+akun)\b\s*[:=]?\s*\d+\b/iu', 'akun aktif', $reply);
    $redacted = preg_replace('/\s{2,}/', ' ', (string) $redacted);

    return trim((string) $redacted);
}

function aiAgentApplyTruthfulnessGuard(string $reply, mysqli $conn, array $options = []): string
{
    return trim($reply);
}
