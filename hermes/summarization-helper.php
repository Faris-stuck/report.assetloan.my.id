<?php

/**
 * Context Summarization & Compression Helper
 * 
 * Implements context window management via conversation summarization.
 * When conversations grow too long, summarizes middle messages while preserving:
 * - Recent messages (for coherence)
 * - Tool call/result pairs (keep them together)
 * - Critical decision points
 */

function aiAgentGetSummarizationConfig(array $config = []): array
{
    return [
        'enabled' => !isset($config['summarization_enabled']) ? true : (bool) $config['summarization_enabled'],
        'threshold_messages' => max(10, (int) ($config['summarization_threshold_messages'] ?? 20)),
        'preserve_recent_messages' => max(3, (int) ($config['summarization_preserve_recent'] ?? 5)),
        'min_summary_lines' => max(1, (int) ($config['summarization_min_lines'] ?? 3)),
        'max_summary_lines' => max(5, (int) ($config['summarization_max_lines'] ?? 15)),
        'target_tokens_after_summary' => max(500, (int) ($config['summarization_target_tokens'] ?? 2000)),
    ];
}

/**
 * Estimate token count (rough approximation: ~4 chars per token on average)
 */
function aiAgentEstimateTokens(string $text): int
{
    return (int) ceil(strlen($text) / 4);
}

/**
 * Check if conversation exceeds summarization threshold
 */
function aiAgentShouldSummarizeConversation(array $messages, array $summaryConfig = []): bool
{
    if (!($summaryConfig['enabled'] ?? true)) {
        return false;
    }

    if (count($messages) < ($summaryConfig['threshold_messages'] ?? 20)) {
        return false;
    }

    return true;
}

/**
 * Group messages by tool-call/result pairs to preserve them intact
 */
function aiAgentGroupMessagesForSummarization(array $messages): array
{
    $groups = [];
    $currentGroup = [];

    foreach ($messages as $index => $msg) {
        $currentGroup[] = $msg;

        // If this is a tool_result or assistant completes a tool-call, close the group
        if (($msg['role'] ?? '') === 'user' && ($msg['type'] ?? '') === 'tool_result') {
            $groups[] = $currentGroup;
            $currentGroup = [];
        } elseif (($msg['role'] ?? '') === 'assistant' && !empty($msg['tool_calls'] ?? [])) {
            // Don't close yet; wait for tool_result
        } elseif (($msg['role'] ?? '') === 'user' && ($msg['type'] ?? '') !== 'tool_result') {
            // Regular user message; treat as complete group
            $groups[] = $currentGroup;
            $currentGroup = [];
        }
    }

    if (!empty($currentGroup)) {
        $groups[] = $currentGroup;
    }

    return $groups;
}

/**
 * Build a concise summary of a message group
 */
function aiAgentBuildGroupSummary(array $groupMessages, int $maxLines = 8): string
{
    $summaryParts = [];

    foreach ($groupMessages as $msg) {
        $role = $msg['role'] ?? 'unknown';
        $content = $msg['content'] ?? '';

        // For tool calls, extract tool name and brief summary
        if ($role === 'assistant' && !empty($msg['tool_calls'])) {
            foreach ($msg['tool_calls'] as $tool) {
                $toolName = $tool['function']['name'] ?? 'unknown';
                $summaryParts[] = "[Tool: $toolName]";
            }
        }

        // Truncate long content
        if (strlen($content) > 200) {
            $content = substr($content, 0, 200) . '...';
        }

        if ($content) {
            $summaryParts[] = ucfirst($role) . ": " . $content;
        }
    }

    return implode("\n", array_slice($summaryParts, 0, $maxLines));
}

/**
 * Summarize conversation: keep recent messages, compress older ones
 */
