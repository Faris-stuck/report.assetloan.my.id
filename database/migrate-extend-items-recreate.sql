-- Migration: Recreate extend_peminjaman_items with per-unit schema
-- This completely replaces the old qty-based structure with per-unit tracking

-- Backup existing data
CREATE TABLE IF NOT EXISTS `extend_peminjaman_items_backup` LIKE `extend_peminjaman_items`;
INSERT IGNORE INTO `extend_peminjaman_items_backup` SELECT * FROM `extend_peminjaman_items`;

-- Drop the old table
DROP TABLE IF EXISTS `extend_peminjaman_items`;

-- Create new per-unit table
CREATE TABLE `extend_peminjaman_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `extend_peminjaman_id` int(11) NOT NULL,
  `detail_peminjaman_id` int(11) NOT NULL,
  `unit_number` int(11) NOT NULL COMMENT 'Unit number within the qty (1, 2, 3...)',
  `tanggal_perpanjang` date NOT NULL COMMENT 'Extended return date for this specific unit',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `extend_peminjaman_id` (`extend_peminjaman_id`),
  KEY `detail_peminjaman_id` (`detail_peminjaman_id`),
  UNIQUE KEY `unique_extend_unit` (`extend_peminjaman_id`, `detail_peminjaman_id`, `unit_number`),
  CONSTRAINT `extend_peminjaman_items_ibfk_1` FOREIGN KEY (`extend_peminjaman_id`) REFERENCES `extend_peminjaman` (`id`) ON DELETE CASCADE,
  CONSTRAINT `extend_peminjaman_items_ibfk_2` FOREIGN KEY (`detail_peminjaman_id`) REFERENCES `detail_peminjaman` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create performance index
CREATE INDEX `idx_extend_items_lookup` ON `extend_peminjaman_items` (`detail_peminjaman_id`, `extend_peminjaman_id`);
