/* ===============================
   LOAD DATA BARANG
================================ */
function getStockStatusBadgeClass(status) {
    const normalized = String(status || '').trim().toLowerCase();

    if (normalized === 'habis' || normalized === 'out of stock') {
        return 'bg-danger';
    }

    if (normalized === 'menipis' || normalized === 'low stock') {
        return 'bg-warning text-dark';
    }

    return 'bg-success';
}

let currentPage = 1;
let perPage = 10;
let sortField = '';
let sortAsc = true;
let barangFilterTimer = null;
let latestBarangTableRequestId = 0;

function normalizeBarangFilterValue(value) {
    return String(value || '').trim();
}

function populateBarangFilterSelect(selectId, values, defaultLabel = 'All') {
    const select = document.getElementById(selectId);
    if (!select) return;

    const currentValue = select.value;
    const uniqueValues = Array.from(
        new Set(
            values
                .map(value => normalizeBarangFilterValue(value))
                .filter(Boolean)
        )
    ).sort((a, b) => a.localeCompare(b));

    select.innerHTML = `<option value="">${defaultLabel}</option>`;
    uniqueValues.forEach((value) => {
        select.innerHTML += `<option value="${value}">${value}</option>`;
    });

    if (uniqueValues.includes(currentValue)) {
        select.value = currentValue;
    }
}

function getBarangFilterValues() {
    return {
        no: document.getElementById('filterNo')?.value.trim() || '',
        itemCode: normalizeBarangFilterValue(document.getElementById('filterItemCode')?.value),
        itemName: normalizeBarangFilterValue(document.getElementById('filterItemName')?.value),
        category: normalizeBarangFilterValue(document.getElementById('filterCategory')?.value),
        location: normalizeBarangFilterValue(document.getElementById('filterLocation')?.value),
        safetyStock: document.getElementById('filterSafetyStock')?.value.trim() || '',
        condition: normalizeBarangFilterValue(document.getElementById('filterCondition')?.value),
        status: normalizeBarangFilterValue(document.getElementById('filterStatus')?.value)
    };
}

function getCurrentPerPage() {
    const perPageElement = document.getElementById('perPage');
    return parseInt(perPageElement?.value, 10) || perPage || 10;
}

function buildBarangQueryParams(options = {}) {
    const filters = getBarangFilterValues();
    const params = new URLSearchParams();
    const shouldPaginate = options.paginate !== false;

    if (filters.no) params.set('no', filters.no);
    if (filters.itemCode) params.set('item_code', filters.itemCode);
    if (filters.itemName) params.set('item_name', filters.itemName);
    if (filters.category) params.set('category', filters.category);
    if (filters.location) params.set('location', filters.location);
    if (filters.safetyStock) params.set('safety_stock', filters.safetyStock);
    if (filters.condition) params.set('condition', filters.condition);
    if (filters.status) params.set('status', filters.status);

    if (sortField) {
        params.set('sort', sortField);
        params.set('order', sortAsc ? 'asc' : 'desc');
    }

    if (shouldPaginate) {
        perPage = getCurrentPerPage();
        params.set('paginate', '1');
        params.set('page', String(currentPage));
        params.set('per_page', String(perPage));
    } else {
        params.set('paginate', '0');
    }

    return params;
}

function updateShowingInfo(from, to, total) {
    const el = document.getElementById('showingInfo');
    if (!el) return;

    if (total === 0) {
        el.textContent = 'Showing 0 entries';
        return;
    }

    el.textContent = `Showing ${from} to ${to} of ${total} entries`;
}

function updateShowingInfoFromMeta(meta) {
    if (!meta) {
        updateShowingInfo(0, 0, 0);
        return;
    }

    const from = Number(meta.from || 0);
    const to = Number(meta.to || 0);
    const total = Number(meta.filtered_records || 0);
    updateShowingInfo(from, to, total);
}

function updateSortIndicator() {
    const icon = document.getElementById('sort-nama_barang');
    if (!icon) return;

    if (sortField === 'nama_barang') {
        icon.className = `feather-${sortAsc ? 'arrow-up' : 'arrow-down'} fs-10`;
    } else {
        icon.className = 'feather-arrow-up fs-10';
    }
}

