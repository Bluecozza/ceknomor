-- ============================================================
-- migrate-001-fix-admins.sql
-- Jalankan ini jika database sudah diimport sebelumnya
-- dan muncul error "Unknown column 'updated_at'"
--
-- Via phpMyAdmin: pilih database cek_resource, tab SQL, paste & jalankan
-- Via CLI: mysql -u root cek_resource < migrate-001-fix-admins.sql
-- ============================================================

USE `cek_resource`;

-- Tambah kolom updated_at jika belum ada
ALTER TABLE `admins`
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Tambah kolom lain yang mungkin kurang
ALTER TABLE `admins`
    ADD COLUMN IF NOT EXISTS `last_login_ip` VARCHAR(45) DEFAULT NULL;

-- Verifikasi struktur tabel
DESCRIBE `admins`;
