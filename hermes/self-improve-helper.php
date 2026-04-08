<?php

function aiAgentGetSelfImproveConfig(array $config = []): array
{
    $patchesDir = aiAgentResolveHermesStoragePath((string) ($config['self_improvement_patches_dir'] ?? 'hermes/patches'));
    $logsDir = aiAgentResolveHermesStoragePath((string) ($config['self_improvement_logs_dir'] ?? 'hermes/logs'));

    return [
        'enabled' => !isset($config['self_improvement_enabled']) ? true : (bool) $config['self_improvement_enabled'],
        'patches_dir' => $patchesDir,
        'logs_dir' => $logsDir,
        'candidates_dir' => $patchesDir . DIRECTORY_SEPARATOR . 'candidate-skills',
        'reviewed_dir' => $patchesDir . DIRECTORY_SEPARATOR . 'reviewed-skills',
        'activated_dir' => $patchesDir . DIRECTORY_SEPARATOR . 'activated-skills',
        'suggestions_file' => $patchesDir . DIRECTORY_SEPARATOR . 'learning-candidates.md',
        'log_file' => $logsDir . DIRECTORY_SEPARATOR . 'self-improvement.jsonl',
        'activation_log_file' => $logsDir . DIRECTORY_SEPARATOR . 'skill-activation.jsonl',
    ];
}

function aiAgentEnsureSelfImproveDirectories(array $selfImproveConfig = []): void
{
    foreach (
        [
            (string) ($selfImproveConfig['patches_dir'] ?? ''),
            (string) ($selfImproveConfig['logs_dir'] ?? ''),
            (string) ($selfImproveConfig['candidates_dir'] ?? ''),
            (string) ($selfImproveConfig['reviewed_dir'] ?? ''),
            (string) ($selfImproveConfig['activated_dir'] ?? ''),
        ] as $directory
    ) {
        if ($directory !== '' && !is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }
}

function aiAgentStoreSelfImprovementObservation(array $selfImproveConfig, array $payload, array $notes = []): void
{
    if (empty($selfImproveConfig['enabled'])) {
        return;
    }

    aiAgentEnsureSelfImproveDirectories($selfImproveConfig);

    @file_put_contents(
        (string) ($selfImproveConfig['log_file'] ?? ''),
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND
    );

    if (empty($notes)) {
        return;
    }

    $markdownLines = [];
    if (!is_file((string) ($selfImproveConfig['suggestions_file'] ?? ''))) {
        $markdownLines[] = '# Learning Candidates';
        $markdownLines[] = '';
    }

    $markdownLines[] = '## ' . date('Y-m-d H:i:s');
    foreach ($notes as $note) {
        $markdownLines[] = '- ' . trim((string) $note);
    }
    $markdownLines[] = '';

    @file_put_contents((string) ($selfImproveConfig['suggestions_file'] ?? ''), implode(PHP_EOL, $markdownLines) . PHP_EOL, FILE_APPEND);
}

function aiAgentBuildSelfImproveSlug(string $seed): string
{
    $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $seed), '-'));
    if ($slug === '') {
        $slug = 'candidate-skill';
    }

    return trim($slug, '-');
}

