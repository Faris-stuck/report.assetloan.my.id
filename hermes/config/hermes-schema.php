<?php

/**
 * HERMES SCHEMA CONFIGURATION
 * 
 * Centralized database schema definitions.
 * Replaces hardcoded table & column names di seluruh codebase.
 */

function aiAgentGetSchemaDefinitions(): array
{
    return [
        'barang' => [
            'label' => 'Item/Inventory',
            'primary_key' => 'id',
            'columns' => [
                'id' => 'int',
                'kode_barang' => 'varchar',
                'nama_barang' => 'varchar',
                'kategori' => 'varchar',
                'lokasi' => 'varchar',
                'stok_tersedia' => 'int',
                'stok_rusak' => 'int',
                'safety_stock' => 'int',
                'kondisi' => 'varchar',
                'created_at' => 'datetime',
                'updated_at' => 'datetime',
            ],
            'searchable_columns' => ['nama_barang', 'kode_barang', 'kategori'],
            'status_column' => 'kondisi',
            'date_columns' => ['created_at', 'updated_at'],
            'scope' => 'inventory',
        ],
        'peminjaman' => [
            'label' => 'Borrowing',
            'primary_key' => 'id',
            'columns' => [
                'id',
                'user_id',
                'tanggal_pinjam',
                'tanggal_kembali_rencana',
                'tanggal_kembali_aktual',
                'status',
                'alasan',
                'approval_status',
                'approval_by',
                'approval_date',
                'pic_barang_id',
                'created_at',
                'updated_at',
            ],
            'searchable_columns' => ['id', 'status', 'alasan'],
            'status_column' => 'status',
            'user_column' => 'user_id',
            'date_columns' => ['tanggal_pinjam', 'tanggal_kembali_rencana', 'tanggal_kembali_aktual', 'approval_date'],
            'scope' => 'peminjaman',
        ],
        'detail_peminjaman' => [
            'label' => 'Borrowing Details',
            'primary_key' => 'id',
            'columns' => ['id', 'peminjaman_id', 'barang_id', 'jumlah', 'kondisi_sebelum', 'kondisi_sesudah', 'catatan'],
            'scope' => 'peminjaman',
        ],
        'pengembalian' => [
            'label' => 'Return',
            'primary_key' => 'id',
            'columns' => ['id', 'peminjaman_id', 'tanggal_kembali', 'status', 'kondisi_barang', 'catatan', 'pic_barang_id', 'created_at', 'updated_at'],
            'searchable_columns' => ['status', 'kondisi_barang'],
            'status_column' => 'status',
            'date_columns' => ['tanggal_kembali', 'created_at', 'updated_at'],
            'scope' => 'pengembalian',
        ],
        'extend_peminjaman' => [
            'label' => 'Borrowing Extension',
            'primary_key' => 'id',
            'columns' => ['id', 'peminjaman_id', 'tanggal_perpanjangan', 'tanggal_kembali_baru', 'alasan', 'status', 'approval_by', 'approval_date', 'created_at', 'updated_at'],
            'status_column' => 'status',
            'date_columns' => ['tanggal_perpanjangan', 'tanggal_kembali_baru', 'approval_date'],
            'scope' => 'extend',
        ],
        'user' => [
            'label' => 'User',
            'primary_key' => 'id',
            'columns' => ['id', 'username', 'password', 'email', 'nama_lengkap', 'role', 'departemen', 'status', 'created_at', 'updated_at'],
            'searchable_columns' => ['nama_lengkap', 'username', 'email'],
            'status_column' => 'status',
            'role_column' => 'role',
            'date_columns' => ['created_at', 'updated_at'],
            'scope' => 'user',
        ],
    ];
}

function aiAgentGetTableSchema(string $tableName): ?array
{
    return aiAgentGetSchemaDefinitions()[$tableName] ?? null;
}

function aiAgentGetTableColumns(string $tableName): array
{
    $schema = aiAgentGetTableSchema($tableName);
    return $schema['columns'] ?? [];
}

function aiAgentGetStatusColumn(string $tableName): ?string
{
    $schema = aiAgentGetTableSchema($tableName);
    return $schema['status_column'] ?? null;
}

function aiAgentGetUserColumn(string $tableName): ?string
{
    $schema = aiAgentGetTableSchema($tableName);
    return $schema['user_column'] ?? null;
}

function aiAgentGetTablesForScope(string $scope): array
{
    $tables = [];
    foreach (aiAgentGetSchemaDefinitions() as $name => $def) {
        if (($def['scope'] ?? '') === $scope) {
            $tables[] = $name;
        }
    }
    return $tables;
}

function aiAgentGetAllTableNames(): array
{
    return array_keys(aiAgentGetSchemaDefinitions());
}
