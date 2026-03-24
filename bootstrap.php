<?php
/**
 * ./bootstrap.php
 * Application bootstrap — load config, core classes, boot modules
 * REFACTORED: Lebih clean dengan separation of concerns
 */

defined('ROOT_PATH') || define('ROOT_PATH', __DIR__);

// 1. Load Config
require_once ROOT_PATH . '/config/config.php';

// 2. Load Core (helpers first — contains polyfills)
require_once CORE_PATH . '/helpers.php';
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/Response.php';

// 3. Load Module System
require_once CORE_PATH . '/HookManager.php';
require_once CORE_PATH . '/ModuleManager.php';

// 4. Load Business Logic
require_once CORE_PATH . '/ReportService.php';

// 5. Ensure directories exist
foreach ([LOG_PATH, STORAGE_PATH . '/cache', STORAGE_PATH . '/sessions'] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

// 6. Initialize module system
$hookManager = new HookManager();
$db = Database::getInstance();
$moduleManager = ModuleManager::getInstance();

// FIX: Set HookManager ke ModuleManager
$moduleManager->setHookManager($hookManager);

// 7. Discovery dan boot modules
try {
    $moduleManager->discoverModules();  // Scan /modules directory
    $moduleManager->bootModules();      // Load yang enabled
} catch (Throwable $e) {
    error_log('Module system error: ' . $e->getMessage());
    // Continue — modules are optional, API masih bisa jalan
}

// 8. Make module system available globally (untuk convenience)
define('__MODULES', [
    'hooks' => $hookManager,
    'manager' => $moduleManager,
]);

// 9. Global exception handler
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