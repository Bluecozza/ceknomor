-- ============================================================
-- migrate-002-fix-columns.sql
-- Jalankan ini jika muncul error "Unknown column" pada database
-- yang sudah diimport sebelumnya.
--
-- Via phpMyAdmin: pilih database cek_resource → tab SQL → paste → jalankan
-- Via CLI:        mysql -u root cek_resource < migrate-002-fix-columns.sql
-- ============================================================

USE `cek_resource`;

-- ── Tabel admins ─────────────────────────────────────────────
ALTER TABLE `admins`
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS `last_login_ip` VARCHAR(45) DEFAULT NULL;

-- ── Tabel categories ─────────────────────────────────────────
-- placeholder_text tidak ada di schema, tidak perlu ditambah
-- (sudah dihandle secara dinamis di PHP)

-- ── Tabel report_types ───────────────────────────────────────
-- color_class tidak ada di schema, tidak perlu ditambah
-- (sudah dihandle secara dinamis di PHP berdasarkan severity)

-- ── Tabel search_logs ────────────────────────────────────────
ALTER TABLE `search_logs`
    ADD COLUMN IF NOT EXISTS `query_normalized` VARCHAR(255) DEFAULT NULL AFTER `query`,
    ADD COLUMN IF NOT EXISTS `category`         VARCHAR(50)  DEFAULT NULL AFTER `query_normalized`,
    ADD COLUMN IF NOT EXISTS `has_result`        TINYINT(1)  DEFAULT 0    AFTER `results_count`;

-- Tambah index jika belum ada (abaikan error jika sudah ada)
ALTER TABLE `search_logs`
    ADD INDEX IF NOT EXISTS `idx_has_result` (`has_result`);

-- ── Tabel activity_logs ───────────────────────────────────────
-- Pastikan kolom menggunakan nama baru (entity_type bukan target_type)
-- Jika tabel masih pakai kolom lama, rename:
ALTER TABLE `activity_logs`
    ADD COLUMN IF NOT EXISTS `entity_type` VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `entity_id`   INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `description` TEXT DEFAULT NULL;

-- Jika kolom lama masih ada, salin datanya ke kolom baru
UPDATE `activity_logs`
SET
    `entity_type` = COALESCE(`entity_type`, `target_type`),
    `entity_id`   = COALESCE(`entity_id`,   `target_id`),
    `description` = COALESCE(`description`, `details`)
WHERE `entity_type` IS NULL OR `entity_id` IS NULL;

-- ── Verifikasi ────────────────────────────────────────────────
SELECT 'admins'        AS tabel, COUNT(*) AS kolom FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins'
UNION ALL
SELECT 'categories',    COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories'
UNION ALL
SELECT 'report_types',  COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'report_types'
UNION ALL
SELECT 'reports',       COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports'
UNION ALL
SELECT 'search_logs',   COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'search_logs'
UNION ALL
SELECT 'activity_logs', COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity_logs';

SELECT 'Migration selesai!' AS status;

-- ── Tabel api_keys (rebuild dengan schema baru) ───────────────
-- Hapus tabel lama dan buat ulang dengan kolom yang benar
-- PERINGATAN: Data API key lama akan hilang!
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
  `created_by`   INT UNSIGNED,
  `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

SELECT 'api_keys table recreated' AS status;
