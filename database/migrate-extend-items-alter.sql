-- Migration: Alter extend_peminjaman_items to support per-unit tracking
-- Changes structure from (barang_id, qty_extend) to (detail_peminjaman_id, unit_number, tanggal_perpanjang)

-- Backup existing data if any
CREATE TABLE IF NOT EXISTS `extend_peminjaman_items_backup` LIKE `extend_peminjaman_items`;
INSERT INTO `extend_peminjaman_items_backup` SELECT * FROM `extend_peminjaman_items`;

-- Drop existing foreign keys and indexes
ALTER TABLE `extend_peminjaman_items` 
  DROP CONSTRAINT IF EXISTS `fk_extend_peminjaman_items_barang`,
  DROP CONSTRAINT IF EXISTS `FK_extend_peminjaman_items_peminjaman`,
  DROP KEY IF EXISTS `barang_id`;

-- Drop old columns
ALTER TABLE `extend_peminjaman_items` 
  DROP COLUMN IF EXISTS `barang_id`,
  DROP COLUMN IF EXISTS `qty_extend`;

-- Add new columns for per-unit tracking
ALTER TABLE `extend_peminjaman_items` 
  ADD COLUMN `detail_peminjaman_id` INT(11) NOT NULL AFTER `extend_peminjaman_id`,
  ADD COLUMN `unit_number` INT(11) NOT NULL AFTER `detail_peminjaman_id`,
  ADD COLUMN `tanggal_perpanjang` DATE NOT NULL AFTER `unit_number`;

-- Add new foreign key
ALTER TABLE `extend_peminjaman_items`
  ADD CONSTRAINT `extend_peminjaman_items_ibfk_detail` FOREIGN KEY (`detail_peminjaman_id`) 
    REFERENCES `detail_peminjaman` (`id`) ON DELETE CASCADE;

-- Add unique constraint for per-unit tracking
ALTER TABLE `extend_peminjaman_items`
  ADD UNIQUE KEY `unique_extend_unit` 
    (`extend_peminjaman_id`, `detail_peminjaman_id`, `unit_number`);

-- Add performance index
CREATE INDEX `idx_extend_items_lookup` ON `extend_peminjaman_items` 
  (`detail_peminjaman_id`, `extend_peminjaman_id`);
