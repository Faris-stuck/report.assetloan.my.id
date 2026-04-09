/**
 * Logout Handler - Securely logout user
 * - Clears server session
 * - Clears localStorage
 * - Redirects to login page
 * - Handles dynamically added logout buttons
 * - Works with Bootstrap dropdowns
 */
(function () {
    if (typeof BASE_URL === 'undefined') {
        console.warn('BASE_URL is not defined. Ensure assets/js/base-url.js is loaded first.');
        window.BASE_URL = window.location.origin;
    }

    function bindLogoutElement(element) {
        if (!element || element.dataset.logoutBound === 'true') {
            return;
        }

        element.dataset.logoutBound = 'true';
        element.addEventListener('click', handleLogoutClick);
    }

    function setupLogoutListeners(root) {
        const scope = root && root.querySelectorAll ? root : document;
        const logoutElements = scope.querySelectorAll('[data-logout]');
        logoutElements.forEach(bindLogoutElement);
    }

    function handleLogoutClick(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        const dropdowns = document.querySelectorAll('.dropdown-menu.show');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
            const toggle = dropdown.previousElementSibling;
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });

        performLogout();
        return false;
    }

    function startObserver() {
        if (window.__logoutObserverStarted || !document.body) {
            return;
        }

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(mutation => {
                if (mutation.type !== 'childList') {
                    return;
                }

                mutation.addedNodes.forEach(node => {
                    if (node.nodeType !== 1) {
                        return;
                    }

                    if (node.matches && node.matches('[data-logout]')) {
                        bindLogoutElement(node);
                    }

                    if (node.querySelectorAll) {
                        setupLogoutListeners(node);
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        window.__logoutObserverStarted = true;
    }

    function initializeLogout() {
        setupLogoutListeners(document);
        startObserver();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeLogout, { once: true });
    } else {
        initializeLogout();
    }

    async function performLogout() {
        try {
            const logoutButtons = document.querySelectorAll('[data-logout]');
            logoutButtons.forEach(btn => {
                if ('disabled' in btn) {
                    btn.disabled = true;
                }
            });

            await fetch(BASE_URL + '/api/auth/logout.php', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            performLocalCleanup();
            setTimeout(() => {
                window.location.href = BASE_URL + '/index.html';
            }, 100);
        } catch (error) {
            console.error('Logout error:', error);
            performLocalCleanup();
            setTimeout(() => {
                window.location.href = BASE_URL + '/index.html';
            }, 100);
        }
    }

    function performLocalCleanup() {
        try {
            localStorage.removeItem('user');
            localStorage.removeItem('role');
            localStorage.removeItem('userId');
            localStorage.removeItem('token');
            localStorage.removeItem('userData');

            sessionStorage.clear();

            if (window.userData) delete window.userData;
            if (window.RoleValidator && window.RoleValidator.clearUser) {
                window.RoleValidator.clearUser();
            }

            return true;
        } catch (e) {
            console.warn('Partial cleanup failed:', e);
            return false;
        }
    }
})();