function aiAgentBuildCandidateSkillMarkdown(array $payload, array $notes): string
{
    $message = trim((string) ($payload['message'] ?? ''));
    $replyExcerpt = trim((string) ($payload['reply_excerpt'] ?? ''));
    $titleSeed = !empty($notes) ? (string) $notes[0] : ($message !== '' ? $message : 'Candidate Skill');
    $title = aiAgentBuildSelfImproveSlug($titleSeed);

    $lines = [];
    $lines[] = '# Candidate Skill: ' . $title;
    $lines[] = '';
    $lines[] = 'Status: candidate';
    $lines[] = 'Created At: ' . date('c');
    $lines[] = '';
    $lines[] = 'Gunakan skill ini ketika pola masalah berikut muncul lagi:';
    if ($message !== '') {
        $lines[] = '- Pertanyaan mirip: ' . $message;
    }
    foreach ($notes as $note) {
        $note = trim((string) $note);
        if ($note !== '') {
            $lines[] = '- Sinyal pembelajaran: ' . $note;
        }
    }
    if ($replyExcerpt !== '') {
        $lines[] = '- Cuplikan jawaban yang sebelumnya dipakai: ' . $replyExcerpt;
    }
    $lines[] = '';
    $lines[] = 'Aturan kerja yang diusulkan:';
    $lines[] = '- Cek konteks halaman, role, dan data live sebelum menjawab.';
    $lines[] = '- Prioritaskan istilah menu, alur bisnis, dan bukti dari codebase/project index.';
    $lines[] = '- Jika konteks kurang, jawab jujur dan sebutkan data yang masih dibutuhkan.';

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function aiAgentStoreCandidateSkill(array $selfImproveConfig, array $payload, array $notes = []): string
{
    if (empty($selfImproveConfig['enabled']) || empty($notes)) {
        return '';
    }

    aiAgentEnsureSelfImproveDirectories($selfImproveConfig);

    $seed = trim((string) ($payload['message'] ?? '')) . ' ' . implode(' ', array_map('strval', $notes));
    $slug = aiAgentBuildSelfImproveSlug($seed);
    $fingerprint = substr(sha1($seed), 0, 12);
    $path = rtrim((string) ($selfImproveConfig['candidates_dir'] ?? ''), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . $slug . '-' . $fingerprint . '.md';

    if (!is_file($path)) {
        @file_put_contents($path, aiAgentBuildCandidateSkillMarkdown($payload, $notes));
    }

    return $path;
}

function aiAgentActivateReviewedSkills(array $selfImproveConfig, array $skillsConfig = []): array
{
    if (empty($selfImproveConfig['enabled'])) {
        return [];
    }

    aiAgentEnsureSelfImproveDirectories($selfImproveConfig);

    $reviewedDir = (string) ($selfImproveConfig['reviewed_dir'] ?? '');
    if ($reviewedDir === '' || !is_dir($reviewedDir)) {
        return [];
    }

    $skillsRoot = rtrim((string) ($skillsConfig['storage_dir'] ?? ''), DIRECTORY_SEPARATOR);
    if ($skillsRoot === '') {
        return [];
    }

    $targetDir = $skillsRoot . DIRECTORY_SEPARATOR . 'auto-reviewed';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }

    $activated = [];
    $reviewedFiles = glob($reviewedDir . DIRECTORY_SEPARATOR . '*.md');
    if (!is_array($reviewedFiles)) {
        return [];
    }

    foreach ($reviewedFiles as $reviewedPath) {
        if (!is_file($reviewedPath)) {
            continue;
        }

        $content = (string) @file_get_contents($reviewedPath);
        if (trim($content) === '') {
            continue;
        }

        $baseName = basename($reviewedPath);
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $baseName;
        if (is_file($targetPath)) {
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . pathinfo($baseName, PATHINFO_FILENAME) . '-' . substr(sha1($baseName . microtime(true)), 0, 6) . '.md';
        }

        @file_put_contents($targetPath, trim($content) . PHP_EOL);

        $archivePath = rtrim((string) ($selfImproveConfig['activated_dir'] ?? ''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($targetPath);
        @rename($reviewedPath, $archivePath);
        @file_put_contents(
            (string) ($selfImproveConfig['activation_log_file'] ?? ''),
            json_encode([
                'reviewed_path' => $reviewedPath,
                'activated_path' => $targetPath,
                'archived_path' => $archivePath,
                'activated_at' => time(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );

        $activated[] = $targetPath;
    }

    return $activated;
}

function aiAgentProcessSelfImprovementPipeline(array $selfImproveConfig, array $skillsConfig, array $payload, array $notes = []): array
{
    if (empty($selfImproveConfig['enabled'])) {
        return [
            'candidate_skill_path' => '',
            'activated_skills' => [],
        ];
    }

    $candidateSkillPath = aiAgentStoreCandidateSkill($selfImproveConfig, $payload, $notes);
    $activatedSkills = aiAgentActivateReviewedSkills($selfImproveConfig, $skillsConfig);

    return [
        'candidate_skill_path' => $candidateSkillPath,
        'activated_skills' => $activatedSkills,
    ];
}
