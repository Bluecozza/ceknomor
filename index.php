<?php
/**
 * index.php
 * ---------------------------------------------------------------
 * Entry point utama untuk halaman web frontend
 * API menggunakan file terpisah di /api/
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/bootstrap.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Routing sederhana untuk halaman web
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
        // Cek apakah path laporan: /laporan/{ulid}
        if (preg_match('#^/laporan/([0-9A-Z]{26})$#', $uri, $m)) {
            $_GET['ulid'] = $m[1];
            include VIEW_PATH . '/report-detail.php';
            break;
        }

        http_response_code(404);
        include VIEW_PATH . '/404.php';
        break;
}
