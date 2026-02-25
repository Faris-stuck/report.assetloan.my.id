-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Waktu pembuatan: 25 Feb 2026 pada 10.59
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
(144, 'ADC-LAP-01', 'Laptop Lenovo Thinkpad', 'Laptop', 'ICT - MAIN OFFICE', 65, 50, 1, 'Baik', '', '2026-02-13 08:09:53', 3),
(145, 'ADC-KEYB-01', 'Keyboard Fantech', 'Keyboard', 'ICT - MAIN OFFICE', 11, 6, 5, 'Baik', 'Keperluan Keyboard', '2026-02-13 08:24:52', 0),
(146, 'ADC-LAP-02', 'Laptop Lenovo Ideapad Slim 3', 'Laptop', 'ICT - MAIN OFFICE', 9, 7, 1, 'Baik', '', '2026-02-13 15:09:29', 1),
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
  `expected_return` date DEFAULT NULL,
  `kondisi_pinjam` enum('Baik','Rusak') DEFAULT 'Baik',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_peminjaman`
--

INSERT INTO `detail_peminjaman` (`id`, `peminjaman_id`, `barang_id`, `lokasi`, `jumlah`, `expected_return`, `kondisi_pinjam`, `created_at`) VALUES
(97, 71, 144, '', 15, '2026-02-16', 'Baik', '2026-02-13 10:35:56'),
(98, 72, 144, '', 10, '2026-02-18', 'Baik', '2026-02-13 11:13:16'),
(99, 73, 145, '', 5, '2026-02-16', 'Baik', '2026-02-13 14:19:59'),
(100, 74, 144, '', 2, '2026-02-17', 'Baik', '2026-02-13 14:43:13'),
(101, 75, 145, '', 3, '2026-02-14', 'Baik', '2026-02-13 15:00:58'),
(102, 76, 144, '', 4, '2026-02-10', 'Baik', '2026-02-13 15:01:15'),
(103, 77, 144, '', 5, '2026-02-13', 'Baik', '2026-02-13 15:01:32'),
(104, 78, 144, '', 2, '2026-02-19', 'Baik', '2026-02-18 07:50:34'),
(105, 79, 144, '', 7, '2026-02-19', 'Baik', '2026-02-18 10:31:06'),
(106, 80, 144, '', 1, '2026-02-22', 'Baik', '2026-02-19 07:22:10'),
(107, 80, 145, '', 1, '2026-02-22', 'Baik', '2026-02-19 07:22:10'),
(108, 80, 146, '', 1, '2026-02-22', 'Baik', '2026-02-19 07:22:10'),
(109, 80, 147, '', 1, '2026-02-22', 'Baik', '2026-02-19 07:22:10'),
(110, 80, 148, '', 1, '2026-02-22', 'Baik', '2026-02-19 07:22:10'),
(111, 80, 149, '', 1, '2026-02-22', 'Baik', '2026-02-19 07:22:10'),
(112, 81, 144, '', 5, '2026-02-22', 'Baik', '2026-02-19 09:13:05'),
(113, 82, 144, '', 2, '2026-02-23', 'Baik', '2026-02-20 07:24:57'),
(114, 83, 144, '', 4, '2026-03-02', 'Baik', '2026-02-20 15:35:35'),
(115, 84, 144, '', 5, '2026-02-21', 'Baik', '2026-02-21 16:21:12'),
(116, 85, 146, 'ICT - MAIN OFFICE', 4, '2026-02-27', 'Baik', '2026-02-23 14:03:51'),
(117, 86, 144, 'ICT - MAIN OFFICE', 2, '2026-03-05', 'Baik', '2026-02-24 07:33:06'),
(118, 86, 146, 'ICT - MAIN OFFICE', 4, '2026-03-05', 'Baik', '2026-02-24 07:33:06'),
(119, 87, 144, 'ICT - MAIN OFFICE', 5, '2026-03-31', 'Baik', '2026-02-24 07:38:43'),
(120, 87, 145, 'ICT - MAIN OFFICE', 11, '2026-03-31', 'Baik', '2026-02-24 07:38:43'),
(121, 88, 144, 'ICT - MAIN OFFICE', 5, '2026-03-04', 'Baik', '2026-02-24 07:47:48'),
(122, 89, 144, 'ICT - MAIN OFFICE', 5, '2026-02-25', 'Baik', '2026-02-24 07:48:32'),
(123, 90, 144, 'ICT - MAIN OFFICE', 5, '2026-02-25', 'Baik', '2026-02-24 16:19:36'),
(126, 93, 144, 'ICT - MAIN OFFICE', 5, '2026-02-27', 'Baik', '2026-02-25 10:32:26');

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
(29, 28, 144, 1, 'Baik', 0, 0, 0.00, '', '2026-02-23 09:17:17'),
(30, 29, 144, 2, 'Rusak', 1, 2, 0.00, '', '2026-02-23 13:13:32'),
(31, 30, 146, 4, 'Baik', 0, 0, 0.00, '', '2026-02-24 07:07:21'),
(32, 31, 144, 1, 'Baik', 0, 1, 0.00, '', '2026-02-24 07:19:48'),
(33, 32, 144, 1, 'Rusak', 1, 0, 0.00, '', '2026-02-24 07:26:21'),
(34, 33, 145, 5, 'Baik', 0, 6, 0.00, '', '2026-02-24 07:39:14'),
(35, 34, 146, 2, 'Rusak', 1, 2, 0.00, '', '2026-02-24 10:22:46'),
(36, 35, 146, 1, 'Baik', 0, 1, 0.00, '', '2026-02-24 10:32:53'),
(37, 36, 145, 1, 'Baik', 0, 3, 0.00, '', '2026-02-24 13:31:35');

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
(1, 83, 1004, '2026-02-20', '2026-02-25', 'TES EXTEND', 'Approved', 1, '2026-02-23 03:47:24', '2026-02-23 09:42:06'),
(2, 85, 1004, '2026-02-24', '2026-02-26', 'tes req', 'Rejected', 1, '2026-02-23 08:21:40', '2026-02-23 14:17:13'),
(3, 85, 1004, '2026-02-24', '2026-02-27', 'tes', 'Approved', 1017, '2026-02-24 00:39:30', '2026-02-23 14:24:29'),
(4, 86, 1004, '2026-02-27', '2026-03-02', 'ijhgf', 'Approved', 1017, '2026-02-24 01:41:26', '2026-02-24 07:37:18'),
(5, 87, 1004, '2026-02-25', '2026-03-03', '\';lkjhgf', 'Approved', 1018, '2026-02-24 08:15:00', '2026-02-24 13:55:06'),
(6, 87, 1004, '2026-03-03', '2026-03-09', 'tes perpanjangan', 'Rejected', 1018, '2026-02-24 10:21:22', '2026-02-24 15:32:47'),
(7, 86, 1004, '2026-03-03', '2026-03-05', 'TS EXTEND 1', 'Approved', 1018, '2026-02-24 09:45:15', '2026-02-24 15:44:29'),
(8, 87, 1004, '2026-03-03', '2026-03-11', 'TES PERPANJANAGAN 1 BARANG SAJA', 'Approved', 1018, '2026-02-25 14:48:04', '2026-02-25 14:47:50'),
(9, 87, 1004, '2026-03-11', '2026-03-31', 'OKJHG', 'Approved', 1018, '2026-02-25 14:51:09', '2026-02-25 14:50:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `extend_peminjaman_items`
--

CREATE TABLE `extend_peminjaman_items` (
  `id` int(11) NOT NULL,
  `extend_peminjaman_id` int(11) NOT NULL,
  `detail_peminjaman_id` int(11) NOT NULL,
  `unit_number` int(11) NOT NULL COMMENT 'Unit number within the qty (1, 2, 3...)',
  `tanggal_perpanjang` date NOT NULL COMMENT 'Extended return date for this specific unit',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `extend_peminjaman_items`
--

INSERT INTO `extend_peminjaman_items` (`id`, `extend_peminjaman_id`, `detail_peminjaman_id`, `unit_number`, `tanggal_perpanjang`, `created_at`) VALUES
(1, 6, 119, 1, '2026-03-09', '2026-02-24 15:32:47'),
(2, 6, 119, 2, '2026-03-09', '2026-02-24 15:32:47'),
(3, 7, 117, 1, '2026-03-05', '2026-02-24 15:44:29'),
(4, 8, 119, 3, '2026-03-11', '2026-02-25 14:47:50'),
(5, 9, 119, 3, '2026-03-31', '2026-02-25 14:50:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `extend_peminjaman_items_backup`
--

CREATE TABLE `extend_peminjaman_items_backup` (
  `id` int(11) NOT NULL,
  `extend_peminjaman_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `qty_extend` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `extend_peminjaman_items_backup`
