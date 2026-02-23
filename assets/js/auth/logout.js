/**
 * Logout Handler - Securely logout user
 * - Clears server session
 * - Clears localStorage
 * - Redirects to login page
 * - Handles dynamically added logout buttons
 * - Works with Bootstrap dropdowns
 */

// Function to setup logout event listeners
function setupLogoutListeners() {
    const logoutElements = document.querySelectorAll('[data-logout]');

    logoutElements.forEach(element => {
        // Remove any existing listeners by cloning and replacing
        const newElement = element.cloneNode(true);
        element.parentNode.replaceChild(newElement, element);

        // Setup click listener on new element
        newElement.addEventListener('click', handleLogoutClick);
    });
}

function handleLogoutClick(e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    // Close any open dropdowns before logout
    const dropdowns = document.querySelectorAll('.dropdown-menu.show');
    dropdowns.forEach(dropdown => {
        dropdown.classList.remove('show');
        const toggle = dropdown.previousElementSibling;
        if (toggle && toggle.ariExpanded) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    // Trigger logout
    performLogout();
    return false;
}

// Setup on initial page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupLogoutListeners);
} else {
    // DOM already loaded
    setupLogoutListeners();
}

// Also setup when DOM changes (for dynamically added elements)
const observer = new MutationObserver(function (mutations) {
    // Check if any data-logout elements were added
    for (const mutation of mutations) {
        if (mutation.type === 'childList') {
            for (const node of mutation.addedNodes) {
                // Check if node or its children contain data-logout
                if (node.nodeType === 1) { // Element node
                    if (node.hasAttribute && node.hasAttribute('data-logout')) {
                        node.addEventListener('click', handleLogoutClick);
                    }
                    const logoutInChildren = node.querySelector ? node.querySelector('[data-logout]') : null;
                    if (logoutInChildren) {
                        setupLogoutListeners();
                    }
                }
            }
        }
    }
});

observer.observe(document.body, {
    childList: true,
    subtree: true
});

async function performLogout() {
    try {
        console.log('🔐 Starting secure logout process...');

        // Disable all logout buttons to prevent double-click
        const logoutButtons = document.querySelectorAll('[data-logout]');
        logoutButtons.forEach(btn => btn.disabled = true);

        // Call server logout endpoint to destroy session
        const response = await fetch(BASE_URL + '/api/auth/logout.php', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        console.log('📤 Server logout response:', response.status);

        // Perform cleanup regardless of server response
        const cleanupSuccess = performLocalCleanup();

        console.log('🧹 Local cleanup:', cleanupSuccess ? 'success' : 'partial');

        // Small delay to ensure cleanup happens before redirect
        setTimeout(() => {
            console.log('🚀 Redirecting to login page...');
            window.location.href = BASE_URL + '/index.html';
        }, 100);

    } catch (error) {
        console.error('❌ Logout error:', error);

        // Still perform cleanup and redirect even if fetch fails
        performLocalCleanup();

        setTimeout(() => {
            window.location.href = BASE_URL + '/index.html';
        }, 100);
    }
}

function performLocalCleanup() {
    try {
        // Clear localStorage
        localStorage.removeItem('user');
        localStorage.removeItem('role');
        localStorage.removeItem('userId');
        localStorage.removeItem('token');
        localStorage.removeItem('userData');

        // Clear sessionStorage
        sessionStorage.clear();

        // Clear any custom session data
        if (window.userData) delete window.userData;
        if (window.RoleValidator) {
            if (window.RoleValidator.clearUser) window.RoleValidator.clearUser();
        }

        console.log('✅ All local data cleared');
        return true;
    } catch (e) {
        console.warn('⚠️ Partial cleanup failed:', e);
        return false;
    }
}
