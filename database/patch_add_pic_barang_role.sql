-- Tambah role 'pic_barang' ke tabel users
-- Jalankan: mysql -u root -p peminjaman < patch_add_pic_barang_role.sql

USE `peminjaman`;

ALTER TABLE `users`
MODIFY COLUMN `role` enum('admin','manager','user','pic_barang') NOT NULL
COMMENT 'admin=Admin, manager=Approver, user=Requester, pic_barang=PIC Barang';

-- (Opsional) Buat akun default PIC Barang
-- Password: picbarang123
INSERT INTO `users` (`nama`, `nrp`, `email`, `password`, `role`)
SELECT 'PIC Barang', '300001', 'picbarang@komatsu.co.id',
       '$2y$10$bemvlCf8z9dcjnBmUMUya.XvLnZ6ZoNKE3G1uzCXKVW3k2RWIcTEu',
       'pic_barang'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE email = 'picbarang@komatsu.co.id');
