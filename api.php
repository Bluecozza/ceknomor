<?php
/**
 * api.php — Single entry point untuk semua API
 * Tidak ada folder api/ — semua handler ada di file ini
 * Mencegah 403 dari Apache/Cloudflare pada folder fisik
 */

require_once __DIR__ . '/bootstrap.php';

// ── Setup ─────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Ambil path, strip /api/v1 prefix
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri  = rtrim($uri, '/');

// Strip subdirectory jika ada (e.g. /cek-resource)
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($base && $base !== '/' && strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base));
}

// path = bagian setelah /api/v1
$path = preg_replace('#^/api/v1#', '', $uri);
if (empty($path)) $path = '/';

$db = Database::getInstance();

// ════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════

function requireAdmin(array $roles = ['superadmin','admin','moderator']): array {
    $token = get_bearer_token();
    if (empty($token)) Response::unauthorized('Token tidak ditemukan');
    $payload = verify_jwt($token);
    if (!$payload) Response::unauthorized('Token tidak valid atau kedaluwarsa');
    if (!in_array($payload['role'], $roles, true)) Response::forbidden('Akses ditolak');
    return $payload;
}

// ════════════════════════════════════════════════════════════
// ROUTING
// ════════════════════════════════════════════════════════════

// ── /stats (publik) ──────────────────────────────────────────
if ($method === 'GET' && preg_match('#^/stats$#', $path)) {
    // Cache sederhana pakai file agar tidak overload DB
    $cacheFile = LOG_PATH . '/stats_cache.json';
    $cacheTtl  = 300; // 5 menit

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
        $stats = json_decode(file_get_contents($cacheFile), true);
    } else {
        $stats = [
            'total_reports'   => (int)$db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status = 'approved'"),
            'searches_today'  => (int)$db->fetchColumn("SELECT COUNT(*) FROM search_logs WHERE DATE(created_at) = CURDATE()"),
            'high_risk_count' => (int)$db->fetchColumn("SELECT COUNT(*) FROM risk_scores WHERE risk_level IN ('high','critical')"),
            'total_reporters' => (int)$db->fetchColumn("SELECT COUNT(*) FROM reporters"),
        ];
        // Simpan cache
        if (is_dir(LOG_PATH)) {
            @file_put_contents($cacheFile, json_encode($stats));
        }
    }
    Response::success($stats);
}

// ── /search ──────────────────────────────────────────────────
elseif (preg_match('#^/search$#', $path) && in_array($method, ['GET','POST'])) {
    if (!check_rate_limit('search_'.get_client_ip(), 30, 60)) Response::rateLimited(60);

    if ($method === 'GET') {
        $query        = trim($_GET['q'] ?? '');
        $categorySlug = trim($_GET['category'] ?? '');
    } else {
        $body         = get_json_body();
        $query        = trim($body['q'] ?? $body['query'] ?? '');
        $categorySlug = trim($body['category'] ?? '');
    }

    if (strlen($query) < 3) Response::error('Query minimal 3 karakter', 422);
    if (strlen($query) > 255) Response::error('Query terlalu panjang', 422);

    $service = new ReportService();
    $result  = $service->search($query, $categorySlug);
    Response::success($result, 'Pencarian berhasil');
}

// ── /categories ──────────────────────────────────────────────
elseif ($method === 'GET' && preg_match('#^/categories/report-types$#', $path)) {
    $types = $db->fetchAll(
        "SELECT id, name, slug, description, severity FROM report_types
         WHERE is_active = 1 ORDER BY severity DESC, sort_order ASC"
    );
    $colors = [1=>'secondary',2=>'warning',3=>'orange',4=>'danger'];
    foreach ($types as &$t) {
        $t['color_class'] = $colors[(int)$t['severity']] ?? 'secondary';
    }
    Response::success(['report_types' => $types]);
}

