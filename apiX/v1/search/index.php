<?php
/**
 * api/v1/search/index.php
 * ---------------------------------------------------------------
 * Endpoint: GET/POST /api/v1/search
 * Pencarian data bermasalah
 * ---------------------------------------------------------------
 */

require_once dirname(__DIR__, 3) . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

// Handle OPTIONS (CORS preflight)
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($method, ['GET', 'POST'])) {
    Response::error('Method not allowed', 405);
}

// Rate limiting
if (!check_rate_limit('search_' . get_client_ip(), 30, 60)) {
    Response::rateLimited(60);
}

// Ambil parameter query
$query        = '';
$categorySlug = '';

if ($method === 'GET') {
    $query        = trim($_GET['q'] ?? '');
    $categorySlug = trim($_GET['category'] ?? '');
} else {
    $body         = get_json_body();
    $query        = trim($body['q'] ?? $body['query'] ?? '');
    $categorySlug = trim($body['category'] ?? '');
}

// Validasi
if (strlen($query) < 3) {
    Response::error('Query pencarian minimal 3 karakter', 422, ['q' => 'Minimal 3 karakter']);
}

if (strlen($query) > 255) {
    Response::error('Query terlalu panjang', 422);
}

// Jalankan pencarian
$service = new ReportService();
$result  = $service->search($query, $categorySlug);

// Tambah info apakah ada data
$result['has_data'] = $result['count'] > 0;

Response::success($result, 'Pencarian berhasil');
