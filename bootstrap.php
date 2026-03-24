<?php
/**
 * ./bootstrap.php
 * Application bootstrap with TRUE plugin system
 */

defined('ROOT_PATH') || define('ROOT_PATH', __DIR__);

// 1. Load Config
require_once ROOT_PATH . '/config/config.php';

// 2. Load Core
require_once CORE_PATH . '/helpers.php';
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/Response.php';
require_once CORE_PATH . '/HookManager.php';
require_once CORE_PATH . '/PluginManager.php';
require_once CORE_PATH . '/AdminNavigation.php';

// 3. Load Business Logic
require_once CORE_PATH . '/ReportService.php';

// 4. Ensure directories exist
foreach ([LOG_PATH, STORAGE_PATH . '/cache', STORAGE_PATH . '/sessions'] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

// 5. Initialize systems
$hooks = HookManager::getInstance();
$db = Database::getInstance();
$pluginManager = PluginManager::getInstance();
$adminNav = AdminNavigation::getInstance();

// 6. Make available globally BEFORE loading plugins
define('__HOOKS', $hooks);
define('__PLUGINS', $pluginManager);
define('__ADMIN_NAV', $adminNav);

// 7. Discover dan load all active plugins
try {
    $pluginManager->loadAll();
} catch (Throwable $e) {
    error_log('Plugin system error: ' . $e->getMessage());
}

// 8. Initialize admin navigation after plugins loaded
$adminNav->initialize();

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