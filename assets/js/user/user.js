// User Management JavaScript
// ═══════════════════════════════════════════════════════════════
// State
let allUsers = [];
let filteredUsers = [];
let currentPage = 1;
let perPage = 100;
let sortField = 'nama';
let sortAsc = true;
let rolesData = []; // Loaded from database, no hardcode

// ═══════════════════════════════════════════════════════════════
// Load roles from database via API
function loadRolesData() {
    return fetch(BASE_URL + '/api/admin/roles.php?action=list')
        .then(r => r.json())
        .then(res => {
            if (res.status && res.data) {
                rolesData = res.data;
                populateRoleDropdowns();
            }
        })
        .catch(err => console.error('Failed to load roles:', err));
}

// Populate all role dropdowns dynamically from DB
function populateRoleDropdowns() {
    // Filter role dropdown in table header
    const filterRole = document.getElementById('filterRole');
    if (filterRole) {
        const currentVal = filterRole.value;
        filterRole.innerHTML = '<option value="">All</option>';
        rolesData.forEach(r => {
            filterRole.innerHTML += `<option value="${r.role}">${getRoleLabel(r.role)}</option>`;
        });
        filterRole.value = currentVal;
    }

    // Role Group dropdown in ADD USER modal
    const roleGroup = document.getElementById('roleGroup');
    if (roleGroup) {
        roleGroup.innerHTML = '<option value="">-- Select Role Group --</option>';
        rolesData.forEach(r => {
            roleGroup.innerHTML += `<option value="${r.role}">${r.role.toUpperCase().replace(/_/g, ' ')}</option>`;
        });
    }

    // Role dropdown in ADD USER modal
    const role = document.getElementById('role');
    if (role) {
        role.innerHTML = '<option value="">-- Select Role --</option>';
        rolesData.forEach(r => {
            role.innerHTML += `<option value="${r.role}">${getRoleLabel(r.role).toUpperCase()}</option>`;
        });
    }

    // Edit Role dropdown in edit modal
    const editRoleSelect = document.getElementById('editRoleSelect');
    if (editRoleSelect) {
        editRoleSelect.innerHTML = '<option value="">-- Select Role --</option>';
        rolesData.forEach(r => {
            editRoleSelect.innerHTML += `<option value="${r.role}">${getRoleLabel(r.role)}</option>`;
        });
    }

    // Role descriptions in edit modal
    const editRoleDesc = document.getElementById('editRoleDescriptions');
    if (editRoleDesc) {
        editRoleDesc.innerHTML = rolesData.map(r =>
            `<strong>${getRoleLabel(r.role)}:</strong> ${r.deskripsi || '-'}`
        ).join('<br>');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const formTambahUser = document.getElementById('formTambahUser');
    const tableUsers = document.getElementById('tableUsers');

    // Handle form submission untuk tambah user
    if (formTambahUser) {
        formTambahUser.addEventListener('submit', function (e) {
            e.preventDefault();
            tambahUser();
        });
    }

    // Handle Change Password form
    const formCP = document.getElementById('formChangePassword');
    if (formCP) {
        formCP.addEventListener('submit', function (e) {
            e.preventDefault();
            changePassword();
        });
    }

    // Check All checkbox
    const checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.user-check').forEach(cb => cb.checked = this.checked);
        });
    }

    // Load roles from DB first, then load users
    if (tableUsers) {
        loadRolesData().then(() => loadUsers());
    }
});

// ═══════════════════════════════════════════════════════════════
// Sync Role Group → Role dropdown
function syncRole() {
    const rg = document.getElementById('roleGroup');
    const r = document.getElementById('role');
    if (rg && r) r.value = rg.value;
}

// ═══════════════════════════════════════════════════════════════
// Tambah User
function tambahUser() {
    const nama = document.getElementById('nama').value;
    const nrp = document.getElementById('nrp').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const role = document.getElementById('role').value;

    // Validasi
    if (!nama || !nrp || !email || !password || !role) {
        showFeedback('All fields are required', 'warning');
        return;
    }

    if (password.length < 6) {
        showFeedback('Password must be at least 6 characters', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('nama', nama);
    formData.append('nrp', nrp);
    formData.append('email', email);
    formData.append('password', password);
    formData.append('role', role);

    fetch(BASE_URL + '/api/user/create.php', { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok && response.status !== 400) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.status) {
                const modalEl = document.getElementById('modalTambahUser');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                showFeedback('✓ User created successfully!', 'success');
                document.getElementById('formTambahUser').reset();
                setTimeout(() => loadUsers(), 500);
            } else {
                showFeedback(data.message || 'Failed to create user', 'danger');
            }
        })
        .catch(error => {
            showFeedback('❌ Error: ' + error.message, 'danger');
        });
}

