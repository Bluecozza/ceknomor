<?php
/**
 * api/v1/modules/index.php
 * ---------------------------------------------------------------
 * Endpoints (Admin only):
 *   GET  /api/v1/modules           → List semua modul
 *   POST /api/v1/modules/{slug}/enable  → Aktifkan modul
 *   POST /api/v1/modules/{slug}/disable → Nonaktifkan modul
 * ---------------------------------------------------------------
 */

require_once dirname(__DIR__, 3) . '/bootstrap.php';

// Semua endpoint modul memerlukan autentikasi admin
$admin = require_admin_auth();

$method  = $_SERVER['REQUEST_METHOD'];
$manager = ModuleManager::getInstance();
$uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts   = explode('/', trim($uri, '/'));
// /api/v1/modules/{slug}/{action}
$slug    = $parts[3] ?? null;
$action  = $parts[4] ?? null;

// ── GET /api/v1/modules ────────────────────────────────────────
if ($method === 'GET' && !$slug) {
    $modules = $manager->getAllModules();
    // Scan untuk modul baru di disk
    $manager->discoverModules();
    Response::success($modules, 'Daftar modul');
}

// ── POST /api/v1/modules/{slug}/enable ────────────────────────
if ($method === 'POST' && $slug && $action === 'enable') {
    if ($manager->enable($slug)) {
        Response::success(null, "Modul {$slug} berhasil diaktifkan");
    }
    Response::error("Gagal mengaktifkan modul {$slug}");
}

// ── POST /api/v1/modules/{slug}/disable ───────────────────────
if ($method === 'POST' && $slug && $action === 'disable') {
    if ($manager->disable($slug)) {
        Response::success(null, "Modul {$slug} berhasil dinonaktifkan");
    }
    Response::error("Gagal menonaktifkan modul. Modul inti tidak dapat dinonaktifkan.");
}

// ── PUT /api/v1/modules/{slug}/config ─────────────────────────
if ($method === 'PUT' && $slug && $action === 'config') {
    $config = get_json_body();
    if ($manager->updateConfig($slug, $config)) {
        Response::success(null, "Konfigurasi modul {$slug} berhasil diupdate");
    }
    Response::error("Gagal update konfigurasi modul");
}

Response::error('Endpoint tidak valid', 404);

// ── Helper: Require Admin Auth ─────────────────────────────────
function require_admin_auth(): array
{
    $token = get_bearer_token();

    if (empty($token)) {
        Response::unauthorized('Token autentikasi diperlukan');
    }

    $payload = verify_jwt($token);
    if (!$payload || !isset($payload['admin_id'])) {
        Response::unauthorized('Token tidak valid atau sudah expired');
    }

    $db    = Database::getInstance();
    $admin = $db->fetchOne("SELECT * FROM admins WHERE id = ? AND is_active = 1", [$payload['admin_id']]);

    if (!$admin) {
        Response::unauthorized('Akun admin tidak aktif');
    }

    return $admin;
}
