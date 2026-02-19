/**
 * Session Security Validator
 * Menjalankan SEBELUM page-guard.js
 * 
 * Validasi:
 * 1. User data harus ada di localStorage
 * 2. Session di server harus valid
 * 3. Jika invalid, redirect ke login dan clear cache
 */

(function () {
    'use strict';

    // Check if user is logged in
    const userStr = localStorage.getItem('user');

    if (!userStr) {
        // No user in localStorage - redirect to login
        console.warn('Session check: No user in localStorage');
        clearSessionAndRedirect();
        return;
    }

    try {
        const user = JSON.parse(userStr);

        // Validate user object has required fields
        if (!user.id || !user.role || !user.email) {
            console.warn('Session check: Invalid user object');
            clearSessionAndRedirect();
            return;
        }

        // Verify session on server asynchronously
        validateServerSession(user);

    } catch (error) {
        console.error('Session check error:', error);
        clearSessionAndRedirect();
    }
})();

/**
 * Validate session with server
 */
async function validateServerSession(user) {
    try {
        const response = await fetch('/PROJECT/api/auth/verify-session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                user_id: user.id,
                user_role: user.role
            })
        });

        if (!response.ok) {
            console.warn('Server session validation failed');
            clearSessionAndRedirect();
        }
    } catch (error) {
        console.warn('Server session validation error:', error);
        // Don't redirect on network errors, but user session might expire soon
    }
}

/**
 * Clear all session data and redirect to login
 */
function clearSessionAndRedirect() {
    localStorage.removeItem('user');
    localStorage.removeItem('role');
    localStorage.removeItem('userId');
    sessionStorage.clear();

    // Redirect to login
    window.location.replace('/PROJECT/index.html');
}