elseif ($method === 'GET' && preg_match('#^/categories/([a-z0-9_-]+)$#', $path, $m)) {
    $cat = $db->fetchOne(
        "SELECT c.*, COUNT(r.id) AS total_reports,
                COUNT(CASE WHEN r.status='approved' THEN 1 END) AS approved_reports
         FROM categories c LEFT JOIN reports r ON r.category_id = c.id
         WHERE c.slug = ? AND c.is_active = 1 GROUP BY c.id",
        [$m[1]]
    );
    if (!$cat) Response::notFound('Kategori tidak ditemukan');
    Response::success(['category' => $cat]);
}

elseif ($method === 'GET' && preg_match('#^/categories$#', $path)) {
    $cats = $db->fetchAll(
        "SELECT id, name, slug, icon, description FROM categories
         WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
    );
    $ph = [
        'phone'=>'Contoh: 08123456789','bank_account'=>'Contoh: 1234567890',
        'dana'=>'Contoh: 08123456789','ovo'=>'Contoh: 08123456789',
        'gopay'=>'Contoh: 08123456789','shopeepay'=>'Contoh: 08123456789',
        'linkaja'=>'Contoh: 08123456789','email'=>'Contoh: user@email.com',
        'social'=>'Contoh: @username','other'=>'Masukkan data yang dilaporkan',
    ];
    foreach ($cats as &$c) {
        $c['placeholder_text'] = $ph[$c['slug']] ?? 'Masukkan data yang dilaporkan';
    }
    Response::success(['categories' => $cats]);
}

// ── /reports ─────────────────────────────────────────────────
elseif ($method === 'GET' && preg_match('#^/reports/([0-9A-Za-z]{26})$#', $path, $m)) {
    $service = new ReportService();
    $report  = $service->getByUlid($m[1]);
    if (!$report) Response::notFound('Laporan tidak ditemukan');
    Response::success($report, 'Detail laporan');
}

elseif ($method === 'GET' && preg_match('#^/reports$#', $path)) {
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(50, max(5, (int)($_GET['per_page'] ?? 20)));
    $service = new ReportService();
    $result  = $service->getAdminList(['status'=>'approved'], $page, $perPage);
    Response::paginated($result['data'], $result['total'], $page, $perPage);
}

elseif ($method === 'POST' && preg_match('#^/reports$#', $path)) {
    $ip = get_client_ip();
    if (!check_rate_limit('report_create_'.$ip, 5, 3600)) Response::rateLimited(3600);

    $data = get_json_body();
    if (empty($data)) $data = $_POST;

    // Handle file upload
    $evidenceUrls = [];
    if (!empty($_FILES['evidence'])) {
        $files = $_FILES['evidence'];
        if (!is_array($files['name'])) {
            $files = array_map(function($v){ return [$v]; }, $files);
        }
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i] > UPLOAD_MAX_SIZE) continue;
            if (!in_array($files['type'][$i], UPLOAD_ALLOWED_TYPES)) continue;
            $ext     = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $fname   = generate_token(16) . '.' . strtolower($ext);
            $destDir = UPLOAD_PATH . '/' . date('Y/m');
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            if (move_uploaded_file($files['tmp_name'][$i], $destDir . '/' . $fname)) {
                $evidenceUrls[] = BASE_URL . '/public/uploads/' . date('Y/m') . '/' . $fname;
            }
            if (count($evidenceUrls) >= UPLOAD_MAX_FILES) break;
        }
    }
    if (!empty($evidenceUrls)) $data['evidence_urls'] = $evidenceUrls;

    $service = new ReportService();
    $result  = $service->create($data);
    if (!$result['success']) Response::validationError($result['errors'] ?? []);
    Response::success(['ulid'=>$result['ulid'],'status'=>$result['status']], $result['message'], 201);
}

