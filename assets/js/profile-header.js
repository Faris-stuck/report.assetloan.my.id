/**
 * Standardized Profile Header Component Script
 * 
 * This script handles fetching the current user data and populating
 * the profile header with user name and email from the database.
 * 
 * Usage: Include this script in pages that have a profile header
 * It will automatically fetch and populate user data.
 */

// Get BASE_URL for API calls
const BASE_URL = window.BASE_URL || (function () {
    const path = window.location.pathname;
    if (path.includes('/admin/')) return '/PROJECT';
    if (path.includes('/user/')) return '/PROJECT';
    if (path.includes('/manager/')) return '/PROJECT';
    if (path.includes('/pic-barang/')) return '/PROJECT';
    return window.location.origin;
})();

/**
 * Initialize profile header with user data from session
 */
async function initializeProfileHeader() {
    try {
        // Fetch current user data from session
        const response = await fetch(BASE_URL + '/api/user/get-current-user.php');

        if (!response.ok) {
            console.error('Failed to fetch user data:', response.status);
            return;
        }

        const data = await response.json();

        if (data.success) {
            // Update profile header with user name and email
            updateProfileHeader(data.nama, data.email);
        }
    } catch (error) {
        console.error('Error initializing profile header:', error);
    }
}

/**
 * Update profile header with user name and email
 * @param {string} name - User name
 * @param {string} email - User email
 */
function updateProfileHeader(name, email) {
    // Find all profile header containers (supports multiple on page)
    const profileHeaders = document.querySelectorAll('[data-profile-header]');

    profileHeaders.forEach(header => {
        // Find name and email elements within this header
        const nameElement = header.querySelector('[data-user-name]');
        const emailElement = header.querySelector('[data-user-email]');

        if (nameElement) {
            nameElement.textContent = name || 'User';
        }

        if (emailElement) {
            emailElement.textContent = email || '';
        }
    });
}

/**
 * Setup automatic profile header when DOM is ready
 */
function setupProfileHeaderOnReady() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeProfileHeader);
    } else {
        // DOM already loaded
        initializeProfileHeader();
    }
}

// Auto-initialize when script loads
setupProfileHeaderOnReady();
