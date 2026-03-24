<?php
/**
 * admin/load-plugin-page.php
 * Load plugin admin pages dynamically
 */

require_once __DIR__ . '/../bootstrap.php';

// Check auth
$token = $_GET['token'] ?? $_POST['token'] ?? null;
if (!$token) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

$payload = verify_jwt($token);
if (!$payload) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

$admin = Database::getInstance()->fetchOne(
    "SELECT id, name, email, role FROM admins WHERE id = ? AND is_active = 1",
    [$payload['admin_id']]
);

if (!$admin) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

$plugin = $_GET['plugin'] ?? null;
$page = $_GET['page'] ?? null;

if (!$plugin || !$page) {
    http_response_code(400);
    echo "Invalid parameters";
    exit;
}

// Security: only alphanumeric and hyphens
if (!preg_match('/^[a-z0-9_-]+$/i', $plugin) || !preg_match('/^[a-z0-9_-]+$/i', $page)) {
    http_response_code(400);
    echo "Invalid parameters";
    exit;
}

$pageFile = MODULE_PATH . '/' . $plugin . '/admin/' . $page . '.php';

if (!file_exists($pageFile)) {
    http_response_code(404);
    echo "Page not found";
    exit;
}

// Set up context
$GLOBALS['admin'] = $admin;
$page_title = ucfirst(str_replace('-', ' ', $page));
$page_icon = 'fa-puzzle-piece';

// Load page
include $pageFile;