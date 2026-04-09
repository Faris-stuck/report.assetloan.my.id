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

function aiAgentGetSensitiveAccessExpiresAt(): int
{
    if (!empty($_SESSION['ai_sensitive_access_unlimited'])) {
        return PHP_INT_MAX;
    }

    $expiresAt = (int) ($_SESSION['ai_sensitive_access_expires_at'] ?? 0);
    if ($expiresAt <= time()) {
        unset($_SESSION['ai_sensitive_access_expires_at']);
        return 0;
    }

    return $expiresAt;
}

function aiAgentGetBusinessOverrideExpiresAt(): int
{
    if (!empty($_SESSION['ai_business_override_unlimited'])) {
        return PHP_INT_MAX;
    }

    $expiresAt = (int) ($_SESSION['ai_business_override_expires_at'] ?? 0);
    if ($expiresAt <= time()) {
        unset($_SESSION['ai_business_override_expires_at']);
        return 0;
    }

    return $expiresAt;
}

function aiAgentGrantSensitiveAccess(int $durationMinutes): void
{
    if ($durationMinutes <= 0) {
        $_SESSION['ai_sensitive_access_unlimited'] = true;
        unset($_SESSION['ai_sensitive_access_expires_at']);
        return;
    }

    unset($_SESSION['ai_sensitive_access_unlimited']);
    $_SESSION['ai_sensitive_access_expires_at'] = time() + max(1, $durationMinutes) * 60;
}

function aiAgentGrantBusinessOverrideAccess(int $durationMinutes): void
{
    if ($durationMinutes <= 0) {
        $_SESSION['ai_business_override_unlimited'] = true;
        unset($_SESSION['ai_business_override_expires_at']);
        return;
    }

    unset($_SESSION['ai_business_override_unlimited']);
    $_SESSION['ai_business_override_expires_at'] = time() + max(1, $durationMinutes) * 60;
}

function aiAgentRevokeSensitiveAccess(): void
{
    unset($_SESSION['ai_sensitive_access_expires_at']);
    unset($_SESSION['ai_sensitive_access_unlimited']);
}

function aiAgentRevokeBusinessOverrideAccess(): void
{
    unset($_SESSION['ai_business_override_expires_at']);
    unset($_SESSION['ai_business_override_unlimited']);
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
