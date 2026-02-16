-- ============================================================
-- Database: peminjaman
-- Schema untuk sistem peminjaman barang (Requester → Approver → Admin)
-- ============================================================
-- Cara pakai:
--   mysql -u root -p < schema_peminjaman.sql
--   Atau di phpMyAdmin: buat database "peminjaman", lalu import file ini.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `peminjaman` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `peminjaman`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Tabel: users (Requester, Approver, Admin)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `nrp` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','user') NOT NULL COMMENT 'admin=Admin, manager=Approver, user=Requester',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nrp` (`nrp`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: barang (inventaris)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `barang`;
CREATE TABLE `barang` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `kode_barang` varchar(30) NOT NULL,
  `nama_barang` varchar(150) NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `lokasi` varchar(100) DEFAULT NULL,
  `stok_total` int(11) NOT NULL DEFAULT 0,
  `stok_tersedia` int(11) NOT NULL DEFAULT 0,
  `safety_stock` int(11) NOT NULL DEFAULT 1,
  `kondisi` enum('Baik','Rusak') DEFAULT 'Baik',
  `keterangan` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_barang` (`kode_barang`),
  UNIQUE KEY `nama_barang` (`nama_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: peminjaman (header peminjaman)
-- Status: Menunggu Persetujuan → Disetujui → Sedang Dipinjam / Ditolak → Dikembalikan
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `peminjaman`;
CREATE TABLE `peminjaman` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `kode_peminjaman` varchar(30) NOT NULL,
  `user_id` int(11) unsigned NOT NULL COMMENT 'Requester',
  `nama_peminjam` varchar(255) NOT NULL,
  `nrp` varchar(50) NOT NULL,
  `lokasi_umum` varchar(255) DEFAULT NULL,
  `tanggal_pinjam` date NOT NULL,
  `rencana_kembali` date NOT NULL,
  `tanggal_disetujui` date DEFAULT NULL COMMENT 'Diisi saat status jadi Sedang Dipinjam',
  `status` enum('Menunggu Persetujuan','Disetujui','Ditolak','Sedang Dipinjam','Dikembalikan','Proses Return') DEFAULT 'Menunggu Persetujuan',
  `catatan` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `tanggal_kembali` date DEFAULT NULL COMMENT 'Diisi saat dikembalikan',
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_peminjaman` (`kode_peminjaman`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_peminjaman_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: detail_peminjaman (item barang per peminjaman)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `detail_peminjaman`;
CREATE TABLE `detail_peminjaman` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `peminjaman_id` int(11) unsigned NOT NULL,
  `barang_id` int(11) unsigned NOT NULL,
  `lokasi` varchar(100) NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `kondisi_pinjam` enum('Baik','Rusak') DEFAULT 'Baik' COMMENT 'Kondisi saat dipinjam',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `peminjaman_id` (`peminjaman_id`),
  KEY `barang_id` (`barang_id`),
  CONSTRAINT `fk_detail_peminjaman` FOREIGN KEY (`peminjaman_id`) REFERENCES `peminjaman` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detail_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: vendor (untuk pembelian barang)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `vendor`;
CREATE TABLE `vendor` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nama_vendor` varchar(150) NOT NULL,
  `alamat` text DEFAULT NULL,
  `kontak` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nama_vendor` (`nama_vendor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: pembelian_barang (riwayat pembelian stok)
-- ----
DROP TABLE IF EXISTS `pembelian_barang`;
CREATE TABLE `pembelian_barang` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `barang_id` int(11) unsigned NOT NULL,
  `vendor_id` int(11) unsigned NOT NULL,
  `tanggal_pembelian` date NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` float DEFAULT NULL COMMENT 'Harga satuan (float for decimal precision)',
  `total_harga` float DEFAULT NULL COMMENT 'Total harga = jumlah * harga_satuan (float)',
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `barang_id` (`barang_id`),
  KEY `vendor_id` (`vendor_id`),
  CONSTRAINT `fk_pembelian_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pembelian_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tabel: riwayat_pembelian (legacy table, keep for backward compatibility)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `riwayat_pembelian`;
CREATE TABLE `riwayat_pembelian` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `barang_id` int(11) unsigned DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `harga` int(11) DEFAULT NULL,
  `vendor` varchar(150) DEFAULT NULL,
  `jumlah` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `barang_id` (`barang_id`),
  CONSTRAINT `fk_riwayat_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Data awal: 1 admin default (password: admin123, ganti di production)
-- ------------------------------------------------------------
INSERT INTO `users` (`nama`, `nrp`, `email`, `password`, `role`) VALUES
('Administrator', '000000', 'admin@example.com', '$2y$10$hashed_password_here', 'admin');

-- Contoh data vendor
INSERT INTO `vendor` (`nama_vendor`, `alamat`, `kontak`) VALUES
('PT. Komatsu Indonesia', 'Jakarta', '021-12345678'),
('CV. Tech Supplier', 'Surabaya', '031-87654321');

-- Contoh data barang
INSERT INTO `barang` (`kode_barang`, `nama_barang`, `kategori`, `lokasi`, `stok_total`, `stok_tersedia`, `safety_stock`, `kondisi`, `keterangan`) VALUES
('LPT-001', 'Laptop Lenovo ThinkPad', 'Elektronik', 'Lab Komputer', 5, 3, 1, 'Baik', 'Laptop untuk programming'),
('CAM-001', 'Kamera Canon EOS', 'Elektronik', 'Studio Foto', 3, 2, 1, 'Baik', 'Kamera DSLR profesional'),
('MSE-001', 'Mouse Logitech', 'Aksesoris', 'Lab Komputer', 10, 7, 2, 'Baik', 'Mouse wireless'),
('KYB-001', 'Keyboard Mechanical', 'Aksesoris', 'Lab Komputer', 15, 10, 2, 'Baik', 'Keyboard gaming RGB'),
('PRT-001', 'Printer HP LaserJet', 'Elektronik', 'Ruang Admin', 5, 4, 1, 'Baik', 'Printer laser hitam putih');

-- Contoh data user tambahan
INSERT INTO `users` (`nama`, `nrp`, `email`, `password`, `role`) VALUES
('Manager ADC', '200001', 'manager@komatsu.co.id', '$2y$10$hashed_password_here', 'manager'),
('Muhammad Faris Azmiarif', '220145', 'azmiariffaris@komatsu.co.id', '$2y$10$hashed_password_here', 'user'),
('Raisyah', '20248372', 'raisyah@komatsu.co.id', '$2y$10$hashed_password_here', 'user'),
('Akbar', '29828294', 'akbar@komatsu.co.id', '$2y$10$hashed_password_here', 'user');
