-- ============================================================
-- fix-database-safe.sql
-- Versi aman: cek kolom dulu sebelum ALTER, tidak error jika
-- kolom sudah ada. Compatible MySQL 5.7+
-- 
-- Jalankan SELURUH file sekaligus di phpMyAdmin tab SQL
-- ============================================================

USE `cek_resource`;

-- Aktifkan delimiter custom untuk stored procedure
DROP PROCEDURE IF EXISTS `add_col_if_missing`;

DELIMITER $$
CREATE PROCEDURE `add_col_if_missing`(
    IN tbl VARCHAR(64),
    IN col VARCHAR(64),
    IN col_def TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = tbl
          AND COLUMN_NAME  = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', col_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('Added: ', tbl, '.', col) AS result;
    ELSE
        SELECT CONCAT('Already exists: ', tbl, '.', col) AS result;
    END IF;
END$$
DELIMITER ;

-- ── admins ────────────────────────────────────────────────────
CALL add_col_if_missing('admins', 'updated_at',    'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
CALL add_col_if_missing('admins', 'last_login_ip', 'VARCHAR(45) DEFAULT NULL');

-- ── search_logs ───────────────────────────────────────────────
CALL add_col_if_missing('search_logs', 'query_normalized', 'VARCHAR(255) DEFAULT NULL');
CALL add_col_if_missing('search_logs', 'category',         'VARCHAR(50) DEFAULT NULL');
CALL add_col_if_missing('search_logs', 'has_result',       'TINYINT(1) DEFAULT 0');

-- ── activity_logs ─────────────────────────────────────────────
CALL add_col_if_missing('activity_logs', 'entity_type', 'VARCHAR(50) DEFAULT NULL');
CALL add_col_if_missing('activity_logs', 'entity_id',   'INT UNSIGNED DEFAULT NULL');
CALL add_col_if_missing('activity_logs', 'description', 'TEXT DEFAULT NULL');

-- ── api_keys (WAJIB rebuild) ──────────────────────────────────
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

-- ── Cleanup ───────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS `add_col_if_missing`;

SELECT 'fix-database-safe.sql selesai!' AS status;
