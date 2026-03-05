<?php
session_start();

$status = $_SESSION['status'] ?? '';
$Message_Pesan = $_SESSION['Message_Pesan'] ?? '';

/*  if (
    $status === 'success'
) {
    echo "<script> alert('Data Input Successful')</script>";
} elseif (
    $status === 'gagal'
) {
    echo "<script> alert('Data Input Successful')</script>";
    unset($_SESSION['status'], $_SESSION['Message_Pesan']);
}

*/

if ($status === 'success') {
    echo "<script>alert('$Message_Pesan');</script>";
} elseif ($status === 'duplicate') {
    echo "<script>alert('$Message_Pesan');</script>";
}

unset($_SESSION['status'], $_SESSION['Message_Pesan']);

?>



<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="" />
    <meta name="keyword" content="" />
    <meta name="author" content="flexilecode" />
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title>KI || Dashboard</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" href="https://webassets.komatsugroup.id/favicon.ico" type="image/x-icon">
    <!--! END: Favicon-->
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="../../assets/css/bootstrap.min.css" />
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="../../assets/vendors/css/vendors.min.css" />
    <link rel="stylesheet" type="text/css" href="../../assets/vendors/css/daterangepicker.min.css" />
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="../../assets/css/theme.min.css" />
    <link rel="stylesheet" href="../../assets/css/custom.css">
    <!--! END: Custom CSS-->
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
			<script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></scrip>
			<script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></sript>
		<![endif]-->
</head>

