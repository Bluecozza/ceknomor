<?php
/**
 * ./api.php
 * Single API entry point — semua /api/v1/* diarahkan ke sini
 * Tidak ada folder api/ fisik agar terhindar dari 403
 */

require_once __DIR__ . '/bootstrap.php';

// ── CORS & Headers ────────────────────────────────────────────
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') { http_response_code(204); exit; }

// ── Path ──────────────────────────────────────────────────────
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri  = rtrim($uri, '/');
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($base && $base !== '/' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
// Strip /api/v1
$path = preg_replace('#^/api/v1#', '', $uri);
if (empty($path)) $path = '/';

$db = Database::getInstance();

// ── Auth helper ───────────────────────────────────────────────
function requireAdmin(array $roles = ['superadmin','admin','moderator']): array {
    $token = get_bearer_token();
    if (empty($token)) Response::unauthorized('Token tidak ditemukan');
    $payload = verify_jwt($token);
    if (!$payload) Response::unauthorized('Token tidak valid atau kedaluwarsa');
    if (!in_array($payload['role'], $roles, true)) Response::forbidden('Akses ditolak: role tidak mencukupi');
    return $payload;
}

// ── Serve Plugin Assets ──────────────────────────────────────
if (preg_match('#^/modules/([a-z0-9_-]+)/(admin|assets)/(.+)$#', $uri, $m)) {
    $plugin = $m[1];
    $type = $m[2];
    $file = $m[3];
    
    $path = MODULE_PATH . '/' . $plugin . '/' . $type . '/' . $file;
    
    // Prevent directory traversal
    if (strpos(realpath($path), realpath(MODULE_PATH . '/' . $plugin)) !== 0) {
        http_response_code(403);
        exit;
    }
    
    if (file_exists($path) && is_file($path)) {
        // Determine MIME type
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimes = [
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];
        
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        
        // For PHP files, execute them
        if ($ext === 'php') {
            $GLOBALS['admin'] = isset($admin) ? $admin : null;
            include $path;
        } else {
            readfile($path);
        }
        exit;
    }
    
    http_response_code(404);
    exit;
}

// ════════════════════════════════════════════════════════════
// ROUTING
// ════════════════════════════════════════════════════════════

// ── GET /debug-jwt ────────────────────────────────────────────
if ($method === 'GET' && $path === '/debug-jwt') {
    $token = get_bearer_token();
    $result = [
        'jwt_secret_len'  => strlen(JWT_SECRET),
        'jwt_secret_hash' => md5(JWT_SECRET),
        'token_received'  => !empty($token),
        'token_len'       => strlen($token),
        'token_parts'     => $token ? count(explode('.', $token)) : 0,
    ];
    if ($token) {
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            [$h, $b, $s] = $parts;
            $expected = b64url_encode(hash_hmac('sha256', "{$h}.{$b}", JWT_SECRET, true));
            $result['sig_match']    = hash_equals($expected, $s);
            $result['sig_expected'] = substr($expected, 0, 10) . '...';
            $result['sig_received'] = substr($s, 0, 10) . '...';
            $decoded = json_decode(b64url_decode($b), true);
            $result['payload_ok']  = is_array($decoded);
            $result['payload_exp'] = $decoded['exp'] ?? null;
            $result['time_now']    = time();
            $result['not_expired'] = ($decoded['exp'] ?? 0) > time();
        }
    }
    Response::success($result);
}

