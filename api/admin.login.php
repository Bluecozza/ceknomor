<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/admin_auth.php';

$data = json_decode(file_get_contents("php://input"), true);

$user     = trim($data['username'] ?? '');
$pass     = $data['password'] ?? '';
$remember = !empty($data['remember']);

$db = db();
$ip = $_SERVER['REMOTE_ADDR'];

/* ===============================
   BRUTE FORCE CHECK
================================ */
$stmt = $db->prepare("
  SELECT attempts, last_attempt
  FROM admin_login_attempts
  WHERE ip=?
");
$stmt->execute([$ip]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['attempts'] >= 5 &&
    strtotime($row['last_attempt']) > time() - 900) {
    json(['status'=>'error','message'=>'too_many_attempts'], 429);
    exit;
}

/* ===============================
   AUTH CHECK
================================ */
$stmt = $db->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
$stmt->execute([$user]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || !password_verify($pass, $admin['password_hash'])) {

    // increment brute force counter
    $db->prepare("
      INSERT INTO admin_login_attempts (ip, attempts, last_attempt)
      VALUES (?, 1, NOW())
      ON DUPLICATE KEY UPDATE
        attempts = attempts + 1,
        last_attempt = NOW()
    ")->execute([$ip]);

    json(['status'=>'error','message'=>'invalid_login'], 401);
    exit;
}

/* ===============================
   LOGIN SUCCESS
================================ */
admin_login($admin['id'], $admin['username']);

// reset brute force counter
$db->prepare("DELETE FROM admin_login_attempts WHERE ip=?")
   ->execute([$ip]);

// update last login
$db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")
   ->execute([$admin['id']]);

/* ===============================
   REMEMBER ME
================================ */
if ($remember) {
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);

    $stmt = $db->prepare("
        INSERT INTO admin_remember_tokens
        (admin_id, token_hash, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
    ");
    $stmt->execute([$admin['id'], $hash]);

    setcookie(
        'admin_remember',
        $token,
        [
          'expires'  => time() + 60*60*24*30,
          'path'     => '/',
          'secure'   => false, // true jika HTTPS
          'httponly' => true,
          'samesite' => 'Strict'
        ]
    );
}

/* ===============================
   RESPONSE
================================ */
json(['status'=>'ok']);
exit;
