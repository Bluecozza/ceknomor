<?php
/**
 * API v1 - Admin Endpoints
 * Semua endpoint ini memerlukan autentikasi JWT admin
 * Mencakup: statistik, moderasi laporan, manajemen user admin, pengaturan, API keys
 */

require_once dirname(__DIR__, 3) . '/bootstrap.php';

// ---------------------------------------------------------------
// Helper: Verifikasi JWT dan kembalikan payload admin
// ---------------------------------------------------------------
function requireAdmin(array $roles = ['superadmin', 'admin', 'moderator']): array
{
    $token = get_bearer_token();
    if (empty($token)) {
        Response::unauthorized('Token tidak ditemukan');
    }

    $payload = verify_jwt($token);

    if (!$payload) {
        Response::unauthorized('Token tidak valid atau sudah kedaluwarsa');
    }

    if (!in_array($payload['role'], $roles, true)) {
        Response::forbidden('Akses ditolak: role tidak mencukupi');
    }

    return $payload;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$db     = Database::getInstance();

// =================================================================
// DASHBOARD STATS
// GET /api/v1/admin/stats
// =================================================================
if ($method === 'GET' && preg_match('#^/api/v1/admin/stats/?$#', $uri)) {
    requireAdmin();

    $service = new ReportService();
    $stats   = $service->getStats();

    // Statistik tambahan untuk admin
    $stats['pending_reports']   = (int) $db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
    $stats['flagged_reports']   = (int) $db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status = 'flagged'");
    $stats['total_reporters']   = (int) $db->fetchColumn("SELECT COUNT(*) FROM reporters");
    $stats['searches_today']    = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM search_logs WHERE DATE(created_at) = CURDATE()"
    );
    $stats['searches_this_week'] = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM search_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );

    // Laporan per hari (7 hari terakhir)
    $stats['reports_per_day'] = $db->fetchAll(
        "SELECT DATE(created_at) as date, COUNT(*) as count
         FROM reports
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at)
         ORDER BY date ASC"
    );

    // Top kategori
    $stats['top_categories'] = $db->fetchAll(
        "SELECT c.name, c.slug, c.icon, COUNT(r.id) as count
         FROM categories c
         LEFT JOIN reports r ON r.category_id = c.id AND r.status = 'approved'
         GROUP BY c.id
         ORDER BY count DESC
         LIMIT 5"
    );

    // Top pencarian
    $stats['top_searches'] = $db->fetchAll(
        "SELECT query, COUNT(*) as count, MAX(created_at) as last_searched
         FROM search_logs
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY query
         ORDER BY count DESC
         LIMIT 10"
    );

    Response::success($stats);
}

// =================================================================
// DAFTAR LAPORAN UNTUK ADMIN
// GET /api/v1/admin/reports
// =================================================================
elseif ($method === 'GET' && preg_match('#^/api/v1/admin/reports/?$#', $uri)) {
    requireAdmin();

    $service = new ReportService();

    $filters = [
        'search'      => $_GET['search']   ?? '',
        'status'      => $_GET['status']   ?? '',
        'category'    => $_GET['category'] ?? '',
        'report_type' => $_GET['type']     ?? '',
        'date_from'   => $_GET['from']     ?? '',
        'date_to'     => $_GET['to']       ?? '',
    ];

    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 20)));

    $result = $service->getAdminList($filters, $page, $perPage);

    Response::paginated(
        $result['data'],
        $result['total'],
        $page,
        $perPage
    );
}

