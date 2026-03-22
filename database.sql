-- ============================================================
-- DATABASE: cek_resource
-- Project: cek.resource.my.id
-- Description: Platform pelaporan data sensitif (nomor telepon,
--              rekening, akun keuangan) untuk deteksi penipuan
-- ============================================================

CREATE DATABASE IF NOT EXISTS `cek_resource` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `cek_resource`;

-- ------------------------------------------------------------
-- TABLE: categories
-- Kategori jenis data yang bisa dilaporkan
-- ------------------------------------------------------------
CREATE TABLE `categories` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,           -- e.g. "Nomor Telepon"
  `slug`        VARCHAR(100) NOT NULL UNIQUE,    -- e.g. "phone"
  `icon`        VARCHAR(50)  DEFAULT 'bi-question-circle',
  `description` TEXT,
  `is_active`   TINYINT(1)   DEFAULT 1,
  `sort_order`  INT          DEFAULT 0,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `categories` (`name`, `slug`, `icon`, `description`, `sort_order`) VALUES
('Nomor Telepon',    'phone',       'bi-telephone',       'Nomor HP/WA yang dilaporkan',                     1),
('Rekening Bank',    'bank_account','bi-bank',            'Nomor rekening bank yang dilaporkan',             2),
('Akun DANA',        'dana',        'bi-wallet2',         'Nomor/akun DANA yang dilaporkan',                 3),
('Akun OVO',         'ovo',         'bi-wallet',          'Nomor/akun OVO yang dilaporkan',                  4),
('Akun GoPay',       'gopay',       'bi-phone',           'Nomor/akun GoPay yang dilaporkan',                5),
('Akun ShopeePay',   'shopeepay',   'bi-bag',             'Nomor/akun ShopeePay yang dilaporkan',            6),
('Akun LinkAja',     'linkaja',     'bi-link-45deg',      'Nomor/akun LinkAja yang dilaporkan',              7),
('Email',            'email',       'bi-envelope',        'Alamat email yang dilaporkan',                    8),
('Akun Media Sosial','social',      'bi-people',          'Akun media sosial yang dilaporkan',               9),
('Lainnya',          'other',       'bi-exclamation-circle','Jenis data lainnya',                            10);

-- ------------------------------------------------------------
-- TABLE: report_types
-- Jenis/alasan pelaporan
-- ------------------------------------------------------------
CREATE TABLE `report_types` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `severity`    TINYINT(1) DEFAULT 3 COMMENT '1=ringan, 2=sedang, 3=berat, 4=sangat berat',
  `is_active`   TINYINT(1) DEFAULT 1,
  `sort_order`  INT DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO `report_types` (`name`, `slug`, `description`, `severity`, `sort_order`) VALUES
('Penipuan Online',         'online_fraud',    'Pelaku melakukan penipuan via online',         4, 1),
('Penjual Fiktif',          'fake_seller',     'Menjual barang/jasa tidak ada/palsu',          4, 2),
('Investasi Bodong',        'fake_investment', 'Menawarkan investasi ilegal/tidak jelas',      4, 3),
('Pinjol Ilegal',           'illegal_loan',    'Pinjaman online ilegal/berbunga tinggi',       4, 4),
('Pemerasan/Blackmail',     'blackmail',       'Memeras atau mengancam korban',                4, 5),
('Judi Online',             'gambling',        'Terlibat kegiatan judi online',                3, 6),
('Spam/Iklan Berlebihan',   'spam',            'Mengirim spam atau iklan tidak diminta',       1, 7),
('Penipuan Berkedok Hadiah','prize_scam',      'Mengaku ada hadiah palsu',                    4, 8),
('Modus Pacaran (Love Scam)','love_scam',      'Penipuan berkedok romansa/asmara',            4, 9),
('Lainnya',                 'other',           'Kasus lain yang tidak tercantum',              2, 10);

