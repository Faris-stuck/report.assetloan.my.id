//FUNCTION FORMAT DOLLAR///
function formatDollar(angka) {
    const floatValue = parseFloat(angka) || 0;
    // Ensure value is formatted with proper decimal places
    const formatted = new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(floatValue);

    // Debug log (optional - can be removed later)
    if (floatValue !== 0) {
        console.debug(`formatDollar: input=${angka}, parsed=${floatValue}, output=${formatted}`);
    }

    return formatted;
}


// Global variable to store purchase data for modal editing
let currentBarangPurchases = [];

document.addEventListener("DOMContentLoaded", function () {

    /* =====================
       AMBIL ID BARANG
    ===================== */
    const params = new URLSearchParams(window.location.search);
    const id = params.get("id");

    if (!id) {
        alert("Item ID is missing");
        window.location.href = "data-barang.php";
        return;
    }

    // hidden input WAJIB ADA
    const idBarangInput = document.getElementById("id_barang");
    if (idBarangInput) idBarangInput.value = id;

    /* =====================
       LOAD DETAIL BARANG
    ===================== */
    fetch(`${API_BASE_URL}/barang/detail.php?id=${id}`)
        .then(res => res.json())
        .then(res => {
            if (!res.status) {
                alert(res.message);
                return;
            }

            const b = res.barang;
            document.getElementById("kodeBarang").innerText = b.kode_barang;
            document.getElementById("namaBarang").innerText = b.nama_barang;
            document.getElementById("lokasiBarang").innerText = b.lokasi;

            document.querySelectorAll(".stokTotal").forEach(el => {
                el.innerText = b.stok_total;
            })

            // Update stok tersedia di kedua tempat (badge dan heading)
            document.getElementById("stokTersedia").innerText = b.stok_tersedia;

            // Update badge stok tersedia dengan styling yang sesuai
            const stokTersediaBadgeEl = document.getElementById("stokTersediaBadge");
            stokTersediaBadgeEl.innerText = b.stok_tersedia;
            stokTersediaBadgeEl.className = "badge " +
                (res.status_barang === "Habis"
                    ? "bg-danger"
                    : res.status_barang === "Menipis"
                        ? "bg-warning"
                        : "bg-success");
            document.getElementById("statusBarang").innerText = b.status_Barang;

            // KONDISI
            const kondisiEl = document.getElementById("kondisiBarang");
            kondisiEl.innerText = b.kondisi;
            kondisiEl.className =
                "badge " + (b.kondisi === "Rusak" ? "bg-danger" : "bg-success");

            // STOK RUSAK (if field not present, default 0)
            const stokRusakEl = document.getElementById('stokRusak');
            if (stokRusakEl) stokRusakEl.innerText = (typeof b.stok_rusak !== 'undefined') ? b.stok_rusak : 0;

            // KETERANGAN
            const ketEl = document.getElementById('keteranganBarang');
            if (ketEl) ketEl.innerText = b.keterangan || '-';

            // STATUS
            const statusEl = document.getElementById("statusBarang");
            statusEl.innerText = res.status_barang;
            statusEl.className =
                "badge " +
                (res.status_barang === "Habis"
                    ? "bg-danger"
                    : res.status_barang === "Menipis"
                        ? "bg-warning"
                        : "bg-success");
        })
    // .catch(err => {
    //     console.log(err);
    //     alert("Gagal mengambil detail barang");
    // });


    ///MEMANGGIL FUNCTION RIWAYAT PEMBELIAN VENDOR///
    loadRiwayatPembelian(id);

    ///MEMANGGIL FUNCTION DAFTAR PEMINJAM///
    loadDaftarPeminjam(id);


    /* =====================
       LOAD VENDOR
    ===================== */
    fetch(`${API_BASE_URL}/vendor/get.php`)
        .then(res => res.json())
        .then(res => {

            const vendorSelect = document.getElementById("vendor");
            const vendorBaru = document.getElementById("vendorBaru");
            const alamatVendor = document.getElementById("alamat_vendor");
            const kontakVendor = document.getElementById("kontak_vendor");

            vendorSelect.innerHTML = `
                <option value="">Select Vendor</option>
                <option value="baru">+ Add New Vendor</option>
            `;

            res.data.forEach(v => {
                vendorSelect.innerHTML += `
                    <option value="${v.id}">${v.nama_vendor}</option>
                `;
            });

            // default hidden
            vendorBaru.style.display = "none";
            alamatVendor.style.display = "none";
            kontakVendor.style.display = "none";

            vendorSelect.addEventListener("change", function () {
                if (this.value === "baru") {
                    vendorBaru.style.display = "block";
                    alamatVendor.style.display = "block";
                    kontakVendor.style.display = "block";

                    vendorBaru.required = true;
                    alamatVendor.required = true;
                    kontakVendor.required = true;
                } else {
                    vendorBaru.style.display = "none";
                    alamatVendor.style.display = "none";
                    kontakVendor.style.display = "none";

                    vendorBaru.required = false;
                    alamatVendor.required = false;
                    kontakVendor.required = false;

                    vendorBaru.value = "";
                    alamatVendor.value = "";
                    kontakVendor.value = "";
                }
            });
        })
        .catch(err => {
            console.error(err);
            alert("Failed to load vendors");
        });

    /* =====================
       DATE VALIDATION FOR PURCHASE
    ===================== */
    // Function to set minimum date untuk tanggal pembelian = hari ini
    function setMinDateForPurchase() {
        const today = new Date().toISOString().split('T')[0];
        const tanggalPembelian = document.getElementById('tanggal_pembelian');
        if (tanggalPembelian) {
            tanggalPembelian.setAttribute('min', today);
            tanggalPembelian.value = ''; // Clear field when modal opens
        }
    }

    // Function to validate date input - prevents selecting past dates
    function validatePurchaseDate() {
        const today = new Date().toISOString().split('T')[0];
        const tanggalPembelian = document.getElementById('tanggal_pembelian');
        if (tanggalPembelian && tanggalPembelian.value < today) {
            tanggalPembelian.value = '';
            alert('Purchase date cannot be earlier than today (' + today + ')');
        }
    }

    // Set min date on page load
    (function initDateValidation() {
        setMinDateForPurchase();

        const tanggalPembelian = document.getElementById('tanggal_pembelian');
        if (tanggalPembelian) {
            // Validate when date changes
            tanggalPembelian.addEventListener('change', validatePurchaseDate);
        }

        // Also set min date whenever modal is shown
        const modalPembelian = document.getElementById('modalPembelian');
        if (modalPembelian) {
            modalPembelian.addEventListener('show.bs.modal', function () {
                setMinDateForPurchase();
            });
        }

        // Also listen for modal button clicks
        const modalButtons = document.querySelectorAll('[data-bs-target="#modalPembelian"]');
        modalButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                setTimeout(() => {
                    setMinDateForPurchase();
                }, 100);
            });
        });
    })();

    /* =====================
       SUBMIT PEMBELIAN
    ===================== */
    document.getElementById("formPembelian")
        .addEventListener("submit", function (e) {
            e.preventDefault();

            // Validasi: tanggal pembelian tidak boleh kurang dari hari ini
            const today = new Date().toISOString().split('T')[0];
            const tanggalPembelian = document.getElementById("tanggal_pembelian").value;

            if (tanggalPembelian < today) {
                alert("Purchase date cannot be earlier than today (" + today + ")");
                return;
            }

            // Validasi: jumlah harus positif
            const jumlah = parseInt(document.getElementById("jumlah").value) || 0;
            if (jumlah <= 0) {
                alert('Purchase quantity must be greater than 0');
                return;
            }

            const vendor = document.getElementById("vendor").value;
            if (!vendor) {
                alert('Please select or add a vendor first');
                return;
            }

            const data = new FormData();
            data.append("id_barang", id);
            data.append("vendor", vendor);
            data.append("vendor_baru", document.getElementById("vendorBaru").value);
            data.append("alamat", document.getElementById("alamat_vendor").value);
            data.append("kontak", document.getElementById("kontak_vendor").value);
            data.append("tanggal_pembelian", document.getElementById("tanggal_pembelian").value);
            data.append("jumlah", jumlah);
            data.append("harga_satuan", document.getElementById("harga_satuan").value);
            data.append("keterangan", document.getElementById("keterangan").value);

            fetch(`${API_BASE_URL}/barang/beli.php`, {
                method: "POST",
                body: data,
                credentials: 'include'  // Send session cookies for authentication
            })
                .then(res => res.json())
                .then(res => {
                    alert(res.message);
                    if (res.status) {
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Failed to save purchase");
                });
        });

    /* =====================
          LOAD RIWAYAT PEMBELIAN
       ===================== */
    function loadRiwayatPembelian(id) {
        fetch(`${API_BASE_URL}/barang/pembelian/get.php?id_barang=${id}`)
            .then(res => res.json())
            .then(res => {
                const tbody = document.getElementById("historyPembelian");
                tbody.innerHTML = "";

                if (!res.status || res.data.length === 0) {
                    tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            No purchase history yet
                        </td>
                    </tr>`;
                    return;
                }

                // Store purchase data for modal lookups
                currentBarangPurchases = res.data;

                res.data.forEach(p => {
                    const hargaSatuan = parseFloat(p.harga_satuan);
                    const total = parseFloat(p.total);

                    tbody.innerHTML += `
                    <tr>
                        <td>${p.tanggal_pembelian}</td>
                        <td>${p.nama_vendor}</td>
                        <td>${p.jumlah}</td>
                        <td>${formatDollar(hargaSatuan)}</td>
                        <td><strong>${formatDollar(total)}</strong></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning" onclick="editPembelian(${p.id})">
                                EDIT
                            </button>
                        </td>
                    </tr>
                `;
                });
            });
    }

    /* =====================
          LOAD DAFTAR PEMINJAM
       ===================== */
    function loadDaftarPeminjam(id_barang) {
        fetch(`${API_BASE_URL}/barang/peminjam.php?id_barang=${id_barang}`)
            .then(res => res.json())
            .then(res => {
                const tbody = document.getElementById("tabelPeminjam");
                const stokDipinjamEl = document.getElementById("stokDipinjam");

                tbody.innerHTML = "";
                let totalDipinjam = 0;

                if (!res.status || res.data.length === 0) {
                    tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted">
                            No one has borrowed this item yet
                        </td>
                    </tr>`;
                    if (stokDipinjamEl) stokDipinjamEl.innerText = "0";
                    return;
                }

                res.data.forEach((p, index) => {
                    // Hitung total yang sedang dipinjam (status aktif)
                    if (p.status === 'Sedang Dipinjam' || p.status.startsWith('Due') || p.status === 'Overdue') {
                        totalDipinjam += p.jumlah;
                    }

                    let statusBadge = "bg-secondary";
                    if (p.status === "Overdue" || p.status === "Due Today") statusBadge = "bg-danger";
                    else if (p.status.startsWith("Due")) statusBadge = "bg-warning text-dark";
                    else if (p.status === "Menunggu Persetujuan") statusBadge = "bg-warning";
                    else if (p.status === "Disetujui") statusBadge = "bg-info";
                    else if (p.status === "Sedang Dipinjam") statusBadge = "bg-primary";
                    else if (p.status === "Sebagian Dikembalikan") statusBadge = "bg-warning text-dark";
                    else if (p.status === "Proses Return") statusBadge = "bg-info text-dark";
                    else if (p.status === "Dikembalikan") statusBadge = "bg-success";
                    else if (p.status === "Ditolak") statusBadge = "bg-danger";

                    tbody.innerHTML += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${p.nama_peminjam}</td>
                        <td>${p.nrp}</td>
                        <td>${p.jumlah}</td>
                        <td>${p.tanggal_pinjam}</td>
                        <td>${p.expected_return_nearest || p.rencana_kembali}</td>
                        <td><span class="badge ${statusBadge}">${p.status}</span></td>
                        <td>${p.kondisi_pinjam}</td>
                    </tr>
                `;
                });

                // Update jumlah yang sedang dipinjam
                if (stokDipinjamEl) stokDipinjamEl.innerText = totalDipinjam;
            })
            .catch(err => {
                console.error("Error loading borrowers:", err);
                const tbody = document.getElementById("tabelPeminjam");
                tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-danger">
                        Failed to load borrower data
                    </td>
                </tr>`;
            });
    }

    // ==================== EDIT PURCHASE FUNCTIONS ====================
    // Edit purchase by ID - fetches data from stored list and opens edit modal
    window.editPembelian = function (purchaseId) {
        const purchase = currentBarangPurchases.find(p => p.id == purchaseId);
        if (!purchase) {
            alert("Purchase not found");
            return;
        }
        openEditModal(
            purchase.id,
            purchase.vendor_id,
            purchase.tanggal_pembelian,
            purchase.jumlah,
            purchase.harga_satuan,
            purchase.keterangan,
            purchase.nama_vendor  // Pass vendor name for display
        );
    };

    window.openEditModalFromData = function (jsonStr) {
        try {
            const data = JSON.parse(jsonStr);
            openEditModal(data.id, data.vendor_id, data.tanggal_pembelian, data.jumlah, data.harga_satuan, data.keterangan);
        } catch (err) {
            console.error("Error parsing purchase data:", err);
            alert("Error opening edit modal");
        }
    };

    window.openEditModal = function (id, vendor_id, tanggal, jumlah, harga, keterangan, namaVendor) {

        // Populate vendor field (readonly display) and hidden vendor_id
        document.getElementById("edit_vendor").value = namaVendor || '';
        document.getElementById("edit_vendor_id").value = vendor_id;

        // Populate form fields
        document.getElementById("edit_id_pembelian").value = id;
        document.getElementById("edit_id_barang_purchase").value = getCurrentIdBarang();
        document.getElementById("edit_tanggal_pembelian").value = tanggal;
        document.getElementById("edit_jumlah").value = jumlah;
        document.getElementById("edit_harga_satuan").value = parseFloat(harga).toFixed(2);
        document.getElementById("edit_keterangan").value = keterangan;

        // Set date min attribute (today's date or earlier if editing older record)
        const dateInput = document.getElementById("edit_tanggal_pembelian");
        // Remove min attribute to allow past dates for editing
        dateInput.removeAttribute("min");

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById("modalEditPembelian"));
        modal.show();
    };

    // Attach submit handler to edit form
    document.getElementById("formEditPembelian")?.addEventListener("submit", function (e) {
        e.preventDefault();

        const id = document.getElementById("edit_id_pembelian").value;
        const tanggal = document.getElementById("edit_tanggal_pembelian").value;
        const vendor_id = document.getElementById("edit_vendor_id").value;  // Use hidden vendor_id
        const jumlah = document.getElementById("edit_jumlah").value;
        const harga_satuan = document.getElementById("edit_harga_satuan").value;
        const keterangan = document.getElementById("edit_keterangan").value;
        const id_barang = getCurrentIdBarang();

        // Validation
        if (!tanggal || !vendor_id || !jumlah || !harga_satuan) {
            alert("Please fill in all required fields");
            return;
        }

        const formData = new FormData();
        formData.append("id", id);
        formData.append("tanggal_pembelian", tanggal);
        formData.append("vendor_id", vendor_id);
        formData.append("jumlah", jumlah);
        formData.append("harga_satuan", harga_satuan);
        formData.append("keterangan", keterangan);
        formData.append("id_barang", id_barang);

        fetch(`${API_BASE_URL}/barang/update-pembelian.php`, {
            method: "POST",
            body: formData,
            credentials: 'include'  // Send session cookies for authentication
        })
            .then(res => res.json())
            .then(res => {
                if (res.status) {
                    alert("Purchase updated successfully");
                    bootstrap.Modal.getInstance(document.getElementById("modalEditPembelian")).hide();
                    loadRiwayatPembelian(id_barang);
                } else {
                    alert("Error: " + (res.message || "Failed to update purchase"));
                }
            })
            .catch(err => {
                console.error("Error:", err);
                alert("Failed to update purchase");
            });
    })

    // Helper function to get current barang ID from page
    function getCurrentIdBarang() {
        return document.getElementById("id_barang")?.value;
    }

    // ==================== VENDOR MANAGEMENT ====================

    // Load vendors when modal opens
    const modalEditVendor = document.getElementById("modalEditVendor");
    if (modalEditVendor) {
        modalEditVendor.addEventListener("show.bs.modal", function () {
            loadVendorTable();
        });
    }

    // Load vendor table - USING api/vendor/get.php for vendor data
    function loadVendorTable() {
        const tbody = document.getElementById("vendorTableBody");
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>`;

        fetch(`${API_BASE_URL}/vendor/get.php`)
            .then(res => res.json())
            .then(res => {
                tbody.innerHTML = "";
                if (!res.status || !res.data || res.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">No vendors yet</td></tr>`;
                    return;
                }

                res.data.forEach((v, i) => {
                    tbody.innerHTML += `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${v.nama_vendor}</td>
                        <td>${v.alamat || '-'}</td>
                        <td>${v.kontak || '-'}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning" onclick="editVendor(${v.id})">
                                EDIT
                            </button>
                        </td>
                    </tr>`;
                });
            })
            .catch(err => {
                console.error("Error loading vendors:", err);
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Failed to load vendor data</td></tr>`;
            });
    }

    // Edit vendor - fetch data and show edit form
    window.editVendor = function (vendorId) {
        fetch(`${API_BASE_URL}/vendor/get.php`)
            .then(res => res.json())
            .then(res => {
                if (!res.status || !res.data) {
                    alert("Failed to load vendor data");
                    return;
                }

                // Find the specific vendor
                const vendor = res.data.find(v => v.id == vendorId);
                if (!vendor) {
                    alert("Vendor not found");
                    return;
                }

                // Populate edit form
                document.getElementById("vendorEditId").value = vendor.id;
                document.getElementById("vendorEditNama").value = vendor.nama_vendor;
                document.getElementById("vendorEditAlamat").value = vendor.alamat || '';
                document.getElementById("vendorEditKontak").value = vendor.kontak || '';
                document.getElementById("vendorFormTitle").textContent = "Edit Vendor: " + vendor.nama_vendor;
                document.getElementById("btnCancelEditVendor").style.display = "inline-block";
                document.getElementById("btnSaveVendor").textContent = 'Update Vendor';

                // Scroll to form
                const formSection = document.getElementById("vendorEditSection");
                if (formSection) {
                    formSection.scrollIntoView({ behavior: 'smooth' });
                }
            })
            .catch(err => {
                console.error("Error fetching vendor:", err);
                alert("Failed to load vendor data");
            });
    };

    // Cancel vendor edit
    window.cancelVendorEdit = function () {
        document.getElementById("vendorEditId").value = "";
        document.getElementById("vendorEditNama").value = "";
        document.getElementById("vendorEditAlamat").value = "";
        document.getElementById("vendorEditKontak").value = "";
        document.getElementById("vendorFormTitle").textContent = "Add New Vendor";
        document.getElementById("formVendorEdit").reset();
        document.getElementById("btnCancelEditVendor").style.display = "none";
        document.getElementById("btnSaveVendor").textContent = 'Save Vendor';
    };

    // Submit vendor form (add or update)
    document.getElementById("formVendorEdit")?.addEventListener("submit", function (e) {
        e.preventDefault();

        const vendorId = document.getElementById("vendorEditId").value;
        const nama = document.getElementById("vendorEditNama").value.trim();
        const alamat = document.getElementById("vendorEditAlamat").value.trim();
        const kontak = document.getElementById("vendorEditKontak").value.trim();

        if (!nama) {
            alert("Vendor name is required");
            return;
        }

        // Konfirmasi sebelum save
        if (!confirm("Are you sure the vendor data is correct?")) {
            return;
        }

        const formData = new FormData();
        formData.append("action", vendorId ? "update" : "add");
        formData.append("nama_vendor", nama);
        formData.append("alamat", alamat);
        formData.append("kontak", kontak);
        if (vendorId) formData.append("id", vendorId);

        fetch(`${API_BASE_URL}/vendor/update.php`, {
            method: "POST",
            body: formData,
            credentials: 'include'  // Send session cookies for authentication
        })
            .then(res => res.json())
            .then(res => {
                alert(res.message);
                if (res.status) {
                    cancelVendorEdit();
                    loadVendorTable();
                    // Also refresh vendor dropdowns
                    refreshVendorDropdowns();
                }
            })
            .catch(err => {
                console.error("Error:", err);
                alert("Failed to save vendor");
            });
    });

    // Refresh vendor dropdowns after vendor changes
    function refreshVendorDropdowns() {
        fetch(`${API_BASE_URL}/vendor/get.php`)
            .then(res => res.json())
            .then(res => {
                // Refresh "Add Purchase" vendor dropdown
                const vendorSelect = document.getElementById("vendor");
                if (vendorSelect && res.status && res.data) {
                    const currentVal = vendorSelect.value;
                    vendorSelect.innerHTML = `
                        <option value="">Select Vendor</option>
                        <option value="baru">+ Add New Vendor</option>
                    `;
                    res.data.forEach(v => {
                        vendorSelect.innerHTML += `<option value="${v.id}">${v.nama_vendor}</option>`;
                    });
                    vendorSelect.value = currentVal;
                }
                // Refresh "Edit Purchase" vendor dropdown
                const editVendorSelect = document.getElementById("edit_vendor");
                if (editVendorSelect && res.status && res.data) {
                    const currentVal = editVendorSelect.value;
                    editVendorSelect.innerHTML = `<option value="">Select Vendor</option>`;
                    res.data.forEach(v => {
                        editVendorSelect.innerHTML += `<option value="${v.id}">${v.nama_vendor}</option>`;
                    });
                    editVendorSelect.value = currentVal;
                }
            });
    }
});
