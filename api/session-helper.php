<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class SessionValidator {
    /**
     * Validasi role user dari session. Jika tidak punya akses, return JSON error dan exit.
     * @param string[] $allowedRoles array role yang diizinkan: 'user'(requester), 'manager'(approver), 'admin', 'pic_barang'
     */
    public static function requireRole(array $allowedRoles) {
        $role = $_SESSION['user_role'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;
        
        // Cek apakah session login aktif
        if ($userId === null || $role === null) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Session tidak valid. Silakan login kembali.']);
            exit;
        }
        
        // Cek apakah role sesuai
        if (!in_array($role, $allowedRoles, true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Akses ditolak. Role Anda (' . $role . ') tidak diizinkan.']);
            exit;
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
    
    /** Ambil nama user dari session */
    public static function getUserName() {
        return $_SESSION['user_nama'] ?? null;
    }
    
    /** Ambil email user dari session */
    public static function getUserEmail() {
        return $_SESSION['user_email'] ?? null;
    }
    
    /** Cek apakah user sudah login */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }
}