// ── GET /test-route-match (debug route matching)
if ($path === '/test-route-match') {
    $modulePath = MODULE_PATH;
    $testPath = '/plugins/csvimport/upload';
    $testMethod = 'POST';
    
    $matchResults = [];
    
    if (is_dir($modulePath)) {
        foreach (glob($modulePath . '/*/routes/api.php') as $routeFile) {
            $pluginSlug = basename(dirname(dirname($routeFile)));
            $routes = @include $routeFile;
            
            if (!is_array($routes)) continue;
            
            foreach ($routes as $idx => $route) {
                $routeMethod = $route['method'] ?? 'GET';
                $routePath = '/plugins/' . $pluginSlug . ($route['path'] ?? '');
                $isPattern = $route['pattern'] ?? false;
                
                $matches = false;
                $regexMatches = [];
                
                if ($routeMethod === $testMethod) {
                    if ($isPattern) {
                        $pattern = '#^' . $routePath . '$#';
                        if (preg_match($pattern, $testPath, $regexMatches)) {
                            $matches = true;
                        }
                        $matchResults[] = [
                            'route_idx' => $idx,
                            'route_method' => $routeMethod,
                            'route_path' => $routePath,
                            'is_pattern' => true,
                            'pattern' => $pattern,
                            'test_path' => $testPath,
                            'matches' => $matches,
                            'regex_matches' => $regexMatches
                        ];
                    } else {
                        if ($testPath === $routePath) {
                            $matches = true;
                        }
                        $matchResults[] = [
                            'route_idx' => $idx,
                            'route_method' => $routeMethod,
                            'route_path' => $routePath,
                            'is_pattern' => false,
                            'test_path' => $testPath,
                            'matches' => $matches
                        ];
                    }
                }
            }
        }
    }
    
    Response::success([
        'test_path' => $testPath,
        'test_method' => $testMethod,
        'routes_checked' => count($matchResults),
        'matches_found' => array_filter($matchResults, fn($m) => $m['matches']),
        'all_routes' => $matchResults
    ]);
}
// ── GET /admin/debug-routes (TANPA AUTH - untuk debugging)
if ($method === 'GET' && $path === '/admin/debug-routes') {
    $modulePath = MODULE_PATH;
    $pluginRoutes = [];
    
    if (is_dir($modulePath)) {
        foreach (glob($modulePath . '/*/routes/api.php') as $routeFile) {
            $pluginSlug = basename(dirname(dirname($routeFile)));
            $routes = @include $routeFile;
            
            $pluginRoutes[$pluginSlug] = [
                'file_exists' => file_exists($routeFile),
                'file_path' => $routeFile,
                'routes_count' => is_array($routes) ? count($routes) : 0,
                'first_route' => is_array($routes) && count($routes) > 0 ? $routes[0] : null
            ];
        }
    }
    
    $plugins = __PLUGINS->getLoadedPlugins();
    
    Response::success([
        'module_path' => $modulePath,
        'module_path_exists' => is_dir($modulePath),
        'loaded_plugins' => array_keys($plugins),
        'csvimport_in_loaded' => isset($plugins['csvimport']),
        'plugin_routes' => $pluginRoutes,
    ]);
}
// ── GET /stats ────────────────────────────────────────────────
elseif ($method === 'GET' && $path === '/stats') {
    $cacheFile = STORAGE_PATH . '/cache/stats.json';
    $ttl       = 300;
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $stats = json_decode(file_get_contents($cacheFile), true) ?? [];
    } else {
        $stats = [
            'total_reports'   => (int)$db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='approved'"),
            'searches_today'  => (int)$db->fetchColumn("SELECT COUNT(*) FROM search_logs WHERE DATE(created_at)=CURDATE()"),
            'high_risk_count' => (int)$db->fetchColumn("SELECT COUNT(*) FROM risk_scores WHERE risk_level IN ('high','critical')"),
        ];
        @file_put_contents($cacheFile, json_encode($stats));
    }
    Response::success($stats);
}

// ── POST|GET /search ──────────────────────────────────────────
elseif ($path === '/search' && in_array($method, ['GET','POST'])) {
    if (!check_rate_limit('search_' . get_client_ip(), 30, 60)) Response::rateLimited(60);
    if ($method === 'GET') {
        $q    = trim($_GET['q']        ?? '');
        $cat  = trim($_GET['category'] ?? '');
    } else {
        $body = get_json_body();
        $q    = trim($body['q'] ?? $body['query'] ?? '');
        $cat  = trim($body['category'] ?? '');
    }
    if (strlen($q) < 3)   Response::error('Query minimal 3 karakter', 422);
    if (strlen($q) > 255) Response::error('Query terlalu panjang', 422);
    $svc = new ReportService();
    Response::success($svc->search($q, $cat), 'Pencarian berhasil');
}

// ── GET /categories/report-types ──────────────────────────────
elseif ($method === 'GET' && $path === '/categories/report-types') {
    $types = $db->fetchAll("SELECT id, name, slug, description, severity FROM report_types WHERE is_active=1 ORDER BY severity DESC, sort_order ASC");
    $colors = [1=>'secondary',2=>'warning',3=>'orange',4=>'danger'];
    foreach ($types as &$t) $t['color_class'] = $colors[(int)$t['severity']] ?? 'secondary';
    Response::success(['report_types' => $types]);
}

// ── GET /categories/{slug} ────────────────────────────────────
elseif ($method === 'GET' && preg_match('#^/categories/([a-z0-9_-]+)$#', $path, $m)) {
    $cat = $db->fetchOne(
        "SELECT c.*, COUNT(r.id) AS total_reports, COUNT(CASE WHEN r.status='approved' THEN 1 END) AS approved_reports
         FROM categories c LEFT JOIN reports r ON r.category_id=c.id
         WHERE c.slug=? AND c.is_active=1 GROUP BY c.id", [$m[1]]
    );
    if (!$cat) Response::notFound('Kategori tidak ditemukan');
    Response::success(['category' => $cat]);
}

