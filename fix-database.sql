-- ============================================================
-- fix-database.sql — Compatible MySQL 5.7+
-- 
-- CARA PAKAI:
-- Jalankan TIAP blok ALTER TABLE secara terpisah di phpMyAdmin.
-- Jika muncul error "Duplicate column name" — ABAIKAN, 
-- artinya kolom sudah ada dan tidak perlu ditambah.
-- Yang WAJIB dijalankan adalah bagian DROP/CREATE api_keys.
-- ============================================================

USE `cek_resource`;

-- ============================================================
-- WAJIB: Rebuild tabel api_keys (schema lama tidak kompatibel)
-- ============================================================
DROP TABLE IF EXISTS `api_keys`;

CREATE TABLE `api_keys` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(150) NOT NULL,
  `key_hash`     VARCHAR(64)  NOT NULL UNIQUE,
  `key_prefix`   VARCHAR(20)  NOT NULL,
  `permissions`  JSON,
  `is_active`    TINYINT(1)   DEFAULT 1,
  `rate_limit`   INT          DEFAULT 60,
  `usage_count`  INT UNSIGNED DEFAULT 0,
  `last_used_at` TIMESTAMP    NULL,
  `expires_at`   TIMESTAMP    NULL,
  `created_by`   INT UNSIGNED DEFAULT NULL,
  `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- OPSIONAL: Jalankan satu per satu, abaikan "Duplicate column"
-- ============================================================

-- admins
ALTER TABLE `admins` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `admins` ADD COLUMN `last_login_ip` VARCHAR(45) DEFAULT NULL;

-- search_logs
ALTER TABLE `search_logs` ADD COLUMN `query_normalized` VARCHAR(255) DEFAULT NULL AFTER `query`;
ALTER TABLE `search_logs` ADD COLUMN `category` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `search_logs` ADD COLUMN `has_result` TINYINT(1) DEFAULT 0;

-- activity_logs
ALTER TABLE `activity_logs` ADD COLUMN `entity_type` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `activity_logs` ADD COLUMN `entity_id` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `activity_logs` ADD COLUMN `description` TEXT DEFAULT NULL;

SELECT 'Selesai!' AS status;
