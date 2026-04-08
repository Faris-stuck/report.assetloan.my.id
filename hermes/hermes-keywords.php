<?php

/**
 * HERMES KEYWORDS CONFIGURATION
 * 
 * Centralized keyword patterns, detection rules, dan mappings.
 */

function aiAgentGetKeywordDefinitions(): array
{
    return [
        'most_borrowed' => [
            'keywords' => ['paling banyak', 'most borrow', 'sering', 'terbanyak', 'top borrow', 'popular', 'frequently'],
            'language' => ['id', 'en'],
            'context' => 'borrowing_analysis',
        ],
        'borrowing_detail' => [
            'keywords' => ['overdue', 'due today', 'due in', 'jatuh tempo', 'terlambat', 'nama peminjam', 'peminjam', 'siapa', 'borrower', 'transaksi', 'pinjaman', 'peminjaman'],
            'language' => ['id', 'en'],
            'context' => 'borrowing_query',
        ],
        'month_filter' => [
            'keywords' => ['bulan', 'month', 'januari', 'february', 'februari', 'maret', 'march', 'april', 'mei', 'may', 'juni', 'june', 'juli', 'july', 'agustus', 'august', 'september', 'oktober', 'october', 'november', 'desember', 'december', 'jan', 'feb', 'mar', 'apr', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'],
            'language' => ['id', 'en'],
            'context' => 'date_filtering',
        ],
        'scope_inventory' => [
            'keywords' => ['inventory', 'barang', 'stok', 'stock', 'item', 'restock', 'procurement'],
            'language' => ['id', 'en'],
            'scope' => 'inventory',
        ],
        'scope_borrowing' => [
            'keywords' => ['peminjaman', 'pinjam', 'borrow', 'borrowing', 'pengambilan', 'ambil'],
            'language' => ['id', 'en'],
            'scope' => 'peminjaman',
        ],
        'scope_return' => [
            'keywords' => ['pengembalian', 'kembali', 'return', 'kembalikan'],
            'language' => ['id', 'en'],
            'scope' => 'pengembalian',
        ],
        'scope_extension' => [
            'keywords' => ['perpanjangan', 'extend', 'extension', 'perpanjang', 'perpanjang lagi'],
            'language' => ['id', 'en'],
            'scope' => 'extend',
        ],
    ];
}

function aiAgentGetMonthMapping(): array
{
    return [
        'januari' => 1,
        'january' => 1,
        'jan' => 1,
        'februari' => 2,
        'february' => 2,
        'feb' => 2,
        'maret' => 3,
        'march' => 3,
        'mar' => 3,
        'april' => 4,
        'apr' => 4,
        'mei' => 5,
        'may' => 5,
        'juni' => 6,
        'june' => 6,
        'jun' => 6,
        'juli' => 7,
        'july' => 7,
        'jul' => 7,
        'agustus' => 8,
        'august' => 8,
        'aug' => 8,
        'september' => 9,
        'sept' => 9,
        'sep' => 9,
        'oktober' => 10,
        'october' => 10,
        'oct' => 10,
        'november' => 11,
        'nov' => 11,
        'desember' => 12,
        'december' => 12,
        'dec' => 12,
    ];
}

function aiAgentGetKeywordsByContext(string $context): array
{
    $defs = aiAgentGetKeywordDefinitions();
    $keywords = [];
    foreach ($defs as $def) {
        if (($def['context'] ?? '') === $context) {
            $keywords = array_merge($keywords, $def['keywords'] ?? []);
        }
    }
    return $keywords;
}

function aiAgentGetKeywordsByScope(string $scope): array
{
    $defs = aiAgentGetKeywordDefinitions();
    $keywords = [];
    foreach ($defs as $def) {
        if (($def['scope'] ?? '') === $scope) {
            $keywords = array_merge($keywords, $def['keywords'] ?? []);
        }
    }
    return $keywords;
}

function aiAgentDetectContext(string $message): array
{
    $message = strtolower($message);
    $detected = [];

    $keywords = aiAgentGetKeywordDefinitions();
    foreach ($keywords as $contextName => $def) {
        foreach ($def['keywords'] as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $detected[$contextName] = true;
                break;
            }
        }
    }

    return array_keys(array_filter($detected));
}
