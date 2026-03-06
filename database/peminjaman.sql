-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Waktu pembuatan: 06 Mar 2026 pada 02.23
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
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approval_time` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `tanggal_kembali` date DEFAULT NULL,
  `last_reminder_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(26, 73, 99, 145, 1, 'Unit 1 of 5', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(27, 73, 99, 145, 2, 'Unit 2 of 5', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(28, 73, 99, 145, 3, 'Unit 3 of 5', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(29, 73, 99, 145, 4, 'Unit 4 of 5', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(30, 73, 99, 145, 5, 'Unit 5 of 5', 'Dikembalikan', '2026-02-16', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(31, 74, 100, 144, 1, 'Unit 1 of 2', 'Dikembalikan', '2026-02-17', 'Baik', '2026-02-18', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(32, 74, 100, 144, 2, 'Unit 2 of 2', 'Dikembalikan', '2026-02-17', 'Baik', '2026-02-18', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(33, 75, 101, 145, 1, 'Unit 1 of 3', 'Dikembalikan', '2026-02-14', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(34, 75, 101, 145, 2, 'Unit 2 of 3', 'Dikembalikan', '2026-02-14', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(35, 75, 101, 145, 3, 'Unit 3 of 3', 'Dikembalikan', '2026-02-14', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(36, 76, 102, 144, 1, 'Unit 1 of 4', 'Dikembalikan', '2026-02-10', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(37, 76, 102, 144, 2, 'Unit 2 of 4', 'Dikembalikan', '2026-02-10', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(38, 76, 102, 144, 3, 'Unit 3 of 4', 'Dikembalikan', '2026-02-10', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(39, 76, 102, 144, 4, 'Unit 4 of 4', 'Dikembalikan', '2026-02-10', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(40, 77, 103, 144, 1, 'Unit 1 of 5', 'Ditolak', '2026-02-13', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(41, 77, 103, 144, 2, 'Unit 2 of 5', 'Ditolak', '2026-02-13', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(42, 77, 103, 144, 3, 'Unit 3 of 5', 'Ditolak', '2026-02-13', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(43, 77, 103, 144, 4, 'Unit 4 of 5', 'Ditolak', '2026-02-13', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(44, 77, 103, 144, 5, 'Unit 5 of 5', 'Ditolak', '2026-02-13', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(45, 78, 104, 144, 1, 'Unit 1 of 2', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(46, 78, 104, 144, 2, 'Unit 2 of 2', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(47, 79, 105, 144, 1, 'Unit 1 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(48, 79, 105, 144, 2, 'Unit 2 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(49, 79, 105, 144, 3, 'Unit 3 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(50, 79, 105, 144, 4, 'Unit 4 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(51, 79, 105, 144, 5, 'Unit 5 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(52, 79, 105, 144, 6, 'Unit 6 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(53, 79, 105, 144, 7, 'Unit 7 of 7', 'Dikembalikan', '2026-02-19', 'Baik', '2026-02-19', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(54, 80, 106, 144, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(55, 80, 107, 145, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(56, 80, 108, 146, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(57, 80, 109, 147, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(58, 80, 110, 148, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(59, 80, 111, 149, 1, 'Unit 1 of 1', 'Ditolak', '2026-02-22', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(60, 81, 112, 144, 1, 'Unit 1 of 5', 'Dikembalikan', '2026-02-22', 'Baik', '2026-02-20', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(61, 81, 112, 144, 2, 'Unit 2 of 5', 'Dikembalikan', '2026-02-22', 'Baik', '2026-02-20', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(62, 81, 112, 144, 3, 'Unit 3 of 5', 'Dikembalikan', '2026-02-22', 'Baik', '2026-02-20', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(63, 81, 112, 144, 4, 'Unit 4 of 5', 'Dikembalikan', '2026-02-22', 'Baik', '2026-02-20', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(64, 81, 112, 144, 5, 'Unit 5 of 5', 'Rusak', '2026-02-22', 'Rusak', '2026-02-20', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(65, 82, 113, 144, 1, 'Unit 1 of 2', 'Dikembalikan', '2026-02-23', 'Baik', '2026-02-20', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(66, 82, 113, 144, 2, 'Unit 2 of 2', 'Dikembalikan', '2026-02-23', 'Baik', '2026-02-20', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(67, 83, 114, 144, 1, 'Unit 1 of 4', 'Dikembalikan', '2026-02-25', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(68, 83, 114, 144, 2, 'Unit 2 of 4', 'Dikembalikan', '2026-02-25', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(69, 83, 114, 144, 3, 'Unit 3 of 4', 'Rusak', '2026-02-25', 'Rusak', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(70, 83, 114, 144, 4, 'Unit 4 of 4', 'Rusak', '2026-02-25', 'Rusak', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(71, 84, 115, 144, 1, 'Unit 1 of 5', 'Dikembalikan', '2026-02-21', 'Baik', '2026-02-23', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(72, 84, 115, 144, 2, 'Unit 2 of 5', 'Dikembalikan', '2026-02-21', 'Baik', '2026-02-23', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(73, 84, 115, 144, 3, 'Unit 3 of 5', 'Dikembalikan', '2026-02-21', 'Baik', '2026-02-23', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(74, 84, 115, 144, 4, 'Unit 4 of 5', 'Dikembalikan', '2026-02-21', 'Baik', '2026-02-23', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(75, 84, 115, 144, 5, 'Unit 5 of 5', 'Dikembalikan', '2026-02-21', 'Baik', '2026-02-23', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(76, 85, 116, 146, 1, 'Unit 1 of 4', 'Dikembalikan', '2026-02-27', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(77, 85, 116, 146, 2, 'Unit 2 of 4', 'Dikembalikan', '2026-02-27', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(78, 85, 116, 146, 3, 'Unit 3 of 4', 'Dikembalikan', '2026-02-27', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(79, 85, 116, 146, 4, 'Unit 4 of 4', 'Dikembalikan', '2026-02-27', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(80, 86, 117, 144, 1, 'Unit 1 of 2', 'Dipinjam', '2026-03-05', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(81, 86, 117, 144, 2, 'Unit 2 of 2', 'Dipinjam', '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(82, 86, 118, 146, 1, 'Unit 1 of 4', 'Dikembalikan', '2026-03-02', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(83, 86, 118, 146, 2, 'Unit 2 of 4', 'Dikembalikan', '2026-03-02', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(84, 86, 118, 146, 3, 'Unit 3 of 4', 'Rusak', '2026-03-02', 'Rusak', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(85, 86, 118, 146, 4, 'Unit 4 of 4', 'Dipinjam', '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(86, 87, 119, 144, 1, 'Unit 1 of 5', 'Dipinjam', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(87, 87, 119, 144, 2, 'Unit 2 of 5', 'Dipinjam', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(88, 87, 119, 144, 3, 'Unit 3 of 5', 'Dipinjam', '2026-03-31', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(89, 87, 119, 144, 4, 'Unit 4 of 5', 'Dipinjam', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(90, 87, 119, 144, 5, 'Unit 5 of 5', 'Dipinjam', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(91, 87, 120, 145, 1, 'Unit 1 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(92, 87, 120, 145, 2, 'Unit 2 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(93, 87, 120, 145, 3, 'Unit 3 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(94, 87, 120, 145, 4, 'Unit 4 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(95, 87, 120, 145, 5, 'Unit 5 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(96, 87, 120, 145, 6, 'Unit 6 of 11', 'Dikembalikan', '2026-03-03', 'Baik', '2026-02-24', NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(97, 87, 120, 145, 7, 'Unit 7 of 11', 'Dipinjam', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(98, 87, 120, 145, 8, 'Unit 8 of 11', 'Dipinjam', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(99, 87, 120, 145, 9, 'Unit 9 of 11', 'Dipinjam', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(100, 87, 120, 145, 10, 'Unit 10 of 11', 'Dipinjam', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(101, 87, 120, 145, 11, 'Unit 11 of 11', 'Dipinjam', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(102, 88, 121, 144, 1, 'Unit 1 of 5', 'Belum Dikembalikan', '2026-03-04', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(103, 88, 121, 144, 2, 'Unit 2 of 5', 'Belum Dikembalikan', '2026-03-04', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(104, 88, 121, 144, 3, 'Unit 3 of 5', 'Belum Dikembalikan', '2026-03-04', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(105, 88, 121, 144, 4, 'Unit 4 of 5', 'Belum Dikembalikan', '2026-03-04', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(106, 88, 121, 144, 5, 'Unit 5 of 5', 'Belum Dikembalikan', '2026-03-04', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(107, 89, 122, 144, 1, 'Unit 1 of 5', 'Ditolak', '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(108, 89, 122, 144, 2, 'Unit 2 of 5', 'Ditolak', '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(109, 89, 122, 144, 3, 'Unit 3 of 5', 'Ditolak', '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(110, 89, 122, 144, 4, 'Unit 4 of 5', 'Ditolak', '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(111, 89, 122, 144, 5, 'Unit 5 of 5', 'Ditolak', '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(112, 90, 123, 144, 1, 'Unit 1 of 5', 'Ditolak', '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(113, 90, 123, 144, 2, 'Unit 2 of 5', 'Ditolak', '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(114, 90, 123, 144, 3, 'Unit 3 of 5', 'Ditolak', '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(115, 90, 123, 144, 4, 'Unit 4 of 5', 'Ditolak', '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(116, 90, 123, 144, 5, 'Unit 5 of 5', 'Ditolak', '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(117, 93, 126, 144, 1, 'Unit 1 of 5', 'Ditolak', '2026-02-27', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(118, 93, 126, 144, 2, 'Unit 2 of 5', 'Ditolak', '2026-02-27', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(119, 93, 126, 144, 3, 'Unit 3 of 5', 'Ditolak', '2026-02-27', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(120, 93, 126, 144, 4, 'Unit 4 of 5', 'Ditolak', '2026-02-27', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(121, 93, 126, 144, 5, 'Unit 5 of 5', 'Ditolak', '2026-02-27', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-25 16:43:34'),
(122, 96, 129, 144, 1, 'Unit 1 of 6', 'Belum Dikembalikan', '2026-03-10', NULL, NULL, 'Disetujui', 1019, '2026-03-05 10:28:05', NULL, '2026-03-05 10:28:05'),
(123, 96, 129, 144, 2, 'Unit 2 of 6', 'Ditolak', '2026-03-10', NULL, NULL, 'Ditolak', 1019, '2026-03-05 10:28:05', NULL, '2026-03-05 10:28:05'),
(124, 96, 129, 144, 3, 'Unit 3 of 6', 'Belum Dikembalikan', '2026-03-10', NULL, NULL, 'Disetujui', 1019, '2026-03-05 10:28:05', NULL, '2026-03-05 10:28:05'),
(125, 96, 129, 144, 4, 'Unit 4 of 6', 'Ditolak', '2026-03-10', NULL, NULL, 'Ditolak', 1019, '2026-03-05 10:28:05', NULL, '2026-03-05 10:28:05'),
(126, 96, 129, 144, 5, 'Unit 5 of 6', 'Belum Dikembalikan', '2026-03-10', NULL, NULL, 'Disetujui', 1019, '2026-03-05 10:28:05', NULL, '2026-03-05 10:28:05'),
(127, 96, 129, 144, 6, 'Unit 6 of 6', 'Ditolak', '2026-03-10', NULL, NULL, 'Ditolak', 1019, '2026-03-05 10:28:05', NULL, '2026-03-05 10:28:05'),
(128, 96, 130, 145, 1, 'Unit 1 of 1', 'Belum Dikembalikan', '2026-03-10', NULL, NULL, 'Disetujui', 1019, '2026-03-05 10:28:05', NULL, '2026-03-05 10:28:05'),
(129, 96, 131, 146, 1, 'Unit 1 of 2', 'Belum Dikembalikan', '2026-03-10', NULL, NULL, 'Disetujui', 1019, '2026-03-05 10:28:05', NULL, '2026-03-05 10:28:05'),
(130, 96, 131, 146, 2, 'Unit 2 of 2', 'Belum Dikembalikan', '2026-03-10', NULL, NULL, 'Disetujui', 1019, '2026-03-05 10:28:05', NULL, '2026-03-05 10:28:05'),
(131, 94, 127, 144, 1, 'Unit 1 of 5', 'Belum Dikembalikan', '2026-02-28', NULL, NULL, 'Disetujui', 1019, '2026-03-05 11:02:17', NULL, '2026-03-05 11:02:16'),
(132, 94, 127, 144, 2, 'Unit 2 of 5', 'Ditolak', '2026-02-28', NULL, NULL, 'Ditolak', 1019, '2026-03-05 11:02:17', NULL, '2026-03-05 11:02:17'),
(133, 94, 127, 144, 3, 'Unit 3 of 5', 'Belum Dikembalikan', '2026-02-28', NULL, NULL, 'Disetujui', 1019, '2026-03-05 11:02:17', NULL, '2026-03-05 11:02:17'),
(134, 94, 127, 144, 4, 'Unit 4 of 5', 'Ditolak', '2026-02-28', NULL, NULL, 'Ditolak', 1019, '2026-03-05 11:02:17', NULL, '2026-03-05 11:02:17'),
(135, 94, 127, 144, 5, 'Unit 5 of 5', 'Ditolak', '2026-02-28', NULL, NULL, 'Ditolak', 1019, '2026-03-05 11:02:17', NULL, '2026-03-05 11:02:17');

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
(1, 'Admin Sistem', '100001', 'admin@komatsu.co.id', '123456', 'admin', '2026-01-27 15:59:06');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT untuk tabel `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- AUTO_INCREMENT untuk tabel `detail_pengembalian`
--
ALTER TABLE `detail_pengembalian`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT untuk tabel `extend_peminjaman`
--
ALTER TABLE `extend_peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `extend_peminjaman_items`
--
ALTER TABLE `extend_peminjaman_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `pembelian_barang`
--
ALTER TABLE `pembelian_barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT untuk tabel `peminjaman_units`
--
ALTER TABLE `peminjaman_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1022;

--
-- AUTO_INCREMENT untuk tabel `vendor`
--
ALTER TABLE `vendor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
