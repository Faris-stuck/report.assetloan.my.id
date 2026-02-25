-- Migration: Add per-unit extend tracking support
-- Creates extend_peminjaman_items table for tracking extends per individual unit

-- Create extend_peminjaman_items table
CREATE TABLE IF NOT EXISTS `extend_peminjaman_items` (
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

-- Alter extend_peminjaman to support single extended date for the whole request
-- (This allows backward compatibility: when no items specified, applies to all units)
-- No changes needed - existing tanggal_perpanjang field serves this purpose