// ── GET /categories ───────────────────────────────────────────
elseif ($method === 'GET' && $path === '/categories') {
    $cats = $db->fetchAll("SELECT id, name, slug, icon, description FROM categories WHERE is_active=1 ORDER BY sort_order ASC, name ASC");
    $ph   = ['phone'=>'Contoh: 08123456789','bank_account'=>'Contoh: 1234567890','dana'=>'Contoh: 08123456789',
             'ovo'=>'Contoh: 08123456789','gopay'=>'Contoh: 08123456789','shopeepay'=>'Contoh: 08123456789',
             'linkaja'=>'Contoh: 08123456789','email'=>'Contoh: user@email.com','social'=>'Contoh: @username'];
    foreach ($cats as &$c) $c['placeholder_text'] = $ph[$c['slug']] ?? 'Masukkan data yang dilaporkan';
    Response::success(['categories' => $cats]);
}

// ── GET /reports/{ulid} ────────────────────────────────────────
elseif ($method === 'GET' && preg_match('#^/reports/([0-9A-Za-z]{26})$#', $path, $m)) {
    $svc    = new ReportService();
    $report = $svc->getByUlid($m[1]);
    if (!$report) Response::notFound('Laporan tidak ditemukan');
    Response::success($report, 'Detail laporan');
}

// ── GET /reports ──────────────────────────────────────────────
elseif ($method === 'GET' && $path === '/reports') {
    $page    = max(1, (int)($_GET['page']     ?? 1));
    $perPage = min(50, max(5, (int)($_GET['per_page'] ?? 20)));
    $svc     = new ReportService();
    $result  = $svc->getAdminList(['status'=>'approved'], $page, $perPage);
    Response::paginated($result['data'], $result['total'], $page, $perPage);
}

// ── POST /reports ──────────────────────────────────────────────
elseif ($method === 'POST' && $path === '/reports') {
    if (!check_rate_limit('report_' . get_client_ip(), 20, 300)) Response::rateLimited(300);
    $data = get_json_body();
    if (empty($data)) $data = $_POST;

    // File upload
    $evidenceUrls = [];
    if (!empty($_FILES['evidence'])) {
        $files = $_FILES['evidence'];
        if (!is_array($files['name'])) {
            $files = array_map(function($v){ return [$v]; }, $files);
        }
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i]  > UPLOAD_MAX_SIZE)  continue;
            if (!in_array($files['type'][$i], UPLOAD_ALLOWED_TYPES)) continue;
            $ext  = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $dest = UPLOAD_PATH . '/' . date('Y/m');
            if (!is_dir($dest)) mkdir($dest, 0755, true);
            $fname = generate_token(16) . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], $dest . '/' . $fname)) {
                $evidenceUrls[] = BASE_URL . '/public/uploads/' . date('Y/m') . '/' . $fname;
            }
            if (count($evidenceUrls) >= UPLOAD_MAX_FILES) break;
        }
    }
    if (!empty($evidenceUrls)) $data['evidence_urls'] = $evidenceUrls;

    $svc    = new ReportService();
    $result = $svc->create($data);
    if (!$result['success']) Response::validationError($result['errors'] ?? []);
    Response::success(['ulid'=>$result['ulid'],'status'=>$result['status']], $result['message'], 201);
}

// ── POST /auth/login ───────────────────────────────────────────
elseif ($method === 'POST' && $path === '/auth/login') {
    $ip = get_client_ip();
    if (!check_rate_limit('login_' . $ip, 5, 300)) Response::rateLimited(300);

    $body   = get_json_body();
    $email  = trim($body['email']    ?? '');
    $pass   = trim($body['password'] ?? '');
    $errors = validate(['email'=>$email,'password'=>$pass], ['email'=>'required|email','password'=>'required|min:6']);
    if (!empty($errors)) Response::validationError($errors);

    $admin = $db->fetchOne("SELECT * FROM admins WHERE email=? AND is_active=1", [strtolower($email)]);
    if (!$admin || !verify_password($pass, $admin['password'])) {
        sleep(1);
        Response::error('Email atau password salah', 401, [], 'INVALID_CREDENTIALS');
    }

    $db->update('admins', ['last_login_at'=>date('Y-m-d H:i:s'),'last_login_ip'=>$ip,'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$admin['id']]);

    try {
        $db->insert('activity_logs', ['admin_id'=>$admin['id'],'action'=>'auth.login','entity_type'=>'admin',
            'entity_id'=>$admin['id'],'description'=>'Login dari '.$ip,'ip_address'=>$ip,'created_at'=>date('Y-m-d H:i:s')]);
    } catch (Exception $e) {}

    $token = generate_jwt(['admin_id'=>$admin['id'],'email'=>$admin['email'],'role'=>$admin['role']]);
    Response::success(['token'=>$token,'expires_in'=>JWT_EXPIRE,
        'admin'=>['id'=>$admin['id'],'name'=>$admin['name'],'email'=>$admin['email'],'role'=>$admin['role']]], 'Login berhasil');
}

// ── GET /auth/me ───────────────────────────────────────────────
elseif ($method === 'GET' && $path === '/auth/me') {
    $token = get_bearer_token();
    if (empty($token)) {
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_') || $k === 'REDIRECT_HTTP_AUTHORIZATION') {
                $headers[$k] = substr((string)$v, 0, 50);
            }
        }
        Response::error('Token tidak ditemukan', 401, APP_DEBUG ? $headers : [], 'UNAUTHORIZED');
    }

    $payload = verify_jwt($token);
    if (!$payload) {
        if (APP_DEBUG) {
            $parts = explode('.', $token);
            $debug = ['parts' => count($parts), 'token_preview' => substr($token, 0, 30) . '...'];
            if (count($parts) === 3) {
                $decoded = json_decode(b64url_decode($parts[1]), true);
                $expected = b64url_encode(hash_hmac('sha256', $parts[0].'.'.$parts[1], JWT_SECRET, true));
                $debug['sig_match']  = hash_equals($expected, $parts[2]);
                $debug['expired']    = ($decoded['exp'] ?? 0) < time();
                $debug['secret_len'] = strlen(JWT_SECRET);
            }
            Response::error('Token tidak valid', 401, $debug, 'UNAUTHORIZED');
        }
        Response::unauthorized('Token tidak valid');
    }

    $admin = $db->fetchOne("SELECT id,name,email,role,last_login_at FROM admins WHERE id=? AND is_active=1", [$payload['admin_id']]);
    if (!$admin) Response::unauthorized('Akun tidak aktif');
    Response::success($admin);
}

