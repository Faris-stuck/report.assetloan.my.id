<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class SessionValidator {
    /**
     * Validasi role user dari session. Melempar Exception jika tidak punya akses.
     * @param string[] $allowedRoles array role yang diizinkan: 'user'(requester), 'manager'(approver), 'admin', 'pic_barang'
     */
    public static function requireRole(array $allowedRoles) {
        $role = $_SESSION['user_role'] ?? null;
        if ($role === null || !in_array($role, $allowedRoles, true)) {
            throw new Exception('Akses ditolak. Role tidak sesuai.');
        }
    }

    /** Ambil role user saat ini dari session */
    public static function getRole() {
        return $_SESSION['user_role'] ?? null;
    }

    /** Ambil user_id dari session */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}
