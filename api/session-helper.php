<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class SessionValidator {
    /**
     * Validate user role from session. If no access, return JSON error and exit.
     * @param string[] $allowedRoles array of allowed roles: 'user'(requester), 'manager'(approver), 'admin', 'pic_barang'
     */
    public static function requireRole(array $allowedRoles) {
        $role = $_SESSION['user_role'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;
        
        // Check if login session is active
        if ($userId === null || $role === null) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Session not valid. Please log in again.']);
            exit;
        }
        
        // Check if role matches
        if (!in_array($role, $allowedRoles, true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Access denied. Your role (' . $role . ') is not authorized.']);
            exit;
        }
    }

    /** Get current user role from session */
    public static function getRole() {
        return $_SESSION['user_role'] ?? null;
    }

    /** Get user_id from session */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /** Get user name from session */
    public static function getUserName() {
        return $_SESSION['user_nama'] ?? null;
    }
    
    /** Get user email from session */
    public static function getUserEmail() {
        return $_SESSION['user_email'] ?? null;
    }
    
    /** Check if user is logged in */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }
}
