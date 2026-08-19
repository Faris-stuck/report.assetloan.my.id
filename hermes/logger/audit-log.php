<?php

function aiAgentResolveAuditLogPathValue(string $path): string
{
    $path = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    if ($path === '') {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'audit-log.jsonl';
    }

    if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1 || strpos($path, DIRECTORY_SEPARATOR) === 0) {
        return $path;
    }

    $projectRoot = dirname(__DIR__, 2);
    return $projectRoot . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function aiAgentGetAuditLogPath(array $config = []): string
{
    $configuredPath = trim((string) ($config['audit_log_path'] ?? ''));
    if ($configuredPath !== '') {
        return aiAgentResolveAuditLogPathValue($configuredPath);
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'audit-log.jsonl';
}

function aiAgentEnsureAuditLogDirectory(string $path): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }
}

function aiAgentAppendAuditLog(array $payload, array $config = []): void
{
    $path = aiAgentGetAuditLogPath($config);
    aiAgentEnsureAuditLogDirectory($path);

    @file_put_contents(
        $path,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function aiAgentLogSensitiveModeEvent(string $mode, string $event, array $context = [], array $config = []): void
{
    $metadata = isset($context['metadata']) && is_array($context['metadata']) ? $context['metadata'] : [];

    $payload = [
        'category' => 'sensitive_mode',
        'event' => trim($event),
        'mode' => trim($mode),
        'reason' => trim((string) ($context['reason'] ?? '')),
        'role' => trim((string) ($context['role'] ?? ($_SESSION['user_role'] ?? ''))),
        'user_id' => (int) ($context['user_id'] ?? ($_SESSION['user_id'] ?? 0)),
        'user_name' => trim((string) ($context['user_name'] ?? ($_SESSION['user_nama'] ?? ''))),
        'conversation_id' => trim((string) ($context['conversation_id'] ?? '')),
        'request_uri' => trim((string) ($_SERVER['REQUEST_URI'] ?? '')),
        'remote_addr' => trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')),
        'timestamp' => time(),
        'metadata' => $metadata,
    ];

    aiAgentAppendAuditLog($payload, $config);
}

function aiAgentReadAuditLogEntries(int $limit = 20, array $filters = [], array $config = []): array
{
    $normalizedLimit = max(1, $limit);
    $path = aiAgentGetAuditLogPath($config);
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || empty($lines)) {
        return [];
    }

    $entries = [];
    foreach (array_reverse($lines) as $line) {
        $decoded = json_decode((string) $line, true);
        if (!is_array($decoded)) {
            continue;
        }

        $matched = true;
        foreach ($filters as $key => $expectedValue) {
            if (!array_key_exists($key, $decoded)) {
                $matched = false;
                break;
            }

            if ((string) $decoded[$key] !== (string) $expectedValue) {
                $matched = false;
                break;
            }
        }

        if (!$matched) {
            continue;
        }

        $entries[] = $decoded;
        if (count($entries) >= $normalizedLimit) {
            break;
        }
    }

    return $entries;
}
