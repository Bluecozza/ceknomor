CREATE TABLE numbers (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  number VARCHAR(32) NOT NULL UNIQUE,
  reputation_score FLOAT DEFAULT 0,
  last_report_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_number (number),
  INDEX idx_last_report_at (last_report_at)
);

CREATE TABLE reports (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  number_id BIGINT UNSIGNED NOT NULL,
  category ENUM('safe','spam','penipuan','iklan','unknown') DEFAULT 'unknown',
  description TEXT,
  reporter_hash CHAR(64) NULL,
  source ENUM('manual','batch','csv','api') DEFAULT 'manual',
  status ENUM('publish','pending','hidden') DEFAULT 'publish',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_numbers
    FOREIGN KEY (number_id) REFERENCES numbers(id)
    ON DELETE CASCADE,
  INDEX idx_number_id (number_id),
  INDEX idx_category (category),
  INDEX idx_status (status)
);

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
