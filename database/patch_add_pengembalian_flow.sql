-- ============================================================
-- Patch: Tambah flow pengembalian + inspeksi (Admin / PIC Barang)
-- Database: peminjaman
-- ============================================================
-- Cara pakai (phpMyAdmin / mysql CLI):
--   USE peminjaman;
--   SOURCE patch_add_pengembalian_flow.sql;
--
-- Patch ini menambah:
-- - tabel pengembalian (header pengajuan pengembalian)
-- - tabel detail_pengembalian (kondisi per barang saat dikembalikan)
--
-- Catatan:
-- - Tidak mengubah enum status di tabel peminjaman (tetap pakai 'Dikembalikan' saat final).
-- - Status proses pengembalian disimpan di tabel pengembalian.status.

USE `peminjaman`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `detail_pengembalian`;
DROP TABLE IF EXISTS `pengembalian`;

CREATE TABLE `pengembalian` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `kode_pengembalian` varchar(30) NOT NULL,
  `peminjaman_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `status` enum('Diajukan','Dicek','Sebagian Dikembalikan','Selesai') NOT NULL DEFAULT 'Diajukan',
  `catatan_user` text DEFAULT NULL,
  `catatan_petugas` text DEFAULT NULL,
  `checked_by_role` enum('admin','pic_barang') DEFAULT NULL,
  `checked_by_user_id` int(11) unsigned DEFAULT NULL,
  `has_rusak` tinyint(1) NOT NULL DEFAULT 0,
  `total_ganti_rugi` decimal(15,2) NOT NULL DEFAULT 0.00,
  `diajukan_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dicek_at` datetime DEFAULT NULL,
  `selesai_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pengembalian_peminjaman` (`peminjaman_id`),
  UNIQUE KEY `uk_kode_pengembalian` (`kode_pengembalian`),
  KEY `idx_pengembalian_status` (`status`),
  KEY `idx_pengembalian_user` (`user_id`),
  CONSTRAINT `fk_pengembalian_peminjaman` FOREIGN KEY (`peminjaman_id`) REFERENCES `peminjaman` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pengembalian_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `detail_pengembalian` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pengembalian_id` int(11) unsigned NOT NULL,
  `barang_id` int(11) unsigned NOT NULL,
  `jumlah_kembali` int(11) NOT NULL DEFAULT 1,
  `kondisi_kembali` enum('Baik','Rusak') NOT NULL DEFAULT 'Baik',
  `jumlah_rusak` int(11) NOT NULL DEFAULT 0,
  `biaya_ganti_rugi` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_detail_pengembalian_pengembalian` (`pengembalian_id`),
  KEY `idx_detail_pengembalian_barang` (`barang_id`),
  CONSTRAINT `fk_detail_pengembalian_pengembalian` FOREIGN KEY (`pengembalian_id`) REFERENCES `pengembalian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detail_pengembalian_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