// =================================================================
// DETAIL LAPORAN (ADMIN VIEW)
// GET /api/v1/admin/reports/{id}
// =================================================================
elseif ($method === 'GET' && preg_match('#^/api/v1/admin/reports/(\d+)/?$#', $uri, $m)) {
    requireAdmin();

    $id = (int) $m[1];

    $report = $db->fetchOne(
        "SELECT r.*,
                c.name  AS category_name, c.slug  AS category_slug, c.icon AS category_icon,
                rt.name AS report_type_name, rt.severity,
                rp.name AS reporter_name, rp.contact AS reporter_contact,
                rp.contact_type, rp.ip_address, rp.is_verified,
                rs.score AS risk_score, rs.level AS risk_level, rs.approved_count AS risk_approved
         FROM reports r
         LEFT JOIN categories   c  ON c.id  = r.category_id
         LEFT JOIN report_types rt ON rt.id = r.report_type_id
         LEFT JOIN reporters    rp ON rp.id = r.reporter_id
         LEFT JOIN risk_scores  rs ON rs.normalized_value = r.reported_value_normalized
                                   AND rs.category_id     = r.category_id
         WHERE r.id = ?",
        [$id]
    );

    if (!$report) {
        Response::notFound('Laporan tidak ditemukan');
    }

    // Decode JSON fields
    if (!empty($report['evidence_urls'])) {
        $report['evidence_urls'] = json_decode($report['evidence_urls'], true);
    }

    // Riwayat moderasi (dari activity_logs)
    $report['moderation_history'] = $db->fetchAll(
        "SELECT al.action, al.description, al.created_at,
                a.name AS admin_name, a.role AS admin_role
         FROM activity_logs al
         LEFT JOIN admins a ON a.id = al.admin_id
         WHERE al.entity_type = 'report' AND al.entity_id = ?
         ORDER BY al.created_at DESC",
        [$id]
    );

    Response::success(['report' => $report]);
}

// =================================================================
// MODERASI LAPORAN
// POST /api/v1/admin/reports/{id}/moderate
// Body: { action: 'approve'|'reject'|'flag', note: '...' }
// =================================================================
elseif ($method === 'POST' && preg_match('#^/api/v1/admin/reports/(\d+)/moderate/?$#', $uri, $m)) {
    $admin = requireAdmin(['superadmin', 'admin', 'moderator']);
    $id    = (int) $m[1];
    $body  = get_json_body();

    $action = trim($body['action'] ?? '');
    $note   = trim($body['note']   ?? '');

    $validActions = ['approve', 'reject', 'flag'];
    if (!in_array($action, $validActions, true)) {
        Response::validationError(['action' => 'Action harus approve, reject, atau flag']);
    }

    $service = new ReportService();
    $result  = $service->moderate($id, $action, $admin['id'], $note);

    if (!$result['success']) {
        Response::error($result['message'], 422);
    }

    Response::success(['message' => $result['message'], 'report' => $result['report']]);
}

// =================================================================
// BULK MODERASI
// POST /api/v1/admin/reports/bulk
// Body: { ids: [1,2,3], action: 'approve'|'reject'|'flag', note: '...' }
// =================================================================
elseif ($method === 'POST' && preg_match('#^/api/v1/admin/reports/bulk/?$#', $uri)) {
    $admin = requireAdmin(['superadmin', 'admin']);
    $body  = get_json_body();

    $ids    = array_map('intval', (array) ($body['ids']    ?? []));
    $action = trim($body['action'] ?? '');
    $note   = trim($body['note']   ?? '');

    if (empty($ids)) {
        Response::validationError(['ids' => 'Pilih minimal 1 laporan']);
    }
    if (!in_array($action, ['approve', 'reject', 'flag'], true)) {
        Response::validationError(['action' => 'Action tidak valid']);
    }
    if (count($ids) > 50) {
        Response::error('Maksimal 50 laporan sekaligus', 422);
    }

    $service = new ReportService();
    $success = 0;
    $failed  = 0;

    foreach ($ids as $id) {
        $r = $service->moderate($id, $action, $admin['id'], $note);
        $r['success'] ? $success++ : $failed++;
    }

    Response::success([
        'message' => "Berhasil: {$success}, Gagal: {$failed}",
        'success' => $success,
        'failed'  => $failed,
    ]);
}

// =================================================================
// DAFTAR ADMIN USERS
// GET /api/v1/admin/users
// =================================================================
elseif ($method === 'GET' && preg_match('#^/api/v1/admin/users/?$#', $uri)) {
    requireAdmin(['superadmin', 'admin']);

    $users = $db->fetchAll(
        "SELECT id, name, email, role, is_active, last_login_at, created_at
         FROM admins
         ORDER BY role ASC, name ASC"
    );

    Response::success(['users' => $users]);
}

