//FUNCTION FORMAT DOLLAR///
function formatDollar(angka) {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD"
    }).format(angka);
}


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
                d
                alert(res.message);
                return;
            }

            const b = res.barang;
            console.log(b)
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
                <option value="baru">+ Tambah Vendor Baru</option>
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
       SUBMIT PEMBELIAN
    ===================== */
    document.getElementById("formPembelian")
        .addEventListener("submit", function (e) {
            e.preventDefault();

            const data = new FormData();
            data.append("id_barang", id);
            data.append("vendor", document.getElementById("vendor").value);
            data.append("vendor_baru", document.getElementById("vendorBaru").value);
            data.append("alamat", document.getElementById("alamat_vendor").value);
            data.append("kontak", document.getElementById("kontak_vendor").value);
            data.append("tanggal_pembelian", document.getElementById("tanggal_pembelian").value);
            data.append("jumlah", document.getElementById("jumlah").value);
            data.append("harga_satuan", document.getElementById("harga_satuan").value);
            data.append("keterangan", document.getElementById("keterangan").value);

            fetch(`${API_BASE_URL}/barang/beli.php`, {
                method: "POST",
                body: data
            })
                .then(res => res.json())
                .then(res => {
                    alert(res.message);
                    if (res.status) location.reload();
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
                        <td colspan="5" class="text-center text-muted">
                            Belum ada riwayat pembelian
                        </td>
                    </tr>`;
                    return;
                }

                res.data.forEach(p => {
                    tbody.innerHTML += `
                    <tr>
                        <td>${p.tanggal_pembelian}</td>
                        <td>${p.nama_vendor}</td>
                        <td>${p.jumlah}</td>
                        <td>${formatDollar(p.harga_satuan)}</td>
                        <td><strong>${formatDollar(p.jumlah * p.harga_satuan)}</strong></td>
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
                            Belum ada yang meminjam barang ini
                        </td>
                    </tr>`;
                    if (stokDipinjamEl) stokDipinjamEl.innerText = "0";
                    return;
                }

                res.data.forEach((p, index) => {
                    // Hitung total yang sedang dipinjam (status Sedang Dipinjam)
                    if (p.status === 'Sedang Dipinjam') {
                        totalDipinjam += p.jumlah;
                    }

                    let statusBadge = "bg-secondary";
                    if (p.status === "Menunggu Persetujuan") statusBadge = "bg-warning";
                    if (p.status === "Sedang Dipinjam") statusBadge = "bg-primary";
                    if (p.status === "Dikembalikan") statusBadge = "bg-success";
                    if (p.status === "Ditolak") statusBadge = "bg-danger";

                    tbody.innerHTML += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${p.nama_peminjam}</td>
                        <td>${p.nrp}</td>
                        <td>${p.jumlah}</td>
                        <td>${p.tanggal_pinjam}</td>
                        <td>${p.rencana_kembali}</td>
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
                        Gagal memuat data peminjam
                    </td>
                </tr>`;
            });
    }


});
