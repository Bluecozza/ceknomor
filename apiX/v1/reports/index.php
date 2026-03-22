<?php
/**
 * api/v1/reports/index.php
 * ---------------------------------------------------------------
 * Endpoints:
 *   GET  /api/v1/reports          → List laporan publik (dengan filter)
 *   POST /api/v1/reports          → Buat laporan baru
 *   GET  /api/v1/reports/{ulid}   → Detail laporan
 * ---------------------------------------------------------------
 */

require_once dirname(__DIR__, 3) . '/bootstrap.php';

$method  = $_SERVER['REQUEST_METHOD'];
$service = new ReportService();

// Routing berdasarkan path
$uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts   = explode('/', trim($uri, '/'));
// Path: api/v1/reports/{ulid}
$ulid    = $parts[3] ?? null; // index 3 = setelah api/v1/reports

// ── GET /api/v1/reports/{ulid} ─────────────────────────────────
if ($method === 'GET' && $ulid) {
    if (!preg_match('/^[0-9A-Z]{26}$/', $ulid)) {
        Response::error('Format ID tidak valid', 400);
    }

    $report = $service->getByUlid($ulid);

    if (!$report) {
        Response::notFound('Laporan tidak ditemukan');
    }

    Response::success($report, 'Detail laporan');
}

// ── GET /api/v1/reports ────────────────────────────────────────
if ($method === 'GET') {
    $page        = max(1, (int)($_GET['page'] ?? 1));
    $perPage     = min(50, max(5, (int)($_GET['per_page'] ?? 20)));
    $filters     = [
        'search'      => $_GET['search'] ?? '',
        'category_id' => (int)($_GET['category_id'] ?? 0),
        'status'      => 'approved', // Public API hanya tampilkan approved
    ];

    $result = $service->getAdminList($filters, $page, $perPage);

    Response::paginated(
        $result['data'],
        $result['total'],
        $page,
        $perPage,
        'Daftar laporan'
    );
}

// ── POST /api/v1/reports ───────────────────────────────────────
if ($method === 'POST') {
    // Rate limiting ketat untuk submit laporan
    $ip = get_client_ip();
    if (!check_rate_limit('report_create_' . $ip, 5, 3600)) {
        Response::rateLimited(3600);
    }

    // Terima data dari JSON body atau form-data
    $data = get_json_body();
    if (empty($data)) {
        $data = $_POST;
    }

    // Handle upload file bukti
    $evidenceUrls = [];
    if (!empty($_FILES['evidence'])) {
        $files = $_FILES['evidence'];
        // Normalize $_FILES array untuk multiple file
        if (!is_array($files['name'])) {
            $files = array_map(function($v) { return [$v]; }, $files);
        }

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i] > UPLOAD_MAX_SIZE) continue;
            if (!in_array($files['type'][$i], UPLOAD_ALLOWED_TYPES)) continue;

            $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $filename = generate_token(16) . '.' . $ext;
            $destDir  = UPLOAD_PATH . '/' . date('Y/m');

            if (!is_dir($destDir)) mkdir($destDir, 0755, true);

            $destPath = $destDir . '/' . $filename;
            if (move_uploaded_file($files['tmp_name'][$i], $destPath)) {
                $evidenceUrls[] = BASE_URL . '/public/uploads/' . date('Y/m') . '/' . $filename;
            }

            if (count($evidenceUrls) >= UPLOAD_MAX_FILES) break;
        }
    }

    if (!empty($evidenceUrls)) {
        $data['evidence_urls'] = $evidenceUrls;
    }

    $result = $service->create($data);

    if (!$result['success']) {
        Response::validationError($result['errors'] ?? []);
    }

    Response::success(
        ['ulid' => $result['ulid'], 'status' => $result['status']],
        $result['message'],
        201
    );
}

Response::error('Method not allowed', 405);