function renderPagination(totalPages) {
    const ul = document.getElementById('pagination');
    if (!ul) return;

    ul.innerHTML = '';

    if (totalPages <= 1) return;

    const liPrev = document.createElement('li');
    liPrev.className = 'page-item' + (currentPage === 1 ? ' disabled' : '');
    liPrev.innerHTML = `<a class="page-link" href="javascript:void(0)" onclick="goToPage(${currentPage - 1})">‹</a>`;
    ul.appendChild(liPrev);

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
        li.innerHTML = `<a class="page-link" href="javascript:void(0)" onclick="goToPage(${i})">${i}</a>`;
        ul.appendChild(li);
    }

    const liNext = document.createElement('li');
    liNext.className = 'page-item' + (currentPage === totalPages ? ' disabled' : '');
    liNext.innerHTML = `<a class="page-link" href="javascript:void(0)" onclick="goToPage(${currentPage + 1})">›</a>`;
    ul.appendChild(liNext);
}

function renderBarangTable(data) {
    const tbody = document.getElementById('tabelBarang');
    if (!tbody) throw new Error('Table element #tabelBarang not found');

    if (!data.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center py-4 text-muted">
                    No data found
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = data.map((item) => {
        const statusBadge = getStockStatusBadgeClass(item.status);
        const rowNo = item.row_no ?? item._rowNo ?? '';

        return `
            <tr>
                <td class="text-center">
                    <input type="radio" name="pilihBarang" value="${item.id}">
                </td>
                <td>${rowNo}</td>
                <td>${item.kode_barang}</td>
                <td>${item.nama_barang}</td>
                <td>${item.kategori || '-'}</td>
                <td>${item.lokasi}</td>
                <td>${item.safety_stock}</td>
                <td>${item.stok_tersedia}</td>
                <td>
                    <span class="badge ${item.kondisi === 'Damaged' ? 'bg-danger' : 'bg-success'}">
                        ${item.kondisi}
                    </span>
                </td>
                <td>
                    <span class="badge ${statusBadge}">
                        ${item.status}
                    </span>
                </td>
                <td class="text-center">
                    <a href="detail-barang.html?id=${item.id}" class="btn btn-sm btn-outline-primary">
                        Detail
                    </a>
                </td>
            </tr>`;
    }).join('');
}

function populateBarangFilterOptions(filterOptions) {
    if (!filterOptions) return;

    if (Array.isArray(filterOptions.condition)) {
        populateBarangFilterSelect('filterCondition', filterOptions.condition, 'All');
    }

    if (Array.isArray(filterOptions.status)) {
        populateBarangFilterSelect('filterStatus', filterOptions.status, 'All');
    }
}

function fetchBarangData(options = {}) {
    const params = buildBarangQueryParams(options).toString();
    const url = `${API_BASE_URL}/barang/get.php?${params}`;

    return fetch(url)
        .then((res) => {
            if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            return res.json();
        })
        .then((res) => {
            if (!res || !res.status || !Array.isArray(res.data)) {
                throw new Error(res?.message || 'Invalid response format');
            }

            return res;
        });
}

function loadBarang(options = {}) {
    const shouldPaginate = options.paginate !== false && !!document.getElementById('perPage');
    const requestId = ++latestBarangTableRequestId;

    return fetchBarangData({ paginate: shouldPaginate })
        .then((res) => {
            if (requestId !== latestBarangTableRequestId) {
                return res;
            }

            populateBarangFilterOptions(res.filter_options);
            updateSortIndicator();

            if (shouldPaginate) {
                currentPage = Number(res.meta?.page || currentPage || 1);
                perPage = Number(res.meta?.per_page || getCurrentPerPage());
                updateShowingInfoFromMeta(res.meta);
                renderPagination(Number(res.meta?.total_pages || 0));
            } else {
                const total = Array.isArray(res.data) ? res.data.length : 0;
                updateShowingInfo(total ? 1 : 0, total, total);
                renderPagination(0);
            }

            renderBarangTable(res.data);
            return res;
        })
        .catch((err) => {
            if (requestId !== latestBarangTableRequestId) {
                return;
            }

            console.error('loadBarang error:', err);
            const errMsg = err?.message || 'Unknown error';
            console.log('API_BASE_URL value:', API_BASE_URL);
            const tbody = document.getElementById('tabelBarang');
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger">Failed to load data: ${errMsg}</td></tr>`;
            }
            updateShowingInfo(0, 0, 0);
            renderPagination(0);
        });
}

