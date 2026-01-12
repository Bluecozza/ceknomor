CREATE TABLE numbers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number VARCHAR(32) NOT NULL UNIQUE,
  reputation_score FLOAT DEFAULT 0,
  last_report_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_number (number),
  INDEX idx_last_report_at (last_report_at)
) ENGINE=InnoDB;
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  weight INT DEFAULT 1
) ENGINE=InnoDB;
INSERT INTO categories (name, weight) VALUES
('spam', 2),
('penipuan', 7),
('fitnah', 3),
('rasis', 3),
('pencemaran', 3),
('kekerasan', 3),
('perdagangan', 7),
('narkoba', 7),
('pencurian', 4),
('perampokan', 7),
('bully', 3),
('other', 0);

CREATE TABLE reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- legacy (sementara)
  number_id BIGINT UNSIGNED NULL,
  category VARCHAR(32) DEFAULT 'unknown',

  -- data utama
  title VARCHAR(150) NULL,
  description TEXT,
  full_description MEDIUMTEXT NULL,

  -- metadata
  loss_amount BIGINT NULL,
  chronology_link VARCHAR(255) NULL,
  reporter_name VARCHAR(100) NULL,
  reporter_contact VARCHAR(100) NULL,
  reporter_hash CHAR(64) NULL,

  source ENUM('manual','batch','csv','api') DEFAULT 'manual',
  status ENUM('pending','approved','rejected') DEFAULT 'pending',

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_reports_numbers
    FOREIGN KEY (number_id) REFERENCES numbers(id)
    ON DELETE SET NULL,

  INDEX idx_number_id (number_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE report_categories (
  report_id BIGINT UNSIGNED NOT NULL,
  category_id INT NOT NULL,
  PRIMARY KEY (report_id, category_id),
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE report_phones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  phone_number VARCHAR(32) NOT NULL,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  INDEX idx_phone_number (phone_number)
) ENGINE=InnoDB;

CREATE TABLE report_bank_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  bank_name VARCHAR(50) NOT NULL,
  account_number VARCHAR(50) NOT NULL,
  bank_type VARCHAR(50) NOT NULL,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;
INSERT INTO report_bank_accounts (report_id, bank_name, account_number, bank_type) VALUES
(1,'contoh',00100,"BRI"),
(1,'contoh',00100,"BNI"),
(1,'contoh',00100,"BCA"),
(1,'contoh',00100,"MANDIRI"),
(1,'contoh',00100,"BTN"),
(1,'contoh',00100,"BSI"),
(1,'contoh',00100,"PERMATA"),
(1,'contoh',00100,"SeaBANK"),
(1,'contoh',00100,"DANA"),
(1,'contoh',00100,"OVO"),
(1,'contoh',00100,"GOPAY"),
(1,'contoh',00100,"Shopee"),
(1,'contoh',00100,"LinkAJA"),
(1,'contoh',00100,"Paypal"),
(1,'contoh',00100,"BTPN"),
(1,'contoh',00100,"Lainnya");


CREATE TABLE report_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_size INT NOT NULL,
  file_type VARCHAR(50),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

--upgrade dari data lama

INSERT IGNORE INTO report_categories (report_id, category_id)
SELECT r.id, c.id
FROM reports r
JOIN categories c ON c.name = r.category;

INSERT INTO report_phones (report_id, phone_number)
SELECT r.id, n.number
FROM reports r
JOIN numbers n ON n.id = r.number_id;

CREATE TABLE rate_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_hash CHAR(64) NOT NULL,
  action VARCHAR(32) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hash_action_time (client_hash, action, created_at)
) ENGINE=InnoDB;

CREATE TABLE phone_reputation (
  phone_number VARCHAR(32) PRIMARY KEY,
  score INT NOT NULL,
  label ENUM('safe','suspicious','high_risk') NOT NULL,
  confidence INT NOT NULL,
  report_count INT NOT NULL,
  last_calculated DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin_remember_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (token_hash),
  INDEX (admin_id),
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE admin_login_attempts (
  ip VARCHAR(45) PRIMARY KEY,
  attempts INT DEFAULT 0,
  last_attempt DATETIME
) ENGINE=InnoDB;