-- ------------------------------------------------------------
-- TABLE: reporters
-- Data pelapor (tidak wajib login)
-- ------------------------------------------------------------
CREATE TABLE `reporters` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(150) NOT NULL,
  `contact`      VARCHAR(200) NOT NULL COMMENT 'Email atau nomor telepon pelapor',
  `contact_type` ENUM('email','phone','wa') DEFAULT 'email',
  `ip_address`   VARCHAR(45),
  `user_agent`   TEXT,
  `is_verified`  TINYINT(1) DEFAULT 0,
  `verify_token` VARCHAR(100),
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: reports
-- Laporan utama
-- ------------------------------------------------------------
CREATE TABLE `reports` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ulid`            VARCHAR(26) NOT NULL UNIQUE COMMENT 'ULID untuk URL publik',
  `category_id`     INT UNSIGNED NOT NULL,
  `report_type_id`  INT UNSIGNED NOT NULL,
  `reported_value`  VARCHAR(255) NOT NULL COMMENT 'Nilai data yang dilaporkan (no HP, no rek, dsb)',
  `reported_value_normalized` VARCHAR(255) NOT NULL COMMENT 'Nilai yang sudah dinormalisasi untuk pencarian',
  `bank_name`       VARCHAR(100) COMMENT 'Nama bank jika rekening',
  `account_name`    VARCHAR(150) COMMENT 'Nama pemilik rekening/akun',
  `title`           VARCHAR(255) NOT NULL COMMENT 'Judul singkat laporan',
  `description`     TEXT NOT NULL COMMENT 'Deskripsi lengkap kejadian',
  `evidence_urls`   JSON COMMENT 'Array URL bukti/screenshot',
  `reporter_id`     INT UNSIGNED NOT NULL,
  `incident_date`   DATE COMMENT 'Tanggal kejadian',
  `amount_lost`     DECIMAL(15,2) DEFAULT NULL COMMENT 'Jumlah kerugian (opsional)',
  `currency`        VARCHAR(5) DEFAULT 'IDR',
  `status`          ENUM('pending','approved','rejected','flagged') DEFAULT 'pending',
  `is_anonymous`    TINYINT(1) DEFAULT 0 COMMENT 'Sembunyikan nama pelapor',
  `admin_note`      TEXT,
  `moderated_by`    INT UNSIGNED DEFAULT NULL,
  `moderated_at`    TIMESTAMP NULL,
  `view_count`      INT UNSIGNED DEFAULT 0,
  `helpful_count`   INT UNSIGNED DEFAULT 0,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`category_id`)    REFERENCES `categories`(`id`),
  FOREIGN KEY (`report_type_id`) REFERENCES `report_types`(`id`),
  FOREIGN KEY (`reporter_id`)    REFERENCES `reporters`(`id`),
  
  INDEX `idx_reported_value`  (`reported_value_normalized`),
  INDEX `idx_status`          (`status`),
  INDEX `idx_category`        (`category_id`),
  INDEX `idx_created_at`      (`created_at`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: risk_scores
-- Skor risiko agregat per data yang dilaporkan
-- Di-update setiap kali ada laporan baru (atau via cron)
-- ------------------------------------------------------------
CREATE TABLE `risk_scores` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reported_value_normalized` VARCHAR(255) NOT NULL UNIQUE,
  `category_id`          INT UNSIGNED,
  `total_reports`        INT UNSIGNED DEFAULT 0,
  `approved_reports`     INT UNSIGNED DEFAULT 0,
  `risk_score`           DECIMAL(5,2) DEFAULT 0 COMMENT 'Skor 0-100',
  `risk_level`           ENUM('unknown','safe','low','medium','high','critical') DEFAULT 'unknown',
  `last_reported_at`     TIMESTAMP NULL,
  `first_reported_at`    TIMESTAMP NULL,
  `updated_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`),
  INDEX `idx_risk_level` (`risk_level`),
  INDEX `idx_risk_score` (`risk_score`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: admins
-- Admin panel users
-- ------------------------------------------------------------
CREATE TABLE `admins` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(150) NOT NULL,
  `email`        VARCHAR(200) NOT NULL UNIQUE,
  `password`     VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  `role`         ENUM('superadmin','admin','moderator') DEFAULT 'moderator',
  `is_active`    TINYINT(1) DEFAULT 1,
  `last_login_at` TIMESTAMP NULL,
  `last_login_ip` VARCHAR(45),
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- CATATAN: Akun admin default TIDAK disertakan di sini
-- karena hash bcrypt harus digenerate oleh PHP server Anda.
--
-- Setelah import, buat akun admin dengan salah satu cara:
--   1. Buka: http://localhost/cek-resource/reset-admin.php
--   2. Atau jalankan: php artisan.php admin:create
-- ============================================================

-- ------------------------------------------------------------
-- TABLE: modules
-- Modul yang bisa diaktifkan/nonaktifkan oleh admin
-- ------------------------------------------------------------
CREATE TABLE `modules` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(100) NOT NULL,
  `slug`          VARCHAR(100) NOT NULL UNIQUE,
  `description`   TEXT,
  `version`       VARCHAR(20) DEFAULT '1.0.0',
  `author`        VARCHAR(100),
  `is_enabled`    TINYINT(1) DEFAULT 1,
  `is_core`       TINYINT(1) DEFAULT 0 COMMENT 'Modul inti tidak bisa dinonaktifkan',
  `config`        JSON COMMENT 'Konfigurasi modul dalam JSON',
  `installed_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `modules` (`name`, `slug`, `description`, `version`, `is_enabled`, `is_core`) VALUES
('Analytics',   'analytics', 'Modul analitik pengunjung dan laporan',  '1.0.0', 1, 0),
('Sharing',     'sharing',   'Modul berbagi laporan ke media sosial',   '1.0.0', 1, 0),
('API Public',  'api_public','Modul REST API untuk aplikasi mobile',    '1.0.0', 1, 1),
('Notifikasi',  'notification','Modul notifikasi email ke pelapor',     '1.0.0', 0, 0),
('reCAPTCHA',   'recaptcha', 'Proteksi form dengan Google reCAPTCHA',   '1.0.0', 0, 0);

-- ------------------------------------------------------------
-- TABLE: settings
-- Pengaturan aplikasi (key-value)
-- ------------------------------------------------------------
CREATE TABLE `settings` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key`         VARCHAR(100) NOT NULL UNIQUE,
  `value`       TEXT,
  `type`        ENUM('string','integer','boolean','json') DEFAULT 'string',
  `group`       VARCHAR(50) DEFAULT 'general',
  `label`       VARCHAR(150),
  `description` TEXT,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `settings` (`key`, `value`, `type`, `group`, `label`) VALUES
('site_name',           'Cek Resource',          'string',  'general',      'Nama Website'),
('site_tagline',        'Database Laporan Penipuan & Data Bermasalah', 'string', 'general', 'Tagline'),
('site_email',          'info@cek.resource.my.id','string', 'general',      'Email Website'),
('site_url',            'https://cek.resource.my.id', 'string', 'general',  'URL Website'),
('require_moderation',  '1',                      'boolean', 'reports',     'Laporan harus dimoderasi'),
('auto_approve',        '0',                      'boolean', 'reports',     'Auto-approve laporan'),
('risk_threshold_low',  '20',                     'integer', 'risk',        'Skor risiko rendah'),
('risk_threshold_medium','50',                    'integer', 'risk',        'Skor risiko sedang'),
('risk_threshold_high', '75',                     'integer', 'risk',        'Skor risiko tinggi'),
('recaptcha_site_key',  '',                       'string',  'security',    'reCAPTCHA Site Key'),
('recaptcha_secret_key','',                       'string',  'security',    'reCAPTCHA Secret Key'),
('max_reports_per_ip',  '10',                     'integer', 'security',    'Maks laporan per IP per hari'),
('api_rate_limit',      '60',                     'integer', 'api',         'Rate limit API per menit');

-- ------------------------------------------------------------
-- TABLE: api_keys
-- API key untuk aplikasi client (Android, iOS, dll)
-- ------------------------------------------------------------
CREATE TABLE `api_keys` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(150) NOT NULL COMMENT 'Nama aplikasi',
  `api_key`      VARCHAR(64)  NOT NULL UNIQUE,
  `api_secret`   VARCHAR(128) NOT NULL,
  `permissions`  JSON COMMENT '["read","write","admin"]',
  `is_active`    TINYINT(1) DEFAULT 1,
  `rate_limit`   INT DEFAULT 60 COMMENT 'Request per menit',
  `last_used_at` TIMESTAMP NULL,
  `created_by`   INT UNSIGNED,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`created_by`) REFERENCES `admins`(`id`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: api_logs
-- Log request API
-- ------------------------------------------------------------
CREATE TABLE `api_logs` (
  `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `api_key_id`   INT UNSIGNED,
  `endpoint`     VARCHAR(255),
  `method`       VARCHAR(10),
  `ip_address`   VARCHAR(45),
  `request_body` TEXT,
  `response_code` SMALLINT,
  `response_time_ms` INT,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_api_key`    (`api_key_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: activity_logs
-- Log aktivitas admin
-- ------------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id`    INT UNSIGNED,
  `action`      VARCHAR(100),
  `entity_type` VARCHAR(50),
  `entity_id`   INT UNSIGNED,
  `description` TEXT,
  `ip_address`  VARCHAR(45),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_admin_id`    (`admin_id`),
  INDEX `idx_entity`      (`entity_type`, `entity_id`),
  INDEX `idx_created_at`  (`created_at`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: analytics_page_views (Module: Analytics)
-- ------------------------------------------------------------
CREATE TABLE `analytics_page_views` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `page`        VARCHAR(255),
  `referrer`    VARCHAR(500),
  `ip_address`  VARCHAR(45),
  `user_agent`  TEXT,
  `session_id`  VARCHAR(64),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_page`       (`page`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: search_logs
-- Log pencarian untuk analytics
-- ------------------------------------------------------------
CREATE TABLE `search_logs` (
  `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `query`            VARCHAR(255) NOT NULL,
  `query_normalized` VARCHAR(255),
  `category`         VARCHAR(50) DEFAULT NULL,
  `results_count`    INT UNSIGNED DEFAULT 0,
  `has_result`       TINYINT(1)  DEFAULT 0,
  `ip_address`       VARCHAR(45),
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX `idx_query`         (`query`),
  INDEX `idx_normalized`    (`query_normalized`),
  INDEX `idx_has_result`    (`has_result`),
  INDEX `idx_created_at`    (`created_at`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: analytics_search_daily
-- Agregasi harian pencarian (digunakan modul analytics)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `analytics_search_daily` (
  `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `date`             DATE NOT NULL,
  `query_normalized` VARCHAR(255) NOT NULL,
  `category`         VARCHAR(50)  NOT NULL DEFAULT 'all',
  `search_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `result_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `last_searched_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY `uq_date_query_cat` (`date`, `query_normalized`, `category`),
  INDEX `idx_date`  (`date`),
  INDEX `idx_query` (`query_normalized`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABLE: analytics_reports_daily
-- Agregasi laporan masuk per hari per kategori
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `analytics_reports_daily` (
  `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `date`         DATE NOT NULL,
  `category_id`  INT  UNSIGNED NOT NULL DEFAULT 0,
  `report_count` INT  UNSIGNED NOT NULL DEFAULT 0,

  UNIQUE KEY `uq_date_cat` (`date`, `category_id`),
  INDEX `idx_date` (`date`)
) ENGINE=InnoDB;
