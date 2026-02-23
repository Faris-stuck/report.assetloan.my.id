-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Waktu pembuatan: 23 Feb 2026 pada 04.10
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `peminjaman`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `barang`
--

CREATE TABLE `barang` (
  `id` int(11) NOT NULL,
  `kode_barang` varchar(30) NOT NULL,
  `nama_barang` varchar(150) NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `lokasi` varchar(100) DEFAULT NULL,
  `stok_total` int(11) NOT NULL DEFAULT 0,
  `stok_tersedia` int(11) NOT NULL DEFAULT 0,
  `safety_stock` int(11) NOT NULL DEFAULT 1,
  `kondisi` enum('Baik','Rusak') DEFAULT 'Baik',
  `keterangan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `stok_rusak` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `barang`
--

INSERT INTO `barang` (`id`, `kode_barang`, `nama_barang`, `kategori`, `lokasi`, `stok_total`, `stok_tersedia`, `safety_stock`, `kondisi`, `keterangan`, `created_at`, `stok_rusak`) VALUES
(144, 'ADC-LAP-01', 'Laptop Lenovo Thinkpad', 'Laptop', 'ICT - MAIN OFFICE', 65, 60, 1, 'Baik', '', '2026-02-13 08:09:53', 1),
(145, 'ADC-KEYB-01', 'Keyboard Fantech', 'Keyboard', 'ICT - MAIN OFFICE', 11, 11, 5, 'Baik', 'Keperluan Keyboard', '2026-02-13 08:24:52', 0),
(146, 'ADC-LAP-02', 'Laptop Lenovo Ideapad Slim 3', 'Laptop', 'ICT - MAIN OFFICE', 9, 9, 1, 'Baik', '', '2026-02-13 15:09:29', 0),
(147, 'ADC-LAP-03', 'Laptop Lenovo IDeapad Slim 5', 'Laptop', 'ICT - MAIN OFFICE', 9, 9, 1, 'Baik', '', '2026-02-13 15:10:01', 0),
(148, 'ADC-LAP-04', 'Macbook', 'Laptop', 'ICT - MAIN OFFICE', 9, 9, 1, 'Baik', '', '2026-02-13 15:10:32', 0),
(149, 'ADC-MOUSE-01', 'Mouse Logitech', 'Mouse', 'ICT - MAIN OFFICE', 2, 2, 1, 'Baik', 'Keperluan Kerja', '2026-02-18 06:34:29', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_peminjaman`
--

CREATE TABLE `detail_peminjaman` (
  `id` int(11) NOT NULL,
  `peminjaman_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `lokasi` varchar(100) NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `kondisi_pinjam` enum('Baik','Rusak') DEFAULT 'Baik',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_peminjaman`
--

INSERT INTO `detail_peminjaman` (`id`, `peminjaman_id`, `barang_id`, `lokasi`, `jumlah`, `kondisi_pinjam`, `created_at`) VALUES
(97, 71, 144, '', 15, 'Baik', '2026-02-13 10:35:56'),
(98, 72, 144, '', 10, 'Baik', '2026-02-13 11:13:16'),
(99, 73, 145, '', 5, 'Baik', '2026-02-13 14:19:59'),
(100, 74, 144, '', 2, 'Baik', '2026-02-13 14:43:13'),
(101, 75, 145, '', 3, 'Baik', '2026-02-13 15:00:58'),
(102, 76, 144, '', 4, 'Baik', '2026-02-13 15:01:15'),
(103, 77, 144, '', 5, 'Baik', '2026-02-13 15:01:32'),
(104, 78, 144, '', 2, 'Baik', '2026-02-18 07:50:34'),
(105, 79, 144, '', 7, 'Baik', '2026-02-18 10:31:06'),
(106, 80, 144, '', 1, 'Baik', '2026-02-19 07:22:10'),
(107, 80, 145, '', 1, 'Baik', '2026-02-19 07:22:10'),
(108, 80, 146, '', 1, 'Baik', '2026-02-19 07:22:10'),
(109, 80, 147, '', 1, 'Baik', '2026-02-19 07:22:10'),
(110, 80, 148, '', 1, 'Baik', '2026-02-19 07:22:10'),
(111, 80, 149, '', 1, 'Baik', '2026-02-19 07:22:10'),
(112, 81, 144, '', 5, 'Baik', '2026-02-19 09:13:05'),
(113, 82, 144, '', 2, 'Baik', '2026-02-20 07:24:57'),
(114, 83, 144, '', 4, 'Baik', '2026-02-20 15:35:35'),
(115, 84, 144, '', 5, 'Baik', '2026-02-21 16:21:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_pengembalian`
--

CREATE TABLE `detail_pengembalian` (
  `id` int(11) UNSIGNED NOT NULL,
  `pengembalian_id` int(11) UNSIGNED NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah_kembali` int(11) NOT NULL DEFAULT 1,
  `kondisi_kembali` enum('Baik','Rusak') NOT NULL DEFAULT 'Baik',
  `jumlah_rusak` int(11) NOT NULL DEFAULT 0,
  `sisa_dikembalikan` int(11) NOT NULL DEFAULT 0,
  `biaya_ganti_rugi` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_pengembalian`
--

INSERT INTO `detail_pengembalian` (`id`, `pengembalian_id`, `barang_id`, `jumlah_kembali`, `kondisi_kembali`, `jumlah_rusak`, `sisa_dikembalikan`, `biaya_ganti_rugi`, `catatan`, `created_at`) VALUES
(13, 10, 144, 15, 'Baik', 0, 0, 0.00, '', '2026-02-13 10:41:43'),
(14, 11, 144, 10, 'Baik', 0, 0, 0.00, '', '2026-02-13 14:07:43'),
(15, 12, 145, 5, 'Baik', 0, 0, 0.00, '', '2026-02-18 07:51:32'),
(16, 13, 144, 2, 'Baik', 0, 0, 0.00, '', '2026-02-18 14:41:55'),
(17, 14, 145, 3, 'Baik', 0, 0, 0.00, '', '2026-02-18 15:33:51'),
(18, 15, 144, 2, 'Baik', 0, 0, 0.00, '', '2026-02-19 07:32:25'),
(19, 16, 144, 4, 'Baik', 0, 0, 0.00, '', '2026-02-19 08:41:16'),
(20, 17, 144, 7, 'Baik', 0, 0, 0.00, '', '2026-02-19 09:06:31'),
(21, 18, 144, 2, 'Baik', 0, 0, 0.00, '', '2026-02-19 09:42:15'),
(22, 19, 144, 1, 'Baik', 0, 0, 0.00, '', '2026-02-20 08:32:24'),
(23, 20, 144, 2, 'Rusak', 1, 0, 0.00, '', '2026-02-20 08:38:10'),
(24, 23, 144, 1, 'Baik', 0, 0, 0.00, '', '2026-02-20 08:54:18'),
(25, 24, 144, 1, 'Baik', 0, 0, 0.00, '', '2026-02-20 12:56:41'),
(26, 25, 144, 2, 'Baik', 0, 3, 0.00, '', '2026-02-21 17:32:43'),
(27, 26, 144, 2, 'Baik', 0, 1, 0.00, '', '2026-02-23 08:46:47'),
(28, 27, 144, 1, 'Baik', 0, 0, 0.00, '', '2026-02-23 08:47:40'),
(29, 28, 144, 1, 'Baik', 0, 0, 0.00, '', '2026-02-23 09:17:17');

-- --------------------------------------------------------

--
-- Struktur dari tabel `extend_peminjaman`
--

CREATE TABLE `extend_peminjaman` (
  `id` int(11) NOT NULL,
  `peminjaman_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tanggal_kembali_sekarang` date NOT NULL,
  `tanggal_perpanjang` date NOT NULL,
  `alasan` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `extend_peminjaman`
--

INSERT INTO `extend_peminjaman` (`id`, `peminjaman_id`, `user_id`, `tanggal_kembali_sekarang`, `tanggal_perpanjang`, `alasan`, `status`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 83, 1004, '2026-02-20', '2026-02-25', 'TES EXTEND', 'Approved', 1, '2026-02-23 03:47:24', '2026-02-23 09:42:06');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembelian_barang`
--

CREATE TABLE `pembelian_barang` (
  `id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `tanggal_pembelian` date NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` float(15,2) NOT NULL,
  `total_harga` float(15,2) GENERATED ALWAYS AS (`jumlah` * `harga_satuan`) STORED,
  `keterangan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembelian_barang`
--

INSERT INTO `pembelian_barang` (`id`, `barang_id`, `vendor_id`, `tanggal_pembelian`, `jumlah`, `harga_satuan`, `keterangan`, `created_at`) VALUES
(41, 144, 15, '2026-02-13', 3, 1.00, '', '2026-02-13 09:21:52'),
(42, 144, 15, '2026-02-13', 2, 3.00, '', '2026-02-13 09:23:06'),
(43, 145, 15, '2026-02-13', 2, 0.17, '', '2026-02-13 09:34:13');

-- --------------------------------------------------------

--
-- Struktur dari tabel `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id` int(11) NOT NULL,
  `kode_peminjaman` varchar(30) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nama_peminjam` varchar(255) NOT NULL,
  `nrp` varchar(50) NOT NULL,
  `lokasi_umum` varchar(255) DEFAULT NULL,
  `tanggal_pinjam` date NOT NULL,
  `rencana_kembali` date NOT NULL,
  `tanggal_disetujui` date DEFAULT NULL,
  `status` enum('Menunggu Persetujuan','Disetujui','Ditolak','Sedang Dipinjam','Dikembalikan','Proses Return') NOT NULL DEFAULT 'Menunggu Persetujuan',
  `catatan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `tanggal_kembali` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `peminjaman`
--

INSERT INTO `peminjaman` (`id`, `kode_peminjaman`, `user_id`, `nama_peminjam`, `nrp`, `lokasi_umum`, `tanggal_pinjam`, `rencana_kembali`, `tanggal_disetujui`, `status`, `catatan`, `created_at`, `tanggal_kembali`) VALUES
(71, 'PMJ-1770953756', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'ICT - MAIN OFFICE', '2026-02-13', '2026-02-16', '2026-02-13', 'Dikembalikan', 'TES SFEAFFSD', '2026-02-13 10:35:56', '2026-02-13'),
(72, 'PMJ-1770955996', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'afa', '2026-02-16', '2026-02-18', '2026-02-13', 'Dikembalikan', 'acafa', '2026-02-13 11:13:16', '2026-02-13'),
(73, 'PMJ-1770967199', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'jkhj', '2026-02-13', '2026-02-16', '2026-02-13', 'Dikembalikan', '', '2026-02-13 14:19:59', '2026-02-19'),
(74, 'PMJ-1770968593', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'asuuaafa', '2026-02-13', '2026-02-17', '2026-02-13', 'Dikembalikan', '', '2026-02-13 14:43:13', '2026-02-18'),
(75, 'PMJ-1770969658', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'seaa', '2026-02-03', '2026-02-14', '2026-02-13', 'Dikembalikan', '', '2026-02-13 15:00:58', '2026-02-19'),
(76, 'PMJ-1770969675', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'afa', '2026-02-01', '2026-02-10', '2026-02-13', 'Dikembalikan', '', '2026-02-13 15:01:15', '2026-02-19'),
(77, 'PMJ-1770969692', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'CAAS', '2026-02-13', '2026-02-13', NULL, 'Ditolak', 'srgsrgsrrgs', '2026-02-13 15:01:32', NULL),
(78, 'PMJ-1771375834', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'sf sfv', '2026-02-18', '2026-02-19', '2026-02-19', 'Dikembalikan', '', '2026-02-18 07:50:34', '2026-02-19'),
(79, 'PMJ-1771385466', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'kjhg', '2026-02-18', '2026-02-19', '2026-02-19', 'Dikembalikan', '', '2026-02-18 10:31:06', '2026-02-19'),
(80, 'PMJ-1771460530', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'JYD', '2026-02-19', '2026-02-22', NULL, 'Ditolak', '55E', '2026-02-19 07:22:10', NULL),
(81, 'PMJ-1771467185', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'sf', '2026-02-19', '2026-02-22', '2026-02-19', 'Dikembalikan', '', '2026-02-19 09:13:05', '2026-02-20'),
(82, 'PMJ-1771547097', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'Hydraulic', '2026-02-20', '2026-02-23', '2026-02-20', 'Dikembalikan', '', '2026-02-20 07:24:57', '2026-02-20'),
(83, 'PMJ-1771576535', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'ICT - MAIN OFFICE', '2026-02-20', '2026-02-25', '2026-02-21', 'Sedang Dipinjam', 'TES NOTIFIKASI PERINGATAN SEGALA KONFIRMASI', '2026-02-20 15:35:35', NULL),
(84, 'PMJ-1771665671', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'ghj', '2026-02-21', '2026-02-21', '2026-02-21', 'Dikembalikan', '', '2026-02-21 16:21:11', '2026-02-23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengembalian`
--

CREATE TABLE `pengembalian` (
  `id` int(11) UNSIGNED NOT NULL,
  `kode_pengembalian` varchar(30) NOT NULL,
  `peminjaman_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Diajukan','Dicek','Selesai','Sebagian Dikembalikan','Sebagian Rusak') DEFAULT NULL,
  `catatan_user` text DEFAULT NULL,
  `catatan_petugas` text DEFAULT NULL,
  `checked_by_role` enum('admin','pic_barang') DEFAULT NULL,
  `checked_by_user_id` int(11) DEFAULT NULL,
  `has_rusak` tinyint(1) NOT NULL DEFAULT 0,
  `total_ganti_rugi` decimal(15,2) NOT NULL DEFAULT 0.00,
  `diajukan_at` datetime NOT NULL DEFAULT current_timestamp(),
  `dicek_at` datetime DEFAULT NULL,
  `selesai_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengembalian`
--

INSERT INTO `pengembalian` (`id`, `kode_pengembalian`, `peminjaman_id`, `user_id`, `status`, `catatan_user`, `catatan_petugas`, `checked_by_role`, `checked_by_user_id`, `has_rusak`, `total_ganti_rugi`, `diajukan_at`, `dicek_at`, `selesai_at`) VALUES
(10, 'KMB-1770954103', 71, 1004, 'Selesai', '', '', 'admin', 1, 0, 0.00, '2026-02-13 10:41:43', '2026-02-13 10:51:02', '2026-02-13 10:51:02'),
(11, 'KMB-1770966463', 72, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-13 14:07:43', '2026-02-13 14:09:14', '2026-02-13 14:09:14'),
(12, 'KMB-1771375892', 73, 1004, 'Selesai', 'ht', '', 'pic_barang', 1006, 0, 0.00, '2026-02-18 07:51:32', '2026-02-19 08:42:01', '2026-02-19 08:42:01'),
(13, 'KMB-1771400515', 74, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-18 14:41:55', '2026-02-18 15:28:06', '2026-02-18 15:28:06'),
(14, 'KMB-1771403631', 75, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-18 15:33:51', '2026-02-19 08:21:21', '2026-02-19 08:21:21'),
(15, 'KMB-1771461145', 78, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-19 07:32:25', '2026-02-19 08:09:03', '2026-02-19 08:09:03'),
(16, 'KMB-1771465276', 76, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-19 08:41:16', '2026-02-19 08:57:18', '2026-02-19 08:57:18'),
(17, 'KMB-1771466791', 79, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-19 09:06:31', '2026-02-19 09:06:52', '2026-02-19 09:06:52'),
(18, 'KMB-1771468935', 81, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-19 09:42:15', '2026-02-19 09:58:23', '2026-02-19 09:58:23'),
(19, 'KMB-1771551144', 82, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-20 08:32:24', '2026-02-20 08:32:46', '2026-02-20 08:32:46'),
(20, 'KMB-1771551490', 81, 1004, 'Selesai', '', '', 'pic_barang', 1006, 1, 0.00, '2026-02-20 08:38:10', '2026-02-20 08:48:36', '2026-02-20 08:48:36'),
(23, 'KMB-1771552458', 82, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-20 08:54:18', '2026-02-20 08:54:28', '2026-02-20 08:54:28'),
(24, 'KMB-1771567001', 81, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-20 12:56:41', '2026-02-20 13:38:38', '2026-02-20 13:38:38'),
(25, 'KMB-1771669963', 84, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-21 17:32:43', '2026-02-23 08:46:16', '2026-02-23 08:46:16'),
(26, 'KMB-1771811207', 84, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-23 08:46:47', '2026-02-23 08:47:31', '2026-02-23 08:47:31'),
(27, 'KMB-1771811260', 84, 1004, 'Selesai', '', '', 'pic_barang', 1006, 0, 0.00, '2026-02-23 08:47:40', '2026-02-23 09:41:31', '2026-02-23 09:41:31'),
(28, 'KMB-1771813037', 84, 1004, 'Diajukan', '', NULL, NULL, NULL, 0, 0.00, '2026-02-23 09:17:17', NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_pembelian`
--

CREATE TABLE `riwayat_pembelian` (
  `id` int(11) NOT NULL,
  `barang_id` int(11) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `harga` int(11) DEFAULT NULL,
  `vendor` varchar(150) DEFAULT NULL,
  `jumlah` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `is_protected` tinyint(1) NOT NULL DEFAULT 0,
  `badge_color` varchar(20) NOT NULL DEFAULT 'secondary',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `deskripsi`, `is_protected`, `badge_color`, `created_at`) VALUES
(1, 'admin', 'Full access: manage users, roles, inventory, loans, approvals, returns', 1, 'danger', '2026-02-12 06:39:05'),
(2, 'manager', 'Approve/reject loan requests from users', 1, 'warning', '2026-02-12 06:39:05'),
(3, 'pic_barang', 'Manage inventory items and process item returns', 1, 'success', '2026-02-12 06:39:05'),
(4, 'user', 'Submit loan requests and view own borrowing history', 1, 'info', '2026-02-12 06:39:05'),
(5, 'teknisi', 'Mengedit barang', 0, 'secondary', '2026-02-12 06:43:49'),
(6, 'operator', 'Mengatur Kendaraan', 0, 'primary', '2026-02-17 20:46:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `nrp` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','user','pic_barang','teknisi','operator') NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `nama`, `nrp`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Admin Sistem', '100001', 'admin@komatsu.co.id', '$2y$10$yg3fxFMHXHDHCq8R3n/nHeySTbLYaJBZJKQco7wDlAoOr0gGOn/82', 'admin', '2026-01-27 15:59:06'),
(1004, 'Muhammad Faris Azmiarif', '1323241224', 'azmiariffaris@gmail.com', '$2y$10$BSPP9V.syKlR5ROdu5bzsujnUnK9cvinSN/peBnIhl8a.kK4tVNIy', 'user', '2026-02-12 16:26:27'),
(1005, 'manager', '321231221', 'manager@komatsu.co.id', '$2y$10$HoURA5hmsEtade..w34S.uSj4RuXH3Uu/I2zi9MwYFWWbelHfekRe', 'manager', '2026-02-12 16:28:00'),
(1006, 'Pic Barang', '323231333213', 'picbarang@komatsu.co.id', '$2y$10$4KZjYvtDFZRuzK/7DWtht.MhjsSEbMARrm82AOn2s9YnBaMiat6C.', 'pic_barang', '2026-02-13 10:52:26'),
(1010, 'sashf', '23232', 'a11@gmail.com', '$2y$10$KEAMczrrpIwLbq/8sDq.FO66UVrjKACM9P/FeVM4hOgc1i6/neBwC', 'admin', '2026-02-13 14:38:30'),
(1011, 'ddvsds', '22332', 'a1@gmail.com', '$2y$10$e7JXqfNMUXRqvbpC2wA5A..jH2T83wtEfXzNfgAcf0vFU/Jd3XKFq', 'admin', '2026-02-13 14:38:54'),
(1012, 'asafa', '2323132', 'a2@gmail.com', '$2y$10$19CwDWkgTSvA/B2MUYbseuIE3kTqmftS2kQoDy91HKFQHKOrPEBZC', 'admin', '2026-02-13 14:39:15'),
(1013, 'afsdfsvs', '13112', 'a3@gmail.com', '$2y$10$5Ayl0AGHXdohwE8iPZ0yvesNNVCg.oOjYUBjTryuDMdjlZmBr12W2', 'pic_barang', '2026-02-13 14:39:47'),
(1014, 'advsdgss', '2424', 'a1221@gmail.com', '$2y$10$iyCiHSkIAsHDmz3svlAbj.hiReztaF4zcaAm7xW/8a8zQ0/Bq9ujO', 'user', '2026-02-13 14:40:07'),
(1015, 'advdvs', '28y2846274', 'aaiigi@gmail.com', '$2y$10$r/3.zpk0yU1L5.ZIoeBC6.nrSQ7KYZ0jlyLvsOPg8fHNfEC0.iV/2', 'admin', '2026-02-13 14:41:17'),
(1016, 'aaue', '2112', 'ds@gmais.com', '$2y$10$r7TzaIymk0YuJQ77g3iSEutqDyhUUCFRgMw2QluMgFTku1n2asBke', 'operator', '2026-02-18 03:47:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `vendor`
--

CREATE TABLE `vendor` (
  `id` int(11) NOT NULL,
  `nama_vendor` varchar(150) NOT NULL,
  `alamat` text DEFAULT NULL,
  `kontak` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `vendor`
--

INSERT INTO `vendor` (`id`, `nama_vendor`, `alamat`, `kontak`, `created_at`) VALUES
(15, 'PT Kemas Indah Maju Kim ', NULL, NULL, '2026-02-13 08:24:03');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `barang`
--
ALTER TABLE `barang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_barang` (`kode_barang`),
  ADD UNIQUE KEY `unique_nama` (`nama_barang`);

--
-- Indeks untuk tabel `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `peminjaman_id` (`peminjaman_id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indeks untuk tabel `detail_pengembalian`
--
ALTER TABLE `detail_pengembalian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_detail_pengembalian_pengembalian` (`pengembalian_id`),
  ADD KEY `idx_detail_pengembalian_barang` (`barang_id`);

--
-- Indeks untuk tabel `extend_peminjaman`
--
ALTER TABLE `extend_peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `peminjaman_id` (`peminjaman_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indeks untuk tabel `pembelian_barang`
--
ALTER TABLE `pembelian_barang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indeks untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_peminjaman` (`kode_peminjaman`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pengembalian` (`kode_pengembalian`),
  ADD KEY `fk_pengembalian_peminjaman` (`peminjaman_id`),
  ADD KEY `fk_pengembalian_user` (`user_id`);

--
-- Indeks untuk tabel `riwayat_pembelian`
--
ALTER TABLE `riwayat_pembelian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indeks untuk tabel `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nrp` (`nrp`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `vendor`
--
ALTER TABLE `vendor`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_vendor` (`nama_vendor`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `barang`
--
ALTER TABLE `barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT untuk tabel `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT untuk tabel `detail_pengembalian`
--
ALTER TABLE `detail_pengembalian`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT untuk tabel `extend_peminjaman`
--
ALTER TABLE `extend_peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pembelian_barang`
--
ALTER TABLE `pembelian_barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT untuk tabel `riwayat_pembelian`
--
ALTER TABLE `riwayat_pembelian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1017;

--
-- AUTO_INCREMENT untuk tabel `vendor`
--
ALTER TABLE `vendor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  ADD CONSTRAINT `detail_peminjaman_ibfk_1` FOREIGN KEY (`peminjaman_id`) REFERENCES `peminjaman` (`id`),
  ADD CONSTRAINT `detail_peminjaman_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`);

--
-- Ketidakleluasaan untuk tabel `detail_pengembalian`
--
ALTER TABLE `detail_pengembalian`
  ADD CONSTRAINT `fk_detail_pengembalian_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_detail_pengembalian_pengembalian` FOREIGN KEY (`pengembalian_id`) REFERENCES `pengembalian` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `extend_peminjaman`
--
ALTER TABLE `extend_peminjaman`
  ADD CONSTRAINT `extend_peminjaman_ibfk_1` FOREIGN KEY (`peminjaman_id`) REFERENCES `peminjaman` (`id`),
  ADD CONSTRAINT `extend_peminjaman_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `extend_peminjaman_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `pembelian_barang`
--
ALTER TABLE `pembelian_barang`
  ADD CONSTRAINT `pembelian_barang_ibfk_1` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`),
  ADD CONSTRAINT `pembelian_barang_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`id`);

--
-- Ketidakleluasaan untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD CONSTRAINT `fk_pengembalian_peminjaman` FOREIGN KEY (`peminjaman_id`) REFERENCES `peminjaman` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pengembalian_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `riwayat_pembelian`
--
ALTER TABLE `riwayat_pembelian`
  ADD CONSTRAINT `riwayat_pembelian_ibfk_1` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
