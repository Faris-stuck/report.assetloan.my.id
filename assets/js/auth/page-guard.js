/**
 * PAGE GUARD — Sistem Keamanan Halaman (Session-Based)
 * ═══════════════════════════════════════════════════════════════
 * Script ini HARUS dimuat di <head> SEBELUM CSS/konten lainnya.
 * 
 * Cara kerja:
 * 1. Langsung membuat layar hitam (sebelum konten render)
 * 2. Cek localStorage 'user' — jika tidak ada → redirect login
 * 3. Deteksi folder role dari URL (admin/, user/, manager/, pic-barang/)
 * 4. Bandingkan user.role dengan folder → jika tidak cocok → redirect login
 * 5. VERIFIKASI SESSION SERVER secara SINKRON → jika invalid → redirect login
 * 6. Jika semua lolos → hapus layar hitam, tampilkan konten
 * 
 * Protection: screen stays black during validation, data not visible.
 * Session server = sumber kebenaran tunggal (1 role per browser).
 */
(function () {
    'use strict';

    // ─── 1. INSTANT BLACKOUT — sebelum apapun di-render ───
    var shield = document.createElement('div');
    shield.id = '__page_guard_shield';
    shield.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:#000;z-index:999999;';
    document.documentElement.appendChild(shield);

    // ─── 2. Konfigurasi path ───
    var loginUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/index.html';
    var verifyUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/api/auth/verify-session.php';
    var path = window.location.pathname || '';

    // ─── 3. Tentukan role yang diperlukan dari folder URL ───
    var requiredRole = null;
    if (path.indexOf('/admin/') >= 0 || path.match(/\/admin$/)) {
        requiredRole = 'admin';
    } else if (path.indexOf('/manager/') >= 0 || path.match(/\/manager$/)) {
        requiredRole = 'manager';
    } else if (path.indexOf('/pic-barang/') >= 0 || path.match(/\/pic-barang$/)) {
        requiredRole = 'pic_barang';
    } else if (path.indexOf('/user/') >= 0 || path.match(/\/user$/)) {
        requiredRole = 'user';
    }

    // Jika tidak bisa tentukan role folder, jangan blokir (mungkin halaman auth)
    if (!requiredRole) {
        removeShield();
        return;
    }

    // ─── 4. Quick check: localStorage 'user' ───
    var user = null;
    try {
        user = JSON.parse(localStorage.getItem('user') || 'null');
    } catch (e) {
        user = null;
    }

    // Tidak ada data user → tendang ke login
    if (!user || !user.id || !user.role) {
        kickToLogin();
        return;
    }

    // Role tidak sesuai folder → tendang ke login
    if (user.role !== requiredRole) {
        kickToLogin();
        return;
    }

    // ─── 5. VERIFIKASI SESSION SERVER (SINKRON) ───
    // Ini memastikan session PHP valid dan role cocok.
    // localStorage bisa dimanipulasi, session server TIDAK bisa.
    // Sinkron agar halaman TIDAK pernah tampil tanpa verifikasi server.
    try {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', verifyUrl, false); // synchronous = blocking
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.withCredentials = true; // kirim session cookie
        xhr.send(JSON.stringify({
            user_id: user.id,
            user_role: user.role
        }));

        if (xhr.status !== 200) {
            // Session server tidak valid — bisa jadi:
            // - Session expired
            // - Role berubah di database
            // - localStorage dimanipulasi
            // - Session hijacking attempt
            kickToLogin();
            return;
        }
    } catch (e) {
        // Network error — untuk keamanan, tetap tendang ke login
        // Lebih baik logout dari pada membiarkan akses tanpa verifikasi
        console.warn('Page Guard: Failed to verify server session:', e.message);
        kickToLogin();
        return;
    }

    // ─── 6. SEMUA LOLOS — tampilkan halaman ───
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            removeShield();
            updateUserProfileDropdown();
        });
    } else {
        removeShield();
        updateUserProfileDropdown();
    }

    // ─── Helper functions ───
    function updateUserProfileDropdown() {
        // Update hardcoded user profile with actual data from localStorage
        // User data stored as "user" key (set by login.php response)
        try {
            let userData = user || JSON.parse(localStorage.getItem('user') || '{}');
            let nama = userData.nama || 'User';
            let email = userData.email || '';

            // Find all profile name and email elements in dropdown headers
            let profileNameElements = document.querySelectorAll('.nxl-user-dropdown .dropdown-header h6');
            let profileEmailElements = document.querySelectorAll('.nxl-user-dropdown .dropdown-header .fs-12.fw-medium.text-muted');

            profileNameElements.forEach(el => {
                // Replace only the name text, keep the badge/span
                let badgeSpan = el.querySelector('span');
                el.textContent = nama;
                if (badgeSpan) {
                    el.appendChild(document.createTextNode(' '));
                    el.appendChild(badgeSpan);
                }
            });

            profileEmailElements.forEach(el => {
                el.textContent = email;
            });
        } catch (e) {
            console.warn('Page Guard: Failed to update user profile dropdown:', e.message);
        }
    }

    function removeShield() {
        var s = document.getElementById('__page_guard_shield');
        if (s) {
            s.style.transition = 'opacity 0.2s ease';
            s.style.opacity = '0';
            setTimeout(function () {
                if (s.parentNode) s.parentNode.removeChild(s);
            }, 200);
        }
    }

    function kickToLogin() {
        // Bersihkan semua data client untuk keamanan
        try {
            localStorage.removeItem('user');
            localStorage.removeItem('role');
            localStorage.removeItem('userId');
            sessionStorage.clear();
        } catch (e) { }
        // Redirect (shield tetap hitam, user tidak lihat apa-apa)
        window.location.replace(loginUrl);
    }

})();
