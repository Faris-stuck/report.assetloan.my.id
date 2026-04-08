<?php

/**
 * HERMES MIGRATION HELPER
 * 
 * Contoh & template untuk update existing files agar gunakan config dinamis.
 * Ini bukan file yang dijalankan, tapi guide untuk refactoring.
 */

// ============================================================================
// EXAMPLE 1: Update tool-helper.php untuk gunakan schema config
// ============================================================================

/*
BEFORE (Hardcoded):
────────────────────────────────────────────────────────────────────────────

function aiAgentQueryMostBorrowedItems(mysqli $conn, array $options = []): array
{
    $limit = max(1, (int) ($options['limit'] ?? 10));
    ...
    $rows = aiAgentFetchRows(
        $conn,
        '
            SELECT
                b.nama_barang,
                b.kode_barang,                           // HARDCODED column names
                COUNT(p.id) AS total_peminjaman          // HARDCODED logic
            FROM peminjaman p                             // HARDCODED table name
            LEFT JOIN detail_peminjaman dp ...            // HARDCODED table name
            LEFT JOIN barang b ...                        // HARDCODED table name
        '
    );
}

AFTER (Dynamic Config):
────────────────────────────────────────────────────────────────────────────

function aiAgentQueryMostBorrowedItems(mysqli $conn, array $options = []): array
{
    $limit = max(1, (int) ($options['limit'] ?? aiAgentGetMaxBorrowedItemsDisplay()));  // Use config
    ...
    
    // Get table names from schema config
    $tableBarang = 'barang';           // Could be from config if multi-schema
    $tablePeminjaman = 'peminjaman';
    $tableDetail = 'detail_peminjaman';
    
    // Get column names from schema config
    $schemaBarang = aiAgentGetTableSchema('barang');
    $namaBarangCol = $schemaBarang['columns']['nama_barang'];  // Dynamic
    $kodeBarangCol = $schemaBarang['columns']['kode_barang'];  // Dynamic
    
    $rows = aiAgentFetchRows(
        $conn,
        '
            SELECT
                b.' . $namaBarangCol . ',
                b.' . $kodeBarangCol . ',
                COUNT(p.id) AS total_peminjaman
            FROM ' . $tablePeminjaman . ' p
            LEFT JOIN ' . $tableDetail . ' dp ON p.id = dp.peminjaman_id
            LEFT JOIN ' . $tableBarang . ' b ON dp.barang_id = b.id
        '
    );
}
*/

// ============================================================================
// EXAMPLE 2: Update context-helper.php untuk gunakan role config
// ============================================================================

/*
BEFORE (Hardcoded):
────────────────────────────────────────────────────────────────────────────

function aiAgentGetRelevantFileLines($focusScopes, $role): array
{
    if ($role === 'admin') {                    // HARDCODED role check
        return [...];
    }
    
    if ($role === 'manager') {                  // HARDCODED role check
        return [...];
    }
    
    if ($role === 'pic_barang') {               // HARDCODED role check
        return [...];
    }
}

AFTER (Dynamic Config):
────────────────────────────────────────────────────────────────────────────

function aiAgentGetRelevantFileLines($focusScopes, $role): array
{
    $role = aiAgentNormalizeRoleName($role);    // Use config normalization
    
    if (!aiAgentGetRoleDef($role)) {
        return [];
    }
    
    // Use configuration instead of hardcoded checks
    $accessibleScopes = aiAgentGetAccessibleScopes($role);
    
    if (aiAgentRoleHasPermission($role, 'access_all_scopes')) {
        // Admin/manager level
        return [...];
    } else if (in_array('inventory', $accessibleScopes, true)) {
        // Inventory scope access
        return [...];
    } else {
        // Limited access
        return [...];
    }
}
*/

// ============================================================================
// EXAMPLE 3: Update tool-helper.php untuk gunakan keywords config
// ============================================================================

/*
BEFORE (Hardcoded):
────────────────────────────────────────────────────────────────────────────

function aiAgentBuildBorrowingLiveDetailLines(mysqli $conn, array $options = []): array
{
    ...
    foreach ([
        'paling banyak',                    // HARDCODED keywords
        'most borrow',
        'sering',
        'terbanyak',
        'top borrow',
        'popular',
        'frequently',
    ] as $keyword) {
        if (strpos($message, $keyword) !== false) {
            $wantsMostBorrowedAnalysis = true;
            break;
        }
    }
}

AFTER (Dynamic Config):
────────────────────────────────────────────────────────────────────────────

function aiAgentBuildBorrowingLiveDetailLines(mysqli $conn, array $options = []): array
{
    ...
    // Use keyword config
    $keywords = aiAgentGetKeywordsByContext('most_borrowed');
    
    foreach ($keywords as $keyword) {
        if (strpos($message, $keyword) !== false) {
            $wantsMostBorrowedAnalysis = true;
            break;
        }
    }
}
*/

