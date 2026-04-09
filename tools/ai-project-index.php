<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script only runs in CLI mode.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/api/koneksi.php';
require_once $projectRoot . '/hermes/engine/context-helper.php';
require_once $projectRoot . '/hermes/engine/codebase-helper.php';
require_once $projectRoot . '/hermes/engine/index-helper.php';
require_once $projectRoot . '/hermes/engine/tool-helper.php';
require_once $projectRoot . '/hermes/model/config-helper.php';
require_once $projectRoot . '/hermes/engine/runtime-helper.php';

$command = strtolower(trim((string) ($_SERVER['argv'][1] ?? 'status')));
$options = aiAgentParseProjectIndexCliOptions(array_slice($_SERVER['argv'] ?? [], 2));

if ($command === 'help' || !in_array($command, ['status', 'signal', 'rebuild'], true)) {
    fwrite(STDOUT, "Usage:\n");
    fwrite(STDOUT, "  php tools/ai-project-index.php status\n");
    fwrite(STDOUT, "  php tools/ai-project-index.php signal --reason=deploy_update\n");
    fwrite(STDOUT, "  php tools/ai-project-index.php rebuild --reason=deploy_update [--touch-signal]\n");
    exit($command === 'help' ? 0 : 2);
}

$config = aiAgentLoadConfig([
    $projectRoot . '/hermes/config/ai_agent.php',
]);

$result = [
    'status' => 'ok',
    'command' => $command,
    'timestamp' => time(),
];

switch ($command) {
    case 'status':
        $state = aiAgentGetProjectIndexStatusSnapshot($conn, [
            'config' => $config,
        ]);
        $result['project_index'] = aiAgentSummarizeProjectIndexState($state);
        break;

    case 'signal':
        $signal = aiAgentTouchProjectIndexWatcherSignal($config, [
            'reason' => aiAgentNormalizeProjectIndexCliReason((string) ($options['reason'] ?? 'manual_signal'), 'manual_signal'),
            'source' => 'cli',
        ]);
        $state = aiAgentGetProjectIndexStatusSnapshot($conn, [
            'config' => $config,
        ]);
        $result['signal'] = $signal;
        $result['project_index'] = aiAgentSummarizeProjectIndexState($state);
        break;

    case 'rebuild':
        if (!empty($options['touch-signal'])) {
            aiAgentTouchProjectIndexWatcherSignal($config, [
                'reason' => aiAgentNormalizeProjectIndexCliReason((string) ($options['reason'] ?? 'manual_rebuild'), 'manual_rebuild'),
                'source' => 'cli',
            ]);
        }

        $state = aiAgentForceProjectIndexBundleRebuild($conn, [
            'config' => $config,
            'reason' => aiAgentNormalizeProjectIndexCliReason((string) ($options['reason'] ?? 'manual_rebuild'), 'manual_rebuild'),
        ]);
        $result['project_index'] = aiAgentSummarizeProjectIndexState($state);
        break;
}

if (!empty($result['project_index']['reason'])) {
    $result['project_index']['reason_label'] = aiAgentFormatProjectIndexReason((string) $result['project_index']['reason']);
}

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit(0);

function aiAgentParseProjectIndexCliOptions(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        $argument = trim((string) $argument);
        if ($argument === '' || strpos($argument, '--') !== 0) {
            continue;
        }

        $argument = substr($argument, 2);
        if ($argument === '') {
            continue;
        }

        $parts = explode('=', $argument, 2);
        $key = strtolower(trim((string) ($parts[0] ?? '')));
        if ($key === '') {
            continue;
        }

        if (count($parts) === 1) {
            $options[$key] = true;
            continue;
        }

        $options[$key] = trim((string) ($parts[1] ?? ''));
    }

    return $options;
}

function aiAgentNormalizeProjectIndexCliReason(string $reason, string $fallback): string
{
    $reason = strtolower(trim($reason));
    $reason = preg_replace('/[^a-z0-9._-]+/', '_', $reason);
    $reason = trim((string) $reason, '_');

    return $reason !== '' ? $reason : $fallback;
}