// =================================================================
// TAMBAH ADMIN USER
// POST /api/v1/admin/users
// =================================================================
elseif ($method === 'POST' && preg_match('#^/api/v1/admin/users/?$#', $uri)) {
    $admin = requireAdmin(['superadmin']);
    $body  = get_json_body();

    $errors = validate($body, [
        'name'     => 'required|min:2|max:100',
        'email'    => 'required|email',
        'password' => 'required|min:8|max:100',
        'role'     => 'required|in:admin,moderator',
    ]);

    if (!empty($errors)) {
        Response::validationError($errors);
    }

    // Cek email duplikat
    $exists = $db->fetchColumn("SELECT id FROM admins WHERE email = ?", [$body['email']]);
    if ($exists) {
        Response::validationError(['email' => 'Email sudah digunakan']);
    }

    $id = $db->insert('admins', [
        'name'       => e($body['name']),
        'email'      => strtolower(trim($body['email'])),
        'password'   => hash_password($body['password']),
        'role'       => $body['role'],
        'is_active'  => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    // Log aktivitas
    $db->insert('activity_logs', [
        'admin_id'    => $admin['id'],
        'action'      => 'create_admin',
        'entity_type' => 'admin',
        'entity_id'   => $id,
        'description' => "Membuat admin baru: {$body['email']} (role: {$body['role']})",
        'ip_address'  => get_client_ip(),
        'created_at'  => date('Y-m-d H:i:s'),
    ]);

    Response::success(['message' => 'Admin berhasil ditambahkan', 'id' => $id], 201);
}

// =================================================================
// UPDATE ADMIN USER
// PUT /api/v1/admin/users/{id}
// =================================================================
elseif ($method === 'PUT' && preg_match('#^/api/v1/admin/users/(\d+)/?$#', $uri, $m)) {
    $admin  = requireAdmin(['superadmin']);
    $userId = (int) $m[1];
    $body   = get_json_body();

    $target = $db->fetchOne("SELECT * FROM admins WHERE id = ?", [$userId]);
    if (!$target) {
        Response::notFound('User tidak ditemukan');
    }

    // Superadmin tidak bisa mengubah superadmin lain (kecuali diri sendiri)
    if ($target['role'] === 'superadmin' && $target['id'] !== $admin['id']) {
        Response::forbidden('Tidak dapat mengubah superadmin lain');
    }

    $update = [];

    if (!empty($body['name']))     $update['name']      = e($body['name']);
    if (!empty($body['role']) && $target['role'] !== 'superadmin') {
        if (!in_array($body['role'], ['admin', 'moderator'], true)) {
            Response::validationError(['role' => 'Role tidak valid']);
        }
        $update['role'] = $body['role'];
    }
    if (isset($body['is_active'])) $update['is_active'] = (int) $body['is_active'];
    if (!empty($body['password'])) {
        if (strlen($body['password']) < 8) {
            Response::validationError(['password' => 'Password minimal 8 karakter']);
        }
        $update['password'] = hash_password($body['password']);
    }

    if (!empty($update)) {
        $update['updated_at'] = date('Y-m-d H:i:s');
        $db->update('admins', $update, 'id = ?', [$userId]);
    }

    Response::success(['message' => 'User berhasil diperbarui']);
}

// =================================================================
// HAPUS ADMIN USER
// DELETE /api/v1/admin/users/{id}
// =================================================================
elseif ($method === 'DELETE' && preg_match('#^/api/v1/admin/users/(\d+)/?$#', $uri, $m)) {
    $admin  = requireAdmin(['superadmin']);
    $userId = (int) $m[1];

    if ($userId === $admin['id']) {
        Response::error('Tidak dapat menghapus akun sendiri', 422);
    }

    $target = $db->fetchOne("SELECT * FROM admins WHERE id = ?", [$userId]);
    if (!$target) {
        Response::notFound('User tidak ditemukan');
    }
    if ($target['role'] === 'superadmin') {
        Response::forbidden('Tidak dapat menghapus superadmin');
    }

    $db->delete('admins', 'id = ?', [$userId]);

    $db->insert('activity_logs', [
        'admin_id'    => $admin['id'],
        'action'      => 'delete_admin',
        'entity_type' => 'admin',
        'entity_id'   => $userId,
        'description' => "Menghapus admin: {$target['email']}",
        'ip_address'  => get_client_ip(),
        'created_at'  => date('Y-m-d H:i:s'),
    ]);

    Response::success(['message' => 'Admin berhasil dihapus']);
}

// =================================================================
// SETTINGS
// GET /api/v1/admin/settings
// =================================================================
elseif ($method === 'GET' && preg_match('#^/api/v1/admin/settings/?$#', $uri)) {
    requireAdmin(['superadmin', 'admin']);

    $rows = $db->fetchAll("SELECT key_name, value, type, description, is_public FROM settings ORDER BY key_name");

    $settings = [];
    foreach ($rows as $row) {
        $value = $row['value'];
        if ($row['type'] === 'boolean') $value = (bool) $value;
        elseif ($row['type'] === 'integer') $value = (int) $value;
        elseif ($row['type'] === 'json') $value = json_decode($value, true);

        $settings[$row['key_name']] = [
            'value'       => $value,
            'type'        => $row['type'],
            'description' => $row['description'],
            'is_public'   => (bool) $row['is_public'],
        ];
    }

    Response::success(['settings' => $settings]);
}

// =================================================================
// UPDATE SETTINGS
// PUT /api/v1/admin/settings
// Body: { key: value, ... }
// =================================================================
elseif ($method === 'PUT' && preg_match('#^/api/v1/admin/settings/?$#', $uri)) {
    $admin = requireAdmin(['superadmin']);
    $body  = get_json_body();

    if (empty($body)) {
        Response::error('Tidak ada data yang dikirim', 422);
    }

    foreach ($body as $key => $value) {
        $existing = $db->fetchOne(
            "SELECT id, type FROM settings WHERE key_name = ?",
            [$key]
        );

        if (!$existing) continue; // Hanya update key yang sudah ada

        // Format nilai sesuai type
        $storedValue = match ($existing['type']) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json'    => json_encode($value),
            default   => (string) $value,
        };

        $db->update('settings', [
            'value'      => $storedValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'key_name = ?', [$key]);
    }

    $db->insert('activity_logs', [
        'admin_id'    => $admin['id'],
        'action'      => 'update_settings',
        'entity_type' => 'settings',
        'entity_id'   => 0,
        'description' => 'Memperbarui pengaturan sistem: ' . implode(', ', array_keys($body)),
        'ip_address'  => get_client_ip(),
        'created_at'  => date('Y-m-d H:i:s'),
    ]);

    Response::success(['message' => 'Pengaturan berhasil disimpan']);
}

// =================================================================
// API KEYS — Daftar
// GET /api/v1/admin/api-keys
// =================================================================
elseif ($method === 'GET' && preg_match('#^/api/v1/admin/api-keys/?$#', $uri)) {
    requireAdmin(['superadmin', 'admin']);

    $keys = $db->fetchAll(
        "SELECT id, name, key_prefix, permissions, rate_limit, is_active,
                last_used_at, usage_count, expires_at, created_at
         FROM api_keys
         ORDER BY created_at DESC"
    );

    foreach ($keys as &$k) {
        if (!empty($k['permissions'])) {
            $k['permissions'] = json_decode($k['permissions'], true);
        }
    }

    Response::success(['api_keys' => $keys]);
}

// =================================================================
// API KEYS — Buat baru
// POST /api/v1/admin/api-keys
// =================================================================
elseif ($method === 'POST' && preg_match('#^/api/v1/admin/api-keys/?$#', $uri)) {
    $admin = requireAdmin(['superadmin', 'admin']);
    $body  = get_json_body();

    $errors = validate($body, [
        'name'       => 'required|min:3|max:100',
        'rate_limit' => 'required|numeric',
    ]);
    if (!empty($errors)) Response::validationError($errors);

    // Generate key: prefix_randomstring
    $prefix  = 'ck_' . ($body['env'] === 'test' ? 'test' : 'live');
    $rawKey  = $prefix . '_' . bin2hex(random_bytes(24));
    $keyHash = hash('sha256', $rawKey);

    $permissions = $body['permissions'] ?? ['search', 'read'];
    if (!is_array($permissions)) $permissions = ['search', 'read'];

    $id = $db->insert('api_keys', [
        'name'        => e($body['name']),
        'key_hash'    => $keyHash,
        'key_prefix'  => substr($rawKey, 0, 12) . '...',
        'permissions' => json_encode($permissions),
        'rate_limit'  => (int) $body['rate_limit'],
        'is_active'   => 1,
        'usage_count' => 0,
        'expires_at'  => !empty($body['expires_at']) ? $body['expires_at'] : null,
        'created_by'  => $admin['id'],
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => date('Y-m-d H:i:s'),
    ]);

    Response::success([
        'message' => 'API key berhasil dibuat. Simpan key ini, tidak akan ditampilkan lagi.',
        'id'      => $id,
        'key'     => $rawKey,   // Ditampilkan SEKALI saja
        'prefix'  => substr($rawKey, 0, 12) . '...',
    ], 201);
}

// =================================================================
// API KEYS — Revoke
// DELETE /api/v1/admin/api-keys/{id}
// =================================================================
elseif ($method === 'DELETE' && preg_match('#^/api/v1/admin/api-keys/(\d+)/?$#', $uri, $m)) {
    $admin = requireAdmin(['superadmin', 'admin']);
    $keyId = (int) $m[1];

    $key = $db->fetchOne("SELECT * FROM api_keys WHERE id = ?", [$keyId]);
    if (!$key) Response::notFound('API key tidak ditemukan');

    $db->update('api_keys', [
        'is_active'  => 0,
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$keyId]);

    $db->insert('activity_logs', [
        'admin_id'    => $admin['id'],
        'action'      => 'revoke_api_key',
        'entity_type' => 'api_key',
        'entity_id'   => $keyId,
        'description' => "Menonaktifkan API key: {$key['name']}",
        'ip_address'  => get_client_ip(),
        'created_at'  => date('Y-m-d H:i:s'),
    ]);

    Response::success(['message' => 'API key berhasil dinonaktifkan']);
}

// =================================================================
// ACTIVITY LOGS
// GET /api/v1/admin/logs
// =================================================================
elseif ($method === 'GET' && preg_match('#^/api/v1/admin/logs/?$#', $uri)) {
    requireAdmin(['superadmin', 'admin']);

    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 30)));
    $offset  = ($page - 1) * $perPage;

    $where  = '1=1';
    $params = [];

    if (!empty($_GET['admin_id'])) {
        $where   .= ' AND al.admin_id = ?';
        $params[] = (int) $_GET['admin_id'];
    }
    if (!empty($_GET['action'])) {
        $where   .= ' AND al.action = ?';
        $params[] = $_GET['action'];
    }
    if (!empty($_GET['date'])) {
        $where   .= ' AND DATE(al.created_at) = ?';
        $params[] = $_GET['date'];
    }

    $total = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM activity_logs al WHERE {$where}",
        $params
    );

    $logs = $db->fetchAll(
        "SELECT al.*, a.name AS admin_name, a.role AS admin_role
         FROM activity_logs al
         LEFT JOIN admins a ON a.id = al.admin_id
         WHERE {$where}
         ORDER BY al.created_at DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );

    Response::paginated($logs, $total, $page, $perPage);
}

