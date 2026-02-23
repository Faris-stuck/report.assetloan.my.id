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

// Inject a small avatar uploader into user dropdowns so any role can change photo
document.addEventListener('DOMContentLoaded', function () {
    try {
        if (!document.getElementById('rv-avatar-input')) {
            const inp = document.createElement('input');
            inp.type = 'file'; inp.accept = 'image/*'; inp.id = 'rv-avatar-input'; inp.style.display = 'none';
            document.body.appendChild(inp);
            inp.addEventListener('change', function () {
                const f = inp.files[0];
                if (!f) return;
                const fd = new FormData(); fd.append('avatar', f);
                fetch(BASE_URL + '/api/user/upload-avatar.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(res => {
                        if (res.status && res.avatar) {
                            try { const lu = JSON.parse(localStorage.getItem('user') || '{}'); lu.avatar = res.avatar; localStorage.setItem('user', JSON.stringify(lu)); } catch (e) { }
                            if (RoleValidator && RoleValidator.applyAvatar) RoleValidator.applyAvatar();
                            alert('Avatar updated');
                        } else {
                            alert(res.message || 'Upload failed');
                        }
                    })
                    .catch(() => alert('Upload error'))
                    .finally(() => { inp.value = ''; });
            });
        }

        document.querySelectorAll('.nxl-user-dropdown, .dropdown.nxl-h-item .dropdown-menu').forEach(menu => {
            if (menu.querySelector('.rv-change-avatar')) return;
            const a = document.createElement('a');
            a.href = 'javascript:void(0);';
            a.className = 'dropdown-item rv-change-avatar';
            a.innerHTML = '<i class="feather-camera"></i> Change Photo';
            a.addEventListener('click', function (e) { e.preventDefault(); document.getElementById('rv-avatar-input').click(); });
            // insert at top
            if (menu.firstChild) menu.insertBefore(a, menu.firstChild);
            else menu.appendChild(a);
        });
    } catch (e) { /* ignore */ }
});
