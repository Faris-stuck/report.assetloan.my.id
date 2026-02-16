/**
 * Logout Handler - Securely logout user
 * - Clears server session
 * - Clears localStorage
 * - Redirects to login page
 */

document.addEventListener('DOMContentLoaded', function () {
    // Find all logout buttons/links
    const logoutElements = document.querySelectorAll('[data-logout]');

    logoutElements.forEach(element => {
        element.addEventListener('click', function (e) {
            e.preventDefault();
            performLogout();
        });
    });
});

async function performLogout() {
    try {
        // Call server logout endpoint to destroy session
        const response = await fetch('/PROJECT/api/auth/logout.php', {
            method: 'POST',
            credentials: 'include'
        });

        // Clear localStorage regardless of server response
        localStorage.removeItem('user');
        localStorage.removeItem('role');
        localStorage.removeItem('userId');
        sessionStorage.clear();

        // Redirect to login page
        window.location.href = '/PROJECT/auth/login.html';
    } catch (error) {
        console.error('Logout error:', error);
        // Still clear localStorage and redirect even if fetch fails
        localStorage.clear();
        sessionStorage.clear();
        window.location.href = '/PROJECT/auth/login.html';
    }
}
