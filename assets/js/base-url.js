/**
 * Dynamic Base URL Detection
 * ═══════════════════════════════════════════════════════════════
 * Automatically detects whether the project runs in a subfolder
 * (e.g. http://localhost/PROJECT) or at the document root
 * (e.g. https://domain.com), and sets window.BASE_URL accordingly.
 *
 * MUST be loaded BEFORE all other scripts in every HTML page.
 *
 * Usage in JS:
 *   fetch(BASE_URL + '/api/auth/login.php', {...})
 *   window.location.href = BASE_URL + '/user/dashboard.html';
 */
(function () {
    'use strict';

    // Primary detection: use the script's own src attribute.
    // If this file is at PROJECT/assets/js/base-url.js, then
    // everything before /assets/js/base-url.js is the base path.
    var scriptSrc = document.currentScript ? document.currentScript.src : '';
    var marker = '/assets/js/base-url.js';
    var idx = scriptSrc.indexOf(marker);

    if (idx !== -1) {
        // Reliable: derive base from the resolved script URL
        window.BASE_URL = scriptSrc.substring(0, idx);
    } else {
        // Fallback: detect from the current page URL
        var pathParts = window.location.pathname.split('/').filter(Boolean);
        var knownFolders = ['admin', 'user', 'manager', 'pic-barang', 'api', 'assets', 'config'];

        if (pathParts.length > 0 && knownFolders.indexOf(pathParts[0]) === -1) {
            // First path segment is NOT a known app folder →
            // probably a project subfolder like /PROJECT
            window.BASE_URL = window.location.origin + '/' + pathParts[0];
        } else {
            // Running at document root
            window.BASE_URL = window.location.origin;
        }
    }

    // Ensure no trailing slash
    if (window.BASE_URL.endsWith('/')) {
        window.BASE_URL = window.BASE_URL.slice(0, -1);
    }

    injectRoleAppAssets();

    function injectRoleAppAssets() {
        var path = window.location.pathname || '';
        var isRolePage = /\/(admin|user|manager|pic-barang)(\/|$)/.test(path);

        if (!isRolePage || window.__roleAppAssetsInjected) {
            return;
        }

        window.__roleAppAssetsInjected = true;

        appendStyle('ai-agent-widget-style', window.BASE_URL + '/assets/css/ai-agent-widget.css');
        appendScript('ai-agent-widget-script', window.BASE_URL + '/assets/js/ai-agent-widget.js');
    }

    function appendStyle(id, href) {
        if (!href || document.getElementById(id)) {
            return;
        }

        var link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    }

    function appendScript(id, src) {
        if (!src || document.getElementById(id)) {
            return;
        }

        var script = document.createElement('script');
        script.id = id;
        script.src = src;
        document.head.appendChild(script);
    }
})();