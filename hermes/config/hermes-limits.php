<?php

/**
 * HERMES LIMITS CONFIGURATION
 * 
 * Centralized magic numbers, timeouts, dan limits.
 */

function aiAgentGetLimitsConfig(): array
{
    return [
        // Query & Display Limits
        'max_tables_in_context' => 6,
        'max_linked_files' => 12,
        'max_status_values' => 6,
        'max_borrowed_items_display' => 5,
        'max_borrowing_detail_rows' => 6,
        'max_status_counts' => 6,
        'max_recent_transaction_rows' => 3,
        'max_sql_result_rows' => 40,

        // Project Index Limits
        'max_project_index_relevant_entries' => 6,
        'max_project_index_function_names' => 8,
        'max_project_index_text_matches' => 8,
        'max_project_index_file_size_bytes' => 200000,  // 200KB

        // Timeouts & Cache
        'project_index_lock_timeout_seconds' => 15,
        'project_index_max_age_seconds' => 300,  // 5 minutes

        // Pagination & Chunking
        'default_items_per_page' => 10,
        'max_batch_size' => 100,

        // Search & Analysis
        'max_similarity_results' => 5,
        'max_memory_search_results' => 10,

        // Context Building
        'max_context_tokens' => 8000,
        'max_grounding_tokens' => 4000,
    ];
}

function aiAgentGetLimit(string $key, $default = null)
{
    $config = aiAgentGetLimitsConfig();
    return $config[$key] ?? $default;
}

function aiAgentGetMaxTablesInContext(): int
{
    return aiAgentGetLimit('max_tables_in_context', 6);
}

function aiAgentGetMaxLinkedFiles(): int
{
    return aiAgentGetLimit('max_linked_files', 12);
}

function aiAgentGetMaxStatusValues(): int
{
    return aiAgentGetLimit('max_status_values', 6);
}

function aiAgentGetMaxBorrowedItemsDisplay(): int
{
    return aiAgentGetLimit('max_borrowed_items_display', 5);
}

function aiAgentGetMaxBorrowingDetailRows(): int
{
    return aiAgentGetLimit('max_borrowing_detail_rows', 6);
}

function aiAgentGetMaxRecentTransactionRows(): int
{
    return aiAgentGetLimit('max_recent_transaction_rows', 3);
}

function aiAgentGetMaxSqlResultRows(): int
{
    return aiAgentGetLimit('max_sql_result_rows', 40);
}

function aiAgentGetIndexLockTimeoutSeconds(): int
{
    return aiAgentGetLimit('project_index_lock_timeout_seconds', 15);
}

function aiAgentGetIndexMaxAgeSeconds(): int
{
    return aiAgentGetLimit('project_index_max_age_seconds', 300);
}