// ── /auth ─────────────────────────────────────────────────────
elseif ($method === 'POST' && preg_match('#^/auth/login$#', $path)) {
    $ip = get_client_ip();
    if (!check_rate_limit('admin_login_'.$ip, 5, 300)) Response::rateLimited(300);

    $body  = get_json_body();
    $email = trim($body['email'] ?? '');
    $pass  = trim($body['password'] ?? '');

    $errors = validate(['email'=>$email,'password'=>$pass],[
        'email'=>'required|email','password'=>'required|min:6',
    ]);
    if (!empty($errors)) Response::validationError($errors);

    $admin = $db->fetchOne("SELECT * FROM admins WHERE email = ? AND is_active = 1", [$email]);
    if (!$admin || !verify_password($pass, $admin['password'])) {
        sleep(1);
        Response::error('Email atau password salah', 401, [], 'INVALID_CREDENTIALS');
    }

    $db->update('admins', [
        'last_login_at' => date('Y-m-d H:i:s'),
        'last_login_ip' => $ip,
        'updated_at'    => date('Y-m-d H:i:s'),
    ], 'id = ?', [$admin['id']]);

    $token = generate_jwt(['admin_id'=>$admin['id'],'email'=>$admin['email'],'role'=>$admin['role']]);

    try {
        $db->insert('activity_logs', [
            'admin_id'    => $admin['id'],
            'action'      => 'auth.login',
            'entity_type' => 'admin',
            'entity_id'   => $admin['id'],
            'description' => 'Login dari IP '.$ip,
            'ip_address'  => $ip,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {}

    Response::success([
        'token'      => $token,
        'expires_in' => JWT_EXPIRE,
        'admin'      => ['id'=>$admin['id'],'name'=>$admin['name'],'email'=>$admin['email'],'role'=>$admin['role']],
    ], 'Login berhasil');
}

elseif ($method === 'GET' && preg_match('#^/auth/me$#', $path)) {
    $token = get_bearer_token();
    if (empty($token)) Response::unauthorized();
    $payload = verify_jwt($token);
    if (!$payload) Response::unauthorized('Token tidak valid');
    $admin = $db->fetchOne(
        "SELECT id, name, email, role, last_login_at FROM admins WHERE id = ? AND is_active = 1",
        [$payload['admin_id']]
    );
    if (!$admin) Response::unauthorized('Akun tidak aktif');
    Response::success($admin);
}

elseif ($method === 'POST' && preg_match('#^/auth/logout$#', $path)) {
    $token = get_bearer_token();
    if (!empty($token)) {
        $payload = verify_jwt($token);
        if ($payload) {
            try {
                $db->insert('activity_logs', [
                    'admin_id'    => $payload['admin_id'],
                    'action'      => 'auth.logout',
                    'entity_type' => 'admin',
                    'entity_id'   => $payload['admin_id'],
                    'description' => 'Logout',
                    'ip_address'  => get_client_ip(),
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {}
        }
    }
    Response::success(null, 'Logout berhasil');
}

// ── /modules ──────────────────────────────────────────────────
elseif ($method === 'GET' && preg_match('#^/modules$#', $path)) {
    $admin   = requireAdmin();
    $manager = ModuleManager::getInstance();
    $manager->discoverModules();
    $modules = $manager->getAllModules();
    Response::success(['modules' => $modules]);
}

elseif ($method === 'POST' && preg_match('#^/modules/([a-z0-9_-]+)/enable$#', $path, $m)) {
    requireAdmin(['superadmin','admin']);
    $manager = ModuleManager::getInstance();
    $ok = $manager->enable($m[1]);
    if (!$ok) Response::error('Modul tidak ditemukan atau gagal diaktifkan', 422);
    Response::success(null, 'Modul berhasil diaktifkan');
}

elseif ($method === 'POST' && preg_match('#^/modules/([a-z0-9_-]+)/disable$#', $path, $m)) {
    requireAdmin(['superadmin','admin']);
    $manager = ModuleManager::getInstance();
    $ok = $manager->disable($m[1]);
    if (!$ok) Response::error('Modul tidak bisa dinonaktifkan (mungkin modul inti)', 422);
    Response::success(null, 'Modul berhasil dinonaktifkan');
}

elseif ($method === 'PUT' && preg_match('#^/modules/([a-z0-9_-]+)/config$#', $path, $m)) {
    requireAdmin(['superadmin']);
    $body    = get_json_body();
    $manager = ModuleManager::getInstance();
    $ok      = $manager->updateConfig($m[1], $body);
    if (!$ok) Response::error('Gagal update konfigurasi', 422);
    Response::success(null, 'Konfigurasi berhasil disimpan');
}

// ── /admin/* ─────────────────────────────────────────────────
elseif (preg_match('#^/admin#', $path)) {

    // --- Stats ---
    if ($method === 'GET' && $path === '/admin/stats') {
        requireAdmin();
        $service = new ReportService();
        $stats   = $service->getStats();
        $stats['pending_reports']    = (int)$db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='pending'");
        $stats['flagged_reports']    = (int)$db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='flagged'");
        $stats['total_reporters']    = (int)$db->fetchColumn("SELECT COUNT(*) FROM reporters");
        $stats['searches_today']     = (int)$db->fetchColumn("SELECT COUNT(*) FROM search_logs WHERE DATE(created_at)=CURDATE()");
        $stats['searches_this_week'] = (int)$db->fetchColumn("SELECT COUNT(*) FROM search_logs WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)");
        $stats['reports_per_day']    = $db->fetchAll("SELECT DATE(created_at) as date, COUNT(*) as count FROM reports WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY date ASC");
        $stats['top_categories']     = $db->fetchAll("SELECT c.name,c.slug,c.icon,COUNT(r.id) as count FROM categories c LEFT JOIN reports r ON r.category_id=c.id AND r.status='approved' GROUP BY c.id ORDER BY count DESC LIMIT 5");
        $stats['top_searches']       = $db->fetchAll("SELECT query,COUNT(*) as count FROM search_logs WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY query ORDER BY count DESC LIMIT 10");
        Response::success($stats);
    }

    // --- Reports list ---
    elseif ($method === 'GET' && $path === '/admin/reports') {
        requireAdmin();
        $service = new ReportService();
        $filters = [
            'search'      => $_GET['search']   ?? '',
            'status'      => $_GET['status']   ?? '',
            'category'    => $_GET['category'] ?? '',
            'report_type' => $_GET['type']     ?? '',
        ];
        $page    = max(1, (int)($_GET['page']     ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 20)));
        $result  = $service->getAdminList($filters, $page, $perPage);
        Response::paginated($result['data'], $result['total'], $page, $perPage);
    }

    // --- Report detail ---
    elseif ($method === 'GET' && preg_match('#^/admin/reports/(\d+)$#', $path, $m)) {
        requireAdmin();
        $id     = (int)$m[1];
        $report = $db->fetchOne(
            "SELECT r.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                    rt.name AS report_type_name, rt.severity,
                    rp.name AS reporter_name, rp.contact AS reporter_contact, rp.contact_type, rp.ip_address,
                    rs.risk_score, rs.risk_level, rs.approved_reports AS risk_approved
             FROM reports r
             LEFT JOIN categories   c  ON c.id  = r.category_id
             LEFT JOIN report_types rt ON rt.id = r.report_type_id
             LEFT JOIN reporters    rp ON rp.id = r.reporter_id
             LEFT JOIN risk_scores  rs ON rs.reported_value_normalized = r.reported_value_normalized AND rs.category_id = r.category_id
             WHERE r.id = ?",
            [$id]
        );
        if (!$report) Response::notFound('Laporan tidak ditemukan');
        if (!empty($report['evidence_urls'])) $report['evidence_urls'] = json_decode($report['evidence_urls'], true);
        $report['moderation_history'] = $db->fetchAll(
            "SELECT al.action, al.description, al.created_at, a.name AS admin_name
             FROM activity_logs al LEFT JOIN admins a ON a.id = al.admin_id
             WHERE al.entity_type='report' AND al.entity_id=? ORDER BY al.created_at DESC",
            [$id]
        );
        Response::success(['report' => $report]);
    }

    // --- Moderate ---
    elseif ($method === 'POST' && preg_match('#^/admin/reports/(\d+)/moderate$#', $path, $m)) {
        $admin = requireAdmin();
        $body  = get_json_body();
        $id    = (int)$m[1];
        $service = new ReportService();
        $result  = $service->moderate($id, $body['action'] ?? '', $admin['id'], $body['note'] ?? '');
        if (!$result['success']) Response::error($result['message'], 422);
        Response::success(['message' => $result['message']]);
    }

    // --- Bulk moderate ---
    elseif ($method === 'POST' && $path === '/admin/reports/bulk') {
        $admin  = requireAdmin(['superadmin','admin']);
        $body   = get_json_body();
        $ids    = array_map('intval', (array)($body['ids'] ?? []));
        $action = trim($body['action'] ?? '');
        $note   = trim($body['note']   ?? '');

        if (empty($ids))   Response::validationError(['ids' => 'Pilih minimal 1 laporan']);
        if (count($ids) > 50) Response::error('Maksimal 50 laporan', 422);
        if (!in_array($action, ['approve','reject','flag'], true)) {
            Response::validationError(['action' => 'Action tidak valid']);
        }

        $service = new ReportService();
        $ok = $fail = 0;
        foreach ($ids as $rid) {
            try {
                $r = $service->moderate($rid, $action, (int)$admin['id'], $note);
                $r['success'] ? $ok++ : $fail++;
            } catch (Exception $e) {
                $fail++;
                error_log('bulk moderate error id='.$rid.': '.$e->getMessage());
            }
        }
        Response::success([
            'message' => "Berhasil: {$ok}, Gagal: {$fail}",
            'success' => $ok,
            'failed'  => $fail,
        ]);
    }

    // --- Users ---
    elseif ($method === 'GET' && $path === '/admin/users') {
        requireAdmin(['superadmin','admin']);
        $users = $db->fetchAll("SELECT id, name, email, role, is_active, last_login_at, created_at FROM admins ORDER BY role ASC, name ASC");
        Response::success(['users' => $users]);
    }

    elseif ($method === 'POST' && $path === '/admin/users') {
        $admin  = requireAdmin(['superadmin']);
        $body   = get_json_body();
        $errors = validate($body, ['name'=>'required|min:2','email'=>'required|email','password'=>'required|min:8','role'=>'required|in:admin,moderator']);
        if (!empty($errors)) Response::validationError($errors);
        if ($db->fetchColumn("SELECT id FROM admins WHERE email=?", [$body['email']])) Response::validationError(['email'=>'Email sudah digunakan']);
        $id = $db->insert('admins', ['name'=>e($body['name']),'email'=>strtolower($body['email']),'password'=>hash_password($body['password']),'role'=>$body['role'],'is_active'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        Response::success(['id'=>$id,'message'=>'Admin berhasil ditambahkan'], 'Created', 201);
    }

    elseif ($method === 'PUT' && preg_match('#^/admin/users/(\d+)$#', $path, $m)) {
        $admin  = requireAdmin(['superadmin']);
        $userId = (int)$m[1];
        $body   = get_json_body();
        $target = $db->fetchOne("SELECT * FROM admins WHERE id=?", [$userId]);
        if (!$target) Response::notFound('User tidak ditemukan');
        $upd = ['updated_at' => date('Y-m-d H:i:s')];
        if (!empty($body['name']))      $upd['name']      = e($body['name']);
        if (isset($body['is_active']))  $upd['is_active'] = (int)$body['is_active'];
        if (!empty($body['password']))  $upd['password']  = hash_password($body['password']);
        if (!empty($body['role']) && $target['role'] !== 'superadmin') $upd['role'] = $body['role'];
        $db->update('admins', $upd, 'id=?', [$userId]);
        Response::success(null, 'User berhasil diperbarui');
    }

    elseif ($method === 'DELETE' && preg_match('#^/admin/users/(\d+)$#', $path, $m)) {
        $admin  = requireAdmin(['superadmin']);
        $userId = (int)$m[1];
        if ($userId === $admin['id']) Response::error('Tidak dapat menghapus akun sendiri', 422);
        $target = $db->fetchOne("SELECT * FROM admins WHERE id=?", [$userId]);
        if (!$target) Response::notFound('User tidak ditemukan');
        if ($target['role'] === 'superadmin') Response::forbidden('Tidak dapat menghapus superadmin');
        $db->delete('admins', 'id=?', [$userId]);
        Response::success(null, 'Admin berhasil dihapus');
    }

    // --- Settings ---
    elseif ($method === 'GET' && $path === '/admin/settings') {
        requireAdmin(['superadmin','admin']);
        $rows     = $db->fetchAll("SELECT `key`, value, type, description FROM settings ORDER BY `key`");
        $settings = [];
        foreach ($rows as $r) {
            $v = $r['value'];
            if ($r['type'] === 'boolean') $v = (bool)$v;
            elseif ($r['type'] === 'integer') $v = (int)$v;
            elseif ($r['type'] === 'json') $v = json_decode($v, true);
            $settings[$r['key']] = ['value'=>$v,'type'=>$r['type'],'description'=>$r['description']];
        }
        Response::success(['settings' => $settings]);
    }

    elseif ($method === 'PUT' && $path === '/admin/settings') {
        $admin = requireAdmin(['superadmin']);
        $body  = get_json_body();
        foreach ($body as $key => $value) {
            $row = $db->fetchOne("SELECT id, type FROM settings WHERE `key`=?", [$key]);
            if (!$row) continue;
            switch ($row['type']) {
                case 'boolean': $sv = $value ? '1' : '0'; break;
                case 'integer': $sv = (string)(int)$value; break;
                case 'json':    $sv = json_encode($value); break;
                default:        $sv = (string)$value; break;
            }
            $db->update('settings', ['value'=>$sv], '`key`=?', [$key]);
        }
        Response::success(null, 'Pengaturan berhasil disimpan');
    }

    // --- API Keys ---
    elseif ($method === 'GET' && $path === '/admin/api-keys') {
        requireAdmin(['superadmin','admin']);
        $keys = $db->fetchAll("SELECT id, name, key_prefix, permissions, rate_limit, is_active, last_used_at, usage_count, expires_at, created_at FROM api_keys ORDER BY created_at DESC");
        foreach ($keys as &$k) { if (!empty($k['permissions'])) $k['permissions'] = json_decode($k['permissions'], true); }
        Response::success(['api_keys' => $keys]);
    }

    elseif ($method === 'POST' && $path === '/admin/api-keys') {
        $admin  = requireAdmin(['superadmin','admin']);
        $body   = get_json_body();
        $errors = validate($body, ['name'=>'required|min:3','rate_limit'=>'required|numeric']);
        if (!empty($errors)) Response::validationError($errors);
        $prefix  = 'ck_' . ($body['env'] === 'test' ? 'test' : 'live');
        $rawKey  = $prefix . '_' . bin2hex(random_bytes(24));
        $id      = $db->insert('api_keys', [
            'name'=>e($body['name']),'key_hash'=>hash('sha256',$rawKey),
            'key_prefix'=>substr($rawKey,0,12).'...','permissions'=>json_encode($body['permissions'] ?? ['search','read']),
            'rate_limit'=>(int)$body['rate_limit'],'is_active'=>1,'usage_count'=>0,
            'expires_at'=>$body['expires_at'] ?? null,'created_by'=>$admin['id'],
            'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),
        ]);
        Response::success(['key'=>$rawKey,'id'=>$id,'message'=>'API key dibuat. Simpan sekarang!'], 'Created', 201);
    }

    elseif ($method === 'DELETE' && preg_match('#^/admin/api-keys/(\d+)$#', $path, $m)) {
        requireAdmin(['superadmin','admin']);
        $key = $db->fetchOne("SELECT * FROM api_keys WHERE id=?", [(int)$m[1]]);
        if (!$key) Response::notFound('API key tidak ditemukan');
        $db->update('api_keys', ['is_active'=>0,'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [(int)$m[1]]);
        Response::success(null, 'API key dinonaktifkan');
    }

    // --- Logs & Risk ---
    elseif ($method === 'GET' && $path === '/admin/risk-scores') {
        requireAdmin();
        $page    = max(1,(int)($_GET['page'] ?? 1));
        $perPage = min(100,max(10,(int)($_GET['per_page'] ?? 20)));
        $offset  = ($page-1)*$perPage;
        $where = '1=1'; $params = [];
        if (!empty($_GET['level']))    { $where .= ' AND rs.risk_level=?'; $params[] = $_GET['level']; }
        if (!empty($_GET['category'])) { $where .= ' AND rs.category_id=?'; $params[] = (int)$_GET['category']; }
        $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM risk_scores rs WHERE {$where}", $params);
        $rows  = $db->fetchAll(
            "SELECT rs.reported_value_normalized AS normalized_value, rs.category_id,
                    rs.total_reports, rs.approved_reports, rs.risk_score AS score,
                    rs.risk_level AS level, rs.last_reported_at, c.name AS category_name
             FROM risk_scores rs LEFT JOIN categories c ON c.id=rs.category_id
             WHERE {$where} ORDER BY rs.risk_score DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        Response::paginated($rows, $total, $page, $perPage);
    }

    elseif ($method === 'GET' && $path === '/admin/search-logs') {
        requireAdmin();
        $page    = max(1,(int)($_GET['page'] ?? 1));
        $perPage = min(100,max(10,(int)($_GET['per_page'] ?? 30)));
        $offset  = ($page-1)*$perPage;
        $where = '1=1'; $params = [];
        if (!empty($_GET['query']))      { $where .= ' AND query LIKE ?'; $params[] = '%'.$db->escapeLike($_GET['query']).'%'; }
        if (isset($_GET['has_result']) && $_GET['has_result'] !== '') { $where .= ' AND has_result=?'; $params[] = (int)$_GET['has_result']; }
        $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM search_logs WHERE {$where}", $params);
        $logs  = $db->fetchAll("SELECT * FROM search_logs WHERE {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}", $params);
        Response::paginated($logs, $total, $page, $perPage);
    }

    elseif ($method === 'GET' && $path === '/admin/logs') {
        requireAdmin(['superadmin','admin']);
        $page    = max(1,(int)($_GET['page'] ?? 1));
        $perPage = min(100,max(10,(int)($_GET['per_page'] ?? 30)));
        $offset  = ($page-1)*$perPage;
        $where = '1=1'; $params = [];
        if (!empty($_GET['admin_id'])) { $where .= ' AND al.admin_id=?'; $params[] = (int)$_GET['admin_id']; }
        $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM activity_logs al WHERE {$where}", $params);
        $logs  = $db->fetchAll(
            "SELECT al.*, a.name AS admin_name, a.role AS admin_role FROM activity_logs al
             LEFT JOIN admins a ON a.id=al.admin_id WHERE {$where} ORDER BY al.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        Response::paginated($logs, $total, $page, $perPage);
    }

    else {
        Response::notFound('Endpoint admin tidak ditemukan');
    }
}

// ── 404 ───────────────────────────────────────────────────────
else {
    http_response_code(404);
    echo json_encode([
        'success'   => false,
        'message'   => 'Endpoint tidak ditemukan: ' . $method . ' /api/v1' . $path,
        'timestamp' => time(),
    ]);
}
