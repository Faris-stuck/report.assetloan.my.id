/* ===============================
   LOAD DATA BARANG
================================ */
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
                alert('Total Stock tidak boleh negatif');
                return;
            }

            // Validasi: safety stock harus >= 1
            if (safetyStockVal < 1) {
                alert('Safety Stock harus minimal 1');
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
   FETCH DATA BARANG
================================ */
function loadBarang() {
    fetch(`${API_BASE_URL}/barang/get.php`)
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            return res.json();
        })
        .then(res => {
            if (!res || !res.data) throw new Error('Invalid response format: missing data field');

            const tbody = document.getElementById("tabelBarang");
            if (!tbody) throw new Error('Table element #tabelBarang not found');
            tbody.innerHTML = "";

            res.data.forEach((item, i) => {
                let statusBadge = "bg-success";
                if (item.status === "Habis") statusBadge = "bg-danger";
                else if (item.status === "Menipis") statusBadge = "bg-warning";

                tbody.innerHTML += `
                <tr>
                    <td class="text-center">
                        <input type="radio" name="pilihBarang" value="${item.id}">
                    </td>
                    <td>${i + 1}</td>
                    <td>${item.kode_barang}</td>
                    <td>${item.nama_barang}</td>
                    <td>${item.kategori || '-'}</td>
                    <td>${item.lokasi}</td>
                    <td>${item.safety_stock}</td>
                    <td>${item.stok_tersedia}</td>
                    <td>
                        <span class="badge ${item.kondisi === 'Rusak' ? 'bg-danger' : 'bg-success'}">
                            ${item.kondisi}
                        </span>
                    </td>
                    <td>
                        <span class="badge ${statusBadge}">
                            ${item.status}
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="detail-barang.html?id=${item.id}" class="btn btn-sm btn-primary">
                            Detail
                        </a>
                    </td>
                </tr>`;
            });
        })
        .catch((err) => {
            console.error('loadBarang error:', err);
            const errMsg = err?.message || 'Unknown error';
            console.log('API_BASE_URL value:', API_BASE_URL);
            alert(`Failed to load item data: ${errMsg}`);
        });
}

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
        alert('Stock tidak boleh negatif');
        return;
    }

    if (safetyVal < 1) {
        alert('Safety Stock harus minimal 1');
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
    fetch(`${API_BASE_URL}/barang/get.php`)
        .then(r => r.json())
        .then(res => {
            if (!res.status) return alert('Failed to fetch data');
            const data = res.data;
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
    fetch(`${API_BASE_URL}/barang/get.php`)
        .then(r => r.json())
        .then(res => {
            if (!res.status) return alert('Failed to fetch data');
            const data = res.data;
            let html = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%;">';
            html += '<thead><tr><th>NO</th><th>Code</th><th>Name</th><th>Location</th><th>Stock</th><th>Condition</th><th>Notes</th></tr></thead>';
            html += '<tbody>';
            data.forEach((d, i) => {
                html += `<tr><tr></tr><td>${i + 1}</td><td>${d.kode_barang}</td><td>${d.nama_barang}</td><td>${d.lokasi}</td><td>${d.stok_tersedia}</td><td>${d.kondisi}</td><td>${d.keterangan || ''}</td></tr>`;
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
