-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 02 Apr 2026 pada 02.42
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

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
  `kondisi` enum('Good','Damaged') DEFAULT 'Good',
  `keterangan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `stok_rusak` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `barang`
--

INSERT INTO `barang` (`id`, `kode_barang`, `nama_barang`, `kategori`, `lokasi`, `stok_total`, `stok_tersedia`, `safety_stock`, `kondisi`, `keterangan`, `created_at`, `stok_rusak`) VALUES
(150, 'ADC-LAP-01', 'Laptop Lenovo Thinkpad', 'Laptop', 'ICT - MAIN OFFICE', 50, 26, 1, 'Good', 'Work Requirements', '2026-03-09 07:25:26', 1),
(151, 'ADC-LAP-02', 'Laptop Lenovo Ideapad Slim 3', 'Laptop', 'ICT-MAIN OFFICE', 20, 16, 1, 'Good', 'Work Requirement', '2026-03-09 08:12:30', 0),
(152, 'ADC-LAP-03', 'Laptop Lenovo IDeapad Slim 5', 'Laptop', 'ICT - MAIN OFFICE', 20, 17, 1, 'Good', 'Work Requirement\r\n', '2026-03-09 22:30:36', 0),
(153, 'ADC-MOUSE-01', 'Mouse Logitech', 'Mouse', 'ICT - MAIN OFFICE', 20, 18, 1, 'Good', 'Work Requirement', '2026-03-09 22:31:01', 0),
(154, 'ADC-MOUSE-02', 'Mouse Robot', 'Mouse', 'ICT - MAIN OFFICE', 20, 18, 1, 'Good', 'Work Requirement\r\n', '2026-03-09 22:31:44', 0),
(155, 'ADC-KEYB-01', 'Keyboard Fantech', 'Keyboard', 'ICT - MAIN OFFICE', 20, 18, 1, 'Good', 'Work Requirement', '2026-03-09 22:32:11', 0),
(156, 'ADC-KEYB-02', 'Keyboard Robot', 'Keyboard', 'ICT - MAIN OFFICE', 20, 20, 1, 'Good', 'Work Requirement\r\n', '2026-03-09 22:33:29', 0),
(157, 'ADC-LAP-04', 'Laptop Lenovo Legion', 'Laptop', 'ICT - MAIN OFFICE', 20, 19, 1, 'Good', 'Work Requirement\r\n', '2026-03-09 22:34:14', 0),
(158, 'ADC-MON-01', 'Monitor ThinkVision', 'Monitor', 'ICT - MAIN OFFICE', 20, 20, 1, 'Good', 'Work Requirement', '2026-03-09 22:35:08', 0),
(159, 'ADC-MON-02', 'Monitor Robot', 'Monitor', 'ICT - MAIN OFFICE', 20, 20, 1, 'Good', 'Work Requirement', '2026-03-09 22:35:40', 0),
(160, 'ADC-LAP-05', 'LAPTOP LENOVO IDEAPAD 5', 'Laptop', 'ICT - MAIN OFFICE', 0, 0, 1, 'Good', 'WORK REQUIREMENTS', '2026-04-01 08:23:17', 0);

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
  `kondisi_pinjam` enum('Good','Damaged') DEFAULT 'Good',
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approval_time` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_peminjaman`
--

INSERT INTO `detail_peminjaman` (`id`, `peminjaman_id`, `barang_id`, `lokasi`, `jumlah`, `expected_return`, `kondisi_pinjam`, `approval_status`, `rejection_reason`, `approved_by`, `approval_time`, `created_at`) VALUES
(136, 101, 150, 'ICT - MAIN OFFICE', 2, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:38:24'),
(137, 101, 151, 'ICT-MAIN OFFICE', 2, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:38:24'),
(138, 101, 152, 'ICT - MAIN OFFICE', 2, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:38:24'),
(139, 102, 153, 'ICT - MAIN OFFICE', 2, '2026-04-01', 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:42:27'),
(140, 102, 154, 'ICT - MAIN OFFICE', 2, '2026-04-01', 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:42:27'),
(141, 102, 155, 'ICT - MAIN OFFICE', 2, '2026-04-01', 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:42:27'),
(142, 103, 156, 'ICT - MAIN OFFICE', 2, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:43:20'),
(143, 103, 157, 'ICT - MAIN OFFICE', 2, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:43:20'),
(144, 103, 158, 'ICT - MAIN OFFICE', 2, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:43:20'),
(145, 104, 150, 'ICT - MAIN OFFICE', 2, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:43:49'),
(146, 104, 151, 'ICT-MAIN OFFICE', 2, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:43:49'),
(147, 104, 159, 'ICT - MAIN OFFICE', 2, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 06:43:49'),
(148, 105, 150, 'ICT - MAIN OFFICE', 5, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 07:18:29'),
(149, 106, 152, 'ICT - MAIN OFFICE', 5, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 13:14:26'),
(150, 107, 150, 'ICT - MAIN OFFICE', 2, '2026-03-30', 'Good', 'pending', NULL, NULL, NULL, '2026-03-10 13:25:29'),
(153, 113, 150, 'ICT - MAIN OFFICE', 3, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-11 13:21:23'),
(169, 129, 150, 'ICT - MAIN OFFICE', 7, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-27 10:12:53'),
(191, 151, 151, 'ICT-MAIN OFFICE', 5, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-03-30 07:58:54'),
(192, 152, 150, 'ICT - MAIN OFFICE', 5, NULL, 'Good', 'pending', NULL, NULL, NULL, '2026-04-01 08:38:54');

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_pengembalian`
--

