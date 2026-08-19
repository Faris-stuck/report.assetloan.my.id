<?php

/**
 * HERMES STRINGS CONFIGURATION
 * 
 * Centralized UI strings & messages (i18n-ready).
 */

function aiAgentGetStringsConfig(string $language = 'id'): array
{
    $strings = [
        'id' => [
            // Grounding & Context
            'grounding_header' => '[HERMES_GROUNDING]',
            'grounding_rules_header' => '[GROUNDING_RULES]',
            'grounding_rules_footer' => '[/GROUNDING_RULES]',
            'session_context_header' => '[SESSION_CONTEXT]',
            'session_context_footer' => '[/SESSION_CONTEXT]',
            'project_context_header' => '[PROJECT_CONTEXT]',
            'project_context_footer' => '[/PROJECT_CONTEXT]',
            'workflow_context_header' => '[WORKFLOW_CONTEXT]',
            'workflow_context_footer' => '[/WORKFLOW_CONTEXT]',
            'database_context_header' => '[DATABASE_CONTEXT]',
            'database_context_footer' => '[/DATABASE_CONTEXT]',
            'live_context_header' => '[LIVE_CONTEXT]',
            'live_context_footer' => '[/LIVE_CONTEXT]',
            'answer_rules_header' => '[ANSWER_RULES]',
            'answer_rules_footer' => '[/ANSWER_RULES]',
            'grounding_footer' => '[/HERMES_GROUNDING]',

            // Project Info
            'project_name' => 'Sistem Informasi Peminjaman Barang Berbasis Web',
            'project_description' => 'Database ini berpusat pada inventaris barang, transaksi peminjaman, approval, pengembalian, perpanjangan, dan master user.',
            'database_active' => 'Database aktif: %s.',
            'role_active' => 'Role session aktif: %s.',
            'agent_role' => 'Role yang dipakai agent: %s.',
            'account_validated' => 'Akun login aktif sudah tervalidasi untuk sesi ini.',
            'closest_module' => 'Modul yang paling dekat dengan halaman aktif: %s.',
            'focused_context' => 'Fokus konteks terpilih: %s.',
            'active_page' => 'Halaman aktif: %s.',
            'current_question' => 'Pertanyaan user saat ini: %s.',

            // Data Status
            'data_live_reading' => 'Data live untuk konteks ini dibaca ulang dari database saat request chat berjalan.',
            'password_override_active' => 'Password override aktif, sehingga data bisnis lintas scope role boleh dipakai untuk jawaban ini.',
            'needs_scope_override' => 'Permintaan menyentuh data di luar scope role, jadi snapshot live yang dipakai tetap dibatasi sesuai role aktif.',
            'scope_limited' => 'Snapshot live ini hanya memakai data yang berada dalam scope role aktif, tanpa perlu password.',
            'snapshot_not_available' => 'Snapshot live tidak tersedia; bila user meminta angka spesifik, jelaskan bahwa data live belum berhasil dibaca.',
            'no_live_snapshot' => 'Belum ada snapshot live yang cocok dengan scope role dan konteks pertanyaan saat ini.',

            // Most Borrowed Display
            'most_borrowed_header' => '📊 Barang paling banyak dipinjam %s:',
            'most_borrowed_alltime' => '(sepanjang masa)',
            'most_borrowed_month' => 'bulan %02d/%04d',
            'most_borrowed_rank' => '- Peringkat %d: %s (%s)',
            'most_borrowed_count' => '  Dipinjam %d kali (%d%% dari total)',
            'most_borrowed_total' => '📋 Total peminjaman: %d transaksi',
            'most_borrowed_items' => '📋 Jumlah barang berbeda: %d item',
            'no_borrowed_data' => 'Tidak ada data peminjaman barang untuk periode yang diminta.',

            // Inventory Data
            'inventory_live' => 'Inventory live: %d master item, %s, stok rusak total %d, item low stock %d.',
            'top_stock_item' => 'Top stock %s (%d unit)',
            'low_stock_items' => 'Contoh item low stock: %s.',
            'loan_counts' => 'Peminjaman live per status: %s.',
            'return_counts' => 'Pengembalian live per status: %s.',
            'extend_counts' => 'Perpanjangan live per status: %s.',

            // Error Messages
            'grid_render_error' => 'Error rendering grid: %s',
            'query_error' => 'Query Error: %s',
            'connection_error' => 'Connection Error: %s',

            // Grounding Rules
            'rule_use_context' => 'Gunakan hanya fakta yang ada pada konteks PROJECT, konteks database live, riwayat chat, dan pertanyaan user.',
            'rule_no_fabrication' => 'Jangan mengarang nama file, folder, tabel, kolom, status, endpoint, role, atau angka yang tidak muncul di konteks.',
            'rule_insufficient_context' => 'Jika konteks belum cukup untuk jawaban yang sangat spesifik, katakan dengan eksplisit bahwa konteks belum cukup lalu sebutkan data, file, atau tabel yang dibutuhkan.',
            'rule_use_workflow' => 'Jika menjawab tentang proses bisnis, utamakan workflow yang benar-benar dipakai project ini, bukan praktik umum aplikasi lain.',
            'rule_distinguish_data' => 'Jika menyebut data live, bedakan dari aturan kode atau inferensi. Jangan menyamakan snapshot data dengan aturan bisnis.',

            // Answer Rules
            'rule_implementation_location' => 'Jika user bertanya lokasi implementasi fitur, sebut file atau folder yang relevan dari konteks PROJECT.',
            'rule_business_data' => 'Jika user bertanya data bisnis, utamakan snapshot live dan tabel yang relevan.',
            'rule_status_workflow' => 'Jika user bertanya status atau alur, gunakan kamus status dan workflow yang tersedia di konteks ini.',
            'rule_uncertainty' => 'Jika ada ketidakpastian, jawab jujur dengan frasa seperti "berdasarkan konteks yang tersedia" atau "konteks saat ini belum cukup".',
        ],
        'en' => [
            // Grounding & Context
            'grounding_header' => '[HERMES_GROUNDING]',
            'grounding_rules_header' => '[GROUNDING_RULES]',
            'grounding_rules_footer' => '[/GROUNDING_RULES]',
            'session_context_header' => '[SESSION_CONTEXT]',
            'project_name' => 'Web-Based Inventory Borrowing Information System',
            'project_description' => 'Database focuses on inventory, borrowing transactions, approvals, returns, extensions, and user master.',
            'database_active' => 'Active database: %s.',
            'role_active' => 'Active session role: %s.',
            'agent_role' => 'Role used by agent: %s.',
            'most_borrowed_header' => '📊 Most borrowed items %s:',
            'most_borrowed_alltime' => '(all-time)',
            'most_borrowed_month' => 'month %02d/%04d',
            'most_borrowed_rank' => '- Rank %d: %s (%s)',
            'most_borrowed_count' => '  Borrowed %d times (%d%% of total)',
            'most_borrowed_total' => '📋 Total borrowing transactions: %d',
            'most_borrowed_items' => '📋 Number of different items: %d',
            'no_borrowed_data' => 'No borrowing data available for the requested period.',
        ],
    ];

    return $strings[$language] ?? $strings['id'];
}

function aiAgentGetString(string $key, string $language = 'id', ...$args): string
{
    $config = aiAgentGetStringsConfig($language);
    $string = $config[$key] ?? $key;

    if (!empty($args)) {
        return sprintf($string, ...$args);
    }

    return $string;
}

function aiAgentGetStringId(string $key, ...$args): string
{
    return aiAgentGetString($key, 'id', ...$args);
}

function aiAgentGetStringEn(string $key, ...$args): string
{
    return aiAgentGetString($key, 'en', ...$args);
}
