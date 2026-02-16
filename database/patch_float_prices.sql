-- Patch to convert price fields to FLOAT type
-- File: patch_float_prices.sql
-- Description: Convert harga_satuan from DECIMAL(15,2) to FLOAT and add total_harga field

SET FOREIGN_KEY_CHECKS = 0;

-- Modify pembelian_barang table
ALTER TABLE `pembelian_barang` 
MODIFY COLUMN `harga_satuan` FLOAT DEFAULT NULL;

-- Add total_harga column if it doesn't exist
ALTER TABLE `pembelian_barang` 
ADD COLUMN `total_harga` FLOAT DEFAULT NULL AFTER `harga_satuan`;

SET FOREIGN_KEY_CHECKS = 1;

SET SQL_NOTES = 1;