function aiAgentSummarizeConversation(
    array $messages,
    array $summaryConfig = []
): array {
    $preserved = max(1, (int) ($summaryConfig['preserve_recent_messages'] ?? 5));
    $minSummaryLines = max(1, (int) ($summaryConfig['min_summary_lines'] ?? 3));
    $maxSummaryLines = max($minSummaryLines, (int) ($summaryConfig['max_summary_lines'] ?? 10));

    $messageCount = count($messages);

    // Not enough messages to summarize
    if ($messageCount <= ($preserved + 5)) {
        return $messages;
    }

    // Split: [to_summarize] + [recent]
    $toSummarizeCount = $messageCount - $preserved;
    $toSummarize = array_slice($messages, 0, $toSummarizeCount);
    $recent = array_slice($messages, $toSummarizeCount);

    // Group for preservation of tool-call/result pairs
    $groups = aiAgentGroupMessagesForSummarization($toSummarize);

    // Collapse middle groups more aggressively, keep first & last groups
    $collapsedGroups = [];
    if (count($groups) > 2) {
        // Keep first group verbatim
        $collapsedGroups[] = $groups[0];

        // Collapse all middle groups into one summary
        $middleGroups = array_slice($groups, 1, count($groups) - 2);
        $middleSummary = [];
        foreach ($middleGroups as $group) {
            $summary = aiAgentBuildGroupSummary($group, 3);
            if ($summary) {
                $middleSummary[] = $summary;
            }
        }

        if (!empty($middleSummary)) {
            $collapsedGroups[] = [
                'role' => 'assistant',
                'type' => 'summary',
                'content' => "[CONVERSATION SUMMARY]\n" . implode("\n---\n", $middleSummary),
            ];
        }

        // Keep last group verbatim
        $collapsedGroups[] = end($groups);
    } else {
        $collapsedGroups = $groups;
    }

    // Flatten groups back into messages
    $summarized = [];
    foreach ($collapsedGroups as $group) {
        foreach ($group as $msg) {
            $summarized[] = $msg;
        }
    }

    // Append recent messages (unchanged)
    return array_merge($summarized, $recent);
}

/**
 * Build a single MEMORY.md string from conversation history
 * This is what gets flushed to disk after each turn
 */
function aiAgentBuildMemoryMarkdown(
    string $userKey,
    array $conversationMessages,
    array $previousMemory = []
): string {
    $timestamp = date('Y-m-d H:i:s');
    $lines = [];

    // Header
    $lines[] = "# Memory for $userKey";
    $lines[] = "";
    $lines[] = "**Last updated:** $timestamp";
    $lines[] = "";

    // Preserve existing memory if present
    if (!empty($previousMemory['profile'])) {
        $lines[] = "## Profile";
        $lines[] = $previousMemory['profile'];
        $lines[] = "";
    } else {
        $lines[] = "## Profile";
        $lines[] = "- Start building user profile here";
        $lines[] = "";
    }

    if (!empty($previousMemory['preferences'])) {
        $lines[] = "## Preferences";
        $lines[] = $previousMemory['preferences'];
        $lines[] = "";
    } else {
        $lines[] = "## Preferences";
        $lines[] = "- No preferences yet";
        $lines[] = "";
    }

    if (!empty($previousMemory['goals'])) {
        $lines[] = "## Goals & Objectives";
        $lines[] = $previousMemory['goals'];
        $lines[] = "";
    } else {
        $lines[] = "## Goals & Objectives";
        $lines[] = "- No goals documented yet";
        $lines[] = "";
    }

    // Recent conversation insights
    $lines[] = "## Recent Conversation Insights";
    $lines[] = "";

    $userMessages = array_filter($conversationMessages, fn($m) => ($m['role'] ?? '') === 'user');
    $recentUserMsgs = array_slice($userMessages, -3);

    foreach ($recentUserMsgs as $msg) {
        $content = $msg['content'] ?? '';
        if (strlen($content) > 100) {
            $content = substr($content, 0, 100) . '...';
        }
        $lines[] = "- User asked: " . preg_replace('/\s+/', ' ', $content);
    }
    $lines[] = "";

    // Lessons learned (if any)
    if (!empty($previousMemory['lessons'])) {
        $lines[] = "## Lessons Learned";
        $lines[] = $previousMemory['lessons'];
        $lines[] = "";
    }

    // Behavioral profile (auto-inferred)
    $lines[] = "## Behavioral Profile";
    if (!empty($previousMemory['goals'])) {
        $lines[] = "**Inferred Goals:** " . preg_replace('/\n- /', ', ', trim($previousMemory['goals']));
    } else {
        $lines[] = "**Inferred Goals:** Building...";
    }
    if (!empty($previousMemory['preferences'])) {
        $lines[] = "**Communication:** " . preg_replace('/\n/', ' | ', trim($previousMemory['preferences']));
    } else {
        $lines[] = "**Communication:** Analyzing style...";
    }
    $lines[] = "";

    // Next steps (placeholder)
    $lines[] = "## Next Steps";
    $lines[] = "- Continue monitoring user patterns";
    $lines[] = "";

    return implode("\n", $lines);
}

