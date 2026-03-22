<?php
/**
 * ./config/config.php
 * Konfigurasi utama aplikasi — semua konstanta global
 */

// ── Environment ───────────────────────────────────────────────
defined('APP_ENV')     || define('APP_ENV',     getenv('APP_ENV') ?: 'development');
defined('APP_DEBUG')   || define('APP_DEBUG',   APP_ENV === 'development');
defined('APP_VERSION') || define('APP_VERSION', '1.0.0');
defined('APP_NAME')    || define('APP_NAME',    'cek.resource.my.id');

// ── Paths ─────────────────────────────────────────────────────
defined('ROOT_PATH')   || define('ROOT_PATH',   dirname(__DIR__));
defined('CONFIG_PATH') || define('CONFIG_PATH', ROOT_PATH . '/config');
defined('CORE_PATH')   || define('CORE_PATH',   ROOT_PATH . '/core');
defined('MODULE_PATH') || define('MODULE_PATH', ROOT_PATH . '/modules');
defined('PUBLIC_PATH') || define('PUBLIC_PATH', ROOT_PATH . '/public');
defined('UPLOAD_PATH') || define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');
defined('LOG_PATH')    || define('LOG_PATH',    ROOT_PATH . '/logs');
defined('VIEW_PATH')   || define('VIEW_PATH',   ROOT_PATH . '/views');
defined('STORAGE_PATH')|| define('STORAGE_PATH',ROOT_PATH . '/storage');

// ── URL ───────────────────────────────────────────────────────
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
defined('BASE_URL') || define('BASE_URL', $protocol . '://' . $host);
defined('APP_URL')  || define('APP_URL',  BASE_URL);

// ── Database ──────────────────────────────────────────────────
defined('DB_HOST')    || define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
defined('DB_PORT')    || define('DB_PORT',    getenv('DB_PORT')    ?: 3306);
defined('DB_NAME')    || define('DB_NAME',    getenv('DB_NAME')    ?: 'cek_resource');
defined('DB_USER')    || define('DB_USER',    getenv('DB_USER')    ?: 'root');
defined('DB_PASS')    || define('DB_PASS',    getenv('DB_PASS')    ?: '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// ── Security ──────────────────────────────────────────────────
defined('APP_KEY')    || define('APP_KEY',    getenv('APP_KEY')    ?: 'change_this_to_random_32_char_string');
defined('JWT_SECRET') || define('JWT_SECRET', getenv('JWT_SECRET') ?: 'change_this_jwt_secret_key_min_32_chars');
defined('JWT_EXPIRE') || define('JWT_EXPIRE', 3600 * 24); // 24 jam

// ── Upload ────────────────────────────────────────────────────
defined('UPLOAD_MAX_SIZE')     || define('UPLOAD_MAX_SIZE',     5 * 1024 * 1024);
defined('UPLOAD_ALLOWED_TYPES')|| define('UPLOAD_ALLOWED_TYPES', ['image/jpeg','image/png','image/gif','image/webp']);
defined('UPLOAD_MAX_FILES')    || define('UPLOAD_MAX_FILES',    5);

// ── Timezone & Error ──────────────────────────────────────────
date_default_timezone_set('Asia/Jakarta');

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}
