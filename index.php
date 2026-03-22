<?php
/**
 * ./index.php
 * Frontend entry point — routes web pages
 */

require_once __DIR__ . '/bootstrap.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Strip base path for subdirectory installs
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($base && $base !== '/' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base)) ?: '/';
}

switch ($uri) {
    case '/':
        include VIEW_PATH . '/home.php';
        break;
    case '/report':
    case '/laporan/baru':
        include VIEW_PATH . '/report-form.php';
        break;
    case '/docs':
    case '/dokumentasi':
        include VIEW_PATH . '/docs.php';
        break;
    default:
        if (preg_match('#^/laporan/([0-9A-Z]{26})$#', $uri, $m)) {
            $_GET['ulid'] = $m[1];
            include VIEW_PATH . '/report-detail.php';
        } else {
            http_response_code(404);
            include VIEW_PATH . '/404.php';
        }
        break;
}