<body>
    <!--! ================================================================ !-->
    <!--! [Start] Navigation Manu !-->
    <!--! ================================================================ !-->
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="#" class="b-brand">
                    <!-- ========   change your logo hear   ============ -->
                    <img src="../../assets/images/logo-komatsu-putih.png" height="auto" width="200px" alt="" class="logo logo-lg" />
                    <img src="../../assets/images/komatsu-indonesia-logo.png" alt="" class="logo logo-sm" />
                </a>
            </div>
            <div class="navbar-content">
                                                <ul class="nxl-navbar">
                    <li class="nxl-item nxl-caption">
                        <label>Navigation</label>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-airplay"></i></span>
                            <span class="nxl-mtext">Dashboard</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="../../admin/dashboard.html">Grafik / Informasi</a>
                            </li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-cast"></i></span>
                            <span class="nxl-mtext">Item / Inventory</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="../../admin/barang/data-barang.php">Item Data</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="../../admin/barang/detail-barang.html">Item Detail</a>
                            </li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-send"></i></span>
                            <span class="nxl-mtext">Item Loan</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="../../admin/peminjaman/data-peminjaman.html">Request Loan</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="../../admin/peminjaman/sedang-dipinjam.html">List Loan</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="../../admin/peminjaman/admin-approval.html">Approval</a>
                            </li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-corner-down-left"></i></span>
                            <span class="nxl-mtext">Item Return</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="../../admin/pengembalian/pengembalian-barang.html">Return Loan</a>
                            </li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-shield"></i></span>
                            <span class="nxl-mtext">Administrator</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="../../admin/user/buat-user.html">User List</a>
                            </li>
                            <li class="nxl-item"><a class="nxl-link" href="../../admin/pengaturan.html">Role List</a>
                            </li>
                        </ul>
                    </li>
                </ul></div>
        </div>
    </nav>
    <!--! ================================================================ !-->
    <!--! [End]  Navigation Manu !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! [Start] Header !-->
    <!--! ================================================================ !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <!--! [Start] Header Left !-->
            <div class="header-left d-flex align-items-center gap-4">
                <!--! [Start] nxl-head-mobile-toggler !-->
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <!--! [Start] nxl-head-mobile-toggler !-->
                <!--! [Start] nxl-navigation-toggle !-->
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
                <!--! [End] nxl-navigation-toggle !-->

                <!--! [End] nxl-lavel-mega-menu !-->
            </div>
            <!--! [End] Header Left !-->
            <!--! [Start] Header Right !-->
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center">

                    <!---FULLSCREEN-->
                    <!---FULLSCREEN-->
                    <!---FULLSCREEN-->
                    <!---FULLSCREEN-->
                    <!---FULLSCREEN-->
                    <!---FULLSCREEN-->
                    <!---FULLSCREEN-->
                    <!---FULLSCREEN-->
                    <!---FULLSCREEN-->
                    <!---FULLSCREEN-->
                    <!---FULLSCREEN-->
                    <div class="nxl-h-item d-none d-sm-flex">
                        <div class="full-screen-switcher">
                            <a href="javascript:void(0);" class="nxl-head-link me-0" onclick="$('body').fullScreenHelper('toggle');">
                                <i class="feather-maximize maximize"></i>
                                <i class="feather-minimize minimize"></i>
                            </a>
                        </div>
                    </div>
                    <!---FULLSCREEN END-->
                    <!---FULLSCREEN END-->
                    <!---FULLSCREEN END-->
                    <!---FULLSCREEN END-->
                    <!---FULLSCREEN END-->
                    <!---FULLSCREEN END-->
                    <!---FULLSCREEN END-->
                    <!---FULLSCREEN END-->

                    <!--PROFILE-->
                    <!--PROFILE-->
                    <!--PROFILE-->
                    <!--PROFILE-->
                    <!--PROFILE-->
                    <!--PROFILE-->
                    <!--PROFILE-->
                    <!--PROFILE-->
                    <!--PROFILE-->
                    <!--PROFILE-->
                    <!--PROFILE-->
                    <!--PROFILE-->
                    <div class="dropdown nxl-h-item user-profile-header" data-profile-header>
                        <div class="user-profile-info" data-bs-toggle="dropdown" role="button">
                            <div class="user-name" data-user-name>Loading...</div>
                            <div class="user-email" data-user-email></div>
                        </div>
                        <div class="dropdown-menu dropdown-menu-end user-profile-dropdown">
                            <a href="javascript:void(0);" data-logout class="dropdown-item">
                                <i class="feather-log-out"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <!--! [End] Header Right !-->
        </div>
    </header>
    <!--! ================================================================ !-->
    <!--! [End] Header !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- [ page-header ] start -->
            <!-- [ page-header ] start -->
            <!-- [ page-header ] start -->
            <!-- [ page-header ] start -->
            <!-- [ page-header ] start -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Dashboard</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                        <li class="breadcrumb-item">Dashboard</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex d-md-none">
                            <a href="javascript:void(0)" class="page-header-right-close-toggle">
                                <i class="feather-arrow-left me-2"></i>
                                <span>Back</span>
                            </a>
                        </div>

                    </div>
                    <div class="d-md-none d-flex align-items-center">
                        <a href="javascript:void(0)" class="page-header-right-open-toggle">
                            <i class="feather-align-right fs-20"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- [ page-header ] end -->
            <!-- [ Main Content ] start -->
            <div class="main-content">
                <div class="row">
                    <!-- [ Page Header ] start -->

                    <!-- [ Page Header ] end -->

                    <!-- [ Data Barang Table ] start -->
                    <div class="card stretch stretch-full">
                        <!-- Toolbar -->
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="mb-0">Item Data</h3>

                            <div class="d-flex gap-2">

                                <button class="btn btn-primary" onclick="openAddModal()">
                                    Add Item
                                </button>

                                <button class="btn btn-warning" onclick="initEditButton()">
                                    Edit Item
                                </button>

                                <button class="btn btn-danger" onclick="hapusBarang()">
                                    Delete Item
                                </button>

                                <button class="btn btn-outline-secondary" onclick="exportCSV()">
                                    Export CSV
                                </button>

                                <button class="btn btn-outline-primary" onclick="printList()">
                                    Print
                                </button>

                            </div>
                        </div>


                        <!---EDIT-->
                        <!---EDIT-->
                        <!---EDIT-->
                        <!---EDIT-->
                        <!---EDIT-->
                        <!-- ================= MODAL EDIT BARANG ================= -->
                        <div class="modal fade" id="modalEditBarang" tabindex="-1">
                            <div class="modal-dialog modal-md">
                                <div class="modal-content">

                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Item</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">
                                        <!-- ID BARANG (HIDDEN) -->
                                        <input type="hidden" id="edit_id">

                                        <!-- KODE BARANG -->
                                        <div class="mb-3">
                                            <label>Item Code</label>
                                            <input type="text" id="edit_kode" class="form-control" readonly>
                                        </div>

                                        <!-- NAMA BARANG -->
                                        <div class="mb-3">
                                            <label>Item Name</label>
                                            <input type="text" id="edit_nama" class="form-control" readonly>
                                        </div>

                                        <!-- KATEGORI -->
                                        <div class="mb-3">
                                            <label>Category</label>
                                            <input type="text" id="edit_kategori" class="form-control" placeholder="e.g. Electronics, Accessories, etc.">
                                        </div>

                                        <!-- LOKASI -->
                                        <div class="mb-3">
                                            <label>Location</label>
                                            <input type="text" id="edit_lokasi" class="form-control">
                                        </div>

                                        <!-- SAFETY STOCK -->
                                        <div class="mb-3">
                                            <label>Safety Stock</label>
                                            <input type="number" id="edit_safety" class="form-control" min="1" value="1">
                                        </div>

                                        <div class="mb-3">
                                            <label>Stock</label>
                                            <input type="number" id="edit_stok" class="form-control" min="0">


                                        </div>

                                        <!-- KONDISI -->
                                        <div class="mb-3">
                                            <label>Condition</label>
                                            <select id="edit_kondisi" class="form-select">
                                                <option value="Baik">Good</option>
                                                <option value="Rusak">Damaged</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label>Notes</label>
                                            <textarea id="edit_keterangan" class="form-control" rows="2"></textarea>
                                        </div>
                                    </div>

                                    <div class="modal-footer">
                                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button class="btn btn-primary" onclick="simpanEditBarang()">Save</button>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <!-- ===================================================== -->


                        <div class="card-body table-responsive">

                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">Select</th>
                                        <th>No</th>
                                        <th>Item Code</th>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Location</th>
                                        <th>Safety Stock</th>
                                        <th>Stock</th>
                                        <th>Condition</th>
                                        <th>Status</th>
                                        <th class="text-center">Action</th>



                                    </tr>
                                </thead>

                                <tbody id="tabelBarang">


                                    <!---DATA DARI API DITARUH DISIN-->
                                    <!---DATA DARI API DITARUH DISIN-->
                                    <!---DATA DARI API DITARUH DISIN-->
                                    <!---DATA DARI API DITARUH DISIN-->
                                    <!---DATA DARI API DITARUH DISIN-->
                                    <!---DATA DARI API DITARUH DISIN-->
                                    <!---DATA DARI API DITARUH DISIN-->
                                    <!---DATA DARI API DITARUH DISIN-->

                                </tbody>

                            </table>

                        </div>
                    </div>
                    <!-- [ Data Barang Table ] end -->





                </div>



                <!--TAMBAH MODAL-->
                <!--TAMBAH MODAL-->
                <!--TAMBAH MODAL-->
                <!--TAMBAH MODAL-->
                <!--TAMBAH MODAL-->

                <div class="modal fade" id="modalTambahBarang" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form id="formTambahBarang" method="POST" action="../../api/barang/update.php">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="judulModal">Add Item</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">
                                    <input type="hidden" id="barang_id" name="id">

                                    <div class="mb-2">
                                        <label>Item Code</label>
                                        <input type="text" id="kode_barang" name="kode_barang" class="form-control" required>
                                    </div>

                                    <div class="mb-2">
                                        <label>Item Name</label>
                                        <input type="text" id="nama_barang" name="nama_barang" class="form-control" required>
                                    </div>

                                    <div class="mb-2">
                                        <label>Category</label>
                                        <input type="text" id="kategori" name="kategori" class="form-control" placeholder="e.g. Electronics, Accessories, etc.">
                                    </div>

                                    <div class="mb-2">
                                        <label>Location</label>
                                        <input type="text" id="lokasi" name="lokasi" class="form-control" required>
                                    </div>

                                    <div class="mb-2">
                                        <label>Safety Stock</label>
                                        <input type="number"
                                            id="safety_stock"
                                            name="safety_stock"
                                            class="form-control"
                                            min="1"
                                            value="1"
                                            required>
                                    </div>

                                    <div class="mb-2">
                                        <label>Total Stock</label>
                                        <input type="number" id="stok_total" name="stok_total" class="form-control" min="0" required>
                                    </div>




                                    <div class="mb-2">
                                        <label>Condition</label>
                                        <select id="kondisi" name="kondisi" class="form-control">
                                            <option value="Baik">Good</option>
                                            <option value="Rusak">Damaged</option>
                                        </select>
                                    </div>

                                        <div class="mb-2">
                                            <label>Notes</label>
                                            <textarea id="keterangan" name="keterangan" class="form-control" rows="2"></textarea>
                                        </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">Save</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>




                <!-- [ Main Content ] end -->
            </div>
            <!-- [ Footer ] start -->
            <footer class="footer">
                <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                    <span>Copyright ©</span>
                    <script>
                        document.write(new Date().getFullYear());
                    </script>
                </p>
                <p><span>By: <a target="_blank" href="https://wrapbootstrap.com/user/theme_ocean" target="_blank">theme_ocean</a></span> • <span>Distributed by: <a target="_blank" href="https://themewagon.com" target="_blank">ThemeWagon</a></span></p>
                <div class="d-flex align-items-center gap-4">
                    <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                    <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                    <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
                </div>
            </footer>
            <!-- [ Footer ] end -->
    </main>
    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="../../assets/vendors/js/vendors.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <script src="../../assets/vendors/js/daterangepicker.min.js"></script>
    <script src="../../assets/vendors/js/apexcharts.min.js"></script>
    <script src="../../assets/vendors/js/circle-progress.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="../../assets/js/common-init.min.js"></script>
    <script src="../../assets/js/dashboard-init.min.js"></script>
    <!--! END: Apps Init !-->
    <!-- BASE URL DETECTION (REQUIRED) -->
    <script src="../../assets/js/base-url.js"></script>
    <!--KONEKSI KE API.JS -->
    <script src="../../assets/js/config/api.js"></script>

    <!-- WAJIB PALING BAWAH -->
    <script src="../../assets/js/barang/data-barang.js"></script>

    <!---END KONEKSI KE DATA-BARANG.JS-->
    <!-- Profile Header & Logout Scripts -->
    <script src="../../assets/js/profile-header.js"></script>
    <script src="../../assets/js/logout.js"></script>

    <!-- Modal: Duplicate Warning -->
    <div class="modal fade" id="modalDuplicateWarning" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Duplicate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="duplicateMessage">Item code or name already exists.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="duplicateOk">OK</button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>