-- ============================================================
-- MIGRATION: Ubah kolom status dari ENUM ke VARCHAR(50) 
-- untuk mendukung status dinamis seperti 'Due in X Days',
-- 'Due Tomorrow', 'Due Today', 'Overdue'
-- Jalankan SQL ini di phpMyAdmin atau MySQL CLI
-- ============================================================

ALTER TABLE `peminjaman` 
MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'Menunggu Persetujuan';