// ── POST /auth/logout ──────────────────────────────────────────
elseif ($method === 'POST' && $path === '/auth/logout') {
    $token = get_bearer_token();
    if (!empty($token)) {
        $p = verify_jwt($token);
        if ($p) { try { $db->insert('activity_logs', ['admin_id'=>$p['admin_id'],'action'=>'auth.logout','entity_type'=>'admin','entity_id'=>$p['admin_id'],'description'=>'Logout','ip_address'=>get_client_ip(),'created_at'=>date('Y-m-d H:i:s')]); } catch (Exception $e) {} }
    }
    Response::success(null, 'Logout berhasil');
}

// ── GET /modules ───────────────────────────────────────────────
elseif ($method === 'GET' && $path === '/modules') {
    requireAdmin();
    $manager = __PLUGINS;
    $plugins = [];
    
    try {
        $manager->loadAll();
        $allPlugins = $manager->getAllPlugins();
        
        foreach ($allPlugins as $slug => $plugin) {
            $plugins[] = [
                'id' => $plugin['slug'],
                'slug' => $slug,
                'name' => $plugin['name'] ?? '',
                'description' => $plugin['description'] ?? '',
                'version' => $plugin['version'] ?? '1.0.0',
                'is_active' => $plugin['is_active'] ?? 0,
                'author' => $plugin['author'] ?? ''
            ];
        }
    } catch (Exception $e) {
        $plugins = [];
    }
    
    Response::success(['modules' => $plugins]);
}

// ── POST /modules/{slug}/enable|disable ────────────────────────
elseif ($method === 'POST' && preg_match('#^/modules/([a-z0-9_-]+)/(enable|disable)$#', $path, $m)) {
    requireAdmin(['superadmin','admin']);
    $manager = __PLUGINS;
    $ok = $m[2] === 'enable' ? $manager->activate($m[1]) : $manager->deactivate($m[1]);
    if (!$ok) Response::error('Gagal ' . $m[2] . ' plugin ' . $m[1], 422);
    Response::success(null, "Plugin {$m[1]} berhasil di-{$m[2]}");
}

// ── GET /modules/{slug} ────────────────────────────────────────
elseif ($method === 'GET' && preg_match('#^/modules/([a-z0-9_-]+)$#', $path, $m)) {
    requireAdmin();
    $manager = __PLUGINS;
    $plugin = $manager->getAllPlugins()[$m[1]] ?? null;
    
    if (!$plugin) {
        Response::notFound('Plugin tidak ditemukan');
    }

    $config = $manager->getConfig($m[1]);
    
    Response::success([
        'plugin' => [
            'slug' => $m[1],
            'name' => $plugin['name'] ?? '',
            'version' => $plugin['version'] ?? '',
            'description' => $plugin['description'] ?? '',
            'is_active' => $plugin['is_active'] ?? 0,
            'config' => $config
        ]
    ]);
}

// ── PUT /modules/{slug}/config ─────────────────────────────────
elseif ($method === 'PUT' && preg_match('#^/modules/([a-z0-9_-]+)/config$#', $path, $m)) {
    requireAdmin(['superadmin']);
    $manager = __PLUGINS;
    $body = get_json_body();
    
    if ($manager->updateConfig($m[1], $body)) {
        Response::success(null, 'Config berhasil disimpan');
    } else {
        Response::error('Gagal update config', 422);
    }
}