// ============================================================================
// EXAMPLE 4: Update context-helper.php untuk gunakan strings config
// ============================================================================

/*
BEFORE (Hardcoded):
────────────────────────────────────────────────────────────────────────────

function aiAgentBuildGroundingContext(mysqli $conn, array $options = []): string
{
    $lines = [];
    $lines[] = '[HERMES_GROUNDING]';           // HARDCODED
    $lines[] = '[GROUNDING_RULES]';            // HARDCODED
    ...
    $lines[] = '- Nama project: Sistem Informasi Peminjaman Barang Berbasis Web.';
    ...
    $lines[] = '[/HERMES_GROUNDING]';          // HARDCODED
}

AFTER (Dynamic Config):
────────────────────────────────────────────────────────────────────────────

function aiAgentBuildGroundingContext(mysqli $conn, array $options = []): string
{
    $lang = $options['language'] ?? 'id';
    $lines = [];
    $lines[] = aiAgentGetString('grounding_header', $lang);
    $lines[] = aiAgentGetString('grounding_rules_header', $lang);
    ...
    $lines[] = '- Nama project: ' . aiAgentGetString('project_name', $lang) . '.';
    ...
    $lines[] = aiAgentGetString('grounding_footer', $lang);
}
*/

// ============================================================================
// EXAMPLE 5: Update tool-helper.php untuk gunakan limits config
// ============================================================================

/*
BEFORE (Hardcoded):
────────────────────────────────────────────────────────────────────────────

function aiAgentBuildLiveData(...): array
{
    ...
    $rendered TableCount = 0;
    foreach ($accessibleTables as $tableName) {
        ...
        if ($renderedTableCount >= 3) {        // HARDCODED limit
            break;
        }
        $renderedTableCount += 1;
    }
}

AFTER (Dynamic Config):
────────────────────────────────────────────────────────────────────────────

function aiAgentBuildLiveData(...): array
{
    ...
    $maxTables = aiAgentGetMaxTablesInContext();  // Use config
    $renderedTableCount = 0;
    
    foreach ($accessibleTables as $tableName) {
        ...
        if ($renderedTableCount >= $maxTables) {
            break;
        }
        $renderedTableCount += 1;
    }
}
*/

// ============================================================================
// CHECKLIST: Files yang perlu di-update (untuk future refactoring)
// ============================================================================

/*
Priority 1 (HIGH - Most Hardcoded):
☐ tool-helper.php
  - Replace table names dengan schema config
  - Replace keywords dengan hermes-keywords.php
  - Replace limits dengan hermes-limits.php
  - Replace display strings dengan hermes-strings.php

☐ context-helper.php
  - Replace role checks dengan hermes-roles.php
  - Replace keywords dengan hermes-keywords.php
  - Replace hardcoded strings dengan hermes-strings.php

Priority 2 (MEDIUM):
☐ index-helper.php
  - Replace magic numbers dengan hermes-limits.php

☐ memory-helper.php
  - Replace strings dengan hermes-strings.php

Priority 3 (LOW - Already mostly dynamic):
☐ runtime-helper.php
  - Minor cleanup

☐ config-helper.php
  - Already loads from config file
  - Might not need changes
*/

// ============================================================================
// TESTING CHECKLIST
// ============================================================================

/*
Setelah setiap file di-update, test:

1. Syntax Check:
   php -l file-yang-diupdate.php

2. Function Test:
   - Query masih return correct data?
   - Role checks masih work?
   - Keywords masih detect context correctly?
   - Display strings masih formatted benar?

3. Integration Test:
   - Chat.php masih bisa di-load?
   - aiAgentBuildToolRuntimeContext() masih bekerja?
   - User bisa bertanya normalnya?

4. Edge Cases:
   - Role yang tidak recognized?
   - Table yang tidak ada di schema?
   - Keyword dengan variasi?
   - Multilingual strings?
*/
