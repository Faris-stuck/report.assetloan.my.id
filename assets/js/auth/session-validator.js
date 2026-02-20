/**
 * Session Security Validator
 * Menjalankan SEBELUM page-guard.js
 * 
 * Validasi cepat:
 * 1. User data harus ada di localStorage
 * 2. Data user harus punya field yang diperlukan (id, role, email)
 * 3. Jika tidak valid, bersihkan dan redirect ke login
 * 
 * NOTE: Verifikasi session server dilakukan oleh page-guard.js secara SINKRON
 *       Script ini hanya melakukan quick-check localStorage
 */

(function () {
    'use strict';

    // Quick check: apakah ada user data di localStorage
    var userStr = localStorage.getItem('user');

    if (!userStr) {
        // Tidak ada data user — redirect ke login
        clearSessionAndRedirect();
        return;
    }

    try {
        var user = JSON.parse(userStr);

        // Validasi field wajib
        if (!user.id || !user.role || !user.email) {
            clearSessionAndRedirect();
            return;
        }

        // Validasi role harus salah satu dari yang diizinkan
        var validRoles = ['admin', 'manager', 'pic_barang', 'user'];
        if (validRoles.indexOf(user.role) === -1) {
            clearSessionAndRedirect();
            return;
        }

    } catch (error) {
        clearSessionAndRedirect();
    }
})();

/**
 * Bersihkan semua data session client dan redirect ke login
 */
function clearSessionAndRedirect() {
    try {
        localStorage.removeItem('user');
        localStorage.removeItem('role');
        localStorage.removeItem('userId');
        sessionStorage.clear();
    } catch (e) { }

    // Redirect ke login
    window.location.replace('/PROJECT/index.html');
}
