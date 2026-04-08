<?php

/**
 * HERMES ROLES CONFIGURATION
 * 
 * Centralized role definitions, permissions, dan access control.
 */

function aiAgentGetRoleDefinitions(): array
{
    return [
        'admin' => [
            'label' => 'Administrator',
            'display_name' => 'Admin',
            'permissions' => [
                'view_all_items' => true,
                'manage_inventory' => true,
                'approve_borrowing' => true,
                'access_all_scopes' => true,
            ],
            'accessible_tables' => ['barang', 'peminjaman', 'pengembalian', 'extend_peminjaman', 'detail_peminjaman', 'user'],
            'accessible_scopes' => ['inventory', 'peminjaman', 'pengembalian', 'extend', 'approval', 'user', 'reports'],
            'can_see_sensitive' => true,
        ],
        'manager' => [
            'label' => 'Manager',
            'display_name' => 'Manager',
            'permissions' => [
                'view_all_items' => true,
                'manage_inventory' => true,
                'approve_borrowing' => true,
                'access_all_scopes' => false,
            ],
            'accessible_tables' => ['barang', 'peminjaman', 'pengembalian', 'extend_peminjaman', 'detail_peminjaman'],
            'accessible_scopes' => ['inventory', 'peminjaman', 'pengembalian', 'extend', 'approval', 'dashboard'],
            'can_see_sensitive' => true,
        ],
        'pic_barang' => [
            'label' => 'Inventory PIC',
            'display_name' => 'PIC',
            'display_as' => 'pic',
            'permissions' => [
                'view_all_items' => true,
                'manage_inventory' => false,
                'approve_borrowing' => false,
                'access_all_scopes' => false,
            ],
            'accessible_tables' => ['barang', 'pengembalian', 'detail_peminjaman'],
            'accessible_scopes' => ['inventory', 'pengembalian'],
            'can_see_sensitive' => false,
        ],
        'user' => [
            'label' => 'User',
            'display_name' => 'User',
            'permissions' => [
                'view_all_items' => false,
                'manage_inventory' => false,
                'approve_borrowing' => false,
                'access_all_scopes' => false,
            ],
            'accessible_tables' => ['barang', 'peminjaman', 'detail_peminjaman'],
            'accessible_scopes' => ['peminjaman'],
            'user_scoped' => true,
            'can_see_sensitive' => false,
        ],
    ];
}

function aiAgentGetRoleDef(string $role): ?array
{
    return aiAgentGetRoleDefinitions()[$role] ?? null;
}

function aiAgentGetAllRoles(): array
{
    return array_keys(aiAgentGetRoleDefinitions());
}

function aiAgentNormalizeRoleName(string $role): ?string
{
    $role = strtolower(trim($role));
    if (aiAgentGetRoleDef($role)) {
        return $role;
    }

    $variants = [
        'pic barang' => 'pic_barang',
        'pic' => 'pic_barang',
        'user biasa' => 'user',
        'requester' => 'user',
    ];

    return $variants[$role] ?? null;
}

function aiAgentGetRoleDisplayName(string $role, bool $short = false): string
{
    $def = aiAgentGetRoleDef($role);
    if (!$def) {
        return ucfirst($role);
    }
    return $short && isset($def['display_name']) ? $def['display_name'] : ($def['label'] ?? $role);
}

function aiAgentGetRoleForAgent(string $role): string
{
    $def = aiAgentGetRoleDef($role);
    return ($def['display_as'] ?? $role);
}

function aiAgentRoleHasPermission(string $role, string $permission): bool
{
    $def = aiAgentGetRoleDef($role);
    return $def['permissions'][$permission] ?? false;
}

function aiAgentGetAccessibleScopes(string $role): array
{
    $def = aiAgentGetRoleDef($role);
    return $def['accessible_scopes'] ?? [];
}

function aiAgentGetAccessibleTables(string $role): array
{
    $def = aiAgentGetRoleDef($role);
    return $def['accessible_tables'] ?? [];
}

function aiAgentRoleIsUserScoped(string $role): bool
{
    $def = aiAgentGetRoleDef($role);
    return $def['user_scoped'] ?? false;
}

function aiAgentRoleCanSeeSensitive(string $role): bool
{
    $def = aiAgentGetRoleDef($role);
    return $def['can_see_sensitive'] ?? false;
}

function aiAgentGetApprovingRoles(): array
{
    $roles = [];
    foreach (aiAgentGetRoleDefinitions() as $name => $def) {
        if ($def['permissions']['approve_borrowing'] ?? false) {
            $roles[] = $name;
        }
    }
    return $roles;
}
