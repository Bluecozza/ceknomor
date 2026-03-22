<?php
/**
 * config/config.php
 * ---------------------------------------------------------------
 * Konfigurasi utama aplikasi cek.resource.my.id
 * Semua nilai sensitif sebaiknya diambil dari .env di production
 * ---------------------------------------------------------------
 */

// ── Environment ──────────────────────────────────────────────
define('APP_ENV',     getenv('APP_ENV')  ?: 'development'); // 'development' | 'production'
define('APP_DEBUG',   APP_ENV === 'development');
define('APP_VERSION', '1.0.0');

// ── Path ─────────────────────────────────────────────────────
// Gunakan defined() agar aman jika bootstrap.php sudah mendefinisikan ROOT_PATH
defined('ROOT_PATH')   || define('ROOT_PATH',   dirname(__DIR__));
defined('CONFIG_PATH') || define('CONFIG_PATH', ROOT_PATH . '/config');
defined('CORE_PATH')   || define('CORE_PATH',   ROOT_PATH . '/core');
defined('MODULE_PATH') || define('MODULE_PATH', ROOT_PATH . '/modules');
defined('PUBLIC_PATH') || define('PUBLIC_PATH', ROOT_PATH . '/public');
defined('UPLOAD_PATH') || define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
defined('LOG_PATH')    || define('LOG_PATH',    ROOT_PATH . '/logs');
defined('VIEW_PATH')   || define('VIEW_PATH',   ROOT_PATH . '/views');
defined('ADMIN_PATH')  || define('ADMIN_PATH',  ROOT_PATH . '/admin');

// ── URL ───────────────────────────────────────────────────────
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host);
define('API_BASE', BASE_URL . '/api/v1');

// Alias untuk digunakan modul-modul
define('APP_URL',  BASE_URL);
define('APP_NAME', 'cek.resource.my.id');

// ── Database ──────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: 3306);
define('DB_NAME',    getenv('DB_NAME')    ?: 'cek_resource');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// ── Security ──────────────────────────────────────────────────
define('APP_KEY',       getenv('APP_KEY') ?: 'change_this_to_random_32_char_string');
define('JWT_SECRET',    getenv('JWT_SECRET') ?: 'change_this_jwt_secret_key');
define('JWT_EXPIRE',    3600 * 24); // 24 jam

// ── Upload ────────────────────────────────────────────────────
define('UPLOAD_MAX_SIZE',  5 * 1024 * 1024); // 5 MB
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_MAX_FILES', 5);

// ── API ───────────────────────────────────────────────────────
define('API_VERSION',     'v1');
define('API_RATE_LIMIT',  60);  // request per menit
define('API_KEY_HEADER',  'X-API-Key');

// ── Timezone ─────────────────────────────────────────────────
date_default_timezone_set('Asia/Jakarta');

// ── Error Handling ────────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . '/php_error.log');
}

// ── Session ───────────────────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (!session_id()) {
    session_start();
}