// ═══════════════════════════════════════════════════════════════
// Load Users
function loadUsers() {
    fetch(BASE_URL + '/api/user/get_all.php')
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.status && data.data) {
                allUsers = data.data;
                applyFilters();
                populateChangePasswordSelect(allUsers);
            } else {
                document.getElementById('tableUsers').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No user data yet</td></tr>';
                updateShowingInfo(0, 0, 0);
            }
        })
        .catch(error => {
            document.getElementById('tableUsers').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">❌ Failed to load data: ' + error.message + '</td></tr>';
        });
}

// ═══════════════════════════════════════════════════════════════
// Filter + Sort + Paginate
function applyFilters() {
    const fName = (document.getElementById('filterName')?.value || '').toLowerCase();
    const fNrp = (document.getElementById('filterNrp')?.value || '').toLowerCase();
    const fEmail = (document.getElementById('filterEmail')?.value || '').toLowerCase();
    const fRole = (document.getElementById('filterRole')?.value || '').toLowerCase();

    filteredUsers = allUsers.filter(u => {
        if (fName && !u.nama.toLowerCase().includes(fName)) return false;
        if (fNrp && !u.nrp.toLowerCase().includes(fNrp)) return false;
        if (fEmail && !u.email.toLowerCase().includes(fEmail)) return false;
        if (fRole && u.role !== fRole) return false;
        return true;
    });

    // Sort
    filteredUsers.sort((a, b) => {
        let va = (a[sortField] || '').toString().toLowerCase();
        let vb = (b[sortField] || '').toString().toLowerCase();
        if (va < vb) return sortAsc ? -1 : 1;
        if (va > vb) return sortAsc ? 1 : -1;
        return 0;
    });

    currentPage = 1;
    renderPage();
}

function sortTable(field) {
    if (sortField === field) {
        sortAsc = !sortAsc;
    } else {
        sortField = field;
        sortAsc = true;
    }
    applyFilters();
}

function changePerPage() {
    perPage = parseInt(document.getElementById('perPage').value) || 100;
    currentPage = 1;
    renderPage();
}

function goToPage(p) {
    currentPage = p;
    renderPage();
}

// ═══════════════════════════════════════════════════════════════
// Render Table Page
function renderPage() {
    const total = filteredUsers.length;
    const totalPages = Math.ceil(total / perPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * perPage;
    const end = Math.min(start + perPage, total);
    const pageData = filteredUsers.slice(start, end);

    updateShowingInfo(start + 1, end, total);
    renderUsersTable(pageData, start);
    renderPagination(totalPages);
}

function updateShowingInfo(from, to, total) {
    const el = document.getElementById('showingInfo');
    if (el) {
        if (total === 0) {
            el.textContent = 'Showing 0 entries';
        } else {
            el.textContent = `Showing ${from} to ${to} of ${total} entries`;
        }
    }
}

function renderPagination(totalPages) {
    const ul = document.getElementById('pagination');
    if (!ul) return;
    ul.innerHTML = '';

    if (totalPages <= 1) return;

    // Prev
    const liPrev = document.createElement('li');
    liPrev.className = 'page-item' + (currentPage === 1 ? ' disabled' : '');
    liPrev.innerHTML = '<a class="page-link" href="javascript:void(0)" onclick="goToPage(' + (currentPage - 1) + ')">‹</a>';
    ul.appendChild(liPrev);

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (totalPages > 7 && i > 3 && i < totalPages - 1 && Math.abs(i - currentPage) > 1) {
            if (i === 4 || i === totalPages - 2) {
                const liDots = document.createElement('li');
                liDots.className = 'page-item disabled';
                liDots.innerHTML = '<span class="page-link">…</span>';
                ul.appendChild(liDots);
            }
            continue;
        }
        const li = document.createElement('li');
        li.className = 'page-item' + (i === currentPage ? ' active' : '');
        li.innerHTML = '<a class="page-link" href="javascript:void(0)" onclick="goToPage(' + i + ')">' + i + '</a>';
        ul.appendChild(li);
    }

    // Next
    const liNext = document.createElement('li');
    liNext.className = 'page-item' + (currentPage === totalPages ? ' disabled' : '');
    liNext.innerHTML = '<a class="page-link" href="javascript:void(0)" onclick="goToPage(' + (currentPage + 1) + ')">›</a>';
    ul.appendChild(liNext);
}

