/**
 * Role Validator - redirect jika role tidak sesuai (berdasarkan localStorage user dari login)
 * Base path dideteksi dari URL saat ini agar works di berbagai environment.
 */
const RoleValidator = (function () {
    function getBasePath() {
        var path = window.location.pathname || "";
        if (path.indexOf("/pic-barang") >= 0) return path.split("/pic-barang")[0];
        if (path.indexOf("/admin") >= 0) return path.split("/admin")[0];
        if (path.indexOf("/manager") >= 0) return path.split("/manager")[0];
        if (path.indexOf("/user") >= 0) return path.split("/user")[0];
        if (path.indexOf("/auth") >= 0) return path.split("/auth")[0];
        return (typeof BASE_URL !== 'undefined') ? BASE_URL : '';
    }

    function getUser() {
        try {
            return JSON.parse(localStorage.getItem("user") || "{}");
        } catch (e) {
            return {};
        }
    }

    function getAvatar() {
        const u = getUser();
        return (u && u.avatar) ? (u.avatar.startsWith('http') ? u.avatar : getBasePath() + '/' + u.avatar) : getBasePath() + '/assets/images/avatar/1.png';
    }

    // Apply avatar to all images with class 'user-avtar'
    function applyAvatar() {
        try {
            const src = getAvatar();
            document.querySelectorAll('img.user-avtar').forEach(img => {
                img.src = src;
            });
        } catch (e) { /* ignore */ }
    }

    function redirectToLogin() {
        window.location.href = getBasePath() + "/index.html";
    }

    function requireAuth() {
        const user = getUser();
        if (!user || !user.id) {
            redirectToLogin();
            return false;
        }
        return true;
    }

    function requireUser() {
        if (!requireAuth()) return;
        const user = getUser();
        const base = getBasePath();
        if (user.role !== "user") {
            if (user.role === "admin") window.location.href = base + "/admin/dashboard.html";
            else if (user.role === "manager") window.location.href = base + "/manager/dashboard.html";
            else if (user.role === "pic_barang") window.location.href = base + "/pic-barang/dashboard.html";
            else redirectToLogin();
        }
    }

    function requireAdmin() {
        if (!requireAuth()) return;
        const user = getUser();
        const base = getBasePath();
        if (user.role !== "admin") {
            if (user.role === "user") window.location.href = base + "/user/dashboard.html";
            else if (user.role === "manager") window.location.href = base + "/manager/dashboard.html";
            else if (user.role === "pic_barang") window.location.href = base + "/pic-barang/dashboard.html";
            else redirectToLogin();
        }
    }

    function requireManager() {
        if (!requireAuth()) return;
        const user = getUser();
        const base = getBasePath();
        if (user.role !== "manager") {
            if (user.role === "admin") window.location.href = base + "/admin/dashboard.html";
            else if (user.role === "user") window.location.href = base + "/user/dashboard.html";
            else if (user.role === "pic_barang") window.location.href = base + "/pic-barang/dashboard.html";
            else redirectToLogin();
        }
    }

    function requirePicBarang() {
        if (!requireAuth()) return;
        const user = getUser();
        const base = getBasePath();
        if (user.role !== "pic_barang") {
            if (user.role === "admin") window.location.href = base + "/admin/dashboard.html";
            else if (user.role === "manager") window.location.href = base + "/manager/dashboard.html";
            else if (user.role === "user") window.location.href = base + "/user/dashboard.html";
            else redirectToLogin();
        }
    }

    return {
        requireAuth,
        requireUser,
        requireAdmin,
        requireManager,
        requirePicBarang,
        getUser,
        getAvatar,
        applyAvatar
    };
})();

// Auto apply avatar on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function () {
    if (typeof RoleValidator !== 'undefined' && RoleValidator.applyAvatar) {
        try { RoleValidator.applyAvatar(); } catch (e) { }
    }
});
