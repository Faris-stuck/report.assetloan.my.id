/**
 * PAGE GUARD — Sistem Keamanan Halaman
 * ═══════════════════════════════════════════════════════════════
 * Script ini HARUS dimuat di <head> SEBELUM CSS/konten lainnya.
 * 
 * Cara kerja:
 * 1. Langsung membuat layar hitam (sebelum konten render)
 * 2. Cek localStorage 'user' — jika tidak ada → redirect login
 * 3. Deteksi folder role dari URL (admin/, user/, manager/, pic-barang/)
 * 4. Bandingkan user.role dengan folder → jika tidak cocok → redirect login
 * 5. Jika cocok → hapus layar hitam, tampilkan konten
 * 
 * Proteksi: layar tetap hitam selama validasi, data tidak terlihat
 */
(function () {
    'use strict';

    // ─── 1. INSTANT BLACKOUT — sebelum apapun di-render ───
    var shield = document.createElement('div');
    shield.id = '__page_guard_shield';
    shield.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:#000;z-index:999999;';
    // Insert into <html> immediately (before <body> exists)
    document.documentElement.appendChild(shield);

    // ─── 2. Detect base path ───
    var path = window.location.pathname || '';
    var basePath = '/PROJECT'; // default
    var segments = ['admin', 'user', 'manager', 'pic-barang', 'auth'];
    for (var i = 0; i < segments.length; i++) {
        var idx = path.indexOf('/' + segments[i]);
        if (idx >= 0) {
            basePath = path.substring(0, idx);
            break;
        }
    }

    var loginUrl = basePath + '/index.html';

    // ─── 3. Determine required role from URL folder ───
    var requiredRole = null;
    if (path.indexOf('/admin/') >= 0 || path.indexOf('/admin') === path.length - 6) {
        requiredRole = 'admin';
    } else if (path.indexOf('/manager/') >= 0 || path.indexOf('/manager') === path.length - 8) {
        requiredRole = 'manager';
    } else if (path.indexOf('/pic-barang/') >= 0 || path.indexOf('/pic-barang') === path.length - 11) {
        requiredRole = 'pic_barang';
    } else if (path.indexOf('/user/') >= 0 || path.indexOf('/user') === path.length - 5) {
        requiredRole = 'user';
    }

    // If we can't determine the role folder, don't block (might be auth page etc)
    if (!requiredRole) {
        removeShield();
        return;
    }

    // ─── 4. Validate user session ───
    var user = null;
    try {
        user = JSON.parse(localStorage.getItem('user') || 'null');
    } catch (e) {
        user = null;
    }

    // No user logged in → redirect to login
    if (!user || !user.id || !user.role) {
        kickToLogin();
        return;
    }

    // Role mismatch → kick out
    if (user.role !== requiredRole) {
        kickToLogin();
        return;
    }

    // ─── 5. PASSED — remove blackout ───
    // Wait for DOM to be ready then remove shield
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            removeShield();
        });
    } else {
        removeShield();
    }

    // ─── Helper functions ───
    function removeShield() {
        var s = document.getElementById('__page_guard_shield');
        if (s) {
            // Smooth fade out
            s.style.transition = 'opacity 0.2s ease';
            s.style.opacity = '0';
            setTimeout(function () {
                if (s.parentNode) s.parentNode.removeChild(s);
            }, 200);
        }
    }

    function kickToLogin() {
        // Clear user data for security
        try { localStorage.removeItem('user'); } catch (e) { }
        // Redirect (shield stays black, user sees nothing)
        window.location.replace(loginUrl);
    }

})();