CREATE TABLE `detail_pengembalian` (
  `id` int(11) UNSIGNED NOT NULL,
  `pengembalian_id` int(11) UNSIGNED NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah_kembali` int(11) NOT NULL DEFAULT 1,
  `kondisi_kembali` enum('Good','Damaged') DEFAULT NULL,
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
(43, 42, 151, 1, 'Good', 0, 0, 0.00, '', '2026-03-10 07:21:12'),
(44, 42, 152, 2, 'Good', 0, 0, 0.00, '', '2026-03-10 07:21:12'),
(45, 42, 150, 1, 'Good', 0, 0, 0.00, '', '2026-03-10 07:21:12'),
(46, 43, 150, 2, 'Good', 0, 0, 0.00, '', '2026-03-10 13:54:06');

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
(11, 107, 1025, '2026-03-10', '2026-03-30', 'TES EXTEND', 'Approved', 1024, '2026-03-10 13:26:59', '2026-03-10 13:26:14'),
(12, 102, 1025, '2026-03-16', '2026-04-01', 'TES EXTEND ALL', 'Approved', 1024, '2026-03-11 09:21:06', '2026-03-11 09:20:38');

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
(8, 11, 150, 1, '2026-03-30', '2026-03-10 13:26:14'),
(9, 11, 150, 2, '2026-03-30', '2026-03-10 13:26:14'),
(10, 12, 139, 1, '2026-04-01', '2026-03-11 09:20:38'),
(11, 12, 139, 2, '2026-04-01', '2026-03-11 09:20:38'),
(12, 12, 140, 1, '2026-04-01', '2026-03-11 09:20:38'),
(13, 12, 140, 2, '2026-04-01', '2026-03-11 09:20:38'),
(14, 12, 141, 1, '2026-04-01', '2026-03-11 09:20:38'),
(15, 12, 141, 2, '2026-04-01', '2026-03-11 09:20:38');

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
(45, 150, 17, '2026-03-16', 40, 30.00, 'tes\r\n', '2026-03-16 11:47:22');

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
  `status` varchar(50) NOT NULL DEFAULT 'Waiting for Approval',
  `catatan` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `tanggal_kembali` date DEFAULT NULL,
  `last_reminder_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `peminjaman`
--

INSERT INTO `peminjaman` (`id`, `kode_peminjaman`, `user_id`, `nama_peminjam`, `nrp`, `lokasi_umum`, `tanggal_pinjam`, `rencana_kembali`, `tanggal_disetujui`, `status`, `catatan`, `rejection_reason`, `created_at`, `tanggal_kembali`, `last_reminder_date`) VALUES
(101, 'PMJ-1773099504', 1025, 'Faris User', '48479373', 'FABRIKASI', '2026-03-10', '2026-03-16', '2026-03-10', 'Returned', 'TES NOTES USER', 'TES REJECTION MANAGER (REJECT 2)', '2026-03-10 06:38:24', '2026-03-10', NULL),
(102, 'PMJ-1773099747', 1025, 'Faris User', '48479373', 'HYDRAULIC', '2026-03-10', '2026-04-01', '2026-03-10', 'Overdue', 'TES NOTES USER', '', '2026-03-10 06:42:27', NULL, NULL),
(103, 'PMJ-1773099800', 1025, 'Faris User', '48479373', 'FABRIKASI', '2026-03-10', '2026-03-16', '2026-03-10', 'Overdue', 'TES NOTES', 'TES NOTES MANAGER (APPROVE 1)', '2026-03-10 06:43:20', NULL, NULL),
(104, 'PMJ-1773099829', 1025, 'Faris User', '48479373', 'HYDRAULIC', '2026-03-10', '2026-03-16', NULL, 'Rejected', 'TES NOTES USER', 'TES NOTES MANAGER (REJECT ALL)', '2026-03-10 06:43:49', NULL, NULL),
(105, 'PMJ-1773101909', 1025, 'Faris User', '48479373', 'FABRIKASI', '2026-03-10', '2026-03-16', '2026-03-10', 'Overdue', 'TES NOTES USER', 'TES REJECT 2', '2026-03-10 07:18:29', NULL, NULL),
(106, 'PMJ-1773123266', 1025, 'Faris User', '48479373', 'KBN', '2026-03-10', '2026-03-25', '2026-03-10', 'Overdue', '', 'tes tolak', '2026-03-10 13:14:26', NULL, NULL),
(107, 'PMJ-1773123929', 1025, 'Faris User', '48479373', 'Kbn', '2026-03-10', '2026-03-30', '2026-03-10', 'Returned', '', '', '2026-03-10 13:25:29', '2026-03-10', NULL),
(113, 'PMJ-1773210083', 1025, 'Faris User', '48479373', 'kbn', '2026-03-11', '2026-03-31', '2026-03-27', 'Overdue', 'tes notes', '', '2026-03-11 13:21:23', NULL, NULL),
(129, 'PMJ-1774581173', 1025, 'Faris User', '48479373', 'KBN', '2026-03-27', '2026-04-01', '2026-03-27', 'Overdue', 'TES NOTES', 'TES TOLAK SEBAGIAN', '2026-03-27 10:12:53', NULL, NULL),
(151, 'PMJ-1774832334', 1025, 'Faris User', '48479373', 'KBN', '2026-03-30', '2026-04-07', '2026-03-30', 'Due In 5 Days', 'TES NOTES', 'TEST PARTIAL APPROVED', '2026-03-30 07:58:54', NULL, NULL),
(152, 'PMJ-1775007534', 1025, 'Faris User', '48479373', 'TEST LOCATION', '2026-04-01', '2026-04-07', NULL, 'Waiting for Approval', 'TEST NOTE', NULL, '2026-04-01 08:38:54', NULL, NULL);

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
  `return_status` varchar(50) NOT NULL DEFAULT 'Not Yet Returned',
  `expected_return` date DEFAULT NULL,
  `kondisi_kembali` enum('Baik','Rusak') DEFAULT NULL,
  `tanggal_kembali` date DEFAULT NULL,
  `approval_status` varchar(20) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approval_time` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `peminjaman_units`
--

INSERT INTO `peminjaman_units` (`id`, `peminjaman_id`, `detail_peminjaman_id`, `barang_id`, `unit_number`, `unit_display`, `return_status`, `expected_return`, `kondisi_kembali`, `tanggal_kembali`, `approval_status`, `approved_by`, `approval_time`, `rejection_reason`, `created_at`) VALUES
(147, 101, 136, 150, 1, 'Unit 1 of 2', 'Returned', '2026-03-16', NULL, NULL, 'Approved', 1023, '2026-03-10 06:47:51', NULL, '2026-03-10 06:47:51'),
(148, 101, 136, 150, 2, 'Unit 2 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:47:51', NULL, '2026-03-10 06:47:51'),
(149, 101, 137, 151, 1, 'Unit 1 of 2', 'Returned', '2026-03-16', NULL, NULL, 'Approved', 1023, '2026-03-10 06:47:51', NULL, '2026-03-10 06:47:51'),
(150, 101, 137, 151, 2, 'Unit 2 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:47:51', NULL, '2026-03-10 06:47:51'),
(151, 101, 138, 152, 1, 'Unit 1 of 2', 'Returned', '2026-03-16', NULL, NULL, 'Approved', 1023, '2026-03-10 06:47:51', NULL, '2026-03-10 06:47:51'),
(152, 101, 138, 152, 2, 'Unit 2 of 2', 'Returned', '2026-03-16', NULL, NULL, 'Approved', 1023, '2026-03-10 06:47:51', NULL, '2026-03-10 06:47:51'),
(153, 102, 139, 153, 1, 'Unit 1 of 2', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-10 06:49:15', NULL, '2026-03-10 06:49:15'),
(154, 102, 139, 153, 2, 'Unit 2 of 2', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-10 06:49:15', NULL, '2026-03-10 06:49:15'),
(155, 102, 140, 154, 1, 'Unit 1 of 2', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-10 06:49:15', NULL, '2026-03-10 06:49:15'),
(156, 102, 140, 154, 2, 'Unit 2 of 2', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-10 06:49:15', NULL, '2026-03-10 06:49:15'),
(157, 102, 141, 155, 1, 'Unit 1 of 2', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-10 06:49:15', NULL, '2026-03-10 06:49:15'),
(158, 102, 141, 155, 2, 'Unit 2 of 2', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-10 06:49:15', NULL, '2026-03-10 06:49:15'),
(159, 103, 142, 156, 1, 'Unit 1 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:49:58', NULL, '2026-03-10 06:49:58'),
(160, 103, 142, 156, 2, 'Unit 2 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:49:58', NULL, '2026-03-10 06:49:58'),
(161, 103, 143, 157, 1, 'Unit 1 of 2', 'Not Yet Returned', '2026-03-16', NULL, NULL, 'Approved', 1023, '2026-03-10 06:49:58', NULL, '2026-03-10 06:49:58'),
(162, 103, 143, 157, 2, 'Unit 2 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:49:58', NULL, '2026-03-10 06:49:58'),
(163, 103, 144, 158, 1, 'Unit 1 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:49:58', NULL, '2026-03-10 06:49:58'),
(164, 103, 144, 158, 2, 'Unit 2 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:49:58', NULL, '2026-03-10 06:49:58'),
(165, 104, 145, 150, 1, 'Unit 1 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:52:05', NULL, '2026-03-10 06:52:05'),
(166, 104, 145, 150, 2, 'Unit 2 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:52:05', NULL, '2026-03-10 06:52:05'),
(167, 104, 146, 151, 1, 'Unit 1 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:52:05', NULL, '2026-03-10 06:52:05'),
(168, 104, 146, 151, 2, 'Unit 2 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:52:05', NULL, '2026-03-10 06:52:05'),
(169, 104, 147, 159, 1, 'Unit 1 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:52:05', NULL, '2026-03-10 06:52:05'),
(170, 104, 147, 159, 2, 'Unit 2 of 2', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 06:52:05', NULL, '2026-03-10 06:52:05'),
(171, 105, 148, 150, 1, 'Unit 1 of 5', 'Not Yet Returned', '2026-03-16', NULL, NULL, 'Approved', 1023, '2026-03-10 07:19:47', NULL, '2026-03-10 07:19:47'),
(172, 105, 148, 150, 2, 'Unit 2 of 5', 'Not Yet Returned', '2026-03-16', NULL, NULL, 'Approved', 1023, '2026-03-10 07:19:47', NULL, '2026-03-10 07:19:47'),
(173, 105, 148, 150, 3, 'Unit 3 of 5', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 07:19:47', NULL, '2026-03-10 07:19:47'),
(174, 105, 148, 150, 4, 'Unit 4 of 5', 'Rejected', '2026-03-16', NULL, NULL, 'Rejected', 1023, '2026-03-10 07:19:47', NULL, '2026-03-10 07:19:47'),
(175, 105, 148, 150, 5, 'Unit 5 of 5', 'Not Yet Returned', '2026-03-16', NULL, NULL, 'Approved', 1023, '2026-03-10 07:19:47', NULL, '2026-03-10 07:19:47'),
(176, 106, 149, 152, 1, 'Unit 1 of 5', 'Not Yet Returned', '2026-03-25', NULL, NULL, 'Approved', 1023, '2026-03-10 13:15:12', NULL, '2026-03-10 13:15:12'),
(177, 106, 149, 152, 2, 'Unit 2 of 5', 'Rejected', '2026-03-25', NULL, NULL, 'Rejected', 1023, '2026-03-10 13:15:12', NULL, '2026-03-10 13:15:12'),
(178, 106, 149, 152, 3, 'Unit 3 of 5', 'Rejected', '2026-03-25', NULL, NULL, 'Rejected', 1023, '2026-03-10 13:15:12', NULL, '2026-03-10 13:15:12'),
(179, 106, 149, 152, 4, 'Unit 4 of 5', 'Not Yet Returned', '2026-03-25', NULL, NULL, 'Approved', 1023, '2026-03-10 13:15:12', NULL, '2026-03-10 13:15:12'),
(180, 106, 149, 152, 5, 'Unit 5 of 5', 'Not Yet Returned', '2026-03-25', NULL, NULL, 'Approved', 1023, '2026-03-10 13:15:12', NULL, '2026-03-10 13:15:12'),
(181, 107, 150, 150, 1, 'Unit 1 of 2', 'Returned', '2026-03-30', NULL, NULL, 'Approved', 1023, '2026-03-10 13:25:40', NULL, '2026-03-10 13:25:40'),
(182, 107, 150, 150, 2, 'Unit 2 of 2', 'Returned', '2026-03-30', NULL, NULL, 'Approved', 1023, '2026-03-10 13:25:40', NULL, '2026-03-10 13:25:40'),
(183, 113, 153, 150, 1, 'Unit 1', 'Not Yet Returned', NULL, NULL, NULL, 'Approved', 1022, '2026-03-27 10:09:46', NULL, '2026-03-12 13:47:09'),
(184, 113, 153, 150, 2, 'Unit 2', 'Not Yet Returned', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-03-12 13:47:09'),
(185, 113, 153, 150, 3, 'Unit 3', 'Not Yet Returned', NULL, NULL, NULL, 'Pending', NULL, NULL, NULL, '2026-03-12 13:47:09'),
(205, 129, 169, 150, 1, 'Unit 1 of 7', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-27 10:32:12', NULL, '2026-03-27 10:32:12'),
(206, 129, 169, 150, 2, 'Unit 2 of 7', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-27 10:32:12', NULL, '2026-03-27 10:32:12'),
(207, 129, 169, 150, 3, 'Unit 3 of 7', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-27 10:32:12', NULL, '2026-03-27 10:32:12'),
(208, 129, 169, 150, 4, 'Unit 4 of 7', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-27 10:32:12', NULL, '2026-03-27 10:32:12'),
(209, 129, 169, 150, 5, 'Unit 5 of 7', 'Rejected', '2026-04-01', NULL, NULL, 'Rejected', 1023, '2026-03-27 10:32:12', NULL, '2026-03-27 10:32:12'),
(210, 129, 169, 150, 6, 'Unit 6 of 7', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-27 10:32:12', NULL, '2026-03-27 10:32:12'),
(211, 129, 169, 150, 7, 'Unit 7 of 7', 'Not Yet Returned', '2026-04-01', NULL, NULL, 'Approved', 1023, '2026-03-27 10:32:12', NULL, '2026-03-27 10:32:12'),
(272, 151, 191, 151, 1, 'Unit 1 of 5', 'Not Yet Returned', '2026-04-07', NULL, NULL, 'Approved', 1023, '2026-03-30 08:01:58', NULL, '2026-03-30 08:01:57'),
(273, 151, 191, 151, 2, 'Unit 2 of 5', 'Not Yet Returned', '2026-04-07', NULL, NULL, 'Approved', 1023, '2026-03-30 08:01:58', NULL, '2026-03-30 08:01:57'),
(274, 151, 191, 151, 3, 'Unit 3 of 5', 'Rejected', '2026-04-07', NULL, NULL, 'Rejected', 1023, '2026-03-30 08:01:58', NULL, '2026-03-30 08:01:58'),
(275, 151, 191, 151, 4, 'Unit 4 of 5', 'Not Yet Returned', '2026-04-07', NULL, NULL, 'Approved', 1023, '2026-03-30 08:01:58', NULL, '2026-03-30 08:01:58'),
(276, 151, 191, 151, 5, 'Unit 5 of 5', 'Not Yet Returned', '2026-04-07', NULL, NULL, 'Approved', 1023, '2026-03-30 08:01:58', NULL, '2026-03-30 08:01:58');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengembalian`
--

CREATE TABLE `pengembalian` (
  `id` int(11) UNSIGNED NOT NULL,
  `kode_pengembalian` varchar(30) NOT NULL,
  `peminjaman_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Submitted','Being Inspected','Completed','Partially Returned','Partially Damaged') DEFAULT NULL,
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
(42, 'KMB-1773102072', 101, 1025, 'Completed', 'TES RETURN USER', 'TES NOTES PIC', 'pic_barang', 1024, 0, 0.00, '2026-03-10 07:21:12', '2026-03-10 07:22:46', '2026-03-10 07:22:46'),
(43, 'KMB-1773125646', 107, 1025, 'Completed', '', '', 'pic_barang', 1024, 0, 0.00, '2026-03-10 13:54:06', '2026-03-10 13:54:22', '2026-03-10 13:54:22');

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
(7, 'admin', 'FULL ACCESS', 0, 'primary', '2026-03-09 00:11:50'),
(8, 'user', 'Loan Application', 0, 'secondary', '2026-03-09 00:13:29'),
(9, 'pic_barang', 'Full Access to Item Data & Approve/Reject Loan Extend', 0, 'info', '2026-03-09 00:15:19'),
(10, 'manager', 'Full Access to Loan Approval & Rejection', 0, 'danger', '2026-03-09 00:16:05');

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
  `created_at` datetime DEFAULT current_timestamp(),
  `reset_token` varchar(10) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `nama`, `nrp`, `email`, `password`, `role`, `created_at`, `reset_token`, `reset_token_expires`) VALUES
(1, 'Admin', '10000', 'admin@komatsu.co.id', '123456', 'admin', '2026-01-27 15:59:06', NULL, NULL),
(1022, 'Faris Admin', '49583984', 'azmiariffaris@gmail.com', '123456', 'admin', '2026-03-09 07:17:47', '652254', '2026-03-30 07:10:13'),
(1023, 'Faris Manager', '202480392', 'azmiariffaris1@gmail.com', '123456', 'manager', '2026-03-09 07:18:27', NULL, NULL),
(1024, 'Faris Pic Barang', '48573837', 'azmiariffaris2@gmail.com', '123456', 'pic_barang', '2026-03-09 07:19:07', NULL, NULL),
(1025, 'Faris User', '48479373', 'azmiariffaris3@gmail.com', '123456', 'user', '2026-03-09 07:19:41', '449524', '2026-03-26 12:14:07'),
(1056, 'TEST ADMIN', '826826', 'testadmin@gmail.com', '123456', 'admin', '2026-03-31 07:13:23', NULL, NULL),
(1057, 'Test user', '24242', 'testuser@gmail.com', '123456', 'user', '2026-03-31 07:13:52', NULL, NULL);

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
(17, 'PT Kemas Indah Maju Kim ', 'Bekasi', '08474627446', '2026-03-09 07:52:19');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT untuk tabel `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=193;

--
-- AUTO_INCREMENT untuk tabel `detail_pengembalian`
--
ALTER TABLE `detail_pengembalian`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT untuk tabel `extend_peminjaman`
--
ALTER TABLE `extend_peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `extend_peminjaman_items`
--
ALTER TABLE `extend_peminjaman_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT untuk tabel `pembelian_barang`
--
ALTER TABLE `pembelian_barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT untuk tabel `peminjaman_units`
--
ALTER TABLE `peminjaman_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=277;

--
-- AUTO_INCREMENT untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT untuk tabel `riwayat_pembelian`
--
ALTER TABLE `riwayat_pembelian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1058;

--
-- AUTO_INCREMENT untuk tabel `vendor`
--
ALTER TABLE `vendor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
