CREATE DATABASE IF NOT EXISTS `cek_resource` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `cek_resource`;
-- ════════════════���══════════════════════════════════════════════════════════
-- COMPREHENSIVE DATABASE SCHEMA
-- cek.resource.my.id - Fraud Report Platform v2.0
-- Updated: 2026-03-24
-- ═══════════════════════════════════════════════════════════════════════════

SET CHARACTER SET utf8mb4;
SET COLLATION_CONNECTION = utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- 1. CATEGORIES
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    slug VARCHAR(255) NOT NULL UNIQUE,
    icon VARCHAR(50),
    description LONGTEXT,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(slug),
    INDEX(is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────��──────────────────────────────────────────────────────────────
-- 2. REPORT TYPES
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS report_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description LONGTEXT,
    severity INT DEFAULT 2,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    category_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX(slug),
    INDEX(severity),
    INDEX(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────
-- 3. REPORTERS
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS reporters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    contact VARCHAR(255),
    contact_type ENUM('phone','email','other') DEFAULT 'phone',
    ip_address VARCHAR(45),
    user_agent LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(contact),
    INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────
-- 4. REPORTS (Main Table)
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ulid VARCHAR(26) NOT NULL UNIQUE,
    reporter_id INT,
    category_id INT NOT NULL,
    report_type_id INT,
    title VARCHAR(255),
    description LONGTEXT,
    reported_value VARCHAR(255),
    reported_value_normalized VARCHAR(255),
    suspect_name VARCHAR(255),
    phones JSON,
    bank_account VARCHAR(50),
    bank_name VARCHAR(100),
    account_name VARCHAR(100),
    links JSON,
    modus JSON,
    keywords JSON,
    source_url VARCHAR(2048),
    image_url VARCHAR(2048),
    evidence_urls JSON,
    incident_date DATE,
    amount_lost DECIMAL(15,2),
    status ENUM('pending','approved','rejected','flagged') DEFAULT 'pending',
    created_by_import TINYINT(1) DEFAULT 0,
    import_session_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(reporter_id) REFERENCES reporters(id) ON DELETE SET NULL,
    FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    FOREIGN KEY(report_type_id) REFERENCES report_types(id) ON DELETE SET NULL,
    INDEX(ulid),
    INDEX(status),
    INDEX(category_id),
    INDEX(reported_value_normalized),
    INDEX(created_at),
    INDEX(created_by_import)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────
-- 5. RISK SCORES
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS risk_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reported_value_normalized VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    total_reports INT DEFAULT 1,
    approved_reports INT DEFAULT 0,
    risk_score DECIMAL(5,2) DEFAULT 0,
    risk_level ENUM('safe','low','medium','high','critical') DEFAULT 'safe',
    last_reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_value (reported_value_normalized, category_id),
    INDEX(risk_level),
    INDEX(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────
-- 6. SEARCH LOGS
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS search_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query VARCHAR(255) NOT NULL,
    query_normalized VARCHAR(255),
    category_id INT,
    results_count INT DEFAULT 0,
    has_result TINYINT(1) DEFAULT 0,
    ip_address VARCHAR(45),
    user_agent LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX(query),
    INDEX(created_at),
    INDEX(has_result)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────
-- 7. ADMINS
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('superadmin','admin','moderator') DEFAULT 'moderator',
    is_active TINYINT(1) DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(email),
    INDEX(role),
    INDEX(is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────
-- 8. ACTIVITY LOGS
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action VARCHAR(100),
    entity_type VARCHAR(50),
    entity_id INT,
    description LONGTEXT,
    ip_address VARCHAR(45),
    user_agent LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX(admin_id),
    INDEX(action),
    INDEX(entity_type),
    INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────
-- 9. API KEYS
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    key_hash VARCHAR(64) NOT NULL UNIQUE,
    key_prefix VARCHAR(20),
    permissions JSON,
    rate_limit INT DEFAULT 60,
    is_active TINYINT(1) DEFAULT 1,
    usage_count INT DEFAULT 0,
    last_used_at TIMESTAMP NULL,
    expires_at DATE NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(created_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX(key_hash),
    INDEX(is_active),
    INDEX(created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────
-- 10. SETTINGS
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(255) NOT NULL UNIQUE,
    value LONGTEXT,
    type ENUM('string','integer','boolean','json') DEFAULT 'string',
    `group` VARCHAR(50),
    label VARCHAR(255),
    description LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(`key`),
    INDEX(`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────
-- 11. PLUGIN MANAGEMENT TABLES (for modular architecture)
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS plugins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description LONGTEXT,
    version VARCHAR(50),
    author VARCHAR(255),
    path VARCHAR(255),
    config JSON,
    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(slug),
    INDEX(is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plugin_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plugin VARCHAR(255) NOT NULL,
    migration VARCHAR(255) NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_migration (plugin, migration),
    INDEX(plugin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plugin_slug VARCHAR(255),
    page_slug VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    icon VARCHAR(50),
    order_by INT DEFAULT 0,
    file_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_page (plugin_slug, page_slug),
    INDEX(plugin_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────────────
-- 12. IMPORT LOGS (CSV Import Plugin)
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS import_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    admin_id INT NOT NULL,
    file_name VARCHAR(255),
    total_records INT DEFAULT 0,
    successful INT DEFAULT 0,
    failed INT DEFAULT 0,
    errors JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX(admin_id),
    INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════════════════════════════════════════════════════════════════════
-- SEED DATA
-- ═══════════════════════════════════════════════════════════════════════════

-- Default categories
INSERT IGNORE INTO categories (name, slug, icon, description, is_active, sort_order) VALUES
('Nomor Telepon', 'phone', 'fa-phone', 'Laporan nomor telepon yang mencurigakan', 1, 1),
('Rekening Bank', 'bank_account', 'fa-university', 'Laporan rekening bank yang diduga fraud', 1, 2),
('DANA', 'dana', 'fa-wallet', 'Laporan akun DANA yang mencurigakan', 1, 3),
('OVO', 'ovo', 'fa-credit-card', 'Laporan akun OVO yang mencurigakan', 1, 4),
('GoPay', 'gopay', 'fa-mobile', 'Laporan akun GoPay yang mencurigakan', 1, 5),
('LinkAja', 'linkaja', 'fa-link', 'Laporan akun LinkAja yang mencurigakan', 1, 6),
('ShopeePay', 'shopeepay', 'fa-shopping-bag', 'Laporan akun ShopeePay yang mencurigakan', 1, 7),
('Email', 'email', 'fa-envelope', 'Laporan email yang mencurigakan', 1, 8),
('Social Media', 'social', 'fa-share-alt', 'Laporan akun media sosial yang mencurigakan', 1, 9),
('Lainnya', 'other', 'fa-question-circle', 'Laporan jenis lain', 1, 10);

-- Default report types
INSERT IGNORE INTO report_types (name, slug, description, severity, is_active, sort_order) VALUES
('Penipuan', 'fraud', 'Penipuan online atau offline', 4, 1, 1),
('Pencurian Identitas', 'identity_theft', 'Pencurian data identitas pribadi', 4, 1, 2),
('Skam', 'scam', 'Skam atau modus penipuan', 3, 1, 3),
('Money Mule', 'money_mule', 'Aktivitas money mule atau pencucian uang', 4, 1, 4),
('Pinjol Ilegal', 'illegal_lending', 'Pinjaman online ilegal', 3, 1, 5),
('Lainnya', 'other', 'Jenis laporan lain', 2, 1, 6);

-- Default settings
INSERT IGNORE INTO settings (`key`, value, type, `group`, label, description) VALUES
('site_name', 'Cek.Resource', 'string', 'general', 'Nama Situs', 'Nama resmi platform'),
('site_url', 'https://cek.resource.my.id', 'string', 'general', 'URL Situs', 'URL utama platform'),
('max_upload_size', '5242880', 'integer', 'general', 'Ukuran Upload Maksimal', 'Maksimal ukuran file (bytes)'),
('enable_registration', '1', 'boolean', 'general', 'Aktifkan Registrasi', 'Biarkan pengguna baru mendaftar'),
('jwt_expire', '86400', 'integer', 'security', 'JWT Expiry', 'Token JWT berlaku (detik)'),
('rate_limit_login', '5', 'integer', 'security', 'Rate Limit Login', 'Maksimal login attempts'),
('rate_limit_search', '30', 'integer', 'security', 'Rate Limit Search', 'Maksimal search per menit'),
('maintenance_mode', '0', 'boolean', 'general', 'Mode Pemeliharaan', 'Tutup platform untuk maintenance');

-- Default admin (superadmin)
INSERT IGNORE INTO admins (name, email, password, role, is_active) VALUES
('Super Admin', 'admin@cek.resource.my.id', '$2y$10$92IXUNpkm1Qx5CTJeXN9C.PSZbGBy4z.KAGbNjRKnWBmZcR9m3Zhi', 'superadmin', 1);

-- ═══════════════════════════════════════════════════════════════════════════
-- END OF DATABASE SCHEMA
-- ═══════════════════════════════════════════════════════════════════════════