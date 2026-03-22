<?php
/**
 * api/v1/auth/index.php
 * ---------------------------------------------------------------
 * Endpoints:
 *   POST /api/v1/auth/login   → Login admin
 *   POST /api/v1/auth/logout  → Logout admin
 *   GET  /api/v1/auth/me      → Info admin yang sedang login
 * ---------------------------------------------------------------
 */

require_once dirname(__DIR__, 3) . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$action = basename($uri); // login, logout, me

$db     = Database::getInstance();

// ── POST /api/v1/auth/login ────────────────────────────────────
if ($method === 'POST' && $action === 'login') {
    // Rate limiting untuk mencegah brute force
    $ip = get_client_ip();
    if (!check_rate_limit('admin_login_' . $ip, 5, 300)) {
        Response::rateLimited(300);
    }

    $body  = get_json_body();
    $email = trim($body['email'] ?? '');
    $pass  = trim($body['password'] ?? '');

    $errors = validate(['email' => $email, 'password' => $pass], [
        'email'    => 'required|email',
        'password' => 'required|min:6',
    ]);

    if (!empty($errors)) {
        Response::validationError($errors);
    }

    $admin = $db->fetchOne("SELECT * FROM admins WHERE email = ? AND is_active = 1", [$email]);

    if (!$admin || !verify_password($pass, $admin['password'])) {
        // Delay untuk mencegah timing attack
        sleep(1);
        Response::error('Email atau password salah', 401, [], 'INVALID_CREDENTIALS');
    }

    // Update last login
    $db->update('admins', [
        'last_login_at' => date('Y-m-d H:i:s'),
        'last_login_ip' => $ip,
    ], 'id = ?', [$admin['id']]);

    // Generate JWT
    $token = generate_jwt([
        'admin_id' => $admin['id'],
        'email'    => $admin['email'],
        'role'     => $admin['role'],
    ]);

    // Log aktivitas
    $db->insert('activity_logs', [
        'admin_id'    => $admin['id'],
        'action'      => 'auth.login',
        'entity_type' => 'admin',
        'entity_id'   => $admin['id'],
        'description' => 'Login berhasil dari IP ' . $ip,
        'ip_address'  => $ip,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);

    Response::success([
        'token'      => $token,
        'expires_in' => JWT_EXPIRE,
        'admin'      => [
            'id'    => $admin['id'],
            'name'  => $admin['name'],
            'email' => $admin['email'],
            'role'  => $admin['role'],
        ],
    ], 'Login berhasil');
}

// ── GET /api/v1/auth/me ───────────────────────────────────────
if ($method === 'GET' && $action === 'me') {
    $token = get_bearer_token();

    if (empty($token)) {
        Response::unauthorized();
    }

    $payload = verify_jwt($token);
    if (!$payload) {
        Response::unauthorized('Token tidak valid atau expired');
    }

    $admin = $db->fetchOne(
        "SELECT id, name, email, role, last_login_at FROM admins WHERE id = ? AND is_active = 1",
        [$payload['admin_id']]
    );

    if (!$admin) {
        Response::unauthorized('Akun tidak aktif');
    }

    Response::success($admin, 'Info admin');
}

// ── POST /api/v1/auth/logout ──────────────────────────────────
if ($method === 'POST' && $action === 'logout') {
    $token = get_bearer_token();

    if (!empty($token)) {
        $payload = verify_jwt($token);
        if ($payload) {
            $db->insert('activity_logs', [
                'admin_id'    => $payload['admin_id'],
                'action'      => 'auth.logout',
                'entity_type' => 'admin',
                'entity_id'   => $payload['admin_id'],
                'description' => 'Logout',
                'ip_address'  => get_client_ip(),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }

    Response::success(null, 'Logout berhasil');
}

Response::error('Endpoint tidak valid', 404);