// ── /admin/* ───────────────────────────────────────────────────
elseif (preg_match('#^/admin#', $path)) {

    // GET /admin/stats
    if ($method === 'GET' && $path === '/admin/stats') {
        requireAdmin();
        $svc   = new ReportService();
        $stats = $svc->getStats();
        $stats['pending_reports']    = (int)$db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='pending'");
        $stats['flagged_reports']    = (int)$db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='flagged'");
        $stats['searches_today']     = (int)$db->fetchColumn("SELECT COUNT(*) FROM search_logs WHERE DATE(created_at)=CURDATE()");
        $stats['searches_this_week'] = (int)$db->fetchColumn("SELECT COUNT(*) FROM search_logs WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)");
        $stats['total_reporters']    = (int)$db->fetchColumn("SELECT COUNT(*) FROM reporters");
        $stats['top_categories']     = $db->fetchAll("SELECT c.name,c.slug,c.icon,COUNT(r.id) AS count FROM categories c LEFT JOIN reports r ON r.category_id=c.id AND r.status='approved' GROUP BY c.id ORDER BY count DESC LIMIT 5");
        $stats['top_searches']       = $db->fetchAll("SELECT query,COUNT(*) AS count FROM search_logs WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY query ORDER BY count DESC LIMIT 10");
        $stats['reports_per_day']    = $db->fetchAll("SELECT DATE(created_at) AS date,COUNT(*) AS count FROM reports WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY date ASC");
        Response::success($stats);
    }

    // GET /admin/reports
    elseif ($method === 'GET' && $path === '/admin/reports') {
        requireAdmin();
        $svc    = new ReportService();
        $page   = max(1,(int)($_GET['page']??1));
        $pp     = min(100,max(10,(int)($_GET['per_page']??20)));
        $result = $svc->getAdminList(['search'=>$_GET['search']??'','status'=>$_GET['status']??'','category'=>$_GET['category']??'','report_type'=>$_GET['type']??''], $page, $pp);
        Response::paginated($result['data'], $result['total'], $page, $pp);
    }

    // GET /admin/reports/{id}
    elseif ($method === 'GET' && preg_match('#^/admin/reports/(\d+)$#', $path, $m)) {
        requireAdmin();
        $report = $db->fetchOne(
            "SELECT r.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                    rt.name AS report_type_name, rt.severity,
                    rp.name AS reporter_name, rp.contact AS reporter_contact, rp.contact_type, rp.ip_address,
                    rs.risk_score, rs.risk_level, rs.approved_reports AS risk_approved
             FROM reports r
             LEFT JOIN categories c ON c.id=r.category_id
             LEFT JOIN report_types rt ON rt.id=r.report_type_id
             LEFT JOIN reporters rp ON rp.id=r.reporter_id
             LEFT JOIN risk_scores rs ON rs.reported_value_normalized=r.reported_value_normalized AND rs.category_id=r.category_id
             WHERE r.id=?", [(int)$m[1]]
        );
        if (!$report) Response::notFound('Laporan tidak ditemukan');
        if (!empty($report['evidence_urls'])) $report['evidence_urls'] = json_decode($report['evidence_urls'], true);
        $report['moderation_history'] = $db->fetchAll(
            "SELECT al.action,al.description,al.created_at,a.name AS admin_name FROM activity_logs al
             LEFT JOIN admins a ON a.id=al.admin_id WHERE al.entity_type='report' AND al.entity_id=? ORDER BY al.created_at DESC",
            [(int)$m[1]]
        );
        Response::success(['report' => $report]);
    }

    // POST /admin/reports/{id}/moderate
    elseif ($method === 'POST' && preg_match('#^/admin/reports/(\d+)/moderate$#', $path, $m)) {
        $admin  = requireAdmin();
        $body   = get_json_body();
        $svc    = new ReportService();
        $result = $svc->moderate((int)$m[1], $body['action']??'', (int)$admin['admin_id'], $body['note']??'');
        if (!$result['success']) Response::error($result['message'], 422);
        Response::success(null, $result['message']);
    }

    // POST /admin/reports/bulk
    elseif ($method === 'POST' && $path === '/admin/reports/bulk') {
        $admin  = requireAdmin(['superadmin','admin']);
        $body   = get_json_body();
        $ids    = array_map('intval', (array)($body['ids']??[]));
        $action = trim($body['action']??'');
        $note   = trim($body['note']??'');
        if (empty($ids)) Response::validationError(['ids'=>'Pilih minimal 1 laporan']);
        if (count($ids) > 50) Response::error('Maksimal 50 laporan', 422);
        if (!in_array($action,['approve','reject','flag'],true)) Response::validationError(['action'=>'Action tidak valid']);
        $svc = new ReportService();
        $ok = $fail = 0;
        foreach ($ids as $id) {
            $r = $svc->moderate($id, $action, (int)$admin['admin_id'], $note);
            $r['success'] ? $ok++ : $fail++;
        }
        Response::success(['message'=>"Berhasil:{$ok}, Gagal:{$fail}",'success'=>$ok,'failed'=>$fail]);
    }

    // GET /admin/users
    elseif ($method === 'GET' && $path === '/admin/users') {
        requireAdmin(['superadmin','admin']);
        $users = $db->fetchAll("SELECT id,name,email,role,is_active,last_login_at,created_at FROM admins ORDER BY role,name");
        Response::success(['users' => $users]);
    }

    // POST /admin/users
    elseif ($method === 'POST' && $path === '/admin/users') {
        requireAdmin(['superadmin']);
        $body   = get_json_body();
        $errors = validate($body, ['name'=>'required|min:2','email'=>'required|email','password'=>'required|min:8','role'=>'required|in:admin,moderator']);
        if (!empty($errors)) Response::validationError($errors);
        if ($db->fetchColumn("SELECT id FROM admins WHERE email=?", [strtolower($body['email'])])) Response::validationError(['email'=>'Email sudah digunakan']);
        $id = $db->insert('admins', ['name'=>e($body['name']),'email'=>strtolower($body['email']),'password'=>hash_password($body['password']),'role'=>$body['role'],'is_active'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        Response::success(['id'=>$id], 'Admin berhasil ditambahkan', 201);
    }

    // PUT /admin/users/{id}
    elseif ($method === 'PUT' && preg_match('#^/admin/users/(\d+)$#', $path, $m)) {
        requireAdmin(['superadmin']);
        $body   = get_json_body();
        $target = $db->fetchOne("SELECT * FROM admins WHERE id=?", [(int)$m[1]]);
        if (!$target) Response::notFound('User tidak ditemukan');
        $upd = ['updated_at'=>date('Y-m-d H:i:s')];
        if (!empty($body['name']))     $upd['name']      = e($body['name']);
        if (isset($body['is_active'])) $upd['is_active'] = (int)$body['is_active'];
        if (!empty($body['password'])) $upd['password']  = hash_password($body['password']);
        if (!empty($body['role']) && $target['role'] !== 'superadmin') $upd['role'] = $body['role'];
        $db->update('admins', $upd, 'id=?', [(int)$m[1]]);
        Response::success(null, 'User diperbarui');
    }

    // DELETE /admin/users/{id}
    elseif ($method === 'DELETE' && preg_match('#^/admin/users/(\d+)$#', $path, $m)) {
        $admin  = requireAdmin(['superadmin']);
        $userId = (int)$m[1];
        if ($userId === (int)$admin['admin_id']) Response::error('Tidak dapat hapus akun sendiri', 422);
        $target = $db->fetchOne("SELECT role FROM admins WHERE id=?", [$userId]);
        if (!$target) Response::notFound('User tidak ditemukan');
        if ($target['role'] === 'superadmin') Response::forbidden('Tidak dapat hapus superadmin');
        $db->delete('admins', 'id=?', [$userId]);
        Response::success(null, 'Admin dihapus');
    }

    // GET /admin/settings
    elseif ($method === 'GET' && $path === '/admin/settings') {
        requireAdmin(['superadmin','admin']);
        $rows = $db->fetchAll("SELECT `key`,value,type,`group`,label,description FROM settings ORDER BY `key`");
        $out  = [];
        foreach ($rows as $r) {
            $v = $r['value'];
            if ($r['type']==='boolean') $v=(bool)$v;
            elseif ($r['type']==='integer') $v=(int)$v;
            elseif ($r['type']==='json') $v=json_decode($v,true);
            $out[$r['key']] = ['value'=>$v,'type'=>$r['type'],'group'=>$r['group']??'general','label'=>$r['label']??$r['key'],'description'=>$r['description']??''];
        }
        Response::success(['settings' => $out]);
    }

    // PUT /admin/settings
    elseif ($method === 'PUT' && $path === '/admin/settings') {
        requireAdmin(['superadmin']);
        $body = get_json_body();
        foreach ($body as $key => $val) {
            $row = $db->fetchOne("SELECT type FROM settings WHERE `key`=?", [$key]);
            if (!$row) continue;
            switch ($row['type']) {
                case 'boolean': $sv = $val ? '1' : '0'; break;
                case 'integer': $sv = (string)(int)$val; break;
                case 'json':    $sv = json_encode($val); break;
                default:        $sv = (string)$val; break;
            }
            $db->update('settings', ['value'=>$sv], '`key`=?', [$key]);
        }
        Response::success(null, 'Pengaturan disimpan');
    }

    // GET /admin/api-keys
    elseif ($method === 'GET' && $path === '/admin/api-keys') {
        requireAdmin(['superadmin','admin']);
        $keys = $db->fetchAll("SELECT id,name,key_prefix,permissions,rate_limit,is_active,last_used_at,usage_count,expires_at,created_at FROM api_keys ORDER BY created_at DESC");
        foreach ($keys as &$k) if (!empty($k['permissions'])) $k['permissions'] = json_decode($k['permissions'],true);
        Response::success(['api_keys' => $keys]);
    }

    // POST /admin/api-keys
    elseif ($method === 'POST' && $path === '/admin/api-keys') {
        $admin  = requireAdmin(['superadmin','admin']);
        $body   = get_json_body();
        $errors = validate($body, ['name'=>'required|min:3','rate_limit'=>'required|numeric']);
        if (!empty($errors)) Response::validationError($errors);
        $env    = ($body['env'] ?? 'live') === 'test' ? 'test' : 'live';
        $rawKey = 'ck_' . $env . '_' . bin2hex(random_bytes(24));
        $id     = $db->insert('api_keys', [
            'name'        => e($body['name']),
            'key_hash'    => hash('sha256', $rawKey),
            'key_prefix'  => substr($rawKey, 0, 16) . '...',
            'permissions' => json_encode($body['permissions'] ?? ['search','read']),
            'rate_limit'  => (int)$body['rate_limit'],
            'is_active'   => 1,
            'usage_count' => 0,
            'expires_at'  => $body['expires_at'] ?? null,
            'created_by'  => (int)$admin['admin_id'],
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        Response::success(['key'=>$rawKey,'id'=>$id,'message'=>'Simpan key ini sekarang!'], 'API key dibuat', 201);
    }

    // DELETE /admin/api-keys/{id}
    elseif ($method === 'DELETE' && preg_match('#^/admin/api-keys/(\d+)$#', $path, $m)) {
        requireAdmin(['superadmin','admin']);
        $key = $db->fetchOne("SELECT id FROM api_keys WHERE id=?", [(int)$m[1]]);
        if (!$key) Response::notFound('API key tidak ditemukan');
        $db->update('api_keys', ['is_active'=>0,'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [(int)$m[1]]);
        Response::success(null, 'API key dinonaktifkan');
    }

    // GET /admin/risk-scores
    elseif ($method === 'GET' && $path === '/admin/risk-scores') {
        requireAdmin();
        $page = max(1,(int)($_GET['page']??1)); $pp = min(100,max(10,(int)($_GET['per_page']??20)));
        $offset = ($page-1)*$pp;
        $where = '1=1'; $params = [];
        if (!empty($_GET['level']))    { $where .= ' AND rs.risk_level=?'; $params[] = $_GET['level']; }
        if (!empty($_GET['category'])) { $where .= ' AND rs.category_id=?'; $params[] = (int)$_GET['category']; }
        $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM risk_scores rs WHERE {$where}", $params);
        $rows  = $db->fetchAll("SELECT rs.reported_value_normalized AS normalized_value, rs.category_id, rs.total_reports, rs.approved_reports, rs.risk_score AS score, rs.risk_level AS level, rs.last_reported_at, c.name AS category_name FROM risk_scores rs LEFT JOIN categories c ON c.id=rs.category_id WHERE {$where} ORDER BY rs.risk_score DESC LIMIT {$pp} OFFSET {$offset}", $params);
        Response::paginated($rows, $total, $page, $pp);
    }

    // GET /admin/search-logs
    elseif ($method === 'GET' && $path === '/admin/search-logs') {
        requireAdmin();
        $page = max(1,(int)($_GET['page']??1)); $pp = min(100,max(10,(int)($_GET['per_page']??30)));
        $offset = ($page-1)*$pp;
        $where = '1=1'; $params = [];
        if (!empty($_GET['query'])) { $where .= ' AND query LIKE ?'; $params[] = '%'.$db->escapeLike($_GET['query']).'%'; }
        if (isset($_GET['has_result']) && $_GET['has_result'] !== '') { $where .= ' AND has_result=?'; $params[] = (int)$_GET['has_result']; }
        $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM search_logs WHERE {$where}", $params);
        $logs  = $db->fetchAll("SELECT * FROM search_logs WHERE {$where} ORDER BY created_at DESC LIMIT {$pp} OFFSET {$offset}", $params);
        Response::paginated($logs, $total, $page, $pp);
    }

    // GET /admin/logs
    elseif ($method === 'GET' && $path === '/admin/logs') {
        requireAdmin(['superadmin','admin']);
        $page = max(1,(int)($_GET['page']??1)); $pp = min(100,max(10,(int)($_GET['per_page']??30)));
        $offset = ($page-1)*$pp;
        $where = '1=1'; $params = [];
        if (!empty($_GET['admin_id'])) { $where .= ' AND al.admin_id=?'; $params[] = (int)$_GET['admin_id']; }
        $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM activity_logs al WHERE {$where}", $params);
        $logs  = $db->fetchAll("SELECT al.*,a.name AS admin_name FROM activity_logs al LEFT JOIN admins a ON a.id=al.admin_id WHERE {$where} ORDER BY al.created_at DESC LIMIT {$pp} OFFSET {$offset}", $params);
        Response::paginated($logs, $total, $page, $pp);
    }

    // GET /admin/navigation
    elseif ($method === 'GET' && $path === '/admin/navigation') {
        requireAdmin();
        $nav = __ADMIN_NAV;
        $items = $nav->build();
        
        // Filter by permission
        $admin = requireAdmin();
        $userRole = $admin['role'];
        
        $filtered = array_filter($items, function($item) use ($userRole) {
            $permissions = $item['permission'] ?? [];
            return in_array($userRole, $permissions, true);
        });

        Response::success(['navigation' => array_values($filtered)]);
    }

    else {
        Response::notFound('Endpoint admin tidak ditemukan: ' . $method . ' ' . $path);
    }
}

// ── LOAD PLUGIN ROUTES ─────────────────────────────────────────
// ── LOAD PLUGIN ROUTES ─────────────────────────────────────────
elseif (is_dir(MODULE_PATH)) {
    $pluginRouteFound = false;
    $debugInfo = [];
    
    foreach (glob(MODULE_PATH . '/*/routes/api.php') as $pluginRouteFile) {
        $pluginSlug = basename(dirname(dirname($pluginRouteFile)));
        $routes = @include $pluginRouteFile;
        
        if (!is_array($routes)) continue;
        
        foreach ($routes as $route) {
            $routeMethod = $route['method'] ?? 'GET';
            $routePath = '/plugins/' . $pluginSlug . ($route['path'] ?? '');
            $handler = $route['handler'] ?? null;
            $isPattern = $route['pattern'] ?? false;
            
            // Check if request matches
            $matches = false;
            $regexMatches = [];
            
            if ($routeMethod !== $method) continue;
            
            if ($isPattern) {
                if (preg_match('#^' . $routePath . '$#', $path, $regexMatches)) {
                    $matches = true;
                }
            } else {
                if ($path === $routePath) {
                    $matches = true;
                }
            }
            
            if ($matches && $handler) {
                error_log("=== PLUGIN ROUTE MATCH ===", 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                error_log("Path: {$path}", 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                error_log("Handler: {$handler}", 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                
                try {
                    list($className, $methodName) = explode('@', $handler);
                    $handlerFile = dirname($pluginRouteFile) . '/../' . $className . '.php';
                    
                    error_log("Handler file: {$handlerFile}", 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                    error_log("Handler file exists: " . (file_exists($handlerFile) ? 'YES' : 'NO'), 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                    
                    if (file_exists($handlerFile)) {
                        error_log("Including handler file...", 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                        require_once $handlerFile;
                        
                        error_log("Class exists: " . (class_exists($className) ? 'YES' : 'NO'), 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                        error_log("Method exists: " . (method_exists($className, $methodName) ? 'YES' : 'NO'), 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                        
                        if (class_exists($className) && method_exists($className, $methodName)) {
                            error_log("Calling handler: {$className}::{$methodName}", 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                            
                            if ($isPattern) {
                                call_user_func_array([$className, $methodName], array_slice($regexMatches, 1));
                            } else {
                                call_user_func([$className, $methodName]);
                            }
                            $pluginRouteFound = true;
                            exit;
                        } else {
                            error_log("ERROR: Class or method not found!", 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                        }
                    } else {
                        error_log("ERROR: Handler file not found!", 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                    }
                } catch (Exception $e) {
                    error_log("EXCEPTION: " . $e->getMessage(), 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                    error_log("TRACE: " . $e->getTraceAsString(), 3, STORAGE_PATH . '/logs/plugin_route_match.log');
                }
            }
        }
    }
    
    // If no plugin route matched, return 404
    if (!$pluginRouteFound) {
        Response::notFound('Endpoint tidak ditemukan: ' . $method . ' /api/v1' . $path);
    }
}

// ── GET /admin/csvimport-logs (debug endpoint)
elseif ($method === 'GET' && $path === '/admin/csvimport-logs') {
    requireAdmin(['superadmin']);
    $logFile = STORAGE_PATH . '/logs/csvimport.log';
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $lines = array_reverse(array_slice(explode("\n", $content), -100)); // Last 100 lines
        Response::success(['logs' => implode("\n", $lines)]);
    } else {
        Response::success(['logs' => 'No logs yet']);
    }
}


// ── 404 ────────────────────────────────────────────────────────//
else {
    Response::notFound('Endpoint tidak ditemukan: ' . $method . ' /api/v1' . $path);
}