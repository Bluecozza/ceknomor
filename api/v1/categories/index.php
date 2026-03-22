<?php
/**
 * API v1 - Categories & Report Types
 * Endpoint untuk mengambil daftar kategori dan jenis laporan
 * Digunakan oleh form laporan dan filter pencarian
 */

require_once dirname(__DIR__, 3) . '/bootstrap.php';

use Core\Response;
use Core\Database;

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ---------------------------------------------------------------
// GET /api/v1/categories
// Mengembalikan semua kategori yang aktif
// ---------------------------------------------------------------
if ($method === 'GET' && preg_match('#^/api/v1/categories/?$#', $uri)) {

    $db = Database::getInstance();

    $categories = $db->fetchAll(
        "SELECT id, name, slug, icon, description, placeholder_text
         FROM categories
         WHERE is_active = 1
         ORDER BY sort_order ASC, name ASC"
    );

    Response::success([
        'categories' => $categories
    ]);
}

// ---------------------------------------------------------------
// GET /api/v1/categories/report-types
// Mengembalikan semua jenis laporan yang tersedia
// ---------------------------------------------------------------
elseif ($method === 'GET' && preg_match('#^/api/v1/categories/report-types/?$#', $uri)) {

    $db = Database::getInstance();

    $reportTypes = $db->fetchAll(
        "SELECT id, name, slug, description, severity, color_class
         FROM report_types
         WHERE is_active = 1
         ORDER BY severity DESC, name ASC"
    );

    Response::success([
        'report_types' => $reportTypes
    ]);
}

// ---------------------------------------------------------------
// GET /api/v1/categories/{slug}
// Mengembalikan detail satu kategori beserta statistiknya
// ---------------------------------------------------------------
elseif ($method === 'GET' && preg_match('#^/api/v1/categories/([a-z0-9_-]+)/?$#', $uri, $matches)) {

    $slug = $matches[1];
    $db   = Database::getInstance();

    $category = $db->fetchOne(
        "SELECT c.*,
                COUNT(r.id) AS total_reports,
                COUNT(CASE WHEN r.status = 'approved' THEN 1 END) AS approved_reports
         FROM categories c
         LEFT JOIN reports r ON r.category_id = c.id
         WHERE c.slug = ? AND c.is_active = 1
         GROUP BY c.id",
        [$slug]
    );

    if (!$category) {
        Response::notFound('Kategori tidak ditemukan');
    }

    Response::success(['category' => $category]);
}

// ---------------------------------------------------------------
// Route tidak dikenali
// ---------------------------------------------------------------
else {
    Response::notFound('Endpoint tidak ditemukan');
}