function applyFilters(resetPage = true, useDebounce = true) {
    if (resetPage) currentPage = 1;

    clearTimeout(barangFilterTimer);

    if (useDebounce) {
        barangFilterTimer = setTimeout(() => {
            loadBarang();
        }, 300);
        return;
    }

    loadBarang();
}

function sortTable(field) {
    clearTimeout(barangFilterTimer);

    if (sortField === field) {
        sortAsc = !sortAsc;
    } else {
        sortField = field;
        sortAsc = true;
    }

    currentPage = 1;
    loadBarang();
}

function changePerPage() {
    clearTimeout(barangFilterTimer);
    currentPage = 1;
    perPage = getCurrentPerPage();
    loadBarang();
}

function goToPage(page) {
    clearTimeout(barangFilterTimer);
    const nextPage = parseInt(page, 10) || 1;
    if (nextPage < 1) return;
    currentPage = nextPage;
    loadBarang();
}

window.applyFilters = applyFilters;
window.sortTable = sortTable;
window.changePerPage = changePerPage;
window.goToPage = goToPage;

document.addEventListener("DOMContentLoaded", function () {
    loadBarang();

    // Grab commonly used DOM elements (after DOM ready)
    const modalTambahBarang = document.getElementById('modalTambahBarang');
    const modalEditBarang = document.getElementById('modalEditBarang');
    const formTambahBarang = document.getElementById('formTambahBarang');
    const kodeInput = document.getElementById('kode_barang');
    const namaInput = document.getElementById('nama_barang');
    const keteranganInput = document.getElementById('keterangan');
    const edit_keterangan = document.getElementById('edit_keterangan');

    // Debounce helper
    function debounce(fn, wait = 300) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        }
    }

    // Helper to show duplicate alert and reset modal
    function handleDuplicateFound() {
        alert('Item code or name must be unique');
        if (formTambahBarang) formTambahBarang.reset();
        if (modalTambahBarang) {
            const bs = bootstrap.Modal.getInstance(modalTambahBarang) || new bootstrap.Modal(modalTambahBarang);
            bs.hide();
        }
    }

    // Real-time duplicate checks on input/blur (debounced)
    async function checkUnique(kode, nama) {
        try {
            const url = `${API_BASE_URL}/barang/check-unique.php?kode=${encodeURIComponent(kode || '')}&nama=${encodeURIComponent(nama || '')}`;
            const res = await fetch(url);
            return await res.json();
        } catch (e) {
            console.error('check-unique error', e);
            return { kode_exists: false, nama_exists: false };
        }
    }

    if (kodeInput) {
        const onKode = debounce(async function () {
            const kode = kodeInput.value.trim();
            if (!kode) return;
            const ans = await checkUnique(kode, '');
            if (ans.kode_exists) handleDuplicateFound();
        }, 400);
        kodeInput.addEventListener('input', onKode);
        kodeInput.addEventListener('blur', onKode);
    }

    if (namaInput) {
        const onNama = debounce(async function () {
            const nama = namaInput.value.trim();
            if (!nama) return;
            const ans = await checkUnique('', nama);
            if (ans.nama_exists) handleDuplicateFound();
        }, 400);
        namaInput.addEventListener('input', onNama);
        namaInput.addEventListener('blur', onNama);
    }

    // Intercept add form submit to perform duplicate check and POST via fetch
    if (formTambahBarang) {
        formTambahBarang.addEventListener('submit', async function (e) {
            e.preventDefault();

            const kode = (kodeInput && kodeInput.value) ? kodeInput.value.trim() : '';
            const nama = (namaInput && namaInput.value) ? namaInput.value.trim() : '';
            const stokTotalVal = parseInt(document.getElementById('stok_total')?.value) || 0;
            const safetyStockVal = parseInt(document.getElementById('safety_stock')?.value) || 0;

            // Validasi: stock harus >= 0
            if (stokTotalVal < 0) {
                alert('Total Stock cannot be negative');
                return;
            }

            // Validasi: safety stock harus >= 1
            if (safetyStockVal < 1) {
                alert('Safety Stock must be at least 1');
                return;
            }

            const ans = await checkUnique(kode, nama);
            if (ans.kode_exists || ans.nama_exists) {
                handleDuplicateFound();
                return;
            }

            // Submit via fetch
            const data = new FormData(formTambahBarang);
            // Ensure keterangan is included if it exists
            if (keteranganInput) {
                data.set('keterangan', keteranganInput.value || '');
            }

            // Log the data being sent
            console.log('FormData being sent:');
            for (let [key, value] of data.entries()) {
                console.log(key + ': ' + value);
            }

            try {
                const res = await fetch(`${API_BASE_URL}/barang/update.php`, { method: 'POST', body: data });

                // Check if response is ok
                if (!res.ok) {
                    const text = await res.text();
                    console.error('HTTP Error:', res.status, res.statusText, text);
                    alert(`HTTP Error ${res.status}: ${res.statusText}\n\nResponse: ${text.substring(0, 200)}`);
                    return;
                }

                // Try to parse JSON
                let json;
                try {
                    json = await res.json();
                } catch (parseErr) {
                    const text = await res.text();
                    console.error('JSON Parse Error:', parseErr, text);
                    alert(`Error parsing server response:\n${parseErr.message}\n\nResponse: ${text.substring(0, 200)}`);
                    return;
                }

                // Build error message to display
                let displayMsg = json.message || 'An error occurred';
                if (json.sql_error) {
                    displayMsg += '\n\n[MySQL Error] ' + json.sql_error;
                }

                alert(displayMsg);
                if (json.status) location.reload();
            } catch (err) {
                console.error('Network/Fetch Error:', err);
                alert('Connection error while saving item:\n' + err.message);
            }
        });
    }

    // Export and print functions attached to window below (they use fetch/get already)

}); // End DOMContentLoaded

