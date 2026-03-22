<?php
/**
 * bootstrap.php
 * ---------------------------------------------------------------
 * File bootstrap utama - dimuat di setiap entry point
 * Urutan loading sangat penting!
 * ---------------------------------------------------------------
 */

// Cegah akses langsung ke file bootstrap
defined('ROOT_PATH') || define('ROOT_PATH', __DIR__);

// 1. Load konfigurasi
require_once ROOT_PATH . '/config/config.php';

// 2. Load core classes & helpers
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/Response.php';
require_once CORE_PATH . '/Router.php';
require_once CORE_PATH . '/helpers.php';
require_once CORE_PATH . '/ModuleManager.php';
require_once CORE_PATH . '/ReportService.php';

// 3. Boot module manager (load modul yang aktif)
try {
    $moduleManager = ModuleManager::getInstance();
    $moduleManager->bootModules();
} catch (Exception $e) {
    // Jangan crash jika modul gagal load
    error_log("Module boot error: " . $e->getMessage());
}

// 4. Set global exception handler
set_exception_handler(function (Throwable $e) {
    error_log("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    if (headers_sent()) {
        exit;
    }

    if (APP_DEBUG) {
        Response::error(
            $e->getMessage(),
            500,
            ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()],
            'INTERNAL_ERROR'
        );
    } else {
        Response::error('Terjadi kesalahan internal. Silakan coba lagi.', 500, [], 'INTERNAL_ERROR');
    }
});

// 5. Ensure log directory exists
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}
