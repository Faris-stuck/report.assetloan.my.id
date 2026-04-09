<?php

function aiAgentGetSkillsConfig(array $config = []): array
{
    $skillsDir = trim((string) ($config['skills_storage_dir'] ?? 'hermes/skills'));
    if ($skillsDir === '') {
        $skillsDir = 'hermes/skills';
    }

    $skillsDir = aiAgentResolveHermesStoragePath($skillsDir);

    return [
        'enabled' => !isset($config['skills_enabled']) ? true : (bool) $config['skills_enabled'],
        'storage_dir' => $skillsDir,
        'max_matches' => max(1, (int) ($config['skills_max_matches'] ?? 3)),
        'max_chars_per_skill' => max(300, (int) ($config['skills_max_chars_per_skill'] ?? 1400)),
    ];
}

function aiAgentEnsureSkillsDirectory(array $skillsConfig = []): void
{
    $dir = (string) ($skillsConfig['storage_dir'] ?? '');
    if ($dir !== '' && !is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

function aiAgentCollectSkillFilesRecursive(string $directory): array
{
    if ($directory === '' || !is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo || !$item->isFile()) {
            continue;
        }

        if (preg_match('/\.md$/i', (string) $item->getFilename()) !== 1) {
            continue;
        }

        $files[] = $item->getPathname();
    }

    sort($files);
    return $files;
}

function aiAgentListSkillFiles(array $skillsConfig = []): array
{
    if (empty($skillsConfig['enabled'])) {
        return [];
    }

    aiAgentEnsureSkillsDirectory($skillsConfig);
    $dir = (string) ($skillsConfig['storage_dir'] ?? '');
    if ($dir === '' || !is_dir($dir)) {
        return [];
    }

    return aiAgentCollectSkillFilesRecursive($dir);
}

function aiAgentScoreSkillFile(string $path, string $content, string $message, array $pageContext = []): int
{
    $score = 0;
    $name = strtolower(basename($path));
    $haystack = strtolower($content . ' ' . $name . ' ' . (string) ($pageContext['title'] ?? '') . ' ' . (string) ($pageContext['path'] ?? ''));
    $messageWords = preg_split('/[^a-z0-9_]+/i', strtolower($message));
    $messageWords = array_values(array_filter(array_unique($messageWords), static function ($word) {
        return strlen((string) $word) >= 3;
    }));

    foreach ($messageWords as $word) {
        $count = substr_count($haystack, $word);
        if ($count > 0) {
            $score += min(20, $count * 4);
        }
    }

    if (strpos($name, 'default') !== false || strpos($name, 'base') !== false) {
        $score += 2;
    }

    if (strpos(str_replace('\\', '/', $path), '/auto-reviewed/') !== false) {
        $score += 6;
    }

    return $score;
}

function aiAgentSelectRelevantSkills(string $message, array $pageContext = [], array $skillsConfig = []): array
{
    $selected = [];
    foreach (aiAgentListSkillFiles($skillsConfig) as $path) {
        $content = (string) @file_get_contents($path);
        if (trim($content) === '') {
            continue;
        }

        $score = aiAgentScoreSkillFile($path, $content, $message, $pageContext);
        if ($score <= 0) {
            continue;
        }

        $selected[] = [
            'path' => $path,
            'score' => $score,
            'content' => $content,
        ];
    }

    usort($selected, static function (array $a, array $b): int {
        return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });

    return array_slice($selected, 0, max(1, (int) ($skillsConfig['max_matches'] ?? 3)));
}

function aiAgentBuildSkillsContext(string $message, array $pageContext = [], array $skillsConfig = []): string
{
    if (empty($skillsConfig['enabled'])) {
        return '';
    }

    $skills = aiAgentSelectRelevantSkills($message, $pageContext, $skillsConfig);
    if (empty($skills)) {
        return '';
    }

    $lines = ['[SKILLS_CONTEXT]'];
    foreach ($skills as $skill) {
        $path = str_replace('\\', '/', (string) ($skill['path'] ?? ''));
        $label = basename($path);
        $content = trim((string) ($skill['content'] ?? ''));
        $maxChars = max(300, (int) ($skillsConfig['max_chars_per_skill'] ?? 1400));
        if (function_exists('aiAgentStringLength') && aiAgentStringLength($content) > $maxChars) {
            $content = aiAgentStringSubstring($content, 0, $maxChars);
        } else {
            $content = substr($content, 0, $maxChars);
        }

        $lines[] = '[SKILL ' . $label . ']';
        $lines[] = trim($content);
        $lines[] = '[/SKILL ' . $label . ']';
    }
    $lines[] = '[/SKILLS_CONTEXT]';

    return implode("\n", $lines);
}