/* ===============================
   AMBIL ID BARANG
================================ */
function getSelectedBarangId() {
    const selected = document.querySelector('input[name="pilihBarang"]:checked');
    if (!selected) {
        alert("Please select one item first!");
        return null;
    }
    return selected.value;
}

/* ===============================
   EDIT BARANG (BUKA MODAL)
================================ */
function initEditButton() {
    // debugger;
    const id = getSelectedBarangId();
    if (!id) return;

    fetch(`${API_BASE_URL}/barang/detail.php?id=${id}`)
        .then(res => res.json())
        .then(res => {
            if (!res.status) return alert(res.message);

            const b = res.barang;

            edit_id.value = b.id;
            edit_kode.value = b.kode_barang;
            edit_nama.value = b.nama_barang;
            document.getElementById('edit_kategori').value = b.kategori || '';
            edit_lokasi.value = b.lokasi;
            edit_safety.value = b.safety_stock;
            edit_stok.value = b.stok_total;
            edit_kondisi.value = b.kondisi;

            const editKetEl = document.getElementById('edit_keterangan');
            if (editKetEl) editKetEl.value = b.keterangan || '';

            const modalEl = document.getElementById('modalEditBarang');
            if (modalEl) new bootstrap.Modal(modalEl).show();
        })
        .catch(() => alert("Failed to fetch item data"));
}