// ═══════════════════════════════════════════════════════════════
// Render Table Rows
function renderUsersTable(users, startIndex) {
    const tbody = document.getElementById('tableUsers');
    if (!users || users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No data found</td></tr>';
        return;
    }
    tbody.innerHTML = '';

    users.forEach((user, index) => {
        const roleClass = getRoleClass(user.role);
        const roleLabel = getRoleLabel(user.role);
        const created = user.created_at ? formatDate(user.created_at) : '-';
        const escapedName = (user.nama || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');

        const row = `
            <tr>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input user-check" value="${user.id}" style="margin:0;">
                </td>
                <td>${user.nama}</td>
                <td>${user.nrp}</td>
                <td><small>${user.email}</small></td>
                <td><span class="badge ${roleClass}">${roleLabel}</span></td>
                <td><small class="text-muted">${created}</small></td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-info" onclick="openChangePasswordForUser(${user.id}, '${escapedName}')" title="Change Password">
                            <i class="feather-lock"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="openEditRoleModal(${user.id}, '${escapedName}', '${user.nrp}', '${user.role}')" title="Edit Role">
                            <i class="feather-edit-2"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteUser(${user.id}, '${escapedName}')" title="Delete">
                            <i class="feather-trash-2"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    const day = String(d.getDate()).padStart(2, '0');
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return day + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
}

// ═══════════════════════════════════════════════════════════════
// Update User Role
function updateUserRole(userId, newRole) {
    const formData = new FormData();
    formData.append('id', userId);
    formData.append('role', newRole);

    fetch(BASE_URL + '/api/user/update_role.php', { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok && response.status !== 400) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.status) {
                showFeedback('✓ Role updated successfully', 'success');
                closeEditRoleModal();
                loadUsers();
            } else {
                showFeedback(data.message || 'Failed to update role', 'danger');
            }
        })
        .catch(error => showFeedback('❌ Error: ' + error.message, 'danger'));
}

// ═══════════════════════════════════════════════════════════════
// Delete User
function deleteUser(userId, userName) {
    if (!confirm('Are you sure you want to delete user: ' + userName + '?')) return;
    const formData = new FormData();
    formData.append('id', userId);

    fetch(BASE_URL + '/api/user/delete.php', { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok && response.status !== 400) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.status) {
                showFeedback('✓ User deleted successfully', 'success');
                loadUsers();
            } else {
                showFeedback(data.message || 'Failed to delete user', 'danger');
            }
        })
        .catch(error => showFeedback('❌ Error: ' + error.message, 'danger'));
}

// ═══════════════════════════════════════════════════════════════
// Edit Role Modal - Open and populate fields
function openEditRoleModal(userId, userName, userNrp, currentRole) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editUserNameField').value = userName;
    document.getElementById('editUserNrpField').value = userNrp;
    document.getElementById('editCurrentRoleField').value = getRoleLabel(currentRole);
    document.getElementById('editRoleSelect').value = currentRole;
    new bootstrap.Modal(document.getElementById('modalEditRole')).show();
}

function closeEditRoleModal() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditRole'));
    if (modal) modal.hide();
}

// Handle Save button click in modal edit role
function saveUserRoleChanges() {
    const userId = document.getElementById('editUserId').value;
    const newName = document.getElementById('editUserNameField').value.trim();
    const newNrp = document.getElementById('editUserNrpField').value.trim();
    const newRole = document.getElementById('editRoleSelect').value;

    if (!newName) {
        showFeedback('Name is required', 'warning');
        return;
    }
    if (!newNrp) {
        showFeedback('NRP is required', 'warning');
        return;
    }
    if (!newRole) {
        showFeedback('Role is required', 'warning');
        return;
    }

    if (!confirm('Are you sure you want to update this user\'s information?')) {
        return;
    }

    const formData = new FormData();
    formData.append('id', userId);
    formData.append('nama', newName);
    formData.append('nrp', newNrp);
    formData.append('role', newRole);

    fetch(BASE_URL + '/api/user/update.php', { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok && response.status !== 400) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.status) {
                showFeedback('✓ User updated successfully', 'success');
                closeEditRoleModal();
                loadUsers();
            } else {
                showFeedback(data.message || 'Failed to update user', 'danger');
            }
        })
        .catch(error => showFeedback('❌ Error: ' + error.message, 'danger'));
}

// ═══════════════════════════════════════════════════════════════
// Change Password
function populateChangePasswordSelect(users) {
    const sel = document.getElementById('cpUserId');
    if (!sel) return;
    sel.innerHTML = '<option value="">-- Select User --</option>';
    users.forEach(u => {
        sel.innerHTML += `<option value="${u.id}">${u.nama} (${u.nrp})</option>`;
    });
}

// Open Change Password modal with pre-selected user from row action button
function openChangePasswordForUser(userId, userName) {
    // Set the user ID in the select dropdown
    const cpUserId = document.getElementById('cpUserId');
    if (cpUserId) {
        cpUserId.value = userId;
    }

    // Open the modal
    const modalEl = document.getElementById('modalChangePassword');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

// Open Change Password modal from toolbar button
function openChangePasswordFromToolbar() {
    // Get all checked user checkboxes
    const checkedBoxes = document.querySelectorAll('.user-check:checked');

    if (checkedBoxes.length === 0) {
        showFeedback('Select a user from the table first, or click the 🔒 button on the user row', 'warning');
        return;
    }

    if (checkedBoxes.length > 1) {
        showFeedback('Select only 1 user for password change', 'warning');
        return;
    }

    // Get the selected user ID
    const selectedUserId = checkedBoxes[0].value;

    // Find the user data to get nama
    const selectedUser = allUsers.find(u => u.id == selectedUserId);
    if (selectedUser) {
        openChangePasswordForUser(selectedUserId, selectedUser.nama);
    } else {
        showFeedback('User not found', 'danger');
    }
}

function changePassword() {
    const userId = document.getElementById('cpUserId').value;
    const newPass = document.getElementById('cpNewPassword').value;
    const confirmPass = document.getElementById('cpConfirmPassword').value;

    if (!userId) { showFeedback('Select a user first', 'warning'); return; }
    if (!newPass || newPass.length < 6) { showFeedback('Password must be at least 6 characters', 'warning'); return; }
    if (newPass !== confirmPass) { showFeedback('Passwords do not match', 'warning'); return; }

    const fd = new FormData();
    fd.append('id', userId);
    fd.append('password', newPass);

    fetch(BASE_URL + '/api/user/change_password.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status) {
                const modalEl = document.getElementById('modalChangePassword');
                if (modalEl) { const m = bootstrap.Modal.getInstance(modalEl); if (m) m.hide(); }
                showFeedback('✓ Password changed successfully', 'success');
                document.getElementById('formChangePassword').reset();
            } else {
                showFeedback(res.message || 'Failed to change password', 'danger');
            }
        })
        .catch(err => showFeedback('❌ Error: ' + err.message, 'danger'));
}

// ═══════════════════════════════════════════════════════════════
// Export CSV
function exportUsersCSV() {
    if (!allUsers || allUsers.length === 0) {
        showFeedback('No data to export', 'warning');
        return;
    }
    const header = ['No', 'Nama', 'NRP', 'Email', 'Role', 'Created'];
    const rows = allUsers.map((u, i) => [
        i + 1,
        '"' + (u.nama || '').replace(/"/g, '""') + '"',
        u.nrp,
        u.email,
        getRoleLabel(u.role),
        u.created_at || '-'
    ]);
    const csv = [header.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'users_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// Fungsi helper untuk mendapatkan badge class berdasarkan role (dari database)
function getRoleClass(role) {
    const found = rolesData.find(r => r.role === role);
    if (found && found.badge_color) {
        return 'bg-' + found.badge_color;
    }
    return 'bg-secondary';
}

// Fungsi helper untuk mendapatkan label role (dari database, no hardcode)
function getRoleLabel(role) {
    return (role || '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

// Fungsi untuk menampilkan feedback/notifikasi
function showFeedback(message, type = 'info') {
    const modal = document.getElementById('modalFeedback');
    if (modal) {
        document.getElementById('modalTitle').textContent = type === 'success' ? 'Success' : type === 'warning' ? 'Warning' : 'Error';
        document.getElementById('modalMessage').textContent = message;

        // Ubah warna modal berdasarkan tipe
        const modalContent = modal.querySelector('.modal-content');
        modalContent.className = 'modal-content';
        if (type === 'success') {
            modalContent.classList.add('border-success');
        } else if (type === 'warning') {
            modalContent.classList.add('border-warning');
        } else if (type === 'danger') {
            modalContent.classList.add('border-danger');
        }

        new bootstrap.Modal(modal).show();
    } else {
        alert(message);
    }
}
