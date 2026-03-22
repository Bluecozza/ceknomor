<?php
/**
 * ./bootstrap.php
 * Application bootstrap — load config, core classes, boot modules
 */

defined('ROOT_PATH') || define('ROOT_PATH', __DIR__);

// 1. Config
require_once ROOT_PATH . '/config/config.php';

// 2. Core (helpers first — contains polyfills)
require_once CORE_PATH . '/helpers.php';
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/Response.php';
require_once CORE_PATH . '/ModuleManager.php';
require_once CORE_PATH . '/ReportService.php';

// 3. Ensure directories exist
foreach ([LOG_PATH, STORAGE_PATH . '/cache', STORAGE_PATH . '/sessions'] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

// 4. Boot modules — completely silent on failure
// Module errors MUST NOT crash the application
try {
    ModuleManager::getInstance()->bootModules();
} catch (Throwable $e) {
    error_log('Module boot error: ' . $e->getMessage());
    // Continue — modules are optional
}

// 5. Global exception handler — last resort
set_exception_handler(function(Throwable $e) {
    error_log('Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (headers_sent()) { exit; }
    if (class_exists('Response')) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            Response::error($e->getMessage(), 500, [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ], 'INTERNAL_ERROR');
        } else {
            Response::error('Terjadi kesalahan internal.', 500, [], 'INTERNAL_ERROR');
        }
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
});
