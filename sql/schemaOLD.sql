CREATE TABLE numbers (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  number VARCHAR(32) NOT NULL UNIQUE,
  reputation_score FLOAT DEFAULT 0,
  last_report_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_number (number),
  INDEX idx_last_report_at (last_report_at)
);
#---------------------------
CREATE TABLE reports (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  number_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(32)('penipuan','fitnah','rasis','pencemaran','kekerasan','perdagangan','narkoba','pencurian','perampokan','spam','bully','unknown') DEFAULT 'unknown',
  description TEXT,
  reporter_hash CHAR(64) NULL,
  source ENUM('manual','batch','csv','api') DEFAULT 'manual',
  status ENUM('approved','pending','rejected') DEFAULT 'publish',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_numbers
    FOREIGN KEY (number_id) REFERENCES numbers(id)
    ON DELETE CASCADE,
  INDEX idx_number_id (number_id),
  INDEX idx_category (category),
  INDEX idx_status (status)
);
ALTER TABLE reports
ADD COLUMN title VARCHAR(150) NULL AFTER id,
ADD COLUMN full_description MEDIUMTEXT NULL AFTER description,
ADD COLUMN loss_amount BIGINT NULL,
ADD COLUMN chronology_link VARCHAR(255) NULL,
ADD COLUMN reporter_name VARCHAR(100) NULL,
ADD COLUMN reporter_contact VARCHAR(100) NULL;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  weight INT DEFAULT 1
);

INSERT INTO categories (name, weight) VALUES
('spam', 2),
('penipuan', 7),
('fitnah', 3),
('rasis', 3),
('pencemaran', 3),
('kekerasan', 3),
('perdagangan', 7),
('narkoba', 7),
('pencurian', 1),
('perampokan', 7),
('spam', 3),
('bully', 3),
('unknown', 0);

CREATE TABLE report_categories (
  report_id INT NOT NULL,
  category_id INT NOT NULL,
  PRIMARY KEY (report_id, category_id),
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE report_phones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  phone_number VARCHAR(20) NOT NULL,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  INDEX(phone_number)
);

CREATE TABLE report_bank_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  bank_name VARCHAR(50),
  account_number VARCHAR(50),
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

CREATE TABLE report_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  file_path VARCHAR(255),
  file_size INT,
  file_type VARCHAR(50),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

INSERT IGNORE INTO report_categories (report_id, category_id)
SELECT r.id, c.id
FROM reports r
JOIN categories c ON c.name = r.category;

INSERT INTO report_phones (report_id, phone_number)
SELECT id, number
FROM reports
WHERE number IS NOT NULL;


#-----------------------------------------
CREATE TABLE rate_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  client_hash CHAR(64) NOT NULL,
  action VARCHAR(32) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hash_action_time (client_hash, action, created_at)
);

CREATE TABLE phone_reputation (
    phone_number VARCHAR(20) PRIMARY KEY,
    score INT NOT NULL,
    label ENUM('safe','suspicious','high_risk') NOT NULL,
    confidence INT NOT NULL,
    report_count INT NOT NULL,
    last_calculated DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admin_remember_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (admin_id),
  UNIQUE (token_hash)
);

CREATE TABLE admin_login_attempts (
  ip VARCHAR(45) PRIMARY KEY,
  attempts INT DEFAULT 0,
  last_attempt DATETIME
);