// =================================================================
// RISK SCORES — Daftar
// GET /api/v1/admin/risk-scores
// =================================================================
elseif ($method === 'GET' && preg_match('#^/api/v1/admin/risk-scores/?$#', $uri)) {
    requireAdmin();

    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 20)));
    $offset  = ($page - 1) * $perPage;

    $level  = $_GET['level']     ?? '';
    $catId  = (int) ($_GET['category'] ?? 0);
    $where  = '1=1';
    $params = [];

    if ($level) {
        $where   .= ' AND rs.level = ?';
        $params[] = $level;
    }
    if ($catId) {
        $where   .= ' AND rs.category_id = ?';
        $params[] = $catId;
    }

    $total = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM risk_scores rs WHERE {$where}",
        $params
    );

    $rows = $db->fetchAll(
        "SELECT rs.*, c.name AS category_name, c.slug AS category_slug
         FROM risk_scores rs
         LEFT JOIN categories c ON c.id = rs.category_id
         WHERE {$where}
         ORDER BY rs.score DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );

    Response::paginated($rows, $total, $page, $perPage);
}

// =================================================================
// SEARCH LOGS
// GET /api/v1/admin/search-logs
// =================================================================
elseif ($method === 'GET' && preg_match('#^/api/v1/admin/search-logs/?$#', $uri)) {
    requireAdmin();

    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 30)));
    $offset  = ($page - 1) * $perPage;

    $where  = '1=1';
    $params = [];

    if (!empty($_GET['query'])) {
        $like     = '%' . $db->escapeLike($_GET['query']) . '%';
        $where   .= ' AND query LIKE ?';
        $params[] = $like;
    }
    if (!empty($_GET['has_result'])) {
        $where   .= ' AND has_result = ?';
        $params[] = $_GET['has_result'] === '1' ? 1 : 0;
    }

    $total = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM search_logs WHERE {$where}",
        $params
    );

    $logs = $db->fetchAll(
        "SELECT * FROM search_logs
         WHERE {$where}
         ORDER BY created_at DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );

    Response::paginated($logs, $total, $page, $perPage);
}

// =================================================================
// Route tidak dikenali
// =================================================================
else {
    Response::notFound('Endpoint admin tidak ditemukan');
}