/* ===============================
   SIMPAN EDIT
================================ */
function simpanEditBarang() {
    // Validasi nilai stock sebelum menyimpan
    const stokVal = parseInt(document.getElementById('edit_stok')?.value) || 0;
    const safetyVal = parseInt(document.getElementById('edit_safety')?.value) || 0;

    if (stokVal < 0) {
        alert('Stock cannot be negative');
        return;
    }

    if (safetyVal < 1) {
        alert('Safety Stock must be at least 1');
        return;
    }

    const data = new FormData();

    data.append("id", edit_id.value);
    data.append("kode_barang", edit_kode.value);
    data.append("nama_barang", edit_nama.value);
    data.append("kategori", document.getElementById('edit_kategori').value);
    data.append("lokasi", edit_lokasi.value);
    data.append("stok_total", stokVal);
    data.append("safety_stock", safetyVal);
    data.append("kondisi", edit_kondisi.value);
    const editKetEl2 = document.getElementById('edit_keterangan');
    data.append("keterangan", editKetEl2 ? editKetEl2.value : '');

    alert('Saving item data...');
    fetch(`${API_BASE_URL}/barang/update.php`, {
        method: "POST",
        body: data
    })
        .then(res => res.json())
        .then(res => {
            let displayMsg = res.message || 'Done';
            if (res.sql_error) {
                displayMsg += '\n\n[MySQL Error] ' + res.sql_error;
            }
            alert(displayMsg);
            if (res.status) location.reload();
        })
        .catch(err => {
            console.error(err);
            alert("Update failed:\n" + err.message);
        });
}

/* ===============================
   TAMBAH BARANG
================================ */
function openAddModal() {
    // find form and modal each time to ensure they exist
    const modal = document.getElementById('modalTambahBarang');
    const form = document.getElementById('formTambahBarang');
    if (form) form.reset();
    if (modal) new bootstrap.Modal(modal).show();
}

/* ===============================
   HAPUS BARANG (AMAN FK)
================================ */
function hapusBarang() {
    const id = getSelectedBarangId();
    if (!id) return;

    if (!confirm("Are you sure you want to delete this item?")) return;

    fetch(`${API_BASE_URL}/barang/delete.php?id=${id}`)
        .then(res => res.json())
        .then(res => {
            if (res.status) {
                alert(res.message);
                location.reload();
            } else {
                alert(res.message);
            }
        })
        .catch(() => alert("Item cannot be deleted (in use)"));
}

/* ===============================
   GLOBAL (WAJIB)
================================ */
window.initEditButton = initEditButton;
window.simpanEditBarang = simpanEditBarang;
window.openAddModal = openAddModal;
window.hapusBarang = hapusBarang;
// Export CSV
function exportCSV() {
    fetchBarangData({ paginate: false })
        .then(res => {
            if (!res.status) return alert('Failed to fetch data');
            const data = res.data;
            if (!data.length) return alert('No data available to export');
            const headers = ['id', 'kode_barang', 'nama_barang', 'lokasi', 'safety_stock', 'stok_total', 'stok_tersedia', 'kondisi', 'status', 'keterangan'];
            const rows = data.map(d => headers.map(h => '"' + ((d[h] === null || d[h] === undefined) ? '' : String(d[h]).replace(/"/g, '""')) + '"').join(','));
            const csv = headers.join(',') + '\n' + rows.join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'data-barang.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        })
        .catch(() => alert('Export failed'));
}

// Print list (open new window with table)
function printList() {
    fetchBarangData({ paginate: false })
        .then(res => {
            if (!res.status) return alert('Failed to fetch data');
            const data = res.data;
            if (!data.length) return alert('No data available to print');
            let html = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%;">';
            html += '<thead><tr><th>NO</th><th>Code</th><th>Name</th><th>Location</th><th>Stock</th><th>Condition</th><th>Notes</th></tr></thead>';
            html += '<tbody>';
            data.forEach((d) => {
                const rowNo = d.row_no ?? '';
                html += `<tr><td>${rowNo}</td><td>${d.kode_barang}</td><td>${d.nama_barang}</td><td>${d.lokasi}</td><td>${d.stok_tersedia}</td><td>${d.kondisi}</td><td>${d.keterangan || ''}</td></tr>`;
            });
            html += '</tbody></table>';
            const w = window.open('', '_blank');
            w.document.write('<html><head><title>Item Data</title></head><body>');
            w.document.write(html);
            w.document.write('</body></html>');
            w.document.close();
            w.print();
        })
        .catch(() => alert('Print failed'));
}

window.exportCSV = exportCSV;
window.printList = printList;