/**
 * Parse MEMORY.md back into structured array
 */
function aiAgentParseMemoryMarkdown(string $markdown): array
{
    $sections = [
        'profile' => '',
        'preferences' => '',
        'goals' => '',
        'lessons' => '',
        'recent_insights' => '',
    ];

    $lines = explode("\n", $markdown);
    $currentSection = null;
    $sectionContent = [];

    foreach ($lines as $line) {
        if (preg_match('/^## /', $line)) {
            if ($currentSection && !empty($sectionContent)) {
                $sections[$currentSection] = trim(implode("\n", $sectionContent));
            }

            if (strpos($line, 'Profile') !== false) {
                $currentSection = 'profile';
            } elseif (strpos($line, 'Preferences') !== false) {
                $currentSection = 'preferences';
            } elseif (strpos($line, 'Goals') !== false) {
                $currentSection = 'goals';
            } elseif (strpos($line, 'Lessons') !== false) {
                $currentSection = 'lessons';
            } elseif (strpos($line, 'Recent') !== false) {
                $currentSection = 'recent_insights';
            }

            $sectionContent = [];
        } elseif ($currentSection) {
            $sectionContent[] = $line;
        }
    }

    if ($currentSection && !empty($sectionContent)) {
        $sections[$currentSection] = trim(implode("\n", $sectionContent));
    }

    return $sections;
}

/**
 * Flush MEMORY.md to disk for a user (with automatic behavioral profiling)
 */
function aiAgentFlushMemoryMarkdown(
    array $memoryConfig,
    string $role,
    int $userId,
    array $conversationMessages,
    array $previousMemory = []
): bool {
    $profDir = $memoryConfig['profiles_dir'] ?? '';
    if (!$profDir || !is_dir($profDir)) {
        return false;
    }

    // Auto-enhance memory with behavioral profile before writing
    if (function_exists('aiAgentEnhanceMemoryWithBehavioralProfile')) {
        $previousMemory = aiAgentEnhanceMemoryWithBehavioralProfile($previousMemory, $conversationMessages);
    }

    $userKey = aiAgentBuildMemoryUserKey($role, $userId);
    $memoryFile = $profDir . DIRECTORY_SEPARATOR . $userKey . '-MEMORY.md';

    $markdown = aiAgentBuildMemoryMarkdown($userKey, $conversationMessages, $previousMemory);

    return file_put_contents($memoryFile, $markdown, LOCK_EX) !== false;
}

/**
 * Load MEMORY.md from disk
 */
function aiAgentLoadMemoryMarkdown(
    array $memoryConfig,
    string $role,
    int $userId
): array {
    $profDir = $memoryConfig['profiles_dir'] ?? '';
    if (!$profDir) {
        return [];
    }

    $userKey = aiAgentBuildMemoryUserKey($role, $userId);
    $memoryFile = $profDir . DIRECTORY_SEPARATOR . $userKey . '-MEMORY.md';

    if (!file_exists($memoryFile)) {
        return [];
    }

    $markdown = file_get_contents($memoryFile);
    return $markdown ? aiAgentParseMemoryMarkdown($markdown) : [];
}

/**
 * Build memory context string for inclusion in system prompt
 * Prioritize MEMORY.md if available, otherwise use conversation search
 */
function aiAgentBuildMemoryContextForPrompt(
    array $memoryConfig,
    string $role,
    int $userId,
    array $conversationMessages = []
): string {
    $lines = ["## Memory Context"];
    $lines[] = "";

    // Try loading MEMORY.md first
    $parsedMemory = aiAgentLoadMemoryMarkdown($memoryConfig, $role, $userId);

    if (!empty($parsedMemory)) {
        if (!empty($parsedMemory['profile'])) {
            $lines[] = "**User Profile:**";
            $lines[] = $parsedMemory['profile'];
            $lines[] = "";
        }

        if (!empty($parsedMemory['preferences'])) {
            $lines[] = "**Preferences:**";
            $lines[] = $parsedMemory['preferences'];
            $lines[] = "";
        }

        if (!empty($parsedMemory['goals'])) {
            $lines[] = "**Goals:**";
            $lines[] = $parsedMemory['goals'];
            $lines[] = "";
        }
    } else {
        $lines[] = "No persistent memory yet. Build profile as conversation progresses.";
        $lines[] = "";
    }

    return implode("\n", $lines);
}