--

INSERT INTO `extend_peminjaman_items_backup` (`id`, `extend_peminjaman_id`, `barang_id`, `qty_extend`, `created_at`) VALUES
(1, 5, 145, 2, '2026-02-24 13:55:06'),
(2, 5, 144, 2, '2026-02-24 13:55:06');

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
  `status` varchar(50) NOT NULL DEFAULT 'Menunggu Persetujuan',
  `catatan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `tanggal_kembali` date DEFAULT NULL,
  `last_reminder_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `peminjaman`
--

INSERT INTO `peminjaman` (`id`, `kode_peminjaman`, `user_id`, `nama_peminjam`, `nrp`, `lokasi_umum`, `tanggal_pinjam`, `rencana_kembali`, `tanggal_disetujui`, `status`, `catatan`, `created_at`, `tanggal_kembali`, `last_reminder_date`) VALUES
(71, 'PMJ-1770953756', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'ICT - MAIN OFFICE', '2026-02-13', '2026-02-16', '2026-02-13', 'Dikembalikan', 'TES SFEAFFSD', '2026-02-13 10:35:56', '2026-02-13', NULL),
(72, 'PMJ-1770955996', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'afa', '2026-02-16', '2026-02-18', '2026-02-13', 'Dikembalikan', 'acafa', '2026-02-13 11:13:16', '2026-02-13', NULL),
(73, 'PMJ-1770967199', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'jkhj', '2026-02-13', '2026-02-16', '2026-02-13', 'Dikembalikan', '', '2026-02-13 14:19:59', '2026-02-19', NULL),
(74, 'PMJ-1770968593', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'asuuaafa', '2026-02-13', '2026-02-17', '2026-02-13', 'Dikembalikan', '', '2026-02-13 14:43:13', '2026-02-18', NULL),
(75, 'PMJ-1770969658', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'seaa', '2026-02-03', '2026-02-14', '2026-02-13', 'Dikembalikan', '', '2026-02-13 15:00:58', '2026-02-19', NULL),
(76, 'PMJ-1770969675', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'afa', '2026-02-01', '2026-02-10', '2026-02-13', 'Dikembalikan', '', '2026-02-13 15:01:15', '2026-02-19', NULL),
(77, 'PMJ-1770969692', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'CAAS', '2026-02-13', '2026-02-13', NULL, 'Ditolak', 'srgsrgsrrgs', '2026-02-13 15:01:32', NULL, NULL),
(78, 'PMJ-1771375834', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'sf sfv', '2026-02-18', '2026-02-19', '2026-02-19', 'Dikembalikan', '', '2026-02-18 07:50:34', '2026-02-19', NULL),
(79, 'PMJ-1771385466', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'kjhg', '2026-02-18', '2026-02-19', '2026-02-19', 'Dikembalikan', '', '2026-02-18 10:31:06', '2026-02-19', NULL),
(80, 'PMJ-1771460530', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'JYD', '2026-02-19', '2026-02-22', NULL, 'Ditolak', '55E', '2026-02-19 07:22:10', NULL, NULL),
(81, 'PMJ-1771467185', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'sf', '2026-02-19', '2026-02-22', '2026-02-19', 'Dikembalikan', '', '2026-02-19 09:13:05', '2026-02-20', NULL),
(82, 'PMJ-1771547097', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'Hydraulic', '2026-02-20', '2026-02-23', '2026-02-20', 'Dikembalikan', '', '2026-02-20 07:24:57', '2026-02-20', NULL),
(83, 'PMJ-1771576535', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'ICT - MAIN OFFICE', '2026-02-20', '2026-03-02', '2026-02-21', 'Dikembalikan', 'TES NOTIFIKASI PERINGATAN SEGALA KONFIRMASI', '2026-02-20 15:35:35', '2026-02-24', NULL),
(84, 'PMJ-1771665671', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'ghj', '2026-02-21', '2026-02-21', '2026-02-21', 'Dikembalikan', '', '2026-02-21 16:21:11', '2026-02-23', NULL),
(85, 'PMJ-1771830231', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'HYDRAULIC', '2026-02-23', '2026-02-27', '2026-02-23', 'Dikembalikan', '', '2026-02-23 14:03:51', '2026-02-24', NULL),
(86, 'PMJ-1771893186', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'ogug', '2026-02-24', '2026-03-05', '2026-02-24', 'Sebagian Dikembalikan', '', '2026-02-24 07:33:06', NULL, NULL),
(87, 'PMJ-1771893523', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'sjcd', '2026-02-24', '2026-03-31', '2026-02-24', 'Sebagian Dikembalikan', '', '2026-02-24 07:38:43', NULL, NULL),
(88, 'PMJ-1771894068', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'w', '2026-02-24', '2026-03-04', '2026-02-24', 'Due Tomorrow', '', '2026-02-24 07:47:48', NULL, '2026-02-25'),
(89, 'PMJ-1771894112', 1004, 'Muhammad Faris Azmiarif', '1323241224', '', '2026-02-24', '2026-02-25', NULL, 'Ditolak', 'weaef', '2026-02-24 07:48:32', NULL, NULL),
(90, 'PMJ-1771924776', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'jhgfd', '2026-02-24', '2026-02-25', NULL, 'Ditolak', 'acasc', '2026-02-24 16:19:36', NULL, NULL),
(93, 'PMJ-1771990346', 1004, 'Muhammad Faris Azmiarif', '1323241224', 'dzeaeesaaffa', '2026-02-25', '2026-02-27', NULL, 'Ditolak', 'tes tolak', '2026-02-25 10:32:26', NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `peminjaman_units`
--

CREATE TABLE `peminjaman_units` (
  `id` int(11) NOT NULL,
  `peminjaman_id` int(11) NOT NULL,
  `detail_peminjaman_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `unit_number` int(11) NOT NULL DEFAULT 1,
  `unit_display` varchar(50) NOT NULL DEFAULT '',
  `return_status` varchar(50) NOT NULL DEFAULT 'Belum Dikembalikan',
  `expected_return` date DEFAULT NULL,
  `kondisi_kembali` enum('Baik','Rusak') DEFAULT NULL,
  `tanggal_kembali` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `peminjaman_units`
--

INSERT INTO `peminjaman_units` (`id`, `peminjaman_id`, `detail_peminjaman_id`, `barang_id`, `unit_number`, `unit_display`, `return_status`, `expected_return`, `kondisi_kembali`, `tanggal_kembali`, `created_at`) VALUES
(1, 71, 97, 144, 1, 'Unit 1 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(2, 71, 97, 144, 2, 'Unit 2 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(3, 71, 97, 144, 3, 'Unit 3 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(4, 71, 97, 144, 4, 'Unit 4 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(5, 71, 97, 144, 5, 'Unit 5 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(6, 71, 97, 144, 6, 'Unit 6 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(7, 71, 97, 144, 7, 'Unit 7 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(8, 71, 97, 144, 8, 'Unit 8 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(9, 71, 97, 144, 9, 'Unit 9 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(10, 71, 97, 144, 10, 'Unit 10 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(11, 71, 97, 144, 11, 'Unit 11 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(12, 71, 97, 144, 12, 'Unit 12 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(13, 71, 97, 144, 13, 'Unit 13 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(14, 71, 97, 144, 14, 'Unit 14 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(15, 71, 97, 144, 15, 'Unit 15 of 15', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(16, 72, 98, 144, 1, 'Unit 1 of 10', 'Dikembalikan', '2026-02-18', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(17, 72, 98, 144, 2, 'Unit 2 of 10', 'Dikembalikan', '2026-02-18', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(18, 72, 98, 144, 3, 'Unit 3 of 10', 'Dikembalikan', '2026-02-18', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(19, 72, 98, 144, 4, 'Unit 4 of 10', 'Dikembalikan', '2026-02-18', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(20, 72, 98, 144, 5, 'Unit 5 of 10', 'Dikembalikan', '2026-02-18', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(21, 72, 98, 144, 6, 'Unit 6 of 10', 'Dikembalikan', '2026-02-18', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(22, 72, 98, 144, 7, 'Unit 7 of 10', 'Dikembalikan', '2026-02-18', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(23, 72, 98, 144, 8, 'Unit 8 of 10', 'Dikembalikan', '2026-02-18', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(24, 72, 98, 144, 9, 'Unit 9 of 10', 'Dikembalikan', '2026-02-18', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(25, 72, 98, 144, 10, 'Unit 10 of 10', 'Dikembalikan', '2026-02-18', 'Baik', '2026-02-13', '2026-02-25 16:43:34'),
(26, 73, 99, 145, 1, 'Unit 1 of 5', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(27, 73, 99, 145, 2, 'Unit 2 of 5', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(28, 73, 99, 145, 3, 'Unit 3 of 5', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(29, 73, 99, 145, 4, 'Unit 4 of 5', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(30, 73, 99, 145, 5, 'Unit 5 of 5', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(31, 74, 100, 144, 1, 'Unit 1 of 2', 'Dikembalikan', '2026-02-17', 'Baik', '2026-02-18', '2026-02-25 16:43:34'),
(32, 74, 100, 144, 2, 'Unit 2 of 2', 'Dikembalikan', '2026-02-17', 'Baik', '2026-02-18', '2026-02-25 16:43:34'),
(33, 75, 101, 145, 1, 'Unit 1 of 3', 'Dikembalikan', '2026-02-14', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(34, 75, 101, 145, 2, 'Unit 2 of 3', 'Dikembalikan', '2026-02-14', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(35, 75, 101, 145, 3, 'Unit 3 of 3', 'Dikembalikan', '2026-02-14', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(36, 76, 102, 144, 1, 'Unit 1 of 4', 'Dikembalikan', '2026-02-10', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(37, 76, 102, 144, 2, 'Unit 2 of 4', 'Dikembalikan', '2026-02-10', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(38, 76, 102, 144, 3, 'Unit 3 of 4', 'Dikembalikan', '2026-02-10', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(39, 76, 102, 144, 4, 'Unit 4 of 4', 'Dikembalikan', '2026-02-10', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(40, 77, 103, 144, 1, 'Unit 1 of 5', 'Ditolak', '2026-02-13', NULL, NULL, '2026-02-25 16:43:34'),
(41, 77, 103, 144, 2, 'Unit 2 of 5', 'Ditolak', '2026-02-13', NULL, NULL, '2026-02-25 16:43:34'),
(42, 77, 103, 144, 3, 'Unit 3 of 5', 'Ditolak', '2026-02-13', NULL, NULL, '2026-02-25 16:43:34'),
(43, 77, 103, 144, 4, 'Unit 4 of 5', 'Ditolak', '2026-02-13', NULL, NULL, '2026-02-25 16:43:34'),
(44, 77, 103, 144, 5, 'Unit 5 of 5', 'Ditolak', '2026-02-13', NULL, NULL, '2026-02-25 16:43:34'),
(45, 78, 104, 144, 1, 'Unit 1 of 2', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(46, 78, 104, 144, 2, 'Unit 2 of 2', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(47, 79, 105, 144, 1, 'Unit 1 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(48, 79, 105, 144, 2, 'Unit 2 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(49, 79, 105, 144, 3, 'Unit 3 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(50, 79, 105, 144, 4, 'Unit 4 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(51, 79, 105, 144, 5, 'Unit 5 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(52, 79, 105, 144, 6, 'Unit 6 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(53, 79, 105, 144, 7, 'Unit 7 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', '2026-02-25 16:43:34'),
(54, 80, 106, 144, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, '2026-02-25 16:43:34'),
(55, 80, 107, 145, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, '2026-02-25 16:43:34'),
(56, 80, 108, 146, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, '2026-02-25 16:43:34'),
(57, 80, 109, 147, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, '2026-02-25 16:43:34'),
(58, 80, 110, 148, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, '2026-02-25 16:43:34'),
(59, 80, 111, 149, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, '2026-02-25 16:43:34'),
(60, 81, 112, 144, 1, 'Unit 1 of 5', 'Dikembalikan', '2026-02-22', 'Baik', '2026-02-20', '2026-02-25 16:43:34'),
(61, 81, 112, 144, 2, 'Unit 2 of 5', 'Dikembalikan', '2026-02-22', 'Baik', '2026-02-20', '2026-02-25 16:43:34'),
(62, 81, 112, 144, 3, 'Unit 3 of 5', 'Dikembalikan', '2026-02-22', 'Baik', '2026-02-20', '2026-02-25 16:43:34'),
(63, 81, 112, 144, 4, 'Unit 4 of 5', 'Dikembalikan', '2026-02-22', 'Baik', '2026-02-20', '2026-02-25 16:43:34'),
(64, 81, 112, 144, 5, 'Unit 5 of 5', 'Rusak', '2026-02-22', 'Rusak', '2026-02-20', '2026-02-25 16:43:34'),
(65, 82, 113, 144, 1, 'Unit 1 of 2', 'Dikembalikan', '2026-02-23', 'Baik', '2026-02-20', '2026-02-25 16:43:34'),
(66, 82, 113, 144, 2, 'Unit 2 of 2', 'Dikembalikan', '2026-02-23', 'Baik', '2026-02-20', '2026-02-25 16:43:34'),
(67, 83, 114, 144, 1, 'Unit 1 of 4', 'Dikembalikan', '2026-02-25', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(68, 83, 114, 144, 2, 'Unit 2 of 4', 'Dikembalikan', '2026-02-25', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(69, 83, 114, 144, 3, 'Unit 3 of 4', 'Rusak', '2026-02-25', 'Rusak', '2026-02-24', '2026-02-25 16:43:34'),
(70, 83, 114, 144, 4, 'Unit 4 of 4', 'Rusak', '2026-02-25', 'Rusak', '2026-02-24', '2026-02-25 16:43:34'),
(71, 84, 115, 144, 1, 'Unit 1 of 5', 'Dikembalikan', '2026-02-21', 'Baik', '2026-02-23', '2026-02-25 16:43:34'),
(72, 84, 115, 144, 2, 'Unit 2 of 5', 'Dikembalikan', '2026-02-21', 'Baik', '2026-02-23', '2026-02-25 16:43:34'),
(73, 84, 115, 144, 3, 'Unit 3 of 5', 'Dikembalikan', '2026-02-21', 'Baik', '2026-02-23', '2026-02-25 16:43:34'),
(74, 84, 115, 144, 4, 'Unit 4 of 5', 'Dikembalikan', '2026-02-21', 'Baik', '2026-02-23', '2026-02-25 16:43:34'),
(75, 84, 115, 144, 5, 'Unit 5 of 5', 'Dikembalikan', '2026-02-21', 'Baik', '2026-02-23', '2026-02-25 16:43:34'),
(76, 85, 116, 146, 1, 'Unit 1 of 4', 'Dikembalikan', '2026-02-27', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(77, 85, 116, 146, 2, 'Unit 2 of 4', 'Dikembalikan', '2026-02-27', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(78, 85, 116, 146, 3, 'Unit 3 of 4', 'Dikembalikan', '2026-02-27', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(79, 85, 116, 146, 4, 'Unit 4 of 4', 'Dikembalikan', '2026-02-27', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(80, 86, 117, 144, 1, 'Unit 1 of 2', 'Dipinjam', '2026-03-05', NULL, NULL, '2026-02-25 16:43:34'),
(81, 86, 117, 144, 2, 'Unit 2 of 2', 'Dipinjam', '2026-03-02', NULL, NULL, '2026-02-25 16:43:34'),
(82, 86, 118, 146, 1, 'Unit 1 of 4', 'Dikembalikan', '2026-03-02', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(83, 86, 118, 146, 2, 'Unit 2 of 4', 'Dikembalikan', '2026-03-02', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(84, 86, 118, 146, 3, 'Unit 3 of 4', 'Rusak', '2026-03-02', 'Rusak', '2026-02-24', '2026-02-25 16:43:34'),
(85, 86, 118, 146, 4, 'Unit 4 of 4', 'Dipinjam', '2026-03-02', NULL, NULL, '2026-02-25 16:43:34'),
(86, 87, 119, 144, 1, 'Unit 1 of 5', 'Dipinjam', '2026-03-03', NULL, NULL, '2026-02-25 16:43:34'),
(87, 87, 119, 144, 2, 'Unit 2 of 5', 'Dipinjam', '2026-03-03', NULL, NULL, '2026-02-25 16:43:34'),
(88, 87, 119, 144, 3, 'Unit 3 of 5', 'Dipinjam', '2026-03-31', NULL, NULL, '2026-02-25 16:43:34'),
(89, 87, 119, 144, 4, 'Unit 4 of 5', 'Dipinjam', '2026-03-03', NULL, NULL, '2026-02-25 16:43:34'),
(90, 87, 119, 144, 5, 'Unit 5 of 5', 'Dipinjam', '2026-03-03', NULL, NULL, '2026-02-25 16:43:34'),
(91, 87, 120, 145, 1, 'Unit 1 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(92, 87, 120, 145, 2, 'Unit 2 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(93, 87, 120, 145, 3, 'Unit 3 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(94, 87, 120, 145, 4, 'Unit 4 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(95, 87, 120, 145, 5, 'Unit 5 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(96, 87, 120, 145, 6, 'Unit 6 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', '2026-02-25 16:43:34'),
(97, 87, 120, 145, 7, 'Unit 7 of 11', 'Dipinjam', '2026-03-03', NULL, NULL, '2026-02-25 16:43:34'),
(98, 87, 120, 145, 8, 'Unit 8 of 11', 'Dipinjam', '2026-03-03', NULL, NULL, '2026-02-25 16:43:34'),
(99, 87, 120, 145, 9, 'Unit 9 of 11', 'Dipinjam', '2026-03-03', NULL, NULL, '2026-02-25 16:43:34'),
(100, 87, 120, 145, 10, 'Unit 10 of 11', 'Dipinjam', '2026-03-03', NULL, NULL, '2026-02-25 16:43:34'),
(101, 87, 120, 145, 11, 'Unit 11 of 11', 'Dipinjam', '2026-03-03', NULL, NULL, '2026-02-25 16:43:34'),
(102, 88, 121, 144, 1, 'Unit 1 of 5', 'Belum Dikembalikan', '2026-03-04', NULL, NULL, '2026-02-25 16:43:34'),
(103, 88, 121, 144, 2, 'Unit 2 of 5', 'Belum Dikembalikan', '2026-03-04', NULL, NULL, '2026-02-25 16:43:34'),
(104, 88, 121, 144, 3, 'Unit 3 of 5', 'Belum Dikembalikan', '2026-03-04', NULL, NULL, '2026-02-25 16:43:34'),
(105, 88, 121, 144, 4, 'Unit 4 of 5', 'Belum Dikembalikan', '2026-03-04', NULL, NULL, '2026-02-25 16:43:34'),
(106, 88, 121, 144, 5, 'Unit 5 of 5', 'Belum Dikembalikan', '2026-03-04', NULL, NULL, '2026-02-25 16:43:34'),
(107, 89, 122, 144, 1, 'Unit 1 of 5', 'Ditolak', '2026-02-25', NULL, NULL, '2026-02-25 16:43:34'),
(108, 89, 122, 144, 2, 'Unit 2 of 5', 'Ditolak', '2026-02-25', NULL, NULL, '2026-02-25 16:43:34'),
(109, 89, 122, 144, 3, 'Unit 3 of 5', 'Ditolak', '2026-02-25', NULL, NULL, '2026-02-25 16:43:34'),
(110, 89, 122, 144, 4, 'Unit 4 of 5', 'Ditolak', '2026-02-25', NULL, NULL, '2026-02-25 16:43:34'),
(111, 89, 122, 144, 5, 'Unit 5 of 5', 'Ditolak', '2026-02-25', NULL, NULL, '2026-02-25 16:43:34'),
(112, 90, 123, 144, 1, 'Unit 1 of 5', 'Ditolak', '2026-02-25', NULL, NULL, '2026-02-25 16:43:34'),
(113, 90, 123, 144, 2, 'Unit 2 of 5', 'Ditolak', '2026-02-25', NULL, NULL, '2026-02-25 16:43:34'),
(114, 90, 123, 144, 3, 'Unit 3 of 5', 'Ditolak', '2026-02-25', NULL, NULL, '2026-02-25 16:43:34'),
(115, 90, 123, 144, 4, 'Unit 4 of 5', 'Ditolak', '2026-02-25', NULL, NULL, '2026-02-25 16:43:34'),
(116, 90, 123, 144, 5, 'Unit 5 of 5', 'Ditolak', '2026-02-25', NULL, NULL, '2026-02-25 16:43:34'),
(117, 93, 126, 144, 1, 'Unit 1 of 5', 'Ditolak', '2026-02-27', NULL, NULL, '2026-02-25 16:43:34'),
(118, 93, 126, 144, 2, 'Unit 2 of 5', 'Ditolak', '2026-02-27', NULL, NULL, '2026-02-25 16:43:34'),
(119, 93, 126, 144, 3, 'Unit 3 of 5', 'Ditolak', '2026-02-27', NULL, NULL, '2026-02-25 16:43:34'),
(120, 93, 126, 144, 4, 'Unit 4 of 5', 'Ditolak', '2026-02-27', NULL, NULL, '2026-02-25 16:43:34'),
(121, 93, 126, 144, 5, 'Unit 5 of 5', 'Ditolak', '2026-02-27', NULL, NULL, '2026-02-25 16:43:34');

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
(28, 'KMB-1771813037', 84, 1004, 'Diajukan', '', NULL, NULL, NULL, 0, 0.00, '2026-02-23 09:17:17', NULL, NULL),
(29, 'KMB-1771827212', 83, 1004, 'Selesai', '', '', 'admin', 1, 1, 0.00, '2026-02-23 13:13:32', '2026-02-23 13:22:32', '2026-02-23 13:22:32'),
(30, 'KMB-1771891641', 85, 1004, 'Selesai', '', '', 'admin', 1017, 0, 0.00, '2026-02-24 07:07:21', '2026-02-24 07:08:20', '2026-02-24 07:08:20'),
(31, 'KMB-1771892388', 83, 1004, 'Selesai', '', '', 'pic_barang', 1018, 0, 0.00, '2026-02-24 07:19:48', '2026-02-24 07:20:44', '2026-02-24 07:20:44'),
(32, 'KMB-1771892781', 83, 1004, 'Selesai', '', '', 'pic_barang', 1018, 1, 0.00, '2026-02-24 07:26:21', '2026-02-24 07:27:38', '2026-02-24 07:27:38'),
(33, 'KMB-1771893554', 87, 1004, 'Selesai', '', '', 'pic_barang', 1018, 0, 0.00, '2026-02-24 07:39:14', '2026-02-24 07:39:54', '2026-02-24 07:39:54'),
(34, 'KMB-1771903366', 86, 1004, 'Selesai', '', '', 'pic_barang', 1018, 1, 0.00, '2026-02-24 10:22:46', '2026-02-24 10:27:21', '2026-02-24 10:27:21'),
(35, 'KMB-1771903973', 86, 1004, 'Selesai', '', '', 'admin', 1017, 0, 0.00, '2026-02-24 10:32:53', '2026-02-24 13:29:34', '2026-02-24 13:29:34'),
(36, 'KMB-1771914695', 87, 1004, 'Selesai', '', '', 'pic_barang', 1018, 0, 0.00, '2026-02-24 13:31:35', '2026-02-24 13:34:26', '2026-02-24 13:34:26');

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
(1, 'Admin Sistem', '100001', 'admin@komatsu.co.id', '123456', 'admin', '2026-01-27 15:59:06'),
(1004, 'Muhammad Faris Azmiarif', '1323241224', 'azmiariffaris@gmail.com', '123456', 'user', '2026-02-12 16:26:27'),
(1005, 'manager', '321231221', 'manager@komatsu.co.id', '123456', 'manager', '2026-02-12 16:28:00'),
(1006, 'Pic Barang', '323231333213', 'picbarang@komatsu.co.id', '123456', 'pic_barang', '2026-02-13 10:52:26'),
(1011, 'ddvsds', '22332', 'a1@gmail.com', '123456', 'admin', '2026-02-13 14:38:54'),
(1012, 'asafa', '2323132', 'a2@gmail.com', '123456', 'admin', '2026-02-13 14:39:15'),
(1013, 'afsdfsvs', '13112', 'a3@gmail.com', '123456', 'pic_barang', '2026-02-13 14:39:47'),
(1014, 'advsdgss', '2424', 'a1221@gmail.com', '123456', 'user', '2026-02-13 14:40:07'),
(1015, 'advdvs', '28y2846274', 'aaiigi@gmail.com', '123456', 'admin', '2026-02-13 14:41:17'),
(1017, 'Arip Rosita', '2324241324', 'ariprosita1@gmail.com', '123456', 'operator', '2026-02-24 06:14:58'),
(1018, 'Farispic', '12223121', 'azmiariffaris1@gmail.com', '123456', 'pic_barang', '2026-02-24 07:17:00'),
(1019, 'FarisManager', '21333313', 'azmiariffaris2@gmail.com', '123456', 'manager', '2026-02-24 10:55:28'),
(1020, 'FARISADMIN', '31231213', 'azmiariffaris3@gmail.com', '123456', 'admin', '2026-02-24 14:17:39');

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
-- Indeks untuk tabel `extend_peminjaman_items`
--
ALTER TABLE `extend_peminjaman_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_extend_unit` (`extend_peminjaman_id`,`detail_peminjaman_id`,`unit_number`),
  ADD KEY `extend_peminjaman_id` (`extend_peminjaman_id`),
  ADD KEY `detail_peminjaman_id` (`detail_peminjaman_id`),
  ADD KEY `idx_extend_items_lookup` (`detail_peminjaman_id`,`extend_peminjaman_id`);

--
-- Indeks untuk tabel `extend_peminjaman_items_backup`
--
ALTER TABLE `extend_peminjaman_items_backup`
  ADD PRIMARY KEY (`id`),
  ADD KEY `extend_peminjaman_id` (`extend_peminjaman_id`),
  ADD KEY `barang_id` (`barang_id`);

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
-- Indeks untuk tabel `peminjaman_units`
--
ALTER TABLE `peminjaman_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_unit` (`detail_peminjaman_id`,`unit_number`),
  ADD KEY `idx_peminjaman_id` (`peminjaman_id`),
  ADD KEY `idx_detail_peminjaman_id` (`detail_peminjaman_id`),
  ADD KEY `idx_barang_id` (`barang_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT untuk tabel `detail_pengembalian`
--
ALTER TABLE `detail_pengembalian`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT untuk tabel `extend_peminjaman`
--
ALTER TABLE `extend_peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `extend_peminjaman_items`
--
ALTER TABLE `extend_peminjaman_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `extend_peminjaman_items_backup`
--
ALTER TABLE `extend_peminjaman_items_backup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `pembelian_barang`
--
ALTER TABLE `pembelian_barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT untuk tabel `peminjaman_units`
--
ALTER TABLE `peminjaman_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- AUTO_INCREMENT untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1021;

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
-- Ketidakleluasaan untuk tabel `extend_peminjaman_items`
--
ALTER TABLE `extend_peminjaman_items`
  ADD CONSTRAINT `extend_peminjaman_items_ibfk_1` FOREIGN KEY (`extend_peminjaman_id`) REFERENCES `extend_peminjaman` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `extend_peminjaman_items_ibfk_2` FOREIGN KEY (`detail_peminjaman_id`) REFERENCES `detail_peminjaman` (`id`) ON DELETE CASCADE;

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
